<?php
// Used by the booking page: which times are already taken for one barber on one date?
// Answers with JSON like {"times":["9:00 AM","12:00 PM"]}
require_once __DIR__ . '/db/connect.php';
require_once __DIR__ . '/functions/appointment_function.php';
require_once __DIR__ . '/functions/auth_guard.php';   // must be logged in

header('Content-Type: application/json');
header('Cache-Control: no-store');

$barberId = (int) ($_GET['barber_id'] ?? 0);
$date     = $_GET['date'] ?? '';

if ($barberId <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    echo json_encode(['times' => []]);
    exit;
}

// Pending, Accepted, On going and Completed all keep the time closed. Only Cancelled frees it.
echo json_encode(['times' => array_values(getBookedTimes($connect2db, $date, $barberId))]);
