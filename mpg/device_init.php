<?php
// ============================================================================
// File: device_init.php
// Purpose: Identify device via cookie-based ID + soft fingerprint,
//          combine with IP whitelist, track visits/entries, allow blocking.
// Exposes:
//   $visitorIP, $deviceId, $deviceName, $isDeviceTrusted, $isDeviceBlocked,
//   $defaultPlate, $isIPWhitelisted, $isAdminTrusted
// Revision: 2.0
// Author: Jason Lamb
// ============================================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$visitorIP  = $_SERVER['REMOTE_ADDR']          ?? 'UNKNOWN';
$userAgent  = $_SERVER['HTTP_USER_AGENT']      ?? 'UNKNOWN';
$acceptLang = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';

// Build a simple soft fingerprint (server-side only)
// NOTE: We can extend this later with JS-provided data (screen size, tz, etc.)
$fingerprintSource = $userAgent . '|' . $acceptLang;
$fingerprintHash   = hash('sha256', $fingerprintSource);

// ------------------------------------------------------------------
// Load device whitelist JSON
// ------------------------------------------------------------------
$deviceWhitelistFile = __DIR__ . "/device_whitelist.json";
$deviceWhitelist = [];

if (file_exists($deviceWhitelistFile)) {
    $decoded = json_decode(file_get_contents($deviceWhitelistFile), true);
    if (is_array($decoded)) {
        $deviceWhitelist = $decoded;
    }
}

// ------------------------------------------------------------------
// Determine deviceId
// 1. If cookie exists and is valid, use it
// 2. Else try to find existing device by fingerprint
// 3. Else create a new deviceId
// ------------------------------------------------------------------
$deviceIdFromCookie = null;
if (isset($_COOKIE['device_id']) && preg_match('/^device_[a-f0-9]{16}$/', $_COOKIE['device_id'])) {
    $deviceIdFromCookie = $_COOKIE['device_id'];
}

$deviceId = null;

// Option 1: valid cookie present
if ($deviceIdFromCookie && isset($deviceWhitelist[$deviceIdFromCookie])) {
    $deviceId = $deviceIdFromCookie;
} elseif ($deviceIdFromCookie && !isset($deviceWhitelist[$deviceIdFromCookie])) {
    // Cookie present but not in file (old cookie or file reset)
    // We'll treat this as "new device" below.
}

// Option 2: try to match by fingerprint if we don't have a usable cookie
if ($deviceId === null) {
    foreach ($deviceWhitelist as $id => $info) {
        if (!empty($info['fingerprint']) && $info['fingerprint'] === $fingerprintHash) {
            // Reuse this existing device
            $deviceId = $id;
            break;
        }
    }
}

// Option 3: no match at all → create new deviceId
if ($deviceId === null) {
    $deviceId = 'device_' . bin2hex(random_bytes(8));
}

// Ensure cookie is set to the chosen deviceId (5-year cookie, scoped to /mpg)
if (!isset($_COOKIE['device_id']) || $_COOKIE['device_id'] !== $deviceId) {
    setcookie('device_id', $deviceId, time() + (86400 * 365 * 5), "/mpg", "", false, true);
}

// ------------------------------------------------------------------
// Get or initialize this device's record
// ------------------------------------------------------------------
$nowIso = date('c');

if (!isset($deviceWhitelist[$deviceId])) {
    $deviceWhitelist[$deviceId] = [
        'trusted'     => false,
        'blocked'     => false,
        'device_name' => null,
        'plate'       => null,
        'created_at'  => $nowIso,
        'last_seen'   => $nowIso,
        'last_ip'     => $visitorIP,
        'user_agent'  => $userAgent,
        'fingerprint' => $fingerprintHash,
        'visit_count' => 1,
        'entry_count' => 0
    ];
} else {
    // Update existing entry
    $deviceWhitelist[$deviceId]['last_seen']   = $nowIso;
    $deviceWhitelist[$deviceId]['last_ip']     = $visitorIP;
    $deviceWhitelist[$deviceId]['user_agent']  = $userAgent;
    $deviceWhitelist[$deviceId]['fingerprint'] = $fingerprintHash;
    $deviceWhitelist[$deviceId]['visit_count'] = ($deviceWhitelist[$deviceId]['visit_count'] ?? 0) + 1;

    if (!isset($deviceWhitelist[$deviceId]['entry_count'])) {
        $deviceWhitelist[$deviceId]['entry_count'] = 0;
    }
    if (!isset($deviceWhitelist[$deviceId]['blocked'])) {
        $deviceWhitelist[$deviceId]['blocked'] = false;
    }
    if (!array_key_exists('device_name', $deviceWhitelist[$deviceId])) {
        $deviceWhitelist[$deviceId]['device_name'] = null;
    }
}

// Persist updated device info
file_put_contents($deviceWhitelistFile, json_encode($deviceWhitelist, JSON_PRETTY_PRINT));

// Snapshot for easy access
$thisDevice    = $deviceWhitelist[$deviceId];
$deviceName    = $thisDevice['device_name'] ?? null;
$defaultPlate  = $thisDevice['plate']       ?? null;
$visitCount    = $thisDevice['visit_count'] ?? 0;
$entryCount    = $thisDevice['entry_count'] ?? 0;
$isDeviceBlockedRaw = !empty($thisDevice['blocked']);

// ------------------------------------------------------------------
// Load IP whitelist
// ------------------------------------------------------------------
$ipWhitelistFile = __DIR__ . "/ip_whitelist.json";
$ipWhitelist = file_exists($ipWhitelistFile)
    ? json_decode(file_get_contents($ipWhitelistFile), true)
    : [];

if (!is_array($ipWhitelist)) {
    $ipWhitelist = [];
}

$isIPWhitelisted = in_array($visitorIP, $ipWhitelist);

// ------------------------------------------------------------------
// Compute trust / blocked / admin status
// ------------------------------------------------------------------
$isDeviceBlocked = $isDeviceBlockedRaw ? true : false;

// If device is blocked and IP is not whitelisted, stop here
if ($isDeviceBlocked && !$isIPWhitelisted) {
    die("<h2>Your device has been blocked from this system.</h2>");
}

$isDeviceTrusted = (!$isDeviceBlocked && !empty($thisDevice['trusted']));
$isAdminTrusted  = ($isIPWhitelisted || $isDeviceTrusted);
