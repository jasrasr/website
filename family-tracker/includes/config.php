<?php
/**
 * Project: Family GPS Tracker
 * File: includes/config.php
 * Revision: 1.4.2
 * Description: Central application configuration.
 * Author: Jason Lamb / ChatGPT scaffold
 * Created: 2026-07-06
 * Modified: 2026-07-09
 */

declare(strict_types=1);

const APP_NAME = 'Family GPS Tracker';
const APP_REVISION = '1.4.2';
const APP_UPDATED = '2026-07-09';
const APP_BUILD_LABEL = '2026-07-09 15:30 ET';

define('DATA_DIR', realpath(__DIR__ . '/../data') ?: (__DIR__ . '/../data'));

const MAX_TRAIL_POINTS = 200;
const LOCATION_STALE_SECONDS = 300;
const DEFAULT_TRAIL_MINUTES = 240;
const MAX_TRAIL_LOOKBACK_MINUTES = 1440;
const MAX_FAMILY_NOTICES = 25;
const AUTO_LOCATION_INTERVAL_SECONDS = 60;
const SESSION_LIFETIME_SECONDS = 2592000;
const REMEMBER_COOKIE_NAME = 'family_tracker_remember';
const REMEMBER_ME_LIFETIME_SECONDS = 7776000;
const MIN_PASSWORD_LENGTH = 8;
const SESSION_NAME = 'family_tracker_session';

$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

ini_set('session.gc_maxlifetime', (string)SESSION_LIFETIME_SECONDS);
ini_set('session.cookie_lifetime', (string)SESSION_LIFETIME_SECONDS);
session_name(SESSION_NAME);
session_set_cookie_params([
    'lifetime' => SESSION_LIFETIME_SECONDS,
    'path' => '/',
    'domain' => '',
    'secure' => $isHttps,
    'httponly' => true,
    'samesite' => 'Lax',
]);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
