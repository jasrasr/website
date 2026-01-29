<?php
// ============================================================================
// File Name    : geocode_helper.php
// Author       : Jason Lamb (with help from ChatGPT)
// Created Date : 2026-01-29
// Modified Date: 2026-01-29
// Revision     : 1.1
// Description : Utility to convert City,ST or ZIP into authoritative
//               lat/lon + ZIP using OpenWeather Geocoding APIs.
//               Intended for config.php generation only.
// Changelog    :
//   Rev 1.0 - Initial helper for generating lat/lon config entries
//   Rev 1.1 - Added ZIP resolution and country/state validation
// ============================================================================

$config = require __DIR__ . '/config.php';
$apiKey = $config['api_key'];

$input = $_GET['q'] ?? null;

if (!$input) {
    echo "Usage:\n";
    echo "  geocode_helper.php?q=Parma,OH\n";
    echo "  geocode_helper.php?q=46237\n";
    exit;
}

$context = stream_context_create(['http' => ['ignore_errors' => true]]);

// --------------------------------------------------------------------------
// Resolve input to lat/lon
// --------------------------------------------------------------------------

if (preg_match('/^\d{5}$/', $input)) {
    // ZIP → lat/lon
    $geoUrl = sprintf(
        'https://api.openweathermap.org/geo/1.0/zip?zip=%s,US&appid=%s',
        $input,
        $apiKey
    );

    $geoResp = file_get_contents($geoUrl, false, $context);
    $geo = json_decode($geoResp, true);

    if (!isset($geo['lat'], $geo['lon'])) {
        echo "Failed to resolve ZIP {$input}\n";
        exit;
    }

    $result = [
        'name'    => $geo['name'],
        'state'   => $geo['state'] ?? null,
        'country' => $geo['country'],
        'lat'     => $geo['lat'],
        'lon'     => $geo['lon'],
        'zip'     => $input
    ];

} elseif (preg_match('/^(.+),\s*([A-Z]{2})$/i', $input, $m)) {
    // City,ST → lat/lon
    $city  = trim($m[1]);
    $state = strtoupper($m[2]);

    $geoUrl = sprintf(
        'https://api.openweathermap.org/geo/1.0/direct?q=%s,%s,US&limit=1&appid=%s',
        urlencode($city),
        $state,
        $apiKey
    );

    $geoResp = file_get_contents($geoUrl, false, $context);
    $geo = json_decode($geoResp, true);

    if (!isset($geo[0]['lat'], $geo[0]['lon'])) {
        echo "Failed to resolve {$city}, {$state}\n";
        exit;
    }

    $result = [
        'name'    => $geo[0]['name'],
        'state'   => $geo[0]['state'],
        'country' => $geo[0]['country'],
        'lat'     => $geo[0]['lat'],
        'lon'     => $geo[0]['lon'],
        'zip'     => null
    ];

    // Attempt reverse ZIP lookup (best-effort)
    $zipUrl = sprintf(
        'https://api.openweathermap.org/geo/1.0/reverse?lat=%s&lon=%s&limit=1&appid=%s',
        $result['lat'],
        $result['lon'],
        $apiKey
    );

    $zipResp = file_get_contents($zipUrl, false, $context);
    $zipData = json_decode($zipResp, true);

    if (isset($zipData[0]['zip'])) {
        $result['zip'] = $zipData[0]['zip'];
    }

} else {
    echo "Invalid input format.\n";
    echo "Use ZIP (#####) or City,ST\n";
    exit;
}

// --------------------------------------------------------------------------
// Validation output
// --------------------------------------------------------------------------

echo "\nResolved Location\n";
echo "-----------------\n";
echo "City    : {$result['name']}\n";
echo "State   : {$result['state']}\n";
echo "Country : {$result['country']}\n";
echo "ZIP     : " . ($result['zip'] ?? 'Not provided by API') . "\n";
echo "Lat     : {$result['lat']}\n";
echo "Lon     : {$result['lon']}\n";

if ($result['country'] !== 'US') {
    echo "\n⚠ WARNING: Country is not US — DO NOT ADD TO CONFIG\n";
}

// --------------------------------------------------------------------------
// Config output
// --------------------------------------------------------------------------

$key = strtolower(str_replace([' ', ','], '_', "{$result['name']}_{$result['state']}"));

echo "\nconfig.php entry\n";
echo "----------------\n";
echo "'{$key}' => [\n";
echo "    'label' => '{$result['name']}, {$result['state']}',\n";
echo "    'lat'   => {$result['lat']},\n";
echo "    'lon'   => {$result['lon']},\n";

if ($result['zip']) {
    echo "    'zip'   => '{$result['zip']}',\n";
}

echo "],\n\n";
