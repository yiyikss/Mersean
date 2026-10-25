<?php

// Text shown to people for each status in the database.
function statusLabel(string $status): string
{
    return match ($status) {
        'Confirmed' => 'Accepted',
        'Ongoing'   => 'On going',
        default     => $status,
    };
}

// Appointments from ALL customers. Pass a status to filter, or null for everything.
function getAllAppointments(mysqli $connect2db, ?string $status = null, bool $newestFirst = false): array
{
    $dir  = $newestFirst ? 'DESC' : 'ASC';   // fixed values only, never user input
    $list = [];

    // LEFT JOINs: an appointment never disappears from the list, even if its
    // customer, service or barber was removed. (The tab counts include every row.)
    $sql = "SELECT a.id, a.barber_id, a.appointment_date, a.appointment_time, a.contact, a.notes, a.status,
                   a.payment_method, a.payment_status,
                   COALESCE(CONCAT(c.firstname, ' ', c.lastname), '(deleted account)') AS customer_name,
                   COALESCE(s.name, '(deleted service)')  AS service_name,
                   COALESCE(s.price, 0)                    AS price,
                   COALESCE(s.duration, 0)                 AS duration,
                   COALESCE(b.name, '(deleted barber)')    AS barber_name
            FROM appointments a
            LEFT JOIN accounts c ON a.user_id    = c.id
            LEFT JOIN services s ON a.service_id = s.id
            LEFT JOIN barbers  b ON a.barber_id  = b.id"
         . ($status !== null ? " WHERE a.status = ?" : "")
         . " ORDER BY a.appointment_date $dir,
                      STR_TO_DATE(a.appointment_time, '%l:%i %p') $dir,
                      a.id $dir";

    $stmt = mysqli_prepare($connect2db, $sql);
    if ($status !== null) {
        mysqli_stmt_bind_param($stmt, "s", $status);
    }
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) {
        $list[] = $row;
    }
    mysqli_stmt_close($stmt);
    return $list;
}

// How many appointments are in each status (used for the tab counts).
function getStatusCounts(mysqli $connect2db): array
{
    $counts = ['Pending' => 0, 'Confirmed' => 0, 'Ongoing' => 0, 'Completed' => 0, 'Cancelled' => 0];

    $result = mysqli_query($connect2db, "SELECT status, COUNT(*) AS total FROM appointments GROUP BY status");
    while ($row = mysqli_fetch_assoc($result)) {
        $counts[$row['status']] = (int) $row['total'];
    }
    $counts['All'] = array_sum($counts);
    return $counts;
}

// Numbers for TODAY only. They are worked out from today's date every time the
// page loads, so they start again from 0 on their own when a new day begins.
//   total      = appointments on today's plate: booked for today, or finished today
//   completed  = appointments finished (marked Complete) today
//   to_collect = today's appointments that are not paid yet
//   revenue    = money actually received today (PayPal + cash, by the time it was paid)
function getAdminStats(mysqli $connect2db, string $date): array
{
    $todays = "(a.appointment_date = ? OR DATE(a.completed_at) = ?)";

    $stmt = mysqli_prepare($connect2db, "
        SELECT
            COUNT(CASE WHEN a.status != 'Cancelled' AND $todays THEN 1 END) AS total,
            COALESCE(SUM(a.status = 'Completed'
                         AND (DATE(a.completed_at) = ?
                              OR (a.completed_at IS NULL AND a.appointment_date = ?))), 0) AS completed,
            COALESCE(SUM(CASE WHEN a.status != 'Cancelled' AND a.payment_status = 'Unpaid' AND $todays
                              THEN COALESCE(s.price, 0) END), 0) AS to_collect
        FROM appointments a
        LEFT JOIN services s ON a.service_id = s.id");
    mysqli_stmt_bind_param($stmt, "ssssss", $date, $date, $date, $date, $date, $date);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    // Money received today, whatever day the appointment itself is on
    $stmt = mysqli_prepare($connect2db, "
        SELECT COALESCE(SUM(amount_paid), 0) AS received
        FROM appointments
        WHERE payment_status = 'Paid' AND DATE(paid_at) = ?");
    mysqli_stmt_bind_param($stmt, "s", $date);
    mysqli_stmt_execute($stmt);
    $paid = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    return [
        'total'      => (int) $row['total'],
        'completed'  => (int) $row['completed'],
        'revenue'    => (float) $paid['received'],
        'to_collect' => (float) $row['to_collect'],
    ];
}

// Barbers who are busy right now (have an appointment that is On going).
// Returns [barber_id => true]. One barber can only do one haircut at a time.
function getBusyBarberIds(mysqli $connect2db): array
{
    $busy   = [];
    $result = mysqli_query($connect2db, "SELECT DISTINCT barber_id FROM appointments WHERE status = 'Ongoing'");
    while ($row = mysqli_fetch_assoc($result)) {
        $busy[(int) $row['barber_id']] = true;
    }
    return $busy;
}

// Move an appointment to its next status. Only valid steps are allowed:
//   Pending -> Ongoing (the Accept button) or Cancelled   (Confirmed is still allowed for old data)
//   Confirmed -> Ongoing or Cancelled
//   Ongoing -> Completed
// A barber can only have ONE appointment On going at a time: starting another one
// is refused until the current one is completed.
// Returns "" if it worked, or a message explaining why it did not.
function updateAppointmentStatus(mysqli $connect2db, int $id, string $newStatus): string
{
    $allowed = [
        'Pending'   => ['Ongoing', 'Confirmed', 'Cancelled'],
        'Confirmed' => ['Ongoing', 'Cancelled'],
        'Ongoing'   => ['Completed'],
    ];

    $stmt = mysqli_prepare($connect2db, "SELECT status, barber_id FROM appointments WHERE id = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    if (!$row || !in_array($newStatus, $allowed[$row['status']] ?? [], true)) {
        return "That change is not allowed.";   // appointment missing, or that step isn't allowed
    }

    // Is this barber already busy with someone else?
    if ($newStatus === 'Ongoing') {
        $stmt = mysqli_prepare($connect2db, "
            SELECT COUNT(*) AS total FROM appointments
            WHERE barber_id = ? AND status = 'Ongoing' AND id != ?");
        $barberId = (int) $row['barber_id'];
        mysqli_stmt_bind_param($stmt, "ii", $barberId, $id);
        mysqli_stmt_execute($stmt);
        $busy = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        if ((int) $busy['total'] > 0) {
            return "This barber is still busy with another customer. Complete that appointment first.";
        }
    }

    // Also match the old status so two admins clicking at once can't clash.
    // Finishing an appointment also records the moment it was finished (completed_at).
    $stmt = mysqli_prepare($connect2db, "
        UPDATE appointments
        SET status = ?, completed_at = IF(? = 'Completed', NOW(), completed_at)
        WHERE id = ? AND status = ?");
    mysqli_stmt_bind_param($stmt, "ssis", $newStatus, $newStatus, $id, $row['status']);
    $ok = mysqli_stmt_execute($stmt) && mysqli_stmt_affected_rows($stmt) === 1;
    mysqli_stmt_close($stmt);

    return $ok ? "" : "That appointment was just changed by someone else. Please refresh.";
}