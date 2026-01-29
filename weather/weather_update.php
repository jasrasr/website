<?php
// ============================================================================
// File Name    : weather_update.php
// Author       : Jason Lamb (with help from ChatGPT)
// Created Date : 2026-01-24
// Modified Date: 2026-01-29
// Revision     : 2.7
// Description : Weather fetch and cache engine using authoritative lat/lon
//               for config cities, ZIP/manual override support, and
//               distance-based sorting.
// Changelog    :
//   Rev 2.0 - Added per-city history and ZIP-based cities
//   Rev 2.1 - Removed humidity and added explicit state handling
//   Rev 2.2 - Fixed OpenWeather ZIP and state query formats
//   Rev 2.3 - Added distance-based sorting from browser location
//   Rev 2.4 - Reverse geocoding for ZIP state resolution
//   Rev 2.5 - Enforced combined City,ST manual entry
//   Rev 2.6 - Switched base cities to lat/lon authoritative lookups
//   Rev 2.7 - Enforced lat/lon-only lookups for config cities (bug fix)
// ============================================================================

$config = require __DIR__ . '/config.php';

$dataDir    = __DIR__ . '/data';
$historyDir = $dataDir . '/history';
$dataFile   = $dataDir . '/weather.json';

@mkdir($dataDir, 0755, true);
@mkdir($historyDir, 0755, true);

$now = time();

$zip      = isset($_GET['zip']) && preg_match('/^\d{5}$/', $_GET['zip']) ? $_GET['zip'] : null;
$location = $_GET['location'] ?? null;

$userLat = isset($_GET['lat']) ? (float)$_GET['lat'] : null;
$userLon = isset($_GET['lon']) ? (float)$_GET['lon'] : null;

// --------------------------------------------------------------------------
// Build city list
// --------------------------------------------------------------------------

$cities = $config['cities'];

// ZIP override (temporary)
if ($zip) {
    $cities['zip_' . $zip] = [
        'label' => "ZIP {$zip}",
        'zip'   => $zip
    ];
}

// Manual City,ST override (temporary)
if ($location && preg_match('/^(.+),\s*([A-Z]{2})$/i', $location, $m)) {
    $city  = trim($m[1]);
    $state = strtoupper($m[2]);

    $cities['custom_' . md5($city . $state)] = [
        'label' => "{$city}, {$state}",
        'query' => "{$city},{$state},US"
    ];
}

// --------------------------------------------------------------------------
// Cache guard
// --------------------------------------------------------------------------

if (file_exists($dataFile) && !$zip && !$location) {
    $cached = json_decode(file_get_contents($dataFile), true);
    if (($now - $cached['updated_epoch']) < $config['update_interval_seconds']) {
        return $cached;
    }
}

// --------------------------------------------------------------------------
// Helpers
// --------------------------------------------------------------------------

function distanceMiles($lat1, $lon1, $lat2, $lon2) {
    $earth = 3959;
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    $a = sin($dLat / 2) ** 2 +
         cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
         sin($dLon / 2) ** 2;
    return round(2 * $earth * asin(sqrt($a)), 1);
}

// --------------------------------------------------------------------------
// Fetch weather data
// --------------------------------------------------------------------------

$result = [
    'updated_epoch' => $now,
    'updated_iso'   => gmdate('c'),
    'ui_revision'   => '2.7',
    'cities'        => []
];

foreach ($cities as $key => $entry) {

    // --- STRICT lookup rules ---
    if (isset($entry['lat'], $entry['lon'])) {
        // Config city: lat/lon ONLY
        $url = sprintf(
            'https://api.openweathermap.org/data/2.5/weather?lat=%s&lon=%s&units=imperial&appid=%s',
            $entry['lat'],
            $entry['lon'],
            $config['api_key']
        );
    } elseif (isset($entry['zip'])) {
        // ZIP lookup
        $url = sprintf(
            'https://api.openweathermap.org/data/2.5/weather?zip=%s,US&units=imperial&appid=%s',
            $entry['zip'],
            $config['api_key']
        );
    } else {
        // Manual lookup (temporary only)
        $url = sprintf(
            'https://api.openweathermap.org/data/2.5/weather?q=%s&units=imperial&appid=%s',
            urlencode($entry['query']),
            $config['api_key']
        );
    }

    $resp = @file_get_contents($url);
    if (!$resp) {
        $result['cities'][$key] = [
            'name'  => $entry['label'] ?? 'Unknown',
            'error' => 'API fetch failed'
        ];
        continue;
    }

    $w = json_decode($resp, true);
    if (!isset($w['main'])) {
        $result['cities'][$key] = [
            'name'  => $entry['label'] ?? 'Unknown',
            'error' => 'Malformed API response'
        ];
        continue;
    }

    $cityData = [
        'name'       => $entry['label'] ?? $w['name'],
        'temp'       => $w['main']['temp'],
        'feels_like' => $w['main']['feels_like'],
        'temp_high'  => $w['main']['temp_max'],
        'temp_low'   => $w['main']['temp_min'],
        'condition'  => $w['weather'][0]['description'],
        'lat'        => $w['coord']['lat'],
        'lon'        => $w['coord']['lon'],
        'epoch'      => $now
    ];

    if ($userLat !== null && $userLon !== null) {
        $cityData['distance'] = distanceMiles(
            $userLat, $userLon,
            $cityData['lat'], $cityData['lon']
        );
    }

    $result['cities'][$key] = $cityData;
}

file_put_contents($dataFile, json_encode($result, JSON_PRETTY_PRINT));
return $result;

