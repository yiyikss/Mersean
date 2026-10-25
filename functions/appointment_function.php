<?php
// This file holds the appointment functions.
// The database connection and session_start() already come from db/connect.php,
// so we only include that file here (require_once means it only loads one time).

require_once __DIR__ . '/../db/connect.php';

// ── The time slots the shop offers (change these anytime) ──
function getTimeSlots(): array {
    return [
        "9:00 AM", "10:00 AM", "11:00 AM", "12:00 PM", "1:00 PM",
        "2:00 PM", "3:00 PM", "4:00 PM", "5:00 PM", "6:00 PM"
    ];
}

// ── Times that already passed today (nobody can book 9:00 AM at 2:00 PM) ──
function getPastTimes(string $date): array {
    $past = [];
    if ($date !== date('Y-m-d')) {
        return $past;   // only today has times that already passed
    }
    foreach (getTimeSlots() as $slot) {
        if (strtotime($date . ' ' . $slot) <= time()) {
            $past[] = $slot;
        }
    }
    return $past;
}

// ── Picture of a service (falls back to default.svg if the file is missing) ──
function serviceImageUrl(?string $image): string {
    $folder = __DIR__ . '/../images/services/';
    if ($image && file_exists($folder . basename($image))) {
        return '../images/services/' . rawurlencode(basename($image));
    }
    return '../images/services/default.svg';
}

// ── How many open times are left on each day of one month, for one barber ──
// Returns ['total' => 10, 'days' => ['2026-10-15' => 4, ...]]
function getMonthAvailability(mysqli $connect2db, int $barberId, int $year, int $month): array {
    $start       = sprintf('%04d-%02d-01', $year, $month);
    $daysInMonth = (int) date('t', strtotime($start));
    $end         = sprintf('%04d-%02d-%02d', $year, $month, $daysInMonth);

    // one query for the whole month
    $booked = [];
    $sql = "SELECT appointment_date, appointment_time FROM appointments
            WHERE barber_id = ? AND appointment_date BETWEEN ? AND ? AND status != 'Cancelled'";
    $stmt = mysqli_prepare($connect2db, $sql);
    mysqli_stmt_bind_param($stmt, "iss", $barberId, $start, $end);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) {
        $booked[$row['appointment_date']][] = $row['appointment_time'];
    }
    mysqli_stmt_close($stmt);

    $slots = getTimeSlots();
    $days  = [];
    for ($d = 1; $d <= $daysInMonth; $d++) {
        $date  = sprintf('%04d-%02d-%02d', $year, $month, $d);
        $taken = array_merge($booked[$date] ?? [], getPastTimes($date));
        $taken = array_intersect(array_unique($taken), $slots);
        $days[$date] = count($slots) - count($taken);
    }

    return ['total' => count($slots), 'days' => $days];
}

// ── Get all services ──
function getServices(mysqli $connect2db): array {
    $list = [];
    $result = mysqli_query($connect2db, "SELECT * FROM services WHERE active = 1 ORDER BY id");
    while ($row = mysqli_fetch_assoc($result)) {
        $list[] = $row;
    }
    return $list;
}

// ── Get one service by its id (returns null if not found) ──
function getService(mysqli $connect2db, int $id): ?array {
    $stmt = mysqli_prepare($connect2db, "SELECT * FROM services WHERE id = ? AND active = 1 LIMIT 1");
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    return $row ?: null;
}

// ── Get all barbers ──
function getBarbers(mysqli $connect2db): array {
    $list = [];
    $result = mysqli_query($connect2db, "SELECT * FROM barbers WHERE active = 1 ORDER BY id");
    while ($row = mysqli_fetch_assoc($result)) {
        $list[] = $row;
    }
    return $list;
}

// ── Get one barber by id (returns null if not found or removed) ──
function getBarber(mysqli $connect2db, int $id): ?array {
    $stmt = mysqli_prepare($connect2db, "SELECT * FROM barbers WHERE id = ? AND active = 1 LIMIT 1");
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    return $row ?: null;
}

// ── Get the times already booked for one barber on one date ──
function getBookedTimes(mysqli $connect2db, string $date, int $barberId): array {
    $times = [];
    $sql = "SELECT appointment_time FROM appointments
            WHERE appointment_date = ? AND barber_id = ? AND status != 'Cancelled'";
    $stmt = mysqli_prepare($connect2db, $sql);
    mysqli_stmt_bind_param($stmt, "si", $date, $barberId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) {
        $times[] = $row['appointment_time'];
    }
    mysqli_stmt_close($stmt);
    return $times;
}

// ── Save a new appointment ──
// Returns an empty text "" if it worked, or an error message if it failed.
function makeAppointment(array $data, mysqli $connect2db, int $userId, ?int &$newId = null): string {
    $serviceId = (int) ($data['service_id'] ?? 0);
    $barberId  = (int) ($data['barber_id'] ?? 0);
    $date      = $data['date'] ?? '';
    $time      = $data['time'] ?? '';
    $contact   = trim($data['contact'] ?? '');
    $notes     = trim($data['notes'] ?? '');
    // 'paypal' = the customer will pay online right after booking; anything else = pay at the shop
    $payMethod = ($data['payment_method'] ?? '') === 'paypal' ? 'paypal' : 'shop';

    if ($serviceId == 0 || $barberId == 0 || $date == '' || $time == '' || $contact == '') {
        return "Please fill in all the required fields.";
    }

    // The service and barber must still be offered (the admin may have removed them)
    if (!getService($connect2db, $serviceId) || !getBarber($connect2db, $barberId)) {
        return "That service or barber is no longer available. Please choose again.";
    }

    if ($date < date('Y-m-d')) {
        return "You cannot book a date in the past.";
    }

    if (!in_array($time, getTimeSlots())) {
        return "Please choose a valid time.";
    }

    if (in_array($time, getPastTimes($date))) {
        return "That time has already passed today. Please pick a later time.";
    }

    // Check if the barber is already booked at that time
    $booked = getBookedTimes($connect2db, $date, $barberId);
    if (in_array($time, $booked)) {
        return "Sorry, that time is already taken. Please pick another time.";
    }

    $sql = "INSERT INTO appointments (user_id, service_id, barber_id, appointment_date, appointment_time, contact, notes, payment_method)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = mysqli_prepare($connect2db, $sql);
    mysqli_stmt_bind_param($stmt, "iiisssss", $userId, $serviceId, $barberId, $date, $time, $contact, $notes, $payMethod);

    if (mysqli_stmt_execute($stmt)) {
        $newId = (int) mysqli_insert_id($connect2db);
        mysqli_stmt_close($stmt);
        return "";
    }
    mysqli_stmt_close($stmt);
    return "Something went wrong. Please try again.";
}

// ── Get one customer's appointments (newest first) ──
function getMyAppointments(mysqli $connect2db, int $userId, int $limit = 5): array {
    $list = [];
    $sql = "SELECT a.*, s.name AS service_name, s.price, s.image AS service_image,
                   b.name AS barber_name, b.photo AS barber_photo
            FROM appointments a
            JOIN services s ON a.service_id = s.id
            JOIN barbers b ON a.barber_id = b.id
            WHERE a.user_id = ?
            ORDER BY a.appointment_date DESC, a.id DESC
            LIMIT ?";
    $stmt = mysqli_prepare($connect2db, $sql);
    mysqli_stmt_bind_param($stmt, "ii", $userId, $limit);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) {
        $list[] = $row;
    }
    mysqli_stmt_close($stmt);
    return $list;
}

// ── Cancel an appointment (only if it belongs to this user) ──
function cancelAppointment(mysqli $connect2db, int $appointmentId, int $userId): void {
    $sql = "UPDATE appointments SET status = 'Cancelled' WHERE id = ? AND user_id = ? AND status = 'Pending'";
    $stmt = mysqli_prepare($connect2db, $sql);
    mysqli_stmt_bind_param($stmt, "ii", $appointmentId, $userId);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

// ── Upcoming appointments for one customer (soonest first, skips past and cancelled) ──
// An appointment that is On going stays in the list even after its start time has passed.
function getUpcomingAppointments(mysqli $connect2db, int $userId): array {
    $today = date('Y-m-d');
    $list  = [];

    $sql = "SELECT a.*, s.name AS service_name, s.price, s.duration,
                   b.name AS barber_name, b.photo AS barber_photo
            FROM appointments a
            JOIN services s ON a.service_id = s.id
            JOIN barbers b ON a.barber_id = b.id
            WHERE a.user_id = ? AND a.appointment_date >= ? AND a.status IN ('Pending', 'Confirmed', 'Ongoing')";
    $stmt = mysqli_prepare($connect2db, $sql);
    mysqli_stmt_bind_param($stmt, "is", $userId, $today);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    while ($row = mysqli_fetch_assoc($result)) {
        // appointment_time is text like "10:00 AM", so we build a real timestamp
        $row['starts_at'] = strtotime($row['appointment_date'] . ' ' . $row['appointment_time']);
        if ($row['starts_at'] !== false && ($row['starts_at'] >= time() || $row['status'] === 'Ongoing')) {
            $list[] = $row;
        }
    }
    mysqli_stmt_close($stmt);

    usort($list, fn($a, $b) => $a['starts_at'] <=> $b['starts_at']);
    return $list;
}

// ── How many appointments the customer has completed ──
function getCompletedCount(mysqli $connect2db, int $userId): int {
    $stmt = mysqli_prepare($connect2db, "SELECT COUNT(*) AS total FROM appointments WHERE user_id = ? AND status = 'Completed'");
    mysqli_stmt_bind_param($stmt, "i", $userId);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    return (int) ($row['total'] ?? 0);
}

// ── The barber this customer books most often (null if no bookings yet) ──
function getFavoriteBarber(mysqli $connect2db, int $userId): ?array {
    $sql = "SELECT b.id, b.name, b.specialty, b.photo, COUNT(*) AS visits
            FROM appointments a
            JOIN barbers b ON a.barber_id = b.id
            WHERE a.user_id = ? AND a.status != 'Cancelled'
            GROUP BY b.id, b.name, b.specialty, b.photo
            ORDER BY visits DESC, MAX(a.id) DESC
            LIMIT 1";
    $stmt = mysqli_prepare($connect2db, $sql);
    mysqli_stmt_bind_param($stmt, "i", $userId);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    return $row ?: null;
}