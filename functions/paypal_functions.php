<?php
// PayPal checkout (Orders v2) + saving the payment on an appointment.
// The price always comes from the database, never from the browser, so it can't be changed by the customer.

require_once __DIR__ . '/../db/connect.php';

function paypalConfig(): array
{
    static $config = null;
    if ($config === null) {
        $config = require __DIR__ . '/paypal_config.php';
    }
    return $config;
}

// True once real credentials were pasted into paypal_config.php
function paypalConfigured(): bool
{
    $c = paypalConfig();
    return ($c['client_id'] ?? '') !== '' && ($c['secret'] ?? '') !== ''
        && strpos($c['client_id'], 'PASTE_') === false
        && strpos($c['secret'], 'PASTE_') === false;
}

function paypalApiBase(): string
{
    if (!empty(paypalConfig()['api_base'])) {
        return rtrim(paypalConfig()['api_base'], '/');   // only used for testing
    }
    return (paypalConfig()['mode'] ?? 'sandbox') === 'live'
        ? 'https://api-m.paypal.com'
        : 'https://api-m.sandbox.paypal.com';
}

// Finds the trusted certificate list (XAMPP on Windows often has none set in php.ini)
function paypalCaFile(): string
{
    $candidates = [
        ini_get('curl.cainfo'),
        ini_get('openssl.cafile'),
        'C:/xampp/php/extras/ssl/cacert.pem',
        'C:/xampp/apache/bin/curl-ca-bundle.crt',
        '/etc/ssl/certs/ca-certificates.crt',
    ];
    foreach ($candidates as $file) {
        if ($file && is_file($file)) {
            return $file;
        }
    }
    return '';
}

// One call to PayPal. Returns [decodedJson|null, errorMessage]
function paypalRequest(string $method, string $path, ?string $body, array $headers): array
{
    if (!function_exists('curl_init')) {
        return [null, 'PHP cURL is not enabled. In XAMPP open php.ini and remove the ; before extension=curl, then restart Apache.'];
    }

    $ch = curl_init(paypalApiBase() . $path);
    $options = [
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_HTTPHEADER     => $headers,
    ];
    if ($body !== null) {
        $options[CURLOPT_POSTFIELDS] = $body;
    }
    $ca = paypalCaFile();
    if ($ca !== '') {
        $options[CURLOPT_CAINFO] = $ca;
    }
    curl_setopt_array($ch, $options);

    $response = curl_exec($ch);
    $status   = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        if (stripos($curlErr, 'certificate') !== false || stripos($curlErr, 'SSL') !== false) {
            return [null, 'PHP could not verify PayPal\'s certificate. Download https://curl.se/ca/cacert.pem to C:\\xampp\\php\\extras\\ssl\\cacert.pem and set curl.cainfo to it in php.ini.'];
        }
        return [null, 'Could not reach PayPal (' . $curlErr . ').'];
    }

    $data = json_decode($response, true);
    if ($status >= 400 || !is_array($data)) {
        $detail = $data['details'][0]['issue'] ?? ($data['error_description'] ?? ($data['message'] ?? 'HTTP ' . $status));
        return [$data, 'PayPal said: ' . $detail];
    }
    return [$data, ''];
}

// Returns [accessToken, errorMessage]
function paypalAccessToken(): array
{
    $c = paypalConfig();
    [$data, $error] = paypalRequest('POST', '/v1/oauth2/token', 'grant_type=client_credentials', [
        'Authorization: Basic ' . base64_encode($c['client_id'] . ':' . $c['secret']),
        'Content-Type: application/x-www-form-urlencoded',
    ]);
    if ($error !== '' || empty($data['access_token'])) {
        return ['', $error !== '' ? $error : 'PayPal login failed. Check the Client ID and Secret.'];
    }
    return [$data['access_token'], ''];
}

// ── Appointments that can be paid ──

// One appointment with its service price. null if it doesn't exist.
function getPayableAppointment(mysqli $connect2db, int $id): ?array
{
    $stmt = mysqli_prepare($connect2db, "
        SELECT a.*, s.name AS service_name, s.price, b.name AS barber_name,
               CONCAT(c.firstname, ' ', c.lastname) AS who
        FROM appointments a
        JOIN services s ON a.service_id = s.id
        JOIN barbers  b ON a.barber_id  = b.id
        JOIN accounts c ON a.user_id = c.id
        WHERE a.id = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    return $row ?: null;
}

// The owner of the appointment, or the shop's staff / admin, may pay it.
function canPayAppointment(array $appointment, array $user): bool
{
    if (in_array($user['role'] ?? '', ['admin', 'staff'], true)) {
        return true;
    }
    return (int) $appointment['user_id'] === (int) ($user['id'] ?? 0);
}

// Can a payment still be taken for this appointment?
function isPayable(array $appointment): bool
{
    return $appointment['payment_status'] === 'Unpaid' && $appointment['status'] !== 'Cancelled';
}

// ── Create and capture ──

// Step 1: ask PayPal for an order. Returns [orderId, errorMessage]
function paypalCreateOrder(mysqli $connect2db, array $appointment): array
{
    [$token, $error] = paypalAccessToken();
    if ($error !== '') {
        return ['', $error];
    }

    $c = paypalConfig();
    $body = json_encode([
        'intent' => 'CAPTURE',
        'purchase_units' => [[
            'reference_id' => 'appointment-' . $appointment['id'],
            'custom_id'    => (string) $appointment['id'],
            'description'  => mb_substr($appointment['service_name'] . ' - Mersean Barbershop', 0, 120),
            'amount'       => [
                'currency_code' => $c['currency'],
                'value'         => number_format((float) $appointment['price'], 2, '.', ''),
            ],
        ]],
    ]);

    [$data, $error] = paypalRequest('POST', '/v2/checkout/orders', $body, [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json',
    ]);
    if ($error !== '' || empty($data['id'])) {
        return ['', $error !== '' ? $error : 'PayPal did not create the order.'];
    }

    // Remember which order belongs to this appointment (checked again when capturing)
    $orderId = (string) $data['id'];
    $stmt = mysqli_prepare($connect2db, "UPDATE appointments SET paypal_order_id = ? WHERE id = ? AND payment_status = 'Unpaid'");
    mysqli_stmt_bind_param($stmt, "si", $orderId, $appointment['id']);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    return [$orderId, ''];
}

// Step 2: after the customer approves, take the money. Returns "" if paid, or an error message.
function paypalCaptureOrder(mysqli $connect2db, array $appointment, string $orderId): string
{
    if ($appointment['paypal_order_id'] !== $orderId) {
        return 'This PayPal order does not belong to this appointment.';
    }
    if ($appointment['payment_status'] === 'Paid') {
        return '';   // already paid (e.g. the page was reloaded) - nothing to do
    }

    [$token, $error] = paypalAccessToken();
    if ($error !== '') {
        return $error;
    }

    [$data, $error] = paypalRequest('POST', '/v2/checkout/orders/' . rawurlencode($orderId) . '/capture', '{}', [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json',
        'PayPal-Request-Id: capture-' . $appointment['id'] . '-' . $orderId,   // same request twice = one payment
    ]);

    [$paid, $captureId, $readError] = paypalReadCapture($data ?? [], $appointment);
    if ($readError !== '') {
        return $error !== '' ? $error : $readError;
    }

    $stmt = mysqli_prepare($connect2db, "
        UPDATE appointments
        SET payment_status = 'Paid', payment_method = 'paypal', amount_paid = ?,
            paypal_capture_id = ?, paid_at = NOW()
        WHERE id = ? AND payment_status = 'Unpaid'");
    mysqli_stmt_bind_param($stmt, "dsi", $paid, $captureId, $appointment['id']);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    return '';
}

// Checks PayPal's answer to a capture. Returns [amountPaid, captureId, errorMessage]
function paypalReadCapture(array $data, array $appointment): array
{
    $capture = $data['purchase_units'][0]['payments']['captures'][0] ?? null;
    if (($data['status'] ?? '') !== 'COMPLETED' || !$capture || ($capture['status'] ?? '') !== 'COMPLETED') {
        return [0.0, '', 'The payment was not completed. You have not been charged.'];
    }

    // Make sure PayPal charged the right amount for the right appointment
    $paid     = (float) ($capture['amount']['value'] ?? 0);
    $expected = round((float) $appointment['price'], 2);
    if (abs($paid - $expected) > 0.001
        || ($capture['amount']['currency_code'] ?? '') !== paypalConfig()['currency']
        || (string) ($capture['custom_id'] ?? $appointment['id']) !== (string) $appointment['id']) {
        return [$paid, '', 'The amount paid does not match the price. Please contact the shop.'];
    }
    return [$paid, (string) ($capture['id'] ?? ''), ''];
}

// Staff took cash at the shop. Returns true if it was marked.
function markPaidCash(mysqli $connect2db, int $id): bool
{
    $stmt = mysqli_prepare($connect2db, "
        UPDATE appointments
        SET payment_status = 'Paid', payment_method = 'cash', paid_at = NOW(),
            amount_paid = (SELECT price FROM services WHERE services.id = appointments.service_id)
        WHERE id = ? AND payment_status = 'Unpaid' AND status != 'Cancelled'");
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    $ok = mysqli_stmt_affected_rows($stmt) === 1;
    mysqli_stmt_close($stmt);
    return $ok;
}

// Text for the payment badge: [label, css class]
function paymentBadge(array $a): array
{
    if (($a['payment_status'] ?? 'Unpaid') === 'Paid') {
        return [$a['payment_method'] === 'paypal' ? 'Paid · PayPal' : 'Paid · Cash', 'st-paid'];
    }
    return [$a['payment_method'] === 'paypal' ? 'Unpaid · PayPal' : 'Pay later', 'st-unpaid'];
}