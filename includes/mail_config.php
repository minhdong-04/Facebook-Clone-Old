<?php
// Mail/SMTP config (local/dev friendly).
// TIP: Do NOT commit real passwords.
//
// Gmail example (recommended):
// - Create an App Password in Google Account Security (2FA required)
// - Use SMTP_USER = your full gmail
// - Use SMTP_PASS = app password (16 chars)
//
// Common SMTP presets:
// - Gmail: host=smtp.gmail.com, port=587, secure=tls
// - Outlook/Hotmail: host=smtp.office365.com, port=587, secure=tls
//
// You can also set these via environment variables instead:
// FB_FROM_EMAIL, FB_SITE_NAME, FB_SMTP_HOST, FB_SMTP_PORT, FB_SMTP_USER, FB_SMTP_PASS, FB_SMTP_SECURE

return [
    // Visible sender
    'FROM_EMAIL' => 'minhdong9678@gmail.com',
    'SITE_NAME'  => 'Facebook',

    // SMTP (leave empty to disable SMTP)
    // Gmail preset:
    // - Host: smtp.gmail.com
    // - Port: 587 (STARTTLS)
    // - User: your full Gmail address
    // - Pass: Google "App Password" (requires 2FA)
    'SMTP_HOST'   => 'smtp.gmail.com',
    'SMTP_PORT'   => 587,
    'SMTP_USER'   => 'minhdong9678@gmail.com',
    'SMTP_PASS'   => 'cfno ayiw cgid eqvi',
    'SMTP_SECURE' => 'tls', // 'tls' or 'ssl'
];
