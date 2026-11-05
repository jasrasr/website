<?php

declare(strict_types=1);

return [
    'app_name' => 'Baby Daily',
    'timezone' => 'America/New_York',
    'due_date' => '2026-11-02',
    'storage_path' => dirname(__DIR__) . '/storage',
    'max_upload_bytes' => 15 * 1024 * 1024,
    'session_name' => 'baby_daily_session',
    'app_url' => 'https://jasr.me/github/daily-babe/',
    'smtp' => [
        'host' => 'smtp.titan.email',
        'port' => 465,
        'encryption' => 'ssl',
        'authentication' => true,
        'username' => 'notifications@justjason.fyi',
        'password' => '', // Set only in ignored config/config.php on the server.
        'from_email' => 'notifications@justjason.fyi',
        'from_name' => 'Daily Babe',
    ],
    'twilio' => [
        'account_sid' => '', // Set only in ignored config/config.php on the server.
        'auth_token' => '', // Set only in ignored config/config.php on the server.
        'from_number' => '', // Twilio SMS-capable number in E.164 format, for example +12165550123.
    ],
];


