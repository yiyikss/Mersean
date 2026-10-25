<?php
require_once __DIR__ . '/db/connect.php';
require_once __DIR__ . '/functions/appointment_function.php';
require_once __DIR__ . '/functions/admin_functions.php';
require_once __DIR__ . '/functions/paypal_functions.php';
require_once __DIR__ . '/functions/admin_guard.php';   // staff/admin only

$today = date('Y-m-d');

// Tabs: url value => [database status (null = all), label]
$tabs = [
    'pending'   => ['Pending',   'Pending'],
    'confirmed' => ['Confirmed', 'Accepted'],
    'ongoing'   => ['Ongoing',   'On going'],
    'completed' => ['Completed', 'Completed'],
    'cancelled' => ['Cancelled', 'Cancelled'],
    'all'       => [null,        'All'],
];
$filter = $_GET['f'] ?? 'pending';
if (!isset($tabs[$filter])) {
    $filter = 'pending';
}

// Simple CSRF token so other sites can't trigger status changes
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(16));
}

// "Cash received" button
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'mark_paid') {
    if (hash_equals($_SESSION['csrf'], $_POST['csrf'] ?? '')) {
        markPaidCash($connect2db, (int) ($_POST['appointment_id'] ?? 0));
    }
    header('Location: admin_dashboard.php?f=' . urlencode($filter) . '#today');
    exit;
}

// Status buttons (handled before any HTML is printed)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'set_status') {
    if (hash_equals($_SESSION['csrf'], $_POST['csrf'] ?? '')) {
        $problem = updateAppointmentStatus($connect2db, (int) ($_POST['appointment_id'] ?? 0), $_POST['status'] ?? '');
        if ($problem !== '') {
            $_SESSION['flash_error'] = $problem;   // shown once after the page reloads
        }
    }
    header('Location: admin_dashboard.php?f=' . urlencode($filter) . '#today');
    exit;
}

$flashError = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_error']);

$busyBarbers = getBusyBarberIds($connect2db);   // barbers with someone On going right now
$counts = getStatusCounts($connect2db);
$stats  = getAdminStats($connect2db, $today);

// Work queues show the soonest first; history shows the newest first
$rows = getAllAppointments(
    $connect2db,
    $tabs[$filter][0],
    in_array($filter, ['completed', 'cancelled', 'all'], true)
);

// Buttons for each status: [new status, button text, primary?, ask to confirm?]
$actions = [
    'Pending'   => [['Ongoing',   'Accept', true, false], ['Cancelled', 'Decline', false, true]],
    'Confirmed' => [['Ongoing', 'Start', true, false],    ['Cancelled', 'Cancel',  false, true]],
    'Ongoing'   => [['Completed', 'Complete', true, false]],
];

$title  = 'Admin dashboard';
$active = 'dashboard';
include __DIR__ . '/includes/admin_header.php';
?>

<small class="mu">Shop management / Dashboard</small>

<?php if ($flashError !== ''): ?>
    <p class="msg error"><?= htmlspecialchars($flashError) ?></p>
<?php endif; ?>

<div class="between head">
    <div>
        <h1>A good day at the shop.</h1>
        <p class="mu"><?= date('l, F j, Y') ?> · Here's every customer's appointments.</p>
    </div>
    <?php if (($_SESSION['user']['role'] ?? '') === 'admin'): ?>
        <div class="row">
            <a href="admin_services.php" class="btn">Manage services</a>
            <a href="admin_barbers.php" class="btn">Manage barbers</a>
        </div>
    <?php endif; ?>
</div>

<div class="g4">
    <div class="card stat">
        <div>
            <small class="mu">Today's appointments</small>
            <h3><?= $stats['total'] ?></h3>
            <small class="mu"><?= $stats['completed'] ?> completed today</small>
        </div>
    </div>
    <div class="card stat">
        <div>
            <small class="mu">Waiting for you</small>
            <h3><?= $counts['Pending'] ?></h3>
            <small class="mu">Pending, not accepted yet</small>
        </div>
    </div>
    <div class="card stat">
        <div>
            <small class="mu">On going now</small>
            <h3><?= $counts['Ongoing'] ?></h3>
            <small class="mu">Barber is on the job</small>
        </div>
    </div>
    <div class="card stat">
        <div>
            <small class="mu">Received today</small>
            <h3><?= money($stats['revenue']) ?></h3>
            <small class="mu"><?= money($stats['to_collect']) ?> still to collect today</small>
        </div>
    </div>
</div>

<div class="card" id="today">
    <h3>Customer appointments</h3>

    <div class="row wrapx tabs">
        <?php foreach ($tabs as $key => [$dbStatus, $label]): ?>
            <a class="chip<?= $filter === $key ? ' on' : '' ?>" href="?f=<?= $key ?>#today">
                <?= $label ?> (<?= $counts[$dbStatus ?? 'All'] ?>)
            </a>
        <?php endforeach; ?>
    </div>

    <div class="scroll">
        <table>
            <tr>
                <th>DATE &amp; TIME</th>
                <th>CUSTOMER</th>
                <th>SERVICE</th>
                <th>BARBER</th>
                <th>STATUS</th>
                <th>PAYMENT</th>
                <th>ACTIONS</th>
            </tr>

            <?php if (empty($rows)): ?>
                <tr><td colspan="7" class="mu">No appointments here.</td></tr>
            <?php else: foreach ($rows as $r): ?>
                <tr>
                    <td>
                        <b><?= $r['appointment_date'] === $today ? 'Today' : date('M j, Y', strtotime($r['appointment_date'])) ?></b><br>
                        <small class="mu"><?= htmlspecialchars($r['appointment_time']) ?></small>
                    </td>
                    <td>
                        <b><?= htmlspecialchars($r['customer_name']) ?></b><br>
                        <small class="mu"><?= htmlspecialchars($r['contact']) ?></small>
                        <?php if (!empty($r['notes'])): ?>
                            <br><small class="mu">Note: <?= htmlspecialchars($r['notes']) ?></small>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?= htmlspecialchars($r['service_name']) ?><br>
                        <small class="mu"><?= (int) $r['duration'] ?> min · <?= money($r['price']) ?></small>
                    </td>
                    <td><?= htmlspecialchars($r['barber_name']) ?></td>
                    <td>
                        <span class="st st-<?= strtolower($r['status']) ?>"><?= htmlspecialchars(statusLabel($r['status'])) ?></span>
                    </td>
                    <td>
                        <?php [$payLabel, $payClass] = paymentBadge($r); ?>
                        <span class="st <?= $payClass ?>"><?= htmlspecialchars($payLabel) ?></span>
                        <?php if (isPayable($r)): ?>
                            <div class="acts" style="margin-top:6px">
                                <form method="post" action="?f=<?= htmlspecialchars($filter) ?>">
                                    <input type="hidden" name="action" value="mark_paid">
                                    <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf']) ?>">
                                    <input type="hidden" name="appointment_id" value="<?= (int) $r['id'] ?>">
                                    <button class="btn" onclick="return confirm('Cash received for this appointment?');">Cash received</button>
                                </form>
                            </div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if (isset($actions[$r['status']])): ?>
                            <form method="post" action="?f=<?= htmlspecialchars($filter) ?>" class="acts">
                                <input type="hidden" name="action" value="set_status">
                                <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf']) ?>">
                                <input type="hidden" name="appointment_id" value="<?= (int) $r['id'] ?>">
                                <?php foreach ($actions[$r['status']] as [$newStatus, $text, $primary, $confirm]):
                                    // Starting someone is blocked while this barber is busy with another customer
                                    $blocked = $newStatus === 'Ongoing' && isset($busyBarbers[(int) $r['barber_id']]);
                                ?>
                                    <button class="btn<?= $primary ? ' p' : '' ?>"
                                            name="status" value="<?= $newStatus ?>"
                                            <?= $blocked ? 'disabled title="' . htmlspecialchars($r['barber_name']) . ' is busy. Complete the on going appointment first."' : '' ?>
                                            <?= $confirm ? "onclick=\"return confirm('$text this appointment?');\"" : '' ?>>
                                        <?= $text ?>
                                    </button>
                                <?php endforeach; ?>
                                <?php if (in_array($r['status'], ['Pending', 'Confirmed'], true) && isset($busyBarbers[(int) $r['barber_id']])): ?>
                                    <small class="mu" style="flex-basis:100%"><?= htmlspecialchars($r['barber_name']) ?> is busy right now.</small>
                                <?php endif; ?>
                            </form>
                        <?php else: ?>
                            <small class="mu">—</small>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
        </table>
    </div>
</div>

<?php include 'includes/footer.php'; ?>