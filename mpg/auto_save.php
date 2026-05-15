<?php
// ============================================================================
// File: auto_save.php
// Purpose: Save fuel entry from scan_photos, return JSON success or error
// Revision: 1.0.1
// Author: Jason Lamb
// ============================================================================

header('Content-Type: application/json');

require_once __DIR__ . '/device_init.php';

function fail($msg) {
    echo json_encode(['error' => $msg]);
    exit;
}

// ── Plate (text field takes priority over dropdown) ───────────────────────────
$dropdown = preg_replace('/[^A-Z0-9]/', '', strtoupper(trim($_POST['plateDropdown'] ?? '')));
$textbox  = preg_replace('/[^A-Z0-9]/', '', strtoupper(trim($_POST['licensePlate']  ?? '')));
$plate    = $textbox !== '' ? $textbox : $dropdown;
if ($plate === '') fail('License plate is required.');
$_SESSION['active_plate'] = $plate;

// ── Date ─────────────────────────────────────────────────────────────────────
$date = trim($_POST['date'] ?? '');
if ($date === '') $date = (new DateTime('now', new DateTimeZone('America/New_York')))->format('Y-m-d');

// ── Odometer ─────────────────────────────────────────────────────────────────
$odometer = floatval($_POST['odometer'] ?? 0);
if ($odometer <= 0) fail('Odometer must be greater than zero.');

// ── Fuel math: any 2 of pricePerGallon / gallons / totalPrice ────────────────
$pg_raw  = $_POST['pricePerGallon'] ?? '';
$gal_raw = $_POST['gallons']        ?? '';
$ttl_raw = $_POST['totalPrice']     ?? '';

$pg_input  = ($pg_raw  !== '') ? floatval($pg_raw)  : null;
$gal_input = ($gal_raw !== '') ? floatval($gal_raw) : null;
$ttl_input = ($ttl_raw !== '') ? floatval($ttl_raw) : null;

$provided = !is_null($pg_input) + !is_null($gal_input) + !is_null($ttl_input);
if ($provided < 2) fail('Enter at least two of: Price per Gallon, Gallons, Total Cost.');

// Calculate missing value
if (is_null($ttl_input))       $ttl_input = round($pg_input * $gal_input, 2);
elseif (is_null($pg_input))    $pg_input  = round($ttl_input / $gal_input, 3);
elseif (is_null($gal_input))   $gal_input = round($ttl_input / $pg_input, 3);

// Add +0.009 only if price was entered with 2 or fewer decimal places
$pg_decimals      = (strpos($pg_raw, '.') !== false) ? strlen(substr($pg_raw, strpos($pg_raw, '.') + 1)) : 0;
$price_per_gallon = ($pg_decimals >= 3) ? $pg_input : $pg_input + 0.009;
$gallons          = $gal_input;
$total_cost       = $ttl_input;

// ── Load existing entries ─────────────────────────────────────────────────────
$logDir  = __DIR__ . '/logs/';
if (!is_dir($logDir)) mkdir($logDir, 0755, true);
$logFile = $logDir . "{$plate}.json";

$entries = [];
if (file_exists($logFile)) {
    $decoded = json_decode(file_get_contents($logFile), true);
    if (is_array($decoded)) $entries = $decoded;
}

// ── Miles & duplicate check ───────────────────────────────────────────────────
$miles = 0;
if (!empty($entries)) {
    $lastOdo  = floatval(end($entries)['odometer'] ?? 0);
    $rawMiles = $odometer - $lastOdo;
    if ($odometer == $lastOdo || $rawMiles <= 0) {
        fail("Duplicate or invalid entry — odometer unchanged or miles ≤ 0. Previous odometer: {$lastOdo}");
    }
    $miles = round($rawMiles, 1);
}

// ── MPG ───────────────────────────────────────────────────────────────────────
$mpg = ($gallons > 0 && $miles > 0) ? round($miles / $gallons, 2) : 0;

// ── Timestamp ─────────────────────────────────────────────────────────────────
$submittedET = (new DateTime('now', new DateTimeZone('America/New_York')))->format('Y-m-d H:i:s T');

// ── Save ──────────────────────────────────────────────────────────────────────
$source = ($_POST['source'] ?? 'manual') === 'scan' ? 'scan' : 'manual';

$entries[] = [
    'license_plate'    => $plate,
    'date'             => $date,
    'odometer'         => $odometer,
    'miles'            => $miles,
    'gallons'          => $gallons,
    'price_per_gallon' => $price_per_gallon,
    'total_cost'       => $total_cost,
    'mpg'              => $mpg,
    'submitted_et'     => $submittedET,
    'ip_address'       => $visitorIP,
    'device_id'        => $deviceId,
    'source'           => $source,
    'verified'         => 'no'
];

file_put_contents($logFile, json_encode($entries, JSON_PRETTY_PRINT), LOCK_EX);

// ── Update device entry count ─────────────────────────────────────────────────
$dwFile = __DIR__ . '/device_whitelist.json';
if (file_exists($dwFile)) {
    $dw = json_decode(file_get_contents($dwFile), true);
    if (is_array($dw) && isset($dw[$deviceId])) {
        $dw[$deviceId]['entry_count'] = ($dw[$deviceId]['entry_count'] ?? 0) + 1;
        file_put_contents($dwFile, json_encode($dw, JSON_PRETTY_PRINT), LOCK_EX);
    }
}

echo json_encode([
    'success'    => true,
    'plate'      => $plate,
    'date'       => $date,
    'odometer'   => $odometer,
    'miles'      => $miles,
    'gallons'    => $gallons,
    'price'      => number_format($price_per_gallon, 3),
    'total'      => number_format($total_cost, 2),
    'mpg'        => $mpg,
    'submitted'  => $submittedET
]);
