<?php
/**
 * Project: Family GPS Tracker
 * File: includes/config.php
 * Revision: 1.6.3
 * Description: Central application configuration with deployment-derived Eastern Time update labeling.
 * Author: Jason Lamb / ChatGPT scaffold
 * Created: 2026-07-06
 * Modified: 2026-07-13
 */

declare(strict_types=1);

const APP_NAME = 'Friends & Family GPS Tracker';
const APP_REVISION = '1.6.3';
const APP_UPDATED = '2026-07-14';
const CONSENT_VERSION = '2026-07-11';
const LOGIN_THROTTLE_MAX_FAILURES = 5;
const LOGIN_THROTTLE_WINDOW_SECONDS = 900;
const LOGIN_THROTTLE_BLOCK_SECONDS = 900;
const AUDIT_RETENTION_DAYS = 90;

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

/**
 * Returns the newest deployed modification time among revision-defining files.
 * This avoids manually entered build clocks and reflects the deployed copy.
 */
function app_latest_update_timestamp(): int
{
    $root = dirname(__DIR__);
    $paths = [
        __FILE__,
        $root . '/index.php',
        $root . '/service-worker.js',
        $root . '/CHANGELOG.md',
    ];

    $timestamps = [];
    foreach ($paths as $path) {
        if (is_file($path)) {
            $modified = filemtime($path);
            if ($modified !== false) {
                $timestamps[] = $modified;
            }
        }
    }

    return $timestamps ? max($timestamps) : time();
}

/**
 * Formats a timestamp in Eastern Time. America/New_York automatically applies
 * EST or EDT while the interface consistently labels the zone as ET.
 */
function app_update_label_et(?int $timestamp = null): string
{
    $timestamp ??= app_latest_update_timestamp();
    $date = (new DateTimeImmutable('@' . $timestamp))
        ->setTimezone(new DateTimeZone('America/New_York'));

    return $date->format('M j, Y \a\t g:i A') . ' ET';
}

define('APP_BUILD_LABEL', app_update_label_et());

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
