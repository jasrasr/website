<?php
/*
===========================================================
 File: config.php
 Version: 2.1.0
 Author: Jason Lamb + ChatGPT
 Created: 2025-11-23
 Modified: 2025-11-23
 Description:
   Global configuration for Secure Upload & File Manager.
   - Defines directories, logging, and security settings.
   - Shared by both /admin and /api components.
===========================================================
*/

// Root directory for this project (file-manager/)
$ROOT_DIR = __DIR__;

// Logs directory
$LOG_DIR = $ROOT_DIR . '/logs';

// Data directory
$DATA_DIR = $ROOT_DIR . '/data';

// Uploadable base directories (relative to ROOT)
$allowedDirectories = [
    'uploads'
];

// API Key for uploads (CHANGE THIS)
define('API_KEY', 'YourSuperSecretKey123');

// Whitelist password for whitelistme.php (CHANGE THIS)
define('WHITELIST_PASSWORD', 'ChangeThisWhitelistPassword!');

// Dynamic IP allowlist file (JSON)
define('ALLOWED_IPS_FILE', $DATA_DIR . '/allowed_ips.json');

// MFA secret file (JSON; currently plain text storage)
define('MFA_SECRET_FILE', $DATA_DIR . '/mfa_secret.json');

// Logs
$LOG_UPLOAD    = $LOG_DIR . '/upload.log';
$LOG_RATE      = $LOG_DIR . '/rate_limit.log';
$LOG_PS        = $LOG_DIR . '/powershell.log';
$WHITELIST_LOG = $LOG_DIR . '/whitelist.log';
$SECURITY_LOG  = $LOG_DIR . '/security.log';

// Rate limiting – uploads: 60 uploads per 60 minutes per IP
$RATE_LIMIT_MAX    = 60;
$RATE_LIMIT_WINDOW = 3600; // seconds (60 minutes)

// Base allowed IPs that are always allowed (merge with dynamic)
$baseAllowedIPs = [
    '127.0.0.1',
    '::1'
];

// MaxMind GeoLite2 Country DB path
$geoDB = $ROOT_DIR . '/geoip/GeoLite2-Country.mmdb';

// --------------------------------------------------------
// Ensure directories and core files exist
// --------------------------------------------------------

if (!is_dir($LOG_DIR)) {
    mkdir($LOG_DIR, 0755, true);
}

if (!is_dir($DATA_DIR)) {
    mkdir($DATA_DIR, 0755, true);
}

foreach ($allowedDirectories as $dir) {
    $full = $ROOT_DIR . '/' . $dir;
    if (!is_dir($full)) {
        mkdir($full, 0755, true);
    }
}

if (!file_exists(ALLOWED_IPS_FILE)) {
    file_put_contents(ALLOWED_IPS_FILE, json_encode([], JSON_PRETTY_PRINT));
}

if (!file_exists(MFA_SECRET_FILE)) {
    file_put_contents(MFA_SECRET_FILE, json_encode(new stdClass()));
}

// --------------------------------------------------------
// Helper: logging
// --------------------------------------------------------
function write_log(string $path, string $msg) : void {
    file_put_contents($path, "[" . date("Y-m-d H:i:s") . "] " . $msg . "\n", FILE_APPEND);
}

// --------------------------------------------------------
// Helper: dynamic allowed IPs
// --------------------------------------------------------
function get_dynamic_allowed_ips() : array {
    $file = ALLOWED_IPS_FILE;
    if (!file_exists($file)) {
        return [];
    }
    $json = file_get_contents($file);
    $data = json_decode($json, true);
    return is_array($data) ? $data : [];
}

function save_dynamic_allowed_ips(array $ips) : void {
    file_put_contents(ALLOWED_IPS_FILE, json_encode(array_values(array_unique($ips)), JSON_PRETTY_PRINT));
}

function add_ip_to_allowed_list(string $ip, string $source = 'unknown') : void {
    global $WHITELIST_LOG;

    $ip = trim($ip);
    if ($ip === '') {
        return;
    }

    $ips = get_dynamic_allowed_ips();
    if (!in_array($ip, $ips, true)) {
        $ips[] = $ip;
        save_dynamic_allowed_ips($ips);
        write_log($WHITELIST_LOG, "WHITELIST | IP=$ip | SOURCE=$source");
    }
}

// Build effective allowed IPs
$dynamicIPs = get_dynamic_allowed_ips();
$allowedIPs = array_values(array_unique(array_merge($baseAllowedIPs, $dynamicIPs)));
