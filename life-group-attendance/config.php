<?php
declare(strict_types=1);

const APP_NAME = 'Life Group Attendance';
const APP_VERSION = '1.3.2';
const APP_UPDATED = '2026-09-02';
const SESSION_NAME = 'life_group_portal';
const DATA_DIR = __DIR__ . '/data';

date_default_timezone_set('America/New_York');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name(SESSION_NAME);
    session_set_cookie_params([
        'httponly' => true,
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'samesite' => 'Lax',
    ]);
    session_start();
}
