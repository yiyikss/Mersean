<?php 
require_once __DIR__ . '/db/connect.php';
require_once __DIR__ . '/functions/appointment_function.php';
require_once __DIR__ . '/functions/paypal_functions.php';
require_once __DIR__ . '/functions/auth_guard.php';   // must be logged in

$userId    = (int) $_SESSION['user']['id'];
$firstName = $_SESSION['user']['firstname'];

// Cancel button (handled before any HTML is printed)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'cancel') {
    cancelAppointment($connect2db, (int) ($_POST['appointment_id'] ?? 0), $userId);
    header('Location: dashboard.php?cancelled=1');
    exit;
}

$upcoming  = getUpcomingAppointments($connect2db, $userId);
$next      = $upcoming[0] ?? null;          // null = no upcoming appointment
$completed = getCompletedCount($connect2db, $userId);
$favorite  = getFavoriteBarber($connect2db, $userId);
$recent    = getMyAppointments($connect2db, $userId, 5);
$paypalOn  = paypalConfigured();

$title  = 'Dashboard';
$active = 'dashboard';
include __DIR__ . '/includes/header.php';
?>

<small class="mu">Your space / Dashboard</small>

<?php if (isset($_GET['booked'])): ?>
    <p class="msg success">Your appointment has been booked. See you soon!</p>
<?php endif; ?>
<?php if (isset($_GET['cancelled'])): ?>
    <p class="msg success">Your appointment has been cancelled.</p>
<?php endif; ?>

<div class="between head">
    <div>
        <h1>Looking sharp, <?= htmlspecialchars($firstName) ?>.</h1>
        <p class="mu">
            <?= $next
                ? "Your next good hair day is on the calendar. Here's everything at a glance."
                : "You have no upcoming appointment. Ready for a fresh cut?" ?>
        </p>
    </div>
    <a href="book.php" class="btn p">Book appointment <?= ic('plus') ?></a>
</div>

<div class="g3">
    <div class="card stat">
        <div>
            <small class="mu">Your next visit</small>
            <?php if ($next): ?>
                <h3><?= date('F j', $next['starts_at']) ?></h3>
                <small class="mu"><?= date('l', $next['starts_at']) ?> at <?= htmlspecialchars($next['appointment_time']) ?></small>
            <?php else: ?>
                <h3>None</h3>
                <small class="mu">Nothing booked yet</small>
            <?php endif; ?>
        </div>
    </div>

    <div class="card stat">
        <div>
            <small class="mu">Appointments completed</small>
            <h3><?= $completed ?> <?= $completed === 1 ? 'visit' : 'visits' ?></h3>
            <small class="mu"><?= $completed > 0 ? 'Looking fresh' : 'Your first cut is waiting' ?></small>
        </div>
    </div>

    <div class="card stat">
        <div>
            <small class="mu">Your go-to barber</small>
            <?php if ($favorite): ?>
                <h3><?= htmlspecialchars($favorite['name']) ?></h3>
                <small class="mu"><?= htmlspecialchars($favorite['specialty'] ?? '') ?></small>
            <?php else: ?>
                <h3>None yet</h3>
                <small class="mu">Book a visit to pick a favorite</small>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="cols">
    <div class="card">
        <div class="between">
            <h3>Your next appointment</h3>
            <?php if ($next): ?>
                <span class="pill"><?= match ($next['status']) {
                    'Confirmed' => '✓ Confirmed',
                    'Ongoing'   => '✂ On going',
                    default     => '● Pending',
                } ?></span>
            <?php endif; ?>
        </div>

        <?php if ($next): 
            $endsAt = $next['starts_at'] + ((int) $next['duration'] * 60);
        ?>
            <div class="appt">
                <div class="date">
                    <small><?= strtoupper(date('M', $next['starts_at'])) ?></small>
                    <b><?= date('j', $next['starts_at']) ?></b>
                    <small><?= strtoupper(date('l', $next['starts_at'])) ?></small>
                </div>
                <div class="grow">
                    <h2><?= htmlspecialchars($next['service_name']) ?></h2>
                    <p class="mu"><?= date('g:i A', $next['starts_at']) ?> – <?= date('g:i A', $endsAt) ?> · <?= (int) $next['duration'] ?> min</p>
                    <p class="mu small"><?= av($next['barber_photo'], 'sm') ?> with <?= htmlspecialchars($next['barber_name']) ?></p>
                </div>
                <h2><?= money($next['price']) ?></h2>
            </div>

            <hr>

            <div class="between wrapx">
                <span class="mu small"><?= ic('pin') ?> Sampaguita, San Roque I.C</span>
                <span class="row wrapx">
                    <?php [$nextPayLabel, $nextPayClass] = paymentBadge($next); ?>
                    <span class="st <?= $nextPayClass ?>"><?= htmlspecialchars($nextPayLabel) ?></span>
                    <?php if ($paypalOn && isPayable($next)): ?>
                        <a href="pay.php?id=<?= (int) $next['id'] ?>" class="btn p">Pay with PayPal</a>
                    <?php endif; ?>
                    <?php if ($next['status'] === 'Pending' && $next['payment_status'] !== 'Paid'): ?>
                        <form method="post" style="display:inline" onsubmit="return confirm('Cancel this appointment?');">
                            <input type="hidden" name="action" value="cancel">
                            <input type="hidden" name="appointment_id" value="<?= (int) $next['id'] ?>">
                            <button type="submit" class="mu small" style="background:none;border:0;cursor:pointer;font:inherit;">Cancel</button>
                        </form>
                    <?php endif; ?>
                    <a href="book.php?s=<?= (int) $next['service_id'] ?>" class="btn">Book another</a>
                </span>
            </div>

        <?php else: ?>
            <p class="mu" style="margin:24px 0 8px;">You don't have an upcoming appointment.</p>
            <a href="book.php" class="btn p">Book your chair <?= ic('arrow') ?></a>
        <?php endif; ?>
    </div>

    <div class="card tint">
        <?= ic('clock') ?>
        <?php if ($next): ?>
            <h2>We'll have your chair ready.</h2>
            <p class="mu">Arrive 5 minutes early, settle in, and let us take care of the rest. A fresh cut is just around the corner.</p>
        <?php else: ?>
            <h2>Your chair is waiting.</h2>
            <p class="mu">Choose a service, pick a barber and a time, and we'll take care of the rest.</p>
        <?php endif; ?>
    </div>
</div>

<div class="cols" id="recent">
    <div class="card">
        <div class="between">
            <h3>Recent appointments</h3>
        </div>

        <div class="scroll">
            <table>
                <tr>
                    <th>SERVICE</th>
                    <th>DATE</th>
                    <th>BARBER</th>
                    <th>STATUS</th>
                </tr>
                <?php if (empty($recent)): ?>
                    <tr>
                        <td colspan="4" class="mu">No appointments yet.</td>
                    </tr>
                <?php else: foreach ($recent as $r): ?>
                    <tr>
                        <td>
                            <b><?= htmlspecialchars($r['service_name']) ?></b><br>
                            <?php [$rPayLabel, $rPayClass] = paymentBadge($r); ?>
                            <small class="mu"><?= money($r['price']) ?></small>
                            <span class="st <?= $rPayClass ?>"><?= htmlspecialchars($rPayLabel) ?></span>
                            <?php if ($paypalOn && isPayable($r)): ?>
                                <a href="pay.php?id=<?= (int) $r['id'] ?>" class="lk small">Pay now</a>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?= date('M j, Y', strtotime($r['appointment_date'])) ?><br>
                            <small class="mu"><?= htmlspecialchars($r['appointment_time']) ?></small>
                        </td>
                        <td><?= htmlspecialchars($r['barber_name']) ?></td>
                        <td class="<?= $r['status'] === 'Cancelled' ? 'mu' : 'lk' ?>"><?= htmlspecialchars($r['status']) ?></td>
                    </tr>
                <?php endforeach; endif; ?>
            </table>
        </div>
    </div>

    <div class="card">
        <h3>Your neighborhood shop</h3>
        <p class="mu"> Sampaguita <br>San Roque, I.C</p>
        <p class="mu small">
            Mon – Fri &nbsp; 9 am – 7 pm<br>
            Saturday &nbsp; 9 am – 5 pm
        </p>
        <p class="mu small">0936 435 0350</p>
    </div>
</div>

<?php include 'includes/footer.php'; ?>