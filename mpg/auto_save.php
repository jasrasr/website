<?php
// ============================================================================
// File: auto_save.php
// Purpose: Save manual/scanned fuel entries and return JSON success or error
// Revision: 1.2.0
// Author: Jason Lamb
//
// Revision Notes:
// 1.2.0 - Resolve saved station profiles, persist location labels, and validate
//         price/gallons/total consistency. Fix .009 handling for calculated PPG.
// 1.1.0 - Add full/partial fill tracking, full-to-full MPG roll-up,
//         optional comments, station brand persistence, and location metadata.
// ============================================================================

header('Content-Type: application/json');
require_once __DIR__ . '/device_init.php';

function fail($msg) {
    echo json_encode(['error' => $msg]);
    exit;
}

function cleanText($value, $maxLength) {
    $value = trim((string)$value);
    return function_exists('mb_substr') ? mb_substr($value, 0, $maxLength) : substr($value, 0, $maxLength);
}

function isFullFill($entry) {
    return strtolower((string)($entry['fill_type'] ?? 'full')) !== 'partial';
}

function loadStations($file) {
    $data = ['brands' => [], 'locations' => []];
    if (file_exists($file)) {
        $decoded = json_decode(file_get_contents($file), true);
        if (is_array($decoded)) $data = array_merge($data, $decoded);
    }
    if (!is_array($data['brands'] ?? null)) $data['brands'] = [];
    if (!is_array($data['locations'] ?? null)) $data['locations'] = [];
    return $data;
}

// ── Plate ─────────────────────────────────────────────────────────────────────
$dropdown = preg_replace('/[^A-Z0-9]/', '', strtoupper(trim($_POST['plateDropdown'] ?? '')));
$textbox  = preg_replace('/[^A-Z0-9]/', '', strtoupper(trim($_POST['licensePlate'] ?? '')));
$plate    = $textbox !== '' ? $textbox : $dropdown;
if ($plate === '') fail('License plate is required.');
$_SESSION['active_plate'] = $plate;

// ── Date / odometer ───────────────────────────────────────────────────────────
$date = trim($_POST['date'] ?? '');
if ($date === '') $date = (new DateTime('now', new DateTimeZone('America/New_York')))->format('Y-m-d');

$odometer = (float)($_POST['odometer'] ?? 0);
if ($odometer <= 0) fail('Odometer must be greater than zero.');

// ── Fill type / notes ─────────────────────────────────────────────────────────
$fillType = strtolower(trim($_POST['fillType'] ?? 'full'));
if (!in_array($fillType, ['full', 'partial'], true)) $fillType = 'full';
$comment = cleanText($_POST['comment'] ?? '', 500);

// ── Station / location metadata ───────────────────────────────────────────────
$stationsFile = __DIR__ . '/stations.json';
$stationData = loadStations($stationsFile);

$stationBrand = cleanText($_POST['stationBrand'] ?? '', 80);
$stationBrandOther = cleanText($_POST['stationBrandOther'] ?? '', 80);
if (strtolower($stationBrand) === 'other' && $stationBrandOther !== '') $stationBrand = $stationBrandOther;
if (strtolower($stationBrand) === 'other') $stationBrand = '';

$stationLocationId = cleanText($_POST['stationLocationId'] ?? '', 120);
$locationSource = cleanText($_POST['locationSource'] ?? '', 40);
$latitude  = ($_POST['latitude'] ?? '') !== '' ? (float)$_POST['latitude'] : null;
$longitude = ($_POST['longitude'] ?? '') !== '' ? (float)$_POST['longitude'] : null;
if ($latitude !== null && ($latitude < -90 || $latitude > 90)) $latitude = null;
if ($longitude !== null && ($longitude < -180 || $longitude > 180)) $longitude = null;

$stationProfile = null;
if ($stationLocationId !== '') {
    foreach ($stationData['locations'] as $loc) {
        if (($loc['id'] ?? '') === $stationLocationId) {
            $stationProfile = $loc;
            break;
        }
    }
}

$stationCity = '';
$stationStreet = '';
$stationIntersection = '';
$stationNickname = '';
$stationLocationLabel = '';

if ($stationProfile) {
    if ($stationBrand === '') $stationBrand = cleanText($stationProfile['brand'] ?? $stationProfile['name'] ?? '', 80);
    if ($latitude === null && isset($stationProfile['latitude'])) $latitude = (float)$stationProfile['latitude'];
    if ($longitude === null && isset($stationProfile['longitude'])) $longitude = (float)$stationProfile['longitude'];
    if ($locationSource === '') $locationSource = 'saved_station';

    $stationCity = cleanText($stationProfile['city'] ?? '', 100);
    $stationStreet = cleanText($stationProfile['street'] ?? '', 140);
    $stationIntersection = cleanText($stationProfile['intersection'] ?? '', 140);
    $stationNickname = cleanText($stationProfile['nickname'] ?? '', 100);

    $labelParts = array_values(array_filter([$stationNickname, $stationStreet ?: $stationIntersection, $stationCity]));
    $stationLocationLabel = implode(', ', $labelParts);
}

// ── Fuel math ─────────────────────────────────────────────────────────────────
$pg_raw  = trim((string)($_POST['pricePerGallon'] ?? ''));
$gal_raw = trim((string)($_POST['gallons'] ?? ''));
$ttl_raw = trim((string)($_POST['totalPrice'] ?? ''));

$pg_input  = $pg_raw  !== '' ? (float)$pg_raw  : null;
$gal_input = $gal_raw !== '' ? (float)$gal_raw : null;
$ttl_input = $ttl_raw !== '' ? (float)$ttl_raw : null;
$provided = (int)($pg_input !== null) + (int)($gal_input !== null) + (int)($ttl_input !== null);
if ($provided < 2) fail('Enter at least two of: Price per Gallon, Gallons, Total Cost.');

if ($pg_input !== null && $pg_input <= 0) fail('Price per gallon must be greater than zero.');
if ($gal_input !== null && $gal_input <= 0) fail('Gallons must be greater than zero.');
if ($ttl_input !== null && $ttl_input <= 0) fail('Total cost must be greater than zero.');

// Normalize a user-entered two-decimal pump price (3.69 => 3.699).
// Do not add .009 when price is derived from total/gallons.
if ($pg_input !== null) {
    $pgDecimals = strpos($pg_raw, '.') !== false ? strlen(substr($pg_raw, strpos($pg_raw, '.') + 1)) : 0;
    if ($pgDecimals <= 2) $pg_input += 0.009;
}

if ($ttl_input === null) {
    $ttl_input = round($pg_input * $gal_input, 2);
} elseif ($pg_input === null) {
    $pg_input = round($ttl_input / $gal_input, 3);
} elseif ($gal_input === null) {
    $gal_input = round($ttl_input / $pg_input, 3);
} else {
    $expectedTotal = round($pg_input * $gal_input, 2);
    if (abs($expectedTotal - $ttl_input) > 0.05) {
        fail('Fuel values conflict: gallons × price is $' . number_format($expectedTotal, 2) . ', but total entered is $' . number_format($ttl_input, 2) . '. Please verify the pump values.');
    }
}

$price_per_gallon = round($pg_input, 3);
$gallons = round($gal_input, 3);
$total_cost = round($ttl_input, 2);

// ── Existing entries / odometer delta ─────────────────────────────────────────
$logDir = __DIR__ . '/logs/';
if (!is_dir($logDir)) mkdir($logDir, 0755, true);
$logFile = $logDir . "{$plate}.json";
$entries = [];
if (file_exists($logFile)) {
    $decoded = json_decode(file_get_contents($logFile), true);
    if (is_array($decoded)) $entries = array_values($decoded);
}

$miles = 0;
if (!empty($entries)) {
    $lastEntry = end($entries);
    $lastOdo = (float)($lastEntry['odometer'] ?? 0);
    $rawMiles = $odometer - $lastOdo;
    if ($rawMiles <= 0) fail("Duplicate or invalid entry — odometer unchanged or lower. Previous odometer: {$lastOdo}");
    $miles = round($rawMiles, 1);
}

// ── Full-to-full MPG ──────────────────────────────────────────────────────────
$mpg = null;
$mpgMiles = null;
$mpgGallons = null;

if ($fillType === 'full' && !empty($entries)) {
    $previousFullIndex = null;
    for ($i = count($entries) - 1; $i >= 0; $i--) {
        if (isFullFill($entries[$i])) { $previousFullIndex = $i; break; }
    }

    if ($previousFullIndex !== null) {
        $previousFullOdo = (float)($entries[$previousFullIndex]['odometer'] ?? 0);
        $spanMiles = $odometer - $previousFullOdo;
        $spanGallons = $gallons;
        for ($i = $previousFullIndex + 1; $i < count($entries); $i++) {
            $spanGallons += (float)($entries[$i]['gallons'] ?? 0);
        }
        if ($spanMiles > 0 && $spanGallons > 0) {
            $mpgMiles = round($spanMiles, 1);
            $mpgGallons = round($spanGallons, 3);
            $mpg = round($spanMiles / $spanGallons, 2);
        }
    }
}

// ── Learn station brand ───────────────────────────────────────────────────────
if ($stationBrand !== '') {
    $exists = false;
    foreach ($stationData['brands'] as $existingBrand) {
        if (strcasecmp(trim((string)$existingBrand), $stationBrand) === 0) {
            $stationBrand = trim((string)$existingBrand);
            $exists = true;
            break;
        }
    }
    if (!$exists) $stationData['brands'][] = $stationBrand;
    natcasesort($stationData['brands']);
    $stationData['brands'] = array_values(array_unique($stationData['brands']));
    file_put_contents($stationsFile, json_encode($stationData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
}

// ── Save entry ────────────────────────────────────────────────────────────────
$submittedET = (new DateTime('now', new DateTimeZone('America/New_York')))->format('Y-m-d H:i:s T');
$source = ($_POST['source'] ?? 'manual') === 'scan' ? 'scan' : 'manual';

$entry = [
    'license_plate' => $plate,
    'date' => $date,
    'odometer' => $odometer,
    'miles' => $miles,
    'gallons' => $gallons,
    'price_per_gallon' => $price_per_gallon,
    'total_cost' => $total_cost,
    'fill_type' => $fillType,
    'mpg' => $mpg,
    'mpg_miles' => $mpgMiles,
    'mpg_gallons' => $mpgGallons,
    'submitted_et' => $submittedET,
    'ip_address' => $visitorIP,
    'device_id' => $deviceId,
    'source' => $source,
    'verified' => 'no'
];

if ($comment !== '') $entry['comment'] = $comment;
if ($stationBrand !== '') $entry['station_brand'] = $stationBrand;
if ($stationLocationId !== '') $entry['station_location_id'] = $stationLocationId;
if ($stationLocationLabel !== '') $entry['station_location_label'] = $stationLocationLabel;
if ($stationCity !== '') $entry['station_city'] = $stationCity;
if ($stationStreet !== '') $entry['station_street'] = $stationStreet;
if ($stationIntersection !== '') $entry['station_intersection'] = $stationIntersection;
if ($latitude !== null) $entry['latitude'] = round($latitude, 6);
if ($longitude !== null) $entry['longitude'] = round($longitude, 6);
if ($locationSource !== '') $entry['location_source'] = $locationSource;

$entries[] = $entry;
file_put_contents($logFile, json_encode($entries, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);

// ── Device count ──────────────────────────────────────────────────────────────
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
    'success' => true,
    'plate' => $plate,
    'date' => $date,
    'odometer' => $odometer,
    'miles' => $miles,
    'gallons' => $gallons,
    'price' => number_format($price_per_gallon, 3),
    'total' => number_format($total_cost, 2),
    'fillType' => $fillType,
    'mpg' => $mpg,
    'mpgDisplay' => $mpgDisplay,
    'mpgMiles' => $mpgMiles,
    'mpgGallons' => $mpgGallons,
    'stationBrand' => $stationBrand,
    'stationLocationId' => $stationLocationId,
    'stationLocationLabel' => $stationLocationLabel,
    'comment' => $comment,
    'submitted' => $submittedET
]);
