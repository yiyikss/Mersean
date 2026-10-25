<?php
$title = 'Book appointment'; $active = 'book';
require_once 'db/connect.php';
require_once 'functions/appointment_function.php';
require_once 'functions/paypal_functions.php';
require_once 'functions/auth_guard.php';   // you must be logged in to book

$paypalOn = paypalConfigured();
$services = getServices($connect2db);
$barbers  = getBarbers($connect2db);

// The selected service (by id). If none is chosen, use the first one.
$sv = getService($connect2db, (int)($_GET['s'] ?? 0));
if (!$sv) {
    $sv = $services[0];
}

// When the form is sent, save the appointment in the database
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newId = 0;
    if (!$paypalOn) {
        $_POST['payment_method'] = 'shop';     // PayPal is not set up yet
    }
    $error = makeAppointment($_POST, $connect2db, (int)$_SESSION['user']['id'], $newId);

    if ($error === '') {
        if (($_POST['payment_method'] ?? '') === 'paypal') {
            header('Location: pay.php?id=' . $newId);   // pay online right away
        } else {
            header('Location: dashboard.php?booked=1');
        }
        exit;
    }
}

include 'includes/header.php';
?>

<small class="mu">Your space / Book appointment</small>
<h1>Make time for a fresh cut.</h1>
<p class="mu">Choose your barber and a time that works for you. We'll save you a chair.</p>

<?php if ($error): ?>
    <p class="msg error"><?= htmlspecialchars($error) ?></p>
<?php endif; ?>

<form action="book.php?s=<?= $sv['id'] ?>" method="post" class="bk">
    <div class="stack">
        
        <div class="card between">
            <div class="row">
                <div>
                    <h3><?= htmlspecialchars($sv['name']) ?></h3>
                    <small class="mu"><?= $sv['duration'] ?> minutes · <?= money($sv['price']) ?></small>
                </div>
            </div>
            <a href="services.php" class="lk">Change service</a>
        </div>
        
        <div class="card">
            <h3>1. Available Barber</h3>
            <div class="g3 bar">
                <?php foreach($barbers as $i =>$b): ?>
                    <label class="bcard">
                        <input type="radio" name="barber_id" value="<?= $b['id'] ?>" data-n="<?= htmlspecialchars($b['name']) ?>" <?= $i ? '' : 'checked' ?>>
                        <span>
                            <?= av($b['photo'], 'sm') ?>
                            <span>
                                <b><?= htmlspecialchars($b['name']) ?></b>
                                <small class="mu"><?= htmlspecialchars($b['specialty']) ?></small>
                            </span>
                        </span>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>
        
        <div class="card">
            <h3>2. Pick a date &amp; time</h3>
            <div class="dt">
                <div>
                    <div class="between">
                        <b id="ttl"></b>
                        <span>
                            <button type="button" id="prev" class="nb">‹</button>
                            <button type="button" id="next" class="nb">›</button>
                        </span>
                    </div>
                    
                    <div class="days">
                        <?php foreach(str_split('MTWTFSS') as $d) echo "<small>$d</small>"; ?>
                    </div>
                    
                    <div class="days" id="cal" data-dur="<?= $sv['duration'] ?>" data-slots="<?= htmlspecialchars(json_encode(getTimeSlots())) ?>"></div>
                    <small class="mu">All times in Eastern Time (ET)</small>
                </div>
                
                <div>
                    <b id="dl"></b><br>
                    <small class="mu">Choose an available time</small>
                    <div class="slots" id="slots"></div>
                    <small class="mu">Crossed-out times are already booked</small>
                    <small class="mu" id="fnote" hidden style="display:block;color:#9b1c1c">No free time left on this day. Please pick another day or barber.</small>
                </div>
            </div>
        </div>
        
        <label>
            <b>Phone number</b>
            <input name="contact" value="<?= htmlspecialchars($_POST['contact'] ?? ($_SESSION['user']['phone'] ?? '')) ?>" placeholder="Your contact number" required>
        </label>

        <label>
            <b>Anything we should know? (optional)</b>
            <input name="notes" placeholder="A style in mind? Let your barber know...">
        </label>
        
    </div>
    
    <aside class="card sum">
        <h2>Your appointment</h2>
        <h3><?= htmlspecialchars($sv['name']) ?></h3>
        <small class="mu">Wash, cut &amp; styled finish</small>
        <hr>
        
        <p>
            <span><b id="sb"><?= htmlspecialchars($barbers[0]['name']) ?></b></span>
        </p>
        <p>
            <span>
                <b id="sd"></b><br>
                <small class="mu" id="st"></small>
            </span>
        </p>
        <p>
            <span>
                <b>Mersean BarberShop</b><br>
                <small class="mu">San Roque, Sampaguita</small>
            </span>
        </p>
        
        <hr>
        
        <div class="between">
            <span>Total</span>
            <h2><?= money($sv['price']) ?></h2>
        </div>

        <b style="font-size:14px">How would you like to pay?</b>
        <div class="payopts">
            <label class="chk payopt">
                <input type="radio" name="payment_method" value="shop" checked>
                <span>Pay Later<br><small class="mu">No deposit needed</small></span>
            </label>
            <label class="chk payopt" <?= $paypalOn ? '' : 'hidden' ?>>
                <input type="radio" name="payment_method" value="paypal">
                <span>Pay now with PayPal<br><small class="mu">Secure online payment</small></span>
            </label>
        </div>
        
        <input type="hidden" name="service_id" value="<?= $sv['id'] ?>">
        <input type="hidden" name="date" id="fd">
        <input type="hidden" name="time" id="ft">

        <button class="btn p full" id="confirmbtn">Confirm appointment <?= ic('arrow') ?></button>
    </aside>
</form>

<?php include 'includes/footer.php'; ?>