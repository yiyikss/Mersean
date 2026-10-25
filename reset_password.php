<?php
require_once __DIR__ . '/db/connect.php';          // starts the session + $connect2db
require_once __DIR__ . '/includes/ui_functions.php';
require_once __DIR__ . '/functions/auth_functions.php';

// The token comes from the email link (?token=...). The form sends it back in a hidden field.
$token = $_POST['token'] ?? $_GET['token'] ?? '';
if (!is_string($token)) {
    $token = '';
}

$result = '';
$resultClass = '';
$done = false;

$valid = validateResetToken($token, $connect2db, $accountEmail);

if ($valid && $_SERVER['REQUEST_METHOD'] === 'POST') {
    resetPassword($_POST, $token, $connect2db, $result, $resultClass);
    $done = ($resultClass === 'success');
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="referrer" content="no-referrer">
    <title>Reset password · Mersean BarberShop</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <?= favicon() ?>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>

<div class="auth">
    <?php include 'includes/auth_side.php'; ?>

    <section class="form">
        <a href="login.php" class="back">← Back to log in</a>

        <form action="reset_password.php" method="post">
            <small class="ey">ALMOST THERE</small>
            <h1>Choose a new password.</h1>

            <?php if ($done): ?>
                <p class="msg success"><?= $result ?></p>

            <?php elseif (!$valid): ?>
                <p class="msg error">This reset link is invalid or has expired.</p>
                <a href="forgot_password.php" class="btn p full">Get a new link <?= ic('arrow') ?></a>

            <?php else: ?>
                <p class="mu">Pick something you'll remember. You'll use it to log in right away.</p>

                <?php if ($result !== ''): ?>
                    <p class="msg <?= htmlspecialchars($resultClass) ?>"><?= $result ?></p>
                <?php endif; ?>

                <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">

                <label>
                    New password
                    <span class="pw">
                        <input type="password" name="password" minlength="8" required autofocus>
                        <button type="button" aria-label="Show password"><?= ic('eye') ?></button>
                    </span>
                    <small class="mu">Use at least 8 characters, including a number.</small>
                </label>

                <label>
                    Confirm new password
                    <span class="pw">
                        <input type="password" name="confirm_password" minlength="8" required>
                        <button type="button" aria-label="Show password"><?= ic('eye') ?></button>
                    </span>
                </label>

                <button class="btn p full">Change password <?= ic('arrow') ?></button>
            <?php endif; ?>

            <hr>

            <p class="mu c">
                Back to <a href="login.php" class="lk">Log in</a>
            </p>
        </form>

        <p class="legal">© 2026 Mersean BarberShop &nbsp; Privacy policy &nbsp; Terms of service</p>
    </section>
</div>

<script src="assets/app.js"></script>
</body>
</html>