<?php
require_once __DIR__ . '/ui_functions.php';

$title  = $title  ?? 'Admin';
$active = $active ?? '';
$user      = $_SESSION['user'];
$fullName  = trim($user['firstname'] . ' ' . $user['lastname']);
$initials  = strtoupper(substr($user['firstname'], 0, 1) . substr($user['lastname'], 0, 1));

$nav = [
    ['dashboard', 'Dashboard',    'grid',     'admin_dashboard.php'],
    ['today',     'Appointments', 'calendar', 'admin_dashboard.php?f=pending#today'],
];

// Only admins can manage services and barbers (staff just see appointments)
if (($user['role'] ?? '') === 'admin') {
    $nav[] = ['services', 'Services', 'scissors', 'admin_services.php'];
    $nav[] = ['barbers',  'Barbers',  'user',     'admin_barbers.php'];
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($title) ?> · Mersean Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <?= favicon() ?>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>

<header class="top">
    <div class="row">
        <button class="btn menu" id="menu" aria-label="Menu"><?= ic('menu') ?></button>
        <?= logo() ?>
    </div>
    <div class="row who">
        <span class="av in"><?= htmlspecialchars($initials) ?></span>
        <span class="nm">
            <b><?= htmlspecialchars($fullName) ?></b>
            <small>Shop <?= $user['role'] === 'admin' ? 'administrator' : 'staff' ?></small>
        </span>
    </div>
</header>

<div class="shell">
    <aside class="side" id="side">
        <small class="lbl">SHOP MANAGEMENT</small>

        <?php foreach ($nav as $n): ?>
            <a href="<?= $n[3] ?>" class="<?= $active === $n[0] ? 'on' : '' ?>">
                <?= ic($n[2]) ?> <?= $n[1] ?>
            </a>
        <?php endforeach; ?>

        <div class="grow"></div>

        <a href="logout.php"><?= ic('logout') ?> Log out</a>
    </aside>

    <main class="main">