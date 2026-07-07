<?php
/**
 * Project: Family GPS Tracker
 * File: includes/config.php
 * Revision: 0.2.0
 * Description: Central application configuration.
 * Author: Jason Lamb / ChatGPT scaffold
 * Created: 2026-07-06
 * Modified: 2026-07-06
 */

declare(strict_types=1);

const APP_NAME = 'Family GPS Tracker';
const APP_REVISION = '0.2.0';
const APP_UPDATED = '2026-07-06';

// For stronger production security, move this directory outside public_html.
define('DATA_DIR', realpath(__DIR__ . '/../data') ?: (__DIR__ . '/../data'));

const MAX_TRAIL_POINTS = 200;
const LOCATION_STALE_SECONDS = 300;
const DEFAULT_TRAIL_MINUTES = 240;
const MAX_TRAIL_LOOKBACK_MINUTES = 1440;
const MIN_PASSWORD_LENGTH = 8;
const SESSION_NAME = 'family_tracker_session';

$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

session_name(SESSION_NAME);
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '',
    'secure' => $isHttps,
    'httponly' => true,
    'samesite' => 'Lax',
]);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
