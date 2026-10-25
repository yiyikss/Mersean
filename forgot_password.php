<?php
require_once __DIR__ . '/db/connect.php';          // starts the session + $connect2db
require_once __DIR__ . '/includes/ui_functions.php';
require_once __DIR__ . '/functions/auth_functions.php';

// Already logged in? Skip this page.
if (isset($_SESSION['user'])) {
    header("Location: dashboard.php");
    exit;
}

$result = '';
$resultClass = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    forgotPassword($_POST, $connect2db, $result, $resultClass);
}

// Once the link is on its way we hide the form and just show the message
$sent = ($resultClass === 'success');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Forgot password · Mersean BarberShop</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <?= favicon() ?>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>

<div class="auth">
    <?php include 'includes/auth_side.php'; ?>

    <section class="form">
        <a href="login.php" class="back">← Back to log in</a>

        <form action="forgot_password.php" method="post">
            <small class="ey">NO WORRIES</small>
            <h1>Forgot your password?</h1>
            <p class="mu">Enter the email you signed up with and we'll send you a link to choose a new one.</p>

            <?php if ($result !== ''): ?>
                <p class="msg <?= htmlspecialchars($resultClass) ?>"><?= $result ?></p>
            <?php endif; ?>

            <?php if (!$sent): ?>
                <label>
                    Email address
                    <input type="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required autofocus>
                </label>

                <button class="btn p full">Send reset link <?= ic('arrow') ?></button>
            <?php else: ?>
                <p class="mu small">Nothing in your inbox after a few minutes? Check your spam folder, or <a href="forgot_password.php" class="lk">try again</a>.</p>
            <?php endif; ?>

            <hr>

            <p class="mu c">
                Remembered it? <a href="login.php" class="lk">Log in</a>
            </p>
        </form>

        <p class="legal">© 2026 Mersean BarberShop &nbsp; Privacy policy &nbsp; Terms of service</p>
    </section>
</div>

<script src="assets/app.js"></script>
</body>
</html>