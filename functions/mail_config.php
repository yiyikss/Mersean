<?php
// Email settings for the password reset email. It is sent from your own Gmail account.
//
// Gmail does not let websites use your normal password. You need an "App Password":
//   1. Turn on 2-Step Verification: myaccount.google.com > Security > 2-Step Verification
//   2. Open myaccount.google.com/apppasswords, type any name (e.g. Mersean) and click Create
//   3. Google shows a 16-letter code. Paste it below (spaces are fine).
//
// Keep this file private. Never share the App Password or upload it to GitHub.
return [
    'smtp_user' => 'echavezbrybry@gmail.com',                        // your Gmail address, e.g. yourname@gmail.com
    'smtp_pass' => 'zeix rhrt xfaw joom',                        // the 16-letter App Password (not your normal password)
    'from_name' => 'Mersean BarberShop',      // the name people see in their inbox
    'app_url'   => 'http://localhost/ob',     // where your site lives (no slash at the end)
    'smtp_host' => 'smtp.gmail.com',          // leave this as it is
    'smtp_port' => 465,                       // leave this as it is
    'ca_file'   => '',                        // only fill this in if you get "certificate verify failed"
];