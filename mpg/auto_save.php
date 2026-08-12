<?php
// ============================================================================
// File: auto_save.php
// Purpose: Save manual/scanned fuel entries and return JSON success or error
// Revision: 1.1.0
// Author: Jason Lamb
//
// Revision Notes:
// 1.1.0 - Add full/partial fill tracking, full-to-full MPG roll-up,
//         optional comments, station brand persistence, and location metadata.
// 1.0.1 - Existing shared fuel save endpoint.
// ============================================================================

header('Content-Type: application/json');

require_once __DIR__ . '/device_init.php';

function fail($msg) {
    echo json_encode(['error' => $msg]);
    exit;
}

function cleanText($value, $maxLength) {
    $value = trim((string)$value);
    if (function_exists('mb_substr')) {
        return mb_substr($value, 0, $maxLength);
    }
    return substr($value, 0, $maxLength);
}

function isFullFill($entry) {
    // Legacy entries pre-date fill_type and are treated as full fills.
    return strtolower((string)($entry['fill_type'] ?? 'full')) !== 'partial';
}

// ── Plate (text field takes priority over dropdown) ───────────────────────────
$dropdown = preg_replace('/[^A-Z0-9]/', '', strtoupper(trim($_POST['plateDropdown'] ?? '')));
$textbox  = preg_replace('/[^A-Z0-9]/', '', strtoupper(trim($_POST['licensePlate']  ?? '')));
$plate    = $textbox !== '' ? $textbox : $dropdown;
if ($plate === '') fail('License plate is required.');
$_SESSION['active_plate'] = $plate;

// ── Date ─────────────────────────────────────────────────────────────────────
$date = trim($_POST['date'] ?? '');
if ($date === '') {
    $date = (new DateTime('now', new DateTimeZone('America/New_York')))->format('Y-m-d');
}

// ── Odometer ─────────────────────────────────────────────────────────────────
$odometer = floatval($_POST['odometer'] ?? 0);
if ($odometer <= 0) fail('Odometer must be greater than zero.');

// ── Fill type / notes / station metadata ─────────────────────────────────────
$fillType = strtolower(trim($_POST['fillType'] ?? 'full'));
if (!in_array($fillType, ['full', 'partial'], true)) {
    $fillType = 'full';
}

$comment = cleanText($_POST['comment'] ?? '', 500);

$stationBrand = cleanText($_POST['stationBrand'] ?? '', 80);
$stationBrandOther = cleanText($_POST['stationBrandOther'] ?? '', 80);
if (strtolower($stationBrand) === 'other' && $stationBrandOther !== '') {
    $stationBrand = $stationBrandOther;
}
if (strtolower($stationBrand) === 'other') {
    $stationBrand = '';
}

$stationLocationId = cleanText($_POST['stationLocationId'] ?? '', 120);
$locationSource    = cleanText($_POST['locationSource'] ?? '', 40);

$latitude  = ($_POST['latitude'] ?? '') !== '' ? floatval($_POST['latitude']) : null;
$longitude = ($_POST['longitude'] ?? '') !== '' ? floatval($_POST['longitude']) : null;
if ($latitude !== null && ($latitude < -90 || $latitude > 90)) $latitude = null;
if ($longitude !== null && ($longitude < -180 || $longitude > 180)) $longitude = null;

// ── Fuel math: any 2 of pricePerGallon / gallons / totalPrice ────────────────
$pg_raw  = $_POST['pricePerGallon'] ?? '';
$gal_raw = $_POST['gallons']        ?? '';
$ttl_raw = $_POST['totalPrice']     ?? '';

$pg_input  = ($pg_raw  !== '') ? floatval($pg_raw)  : null;
$gal_input = ($gal_raw !== '') ? floatval($gal_raw) : null;
$ttl_input = ($ttl_raw !== '') ? floatval($ttl_raw) : null;

$provided = !is_null($pg_input) + !is_null($gal_input) + !is_null($ttl_input);
if ($provided < 2) fail('Enter at least two of: Price per Gallon, Gallons, Total Cost.');

if (!is_null($pg_input) && $pg_input <= 0) fail('Price per gallon must be greater than zero.');
if (!is_null($gal_input) && $gal_input <= 0) fail('Gallons must be greater than zero.');
if (!is_null($ttl_input) && $ttl_input <= 0) fail('Total cost must be greater than zero.');

// Calculate missing value.
if (is_null($ttl_input))       $ttl_input = round($pg_input * $gal_input, 2);
elseif (is_null($pg_input))    $pg_input  = round($ttl_input / $gal_input, 3);
elseif (is_null($gal_input))   $gal_input = round($ttl_input / $pg_input, 3);

// Add +0.009 only if price was entered with 2 or fewer decimal places.
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
    $lastEntry = end($entries);
    $lastOdo   = floatval($lastEntry['odometer'] ?? 0);
    $rawMiles  = $odometer - $lastOdo;
    if ($odometer == $lastOdo || $rawMiles <= 0) {
        fail("Duplicate or invalid entry — odometer unchanged or miles ≤ 0. Previous odometer: {$lastOdo}");
    }
    $miles = round($rawMiles, 1);
}

// ── Full-to-full MPG ──────────────────────────────────────────────────────────
// Partial fills remain real fuel events but do not produce a standalone MPG.
// The next full fill closes the interval and includes every intervening gallon.
$mpg = null;
$mpgMiles = null;
$mpgGallons = null;

if ($fillType === 'full' && !empty($entries)) {
    $previousFullIndex = null;

    for ($i = count($entries) - 1; $i >= 0; $i--) {
        if (isFullFill($entries[$i])) {
            $previousFullIndex = $i;
            break;
        }
    }

    if ($previousFullIndex !== null) {
        $previousFullOdo = floatval($entries[$previousFullIndex]['odometer'] ?? 0);
        $spanMiles = $odometer - $previousFullOdo;

        $spanGallons = $gallons;
        for ($i = $previousFullIndex + 1; $i < count($entries); $i++) {
            $spanGallons += floatval($entries[$i]['gallons'] ?? 0);
        }

        if ($spanMiles > 0 && $spanGallons > 0) {
            $mpgMiles   = round($spanMiles, 1);
            $mpgGallons = round($spanGallons, 3);
            $mpg        = round($spanMiles / $spanGallons, 2);
        }
    }
}

// ── Persist newly entered station brands ──────────────────────────────────────
if ($stationBrand !== '') {
    $stationsFile = __DIR__ . '/stations.json';
    $stationData = ['brands' => [], 'locations' => []];

    if (file_exists($stationsFile)) {
        $decodedStations = json_decode(file_get_contents($stationsFile), true);
        if (is_array($decodedStations)) {
            $stationData = array_merge($stationData, $decodedStations);
        }
    }

    if (!isset($stationData['brands']) || !is_array($stationData['brands'])) {
        $stationData['brands'] = [];
    }

    $exists = false;
    foreach ($stationData['brands'] as $existingBrand) {
        if (strcasecmp(trim((string)$existingBrand), $stationBrand) === 0) {
            $stationBrand = trim((string)$existingBrand); // Preserve existing display casing.
            $exists = true;
            break;
        }
    }

    if (!$exists) {
        $stationData['brands'][] = $stationBrand;
        natcasesort($stationData['brands']);
        $stationData['brands'] = array_values($stationData['brands']);
        file_put_contents($stationsFile, json_encode($stationData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
    }
}

// ── Timestamp ─────────────────────────────────────────────────────────────────
$submittedET = (new DateTime('now', new DateTimeZone('America/New_York')))->format('Y-m-d H:i:s T');

// ── Save ──────────────────────────────────────────────────────────────────────
$source = ($_POST['source'] ?? 'manual') === 'scan' ? 'scan' : 'manual';

$entry = [
    'license_plate'    => $plate,
    'date'             => $date,
    'odometer'         => $odometer,
    'miles'            => $miles,
    'gallons'          => $gallons,
    'price_per_gallon' => $price_per_gallon,
    'total_cost'       => $total_cost,
    'fill_type'        => $fillType,
    'mpg'              => $mpg,
    'mpg_miles'        => $mpgMiles,
    'mpg_gallons'      => $mpgGallons,
    'submitted_et'     => $submittedET,
    'ip_address'       => $visitorIP,
    'device_id'        => $deviceId,
    'source'           => $source,
    'verified'         => 'no'
];

if ($comment !== '') $entry['comment'] = $comment;
if ($stationBrand !== '') $entry['station_brand'] = $stationBrand;
if ($stationLocationId !== '') $entry['station_location_id'] = $stationLocationId;
if ($latitude !== null) $entry['latitude'] = $latitude;
if ($longitude !== null) $entry['longitude'] = $longitude;
if ($locationSource !== '') $entry['location_source'] = $locationSource;

$entries[] = $entry;

file_put_contents($logFile, json_encode($entries, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);

// ── Update device entry count ─────────────────────────────────────────────────
$dwFile = __DIR__ . '/device_whitelist.json';
if (file_exists($dwFile)) {
    $dw = json_decode(file_get_contents($dwFile), true);
    if (is_array($dw) && isset($dw[$deviceId])) {
        $dw[$deviceId]['entry_count'] = ($dw[$deviceId]['entry_count'] ?? 0) + 1;
        file_put_contents($dwFile, json_encode($dw, JSON_PRETTY_PRINT), LOCK_EX);
    }
}

$mpgDisplay = $fillType === 'partial'
    ? 'Pending next full fill-up'
    : ($mpg !== null ? number_format($mpg, 2) : 'N/A — baseline full fill');

echo json_encode([
    'success'      => true,
    'plate'        => $plate,
    'date'         => $date,
    'odometer'     => $odometer,
    'miles'        => $miles,
    'gallons'      => $gallons,
    'price'        => number_format($price_per_gallon, 3),
    'total'        => number_format($total_cost, 2),
    'fillType'     => $fillType,
    'mpg'          => $mpg,
    'mpgDisplay'   => $mpgDisplay,
    'mpgMiles'     => $mpgMiles,
    'mpgGallons'   => $mpgGallons,
    'stationBrand' => $stationBrand,
    'comment'      => $comment,
    'submitted'    => $submittedET
]);
