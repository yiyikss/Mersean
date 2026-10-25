<?php 
require_once __DIR__ . '/../db/connect.php';
require_once __DIR__ . '/ui_functions.php';
require_once __DIR__ . '/../functions/appointment_function.php';

$title  = $title  ?? 'Oak & Blade';
$active = $active ?? '';

// Logged-in user info (falls back to a generic label if nobody is logged in)
$user      = $_SESSION['user'] ?? null;
$fullName  = $user ? trim($user['firstname'] . ' ' . $user['lastname']) : 'Guest';
$initials  = $user
    ? strtoupper(substr($user['firstname'], 0, 1) . substr($user['lastname'], 0, 1))
    : '?';

$nav = [
    ['dashboard', 'Dashboard', 'grid', 'dashboard.php'],
    ['services', 'Services', 'scissors', 'services.php'],
    ['book', 'Book appointment', 'calendar', 'book.php'],
    ['appts', 'My appointments', 'calendar', 'dashboard.php#recent']
];
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($title) ?> Mersean BarberShop</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <?= favicon() ?>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>

<header class="top">
    <div class="row">
        <button class="btn menu" id="menu" aria-label="Menu">
            <?= ic('menu') ?>
        </button>
        <?= logo() ?>
    </div>
    
    <div class="row who">
        <span class="loc">
            <?= ic('pin') ?> Sampaguita, San Roque I.C
        </span>
        <?= ic('bell') ?>
        <span class="av in"><?= htmlspecialchars($initials) ?></span>
        <span class="nm">
            <b><?= htmlspecialchars($fullName) ?></b>
            <small><?= $user ? ucfirst($user['role']) . ' account' : 'Not logged in' ?></small>
        </span>
    </div>
</header>

<div class="shell">
    <aside class="side" id="side">
        <small class="lbl">YOUR SPACE</small>
        
        <?php foreach($nav as $n): ?>
            <a href="<?= $n[3] ?>" class="<?= $active == $n[0] ? 'on' : '' ?>">
                <?= ic($n[2]) ?> <?= $n[1] ?>
            </a>
        <?php endforeach; ?>
        
        <div class="grow"></div>
        
        <a href="logout.php">
            <?= ic('logout') ?> Log out
        </a>
    </aside>
    
    <main class="main">