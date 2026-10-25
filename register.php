<?php
require_once 'db/connect.php';
require_once 'functions/auth_functions.php';
require_once 'includes/ui_functions.php';

// Already logged in? Go straight to the dashboard.
if (isset($_SESSION['user'])) {
    header('Location: dashboard.php');
    exit;
}

$result = '';
$resultClass = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first    = trim($_POST['first'] ?? '');
    $last     = trim($_POST['last'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $phone    = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm'] ?? '';

    if ($first == '' || $last == '' || $email == '' || $password == '') {
        $result = 'Please fill in all the required fields.';
        $resultClass = 'error';
    } elseif (strlen(preg_replace('/[^0-9]/', '', $phone)) < 7) {
        $result = 'Please enter a valid phone number.';
        $resultClass = 'error';
    } elseif (strlen($password) < 8 || !preg_match('/[0-9]/', $password)) {
        $result = 'Your password needs at least 8 characters, including a number.';
        $resultClass = 'error';
    } elseif ($password !== $confirm) {
        $result = 'The two passwords do not match.';
        $resultClass = 'error';
    } else {
        // register() expects the keys firstname, lastname, email, phone, password
        $data = [
            'firstname' => $first,
            'lastname'  => $last,
            'email'     => $email,
            'phone'     => $phone,
            'password'  => $password
        ];
        register($data, $connect2db, $result, $resultClass);

        if ($resultClass === 'success') {
            header('Location: login.php?registered=1');
            exit;
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Create account · Mersean BarberShop</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <?= favicon() ?>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>

<div class="auth">
    <?php include 'includes/auth_side.php'; ?>

    <section class="form">
        <a href="index.php" class="back">← Back to home</a>

        <form action="register.php" method="post">
            <small class="ey">MAKE YOURSELF AT HOME</small>
            <h1>Join the neighborhood.</h1>
            <p class="mu">A better cut is just a few details away.</p>

            <?php if ($result): ?>
                <p class="msg <?= $resultClass ?>"><?= htmlspecialchars($result) ?></p>
            <?php endif; ?>

            <div class="two">
                <label>
                    First name
                    <input name="first" value="<?= htmlspecialchars($_POST['first'] ?? '') ?>" required>
                </label>
                <label>
                    Last name
                    <input name="last" value="<?= htmlspecialchars($_POST['last'] ?? '') ?>" required>
                </label>
            </div>

            <label>
                Email address
                <input type="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
            </label>

            <label>
                Phone number
                <input type="tel" name="phone" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>" placeholder="0917 123 4567" required>
            </label>

            <label>
                Password
                <span class="pw">
                    <input type="password" name="password" minlength="8" required>
                    <button type="button" aria-label="Show password"><?= ic('eye') ?></button>
                </span>
                <small class="mu">Use at least 8 characters, including a number.</small>
            </label>

            <label>
                Confirm password
                <span class="pw">
                    <input type="password" name="confirm" minlength="8" required>
                    <button type="button" aria-label="Show password"><?= ic('eye') ?></button>
                </span>
            </label>

            <button class="btn p full">Create account <?= ic('arrow') ?></button>

            <hr>

            <p class="mu c">
                Already have an account? <a href="login.php" class="lk">Log in</a>
            </p>
        </form>

        <p class="legal">© 2026 Oak &amp; Blade &nbsp; Privacy policy &nbsp; Terms of service</p>
    </section>
</div>

<script src="assets/app.js"></script>
</body>
</html>