<?php

require_once __DIR__ . '/mail_functions.php';

function register(array $data, mysqli $connect2db, &$result, &$resultClass): void
{
    $firstname = trim($data['firstname'] ?? '');
    $lastname  = trim($data['lastname'] ?? '');
    $email     = trim($data['email'] ?? '');
    $phone     = trim($data['phone'] ?? '');
    $password  = $data['password'] ?? '';

    if ($firstname === '' || $lastname === '' || $email === '' || $password === '') {
        $resultClass = "error";
        $result = "Please fill in all the required fields.";
        return;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $resultClass = "error";
        $result = "Please enter a valid email address.";
        return;
    }

    if (strlen($password) < 6) {
        $resultClass = "error";
        $result = "Password must be at least 6 characters.";
        return;
    }

    // Stop if this email is already registered
    $check = mysqli_prepare($connect2db, "SELECT id FROM accounts WHERE email = ? LIMIT 1");
    mysqli_stmt_bind_param($check, "s", $email);
    mysqli_stmt_execute($check);
    mysqli_stmt_store_result($check);
    $exists = mysqli_stmt_num_rows($check) > 0;
    mysqli_stmt_close($check);

    if ($exists) {
        $resultClass = "error";
        $result = "That email is already registered. Please log in instead.";
        return;
    }

    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    $stmt = mysqli_prepare($connect2db, "
        INSERT INTO accounts (firstname, lastname, email, phone, password)
        VALUES (?, ?, ?, ?, ?)
    ");

    if (!$stmt) {
        $resultClass = "error";
        $result = "Something went wrong. Please try again.";
        return;
    }

    mysqli_stmt_bind_param($stmt, "sssss", $firstname, $lastname, $email, $phone, $hashedPassword);

    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        $resultClass = "error";
        $result = "Something went wrong. Please try again.";
        return;
    }

    mysqli_stmt_close($stmt);

    $resultClass = "success";
    $result = "Registered Successfully. <a href='login.php'>Log in here</a>.";
}

function login(array $data, mysqli $connect2db, &$result, &$resultClass): void
{
    $email    = trim($data['email'] ?? '');
    $password = $data['password'] ?? '';

    $stmt = mysqli_prepare($connect2db, "
        SELECT id, firstname, lastname, email, phone, password, role
        FROM accounts
        WHERE email = ?
        LIMIT 1
    ");

    if (!$stmt) {
        $resultClass = "error";
        $result = "Something went wrong. Please try again.";
        return;
    }

    mysqli_stmt_bind_param($stmt, "s", $email);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    // One message for both cases so nobody can tell which emails exist
    if (!$row || !password_verify($password, $row['password'])) {
        $resultClass = "error";
        $result = "Incorrect email or password.";
        return;
    }

    // Fresh session id after login (prevents session fixation)
    session_regenerate_id(true);

    $_SESSION['user'] = [
        'id'        => (int) $row['id'],
        'firstname' => $row['firstname'],
        'lastname'  => $row['lastname'],
        'email'     => $row['email'],
        'phone'     => $row['phone'],
        'role'      => $row['role']
    ];

    // Admins and staff start at the admin dashboard, customers at their own
    $default = in_array($row['role'], ['admin', 'staff'], true) ? 'admin_dashboard.php' : 'dashboard.php';

    // Go back to the page they wanted, or the default page for their role
    $target = $_SESSION['redirect_after_login'] ?? $default;
    unset($_SESSION['redirect_after_login']);

    // Only allow redirects within this site (blocks //evil.com and full URLs)
    $isLocalPath = preg_match('#^/(?![/\\\\])#', $target) || preg_match('#^[a-z0-9_\-]+\.php#i', $target);
    if (!$isLocalPath) {
        $target = $default;
    }

    header("Location: " . $target);
    exit();
}

function forgotPassword(array $data, mysqli $connect2db, &$result, &$resultClass): void
{
    $email = trim($data['email'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $resultClass = "error";
        $result = "Please enter a valid email address.";
        return;
    }

    // Same message whether or not the email has an account,
    // so nobody can use this form to find out who is registered.
    $resultClass = "success";
    $result = "If that email is registered, we've sent a link to reset your password. The link works for 1 hour.";

    $stmt = mysqli_prepare($connect2db, "SELECT firstname FROM accounts WHERE email = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, "s", $email);
    mysqli_stmt_execute($stmt);
    $account = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    if (!$account) {
        return;
    }

    // At most one email per minute for the same address (stops people spamming an inbox).
    // A link lasts 1 hour, so a link with more than 59 minutes left was made in the last minute.
    $stmt = mysqli_prepare($connect2db, "
        SELECT id FROM password_resets
        WHERE email = ? AND used = 0 AND expires_at > DATE_ADD(NOW(), INTERVAL 59 MINUTE)
        LIMIT 1
    ");
    mysqli_stmt_bind_param($stmt, "s", $email);
    mysqli_stmt_execute($stmt);
    $tooSoon = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)) !== null;
    mysqli_stmt_close($stmt);

    if ($tooSoon) {
        return;
    }

    // Remove any old links for this email
    $del = mysqli_prepare($connect2db, "DELETE FROM password_resets WHERE email = ?");
    mysqli_stmt_bind_param($del, "s", $email);
    mysqli_stmt_execute($del);
    mysqli_stmt_close($del);

    // The email gets the real token. The database only keeps a hash of it,
    // so a leaked database cannot be used to reset anyone's password.
    $token     = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $token);

    // The expiry time is worked out by the database itself, so PHP and MySQL
    // can never disagree about the time zone.
    $ins = mysqli_prepare($connect2db, "
        INSERT INTO password_resets (email, token, expires_at)
        VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR))
    ");
    mysqli_stmt_bind_param($ins, "ss", $email, $tokenHash);
    mysqli_stmt_execute($ins);
    mysqli_stmt_close($ins);

    // The link starts with app_url from functions/mail_config.php (not the browser's Host header,
    // which anyone could fake to send people to a fake site).
    $resetLink = rtrim(mailConfig()['app_url'], '/') . "/reset_password.php?token=" . $token;

    // Email not set up yet: on your own computer, show the link on screen so you can test.
    // On a real server this never shows the link to anyone.
    if (!mailIsConfigured()) {
        if (isLocalRequest()) {
            $result = "Email is not set up yet, so here is your link (only shown on this computer): "
                    . "<a href='" . htmlspecialchars($resetLink) . "'>Reset my password</a>";
        }
        return;
    }

    $name = htmlspecialchars($account['firstname']);
    $link = htmlspecialchars($resetLink);
    $html = "
        <div style='font-family:Arial,sans-serif;max-width:480px;margin:auto;color:#1f2a24'>
            <h2>Reset your password</h2>
            <p>Hi $name,</p>
            <p>We got a request to reset the password for your Mersean BarberShop account.
               Click the button below to choose a new one. This link works for 1 hour.</p>
            <p><a href='$link' style='display:inline-block;background:#275440;color:#fff;
               padding:12px 20px;border-radius:8px;text-decoration:none;font-weight:bold'>Reset my password</a></p>
            <p style='color:#6d746e;font-size:13px'>If the button does not work, copy this address into your browser:<br>$link</p>
            <p style='color:#6d746e;font-size:13px'>Didn't ask for this? You can ignore this email. Your password will stay the same.</p>
        </div>";

    $plain = "Hi " . $account['firstname'] . ",\n\n"
           . "We got a request to reset the password for your Mersean BarberShop account.\n"
           . "Open this link to choose a new one (it works for 1 hour):\n\n$resetLink\n\n"
           . "Didn't ask for this? You can ignore this email. Your password will stay the same.";

    $error = sendEmail($email, $account['firstname'], "Reset your Mersean BarberShop password", $html, $plain);

    if ($error !== '') {
        error_log("Password reset email failed: " . $error);

        // Show the reason only on your own computer
        if (isLocalRequest()) {
            $resultClass = "error";
            $result = "The email could not be sent: " . htmlspecialchars($error);
        }
    }
}

// True if the token from the email link is real, not used yet, and not expired.
// Puts the account's email in $email.
function validateResetToken(string $token, mysqli $connect2db, &$email): bool
{
    if ($token === '') return false;

    $tokenHash = hash('sha256', $token);

    $stmt = mysqli_prepare($connect2db, "
        SELECT email FROM password_resets
        WHERE token = ? AND used = 0 AND expires_at > NOW()
        LIMIT 1
    ");
    mysqli_stmt_bind_param($stmt, "s", $tokenHash);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    if (!$row) return false;

    $email = $row['email'];
    return true;
}

function resetPassword(array $data, string $token, mysqli $connect2db, &$result, &$resultClass): void
{
    $password        = $data['password'] ?? '';
    $confirmPassword = $data['confirm_password'] ?? '';

    if ($password !== $confirmPassword) {
        $resultClass = "error";
        $result = "The two passwords do not match.";
        return;
    }

    // Same rule the register page shows
    if (strlen($password) < 8 || !preg_match('/[0-9]/', $password)) {
        $resultClass = "error";
        $result = "Your password needs at least 8 characters, including a number.";
        return;
    }

    if (!validateResetToken($token, $connect2db, $email)) {
        $resultClass = "error";
        $result = "This reset link is invalid or has expired. Please ask for a new one.";
        return;
    }

    $hashed = password_hash($password, PASSWORD_DEFAULT);

    $upd = mysqli_prepare($connect2db, "UPDATE accounts SET password = ? WHERE email = ?");
    mysqli_stmt_bind_param($upd, "ss", $hashed, $email);
    mysqli_stmt_execute($upd);
    mysqli_stmt_close($upd);

    // Remove every reset link for this account, so none of them can be used again
    $del = mysqli_prepare($connect2db, "DELETE FROM password_resets WHERE email = ?");
    mysqli_stmt_bind_param($del, "s", $email);
    mysqli_stmt_execute($del);
    mysqli_stmt_close($del);

    $resultClass = "success";
    $result = "Your password was changed. <a href='login.php'>Log in here</a>.";
}