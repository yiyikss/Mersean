<?php 
require_once __DIR__ . '/db/connect.php';              // defines $connect2db + starts session
require_once __DIR__ . '/includes/ui_functions.php';
require_once __DIR__ . '/functions/appointment_function.php';
require_once __DIR__ . '/functions/catalog_functions.php';

$services = getServices($connect2db);
$barbers  = getBarbers($connect2db);

$rev = [
    // ... unchanged ...
];
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Mersean BarberShop · Neighborhood barbers</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <?= favicon() ?>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>

<header class="top">
    <?= logo() ?>
    <nav class="lnav">
        <a href="#">Home</a>
        <a href="#services">Our services</a>
        <a href="#barbers">Our barbers</a>
    </nav>
    <div class="row">
        <a href="login.php" class="lk">Log in</a>
        <a href="register.php" class="btn p">Book an appointment</a>
    </div>
</header>

<section class="wrap hero">
    <div>
        <span class="pill">● YOUR NEIGHBORHOOD BARBERSHOP</span>
        <h1 class="big">A fresh cut.<br>A better day.</h1>
        <p class="mu">Good hair shouldn't be complicated. Find your style, pick your barber, and leave the rest to us.</p>
        
        <div class="row wrapx">
            <a href="register.php" class="btn p">Book your chair <?= ic('arrow') ?></a>
            <a href="#services" class="btn">Explore services</a>
        </div>
        
    </div>
    
    <div class="photo" style="background-image:url(assets/img/hero-4x3.jpg)">
        <span class="card">
            Mersean BarberShop<br>
            <small class="mu">San Roque, Sampaguita</small>
        </span>
    </div>
</section>

<section class="strip">
    <div class="wrap g3">
        <span><?= ic('calendar') ?> Book in less than a minute</span>
        <span><?= ic('scissors') ?> Experienced, friendly barbers</span>
        <span><?= ic('clock') ?> Your time. Your chair.</span>
    </div>
</section>

<section class="wrap sec" id="services">
    <div class="between wrapx">
        <div>
            <small class="ey">THE MENU</small>
            <h2 class="h2">Your style. Our craft.</h2>
            <p class="mu">Honest prices. Thoughtful service. A look you'll feel good in.</p>
        </div>
        <a href="services.php" class="btn">View all services <?= ic('arrow') ?></a>
    </div>
    
    <div class="g3 svc-grid">
        <?php foreach(array_slice($services, 0, 4) as $s): ?>
            <div class="card">
                <?php if (serviceImagePath($s) !== ''): ?>
                    <img class="svc-img" src="<?= htmlspecialchars(serviceImagePath($s)) ?>"
                         alt="<?= htmlspecialchars($s['name']) ?> reference photo" loading="lazy">
                <?php endif; ?>
                <h3><?= htmlspecialchars($s['name']) ?></h3>
                <p class="mu"><?= htmlspecialchars($s['description']) ?></p>
                <div class="between">
                    <h2><?= money($s['price']) ?></h2>
                    <small class="mu"><?= $s['duration'] ?> min</small>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<section class="band" id="barbers">
    <div class="wrap sec c">
        <small class="ey">THE FACES BEHIND THE FADE</small>
        <h2 class="h2">Good hands. Great people.</h2>
        <p class="mu">Meet the crew who makes every visit feel like catching up with a friend.</p>
        
        <div class="g3 l">
            <?php foreach($barbers as $b): ?>
                <div class="card row">
                    <?= av($b['photo']) ?>
                    <div>
                        <b><?= htmlspecialchars($b['name']) ?></b><br>
                        <small class="mu"><?= htmlspecialchars($b['specialty']) ?></small>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<footer class="wrap foot">
    <div>
        <?= logo() ?>
        <p class="mu small">A neighborhood shop with a simple mission: good hair, good company, every time.</p>
    </div>
    
    <div>
        <b>Come say hello</b>
        <p class="mu small">
            San Roque, Sampaguita<br>
            (718) 555-0148
        </p>
    </div>
    
    <div>
        <b>Shop hours</b>
        <p class="mu small">
            Mon–Fri 9 am – 7 pm<br>
            Saturday 9 am – 5 pm<br>
            Sunday Closed
        </p>
    </div>
    
    <div>
        <b>Stay connected</b>
        <p class="mu small">
            Instagram ↗<br>
            Our services<br>
            Contact us
        </p>
    </div>
</footer>

</body>
</html>