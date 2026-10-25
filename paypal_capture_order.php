<?php
// Called by pay.php after the customer approved the payment in PayPal. Answers with JSON.
require_once __DIR__ . '/db/connect.php';
require_once __DIR__ . '/functions/paypal_functions.php';

header('Content-Type: application/json');

function fail(string $message, int $code = 400): void
{
    http_response_code($code);
    echo json_encode(['error' => $message]);
    exit;
}

if (!isset($_SESSION['user'])) {
    fail('Please log in again.', 401);
}
$in = json_decode(file_get_contents('php://input'), true) ?: [];

if (empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], (string) ($in['csrf'] ?? ''))) {
    fail('Your session expired. Please reload the page.', 403);
}

$appt = getPayableAppointment($connect2db, (int) ($in['id'] ?? 0));
if (!$appt || !canPayAppointment($appt, $_SESSION['user'])) {
    fail('Appointment not found.', 404);
}

$error = paypalCaptureOrder($connect2db, $appt, (string) ($in['order_id'] ?? ''));
if ($error !== '') {
    fail($error, 502);
}
echo json_encode(['ok' => true]);
