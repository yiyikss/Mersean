<?php
require_once __DIR__ . '/db/connect.php';          // starts the session + $connect2db
require_once __DIR__ . '/includes/ui_functions.php';
require_once __DIR__ . '/functions/auth_functions.php';

// Already logged in? Skip the login page.
if (isset($_SESSION['user'])) {
    header("Location: dashboard.php");
    exit;
}

$result = '';
$resultClass = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // On success, login() redirects and exits. On failure it fills $result.
    login($_POST, $connect2db, $result, $resultClass);
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Log in · Mersean BarberShop</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <?= favicon() ?>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>

<div class="auth">
    <?php include 'includes/auth_side.php'; ?>
    
    <section class="form">
        <a href="index.php" class="back">← Back to home</a>
        
        <form action="login.php" method="post">
            <small class="ey">WELCOME BACK</small>
            <h1>Good to see you again.</h1>
            <p class="mu">Log in to book your next cut and manage your visits.</p>

            <?php if ($result !== ''): ?>
                <p class="msg <?= htmlspecialchars($resultClass) ?>"><?= $result ?></p>
            <?php endif; ?>
            
            <label>
                Email address
                <input type="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
            </label>
            
            <label>
                Password
                <span class="pw">
                    <input type="password" name="password" required>
                    <button type="button" aria-label="Show password"><?= ic('eye') ?></button>
                </span>
            </label>
            
            <div class="between">
                <label class="chk">
                    <input type="checkbox" checked> Remember me
                </label>
                <a href="forgot_password.php" class="lk">Forgot password?</a>
            </div>
            
            <button class="btn p full">Log in <?= ic('arrow') ?></button>
            
            <hr>
            
            <p class="mu c">
                New around here? <a href="register.php" class="lk">Create an account</a>
            </p>
        </form>
        
        <p class="legal">© 2026 Mersean BarberShop &nbsp; Privacy policy &nbsp; Terms of service</p>
    </section>
</div>

<script src="assets/app.js"></script>
</body>
</html>