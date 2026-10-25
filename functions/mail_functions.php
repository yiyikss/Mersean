<?php
// Sends emails through your Gmail account (SMTP over a secure connection, no extra libraries needed).

function mailConfig(): array
{
    static $config = null;
    if ($config === null) {
        $config = require __DIR__ . '/mail_config.php';
    }
    return $config;
}

// True when the Gmail address and App Password have been filled in
function mailIsConfigured(): bool
{
    $c = mailConfig();
    return trim($c['smtp_user']) !== '' && trim($c['smtp_pass']) !== '';
}

// True when the page was opened on this same computer (localhost)
function isLocalRequest(): bool
{
    return in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true);
}

// Finds the list of trusted certificates, so PHP can check that the server really is Gmail.
// XAMPP on Windows often has none set in php.ini, so we also look in the usual XAMPP places.
function findCaFile(): string
{
    $candidates = [
        ini_get('openssl.cafile'),
        ini_get('curl.cainfo'),
        'C:/xampp/php/extras/ssl/cacert.pem',
        'C:/xampp/apache/bin/curl-ca-bundle.crt',
        '/etc/ssl/certs/ca-certificates.crt',
    ];
    foreach ($candidates as $file) {
        if ($file && is_file($file)) {
            return $file;
        }
    }
    return '';
}

// Makes a name safe to put in an email header (no line breaks, no quotes), and supports any language
function mailHeaderText(string $text): string
{
    $text = trim(str_replace(["\r", "\n", '"', '<', '>'], ' ', $text));
    return '=?UTF-8?B?' . base64_encode($text) . '?=';
}

// Reads one reply from the mail server. Returns [code, text].
function smtpReply($fp): array
{
    $text = '';
    $code = 0;
    while (($line = fgets($fp, 1024)) !== false) {
        $text .= $line;
        $code  = (int) substr($line, 0, 3);
        if (strlen($line) < 4 || $line[3] === ' ') {
            break;   // last line of this reply
        }
    }
    return [$code, trim($text)];
}

// Sends one command and returns the server's reply code and text.
function smtpCommand($fp, string $command): array
{
    fwrite($fp, $command . "\r\n");
    return smtpReply($fp);
}

// Sends one email. Returns "" if it was sent, or a short error message.
function sendEmail(string $toEmail, string $toName, string $subject, string $html, string $text = ''): string
{
    if (!mailIsConfigured()) {
        return "Email is not set up yet. Fill in functions/mail_config.php.";
    }
    if (!filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
        return "That email address is not valid.";
    }

    $c    = mailConfig();
    $user = trim($c['smtp_user']);
    $pass = str_replace(' ', '', $c['smtp_pass']);   // Google shows the code with spaces
    $host = $c['smtp_host'];
    $port = (int) $c['smtp_port'];

    // Check the server's certificate, so nobody can pretend to be Gmail
    $ssl = ['verify_peer' => true, 'verify_peer_name' => true, 'peer_name' => $host];
    $caFile = !empty($c['ca_file']) ? $c['ca_file'] : findCaFile();
    if ($caFile !== '') {
        $ssl['cafile'] = $caFile;
    }
    $context = stream_context_create(['ssl' => $ssl]);

    $fp = @stream_socket_client("ssl://$host:$port", $errno, $errstr, 10, STREAM_CLIENT_CONNECT, $context);
    if (!$fp) {
        $phpMessage = error_get_last()['message'] ?? '';
        error_log("SMTP connect failed: $errstr $phpMessage");

        if (stripos($phpMessage, 'certificate') !== false || stripos($phpMessage, 'SSL') !== false) {
            return "PHP could not verify the mail server's certificate. Download https://curl.se/ca/cacert.pem, "
                 . "save it on your computer, and put its full path in 'ca_file' in functions/mail_config.php.";
        }
        return "Could not connect to the mail server ($errstr). Check your internet connection, "
             . "and that extension=openssl is turned on in php.ini.";
    }
    stream_set_timeout($fp, 10);

    // Each step: [command to send, reply codes that mean OK, message if it fails]
    $steps = [
        ['EHLO localhost',               [250], "The mail server did not say hello."],
        ['AUTH LOGIN',                   [334], "The mail server does not accept this kind of login."],
        [base64_encode($user),           [334], "The mail server did not accept the Gmail address."],
        [base64_encode($pass),           [235], "Gmail refused the login. Check the Gmail address and that smtp_pass is an App Password, not your normal password."],
        ["MAIL FROM:<$user>",            [250], "The mail server did not accept the sender."],
        ["RCPT TO:<$toEmail>",           [250, 251], "The mail server did not accept the recipient."],
        ['DATA',                         [354], "The mail server would not take the message."],
    ];

    [$code, $reply] = smtpReply($fp);   // the server greets us first
    if ($code !== 220) {
        fclose($fp);
        error_log("SMTP greeting failed: $reply");
        return "The mail server did not answer correctly.";
    }

    foreach ($steps as [$command, $okCodes, $failMessage]) {
        [$code, $reply] = smtpCommand($fp, $command);
        if (!in_array($code, $okCodes, true)) {
            smtpCommand($fp, 'QUIT');
            fclose($fp);
            error_log("SMTP step failed (code $code): $reply");   // never logs the password
            return $failMessage;
        }
    }

    // Build the message. The body is base64, so no line can be too long and no dot-stuffing is needed.
    $boundary = 'b' . bin2hex(random_bytes(8));
    $headers  = [
        'Date: ' . date('r'),
        'From: ' . mailHeaderText($c['from_name']) . " <$user>",
        'To: ' . mailHeaderText($toName) . " <$toEmail>",
        'Subject: ' . mailHeaderText($subject),
        'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . $host . '>',
        'MIME-Version: 1.0',
    ];

    if ($text !== '') {
        $headers[] = "Content-Type: multipart/alternative; boundary=\"$boundary\"";
        $body  = "--$boundary\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
               . chunk_split(base64_encode($text), 76, "\r\n")
               . "--$boundary\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
               . chunk_split(base64_encode($html), 76, "\r\n")
               . "--$boundary--\r\n";
    } else {
        $headers[] = 'Content-Type: text/html; charset=UTF-8';
        $headers[] = 'Content-Transfer-Encoding: base64';
        $body = chunk_split(base64_encode($html), 76, "\r\n");
    }

    fwrite($fp, implode("\r\n", $headers) . "\r\n\r\n" . $body . "\r\n.\r\n");
    [$code, $reply] = smtpReply($fp);

    smtpCommand($fp, 'QUIT');
    fclose($fp);

    if ($code !== 250) {
        error_log("SMTP message rejected (code $code): $reply");
        return "The mail server did not accept the message.";
    }
    return "";
}