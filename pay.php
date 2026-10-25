<?php
// Pay for one appointment with PayPal.
// Customers pay their own appointments; staff can also open it from the admin dashboard.
require_once __DIR__ . '/db/connect.php';
require_once __DIR__ . '/functions/appointment_function.php';
require_once __DIR__ . '/functions/paypal_functions.php';
require_once __DIR__ . '/functions/auth_guard.php';

if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(16));
}

$user     = $_SESSION['user'];
$isStaff  = in_array($user['role'] ?? '', ['admin', 'staff'], true);
$appt     = getPayableAppointment($connect2db, (int) ($_GET['id'] ?? 0));
$backUrl  = $isStaff ? 'admin_dashboard.php?f=all#today' : 'dashboard.php';

if (!$appt || !canPayAppointment($appt, $user)) {
    header('Location: ' . $backUrl);
    exit;
}

$config   = paypalConfig();
$ready    = paypalConfigured();
$canPay   = isPayable($appt) && $ready;
$paid     = $appt['payment_status'] === 'Paid';

$title  = 'Pay with PayPal';
$active = $isStaff ? 'dashboard' : 'dashboard';
include __DIR__ . '/includes/' . ($isStaff ? 'admin_header.php' : 'header.php');
?>

<small class="mu"><?= $isStaff ? 'Shop management' : 'Your space' ?> / Payment</small>
<h1><?= $paid ? 'Payment received.' : 'Pay with PayPal.' ?></h1>
<p class="mu"><?= $paid ? 'Thank you! This appointment is paid.' : 'Check the details, then pay securely with PayPal.' ?></p>

<div class="card paybox">
    <h3><?= htmlspecialchars($appt['service_name']) ?></h3>
    <div class="sumline"><span class="mu">Customer</span><b><?= htmlspecialchars($appt['who'] ?? '') ?></b></div>
    <div class="sumline"><span class="mu">Barber</span><b><?= htmlspecialchars($appt['barber_name']) ?></b></div>
    <div class="sumline"><span class="mu">When</span><b><?= date('M j, Y', strtotime($appt['appointment_date'])) ?> · <?= htmlspecialchars($appt['appointment_time']) ?></b></div>
    <hr>
    <div class="between"><span>Total</span><h2><?= money($appt['price']) ?></h2></div>

    <p class="msg error" id="paymsg" hidden></p>

    <?php if ($paid): ?>
        <p class="msg success">Paid <?= $appt['payment_method'] === 'paypal' ? 'with PayPal' : 'in cash' ?>.</p>
        <a href="<?= $backUrl ?>" class="btn p full">Back to dashboard</a>
    <?php elseif ($appt['status'] === 'Cancelled'): ?>
        <p class="msg error">This appointment was cancelled, so it can't be paid.</p>
        <a href="<?= $backUrl ?>" class="btn full">Back</a>
    <?php elseif (!$ready): ?>
        <p class="msg error">PayPal is not set up yet. Add the Client ID and Secret in <code>functions/paypal_config.php</code>.</p>
        <a href="<?= $backUrl ?>" class="btn full">Back</a>
    <?php else: ?>
        <div id="paypal-buttons"></div>
        <p class="mu small" style="margin-top:14px">
            You can also pay at the shop instead.
            <a href="<?= $backUrl ?>" class="lk">Pay later</a>
        </p>
    <?php endif; ?>
</div>

<?php if ($canPay): ?>
<script src="https://www.paypal.com/sdk/js?client-id=<?= rawurlencode($config['client_id']) ?>&currency=<?= rawurlencode($config['currency']) ?>&intent=capture&components=buttons"></script>
<script>
const msg = document.getElementById('paymsg');
const post = (url, data) => fetch(url, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ id: <?= (int) $appt['id'] ?>, csrf: <?= json_encode($_SESSION['csrf']) ?>, ...data })
}).then(r => r.json());

function showError(text) {
    msg.textContent = text;
    msg.hidden = false;
}

paypal.Buttons({
    style: { layout: 'vertical', shape: 'rect', label: 'pay' },

    // 1. Ask our server for an order (the price is read from the database there)
    createOrder: () => post('paypal_create_order.php', {}).then(d => {
        if (d.error) { showError(d.error); throw new Error(d.error); }
        return d.id;
    }),

    // 2. Customer approved in PayPal: our server takes the payment
    onApprove: data => post('paypal_capture_order.php', { order_id: data.orderID }).then(d => {
        if (d.error) { showError(d.error); return; }
        window.location = 'pay.php?id=<?= (int) $appt['id'] ?>&done=1';
    }),

    onCancel: () => showError('Payment cancelled. You have not been charged.'),
    onError: err => showError('Something went wrong with PayPal. Please try again.')
}).render('#paypal-buttons');
</script>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
