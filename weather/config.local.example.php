<?php
// Copy this file to config.local.php on the server and put the real API key there.
// config.local.php is ignored by Git so GitHub updates do not overwrite it.

return [
    'api_key' => 'ENTER-API-HERE',

    // Refresh data only if older than this (seconds)
    'update_interval_seconds' => 3600,

    // Number of history points to retain per city
    'history_points' => 48,

    // Base cities (authoritative lat/lon)
    'cities' => [
        'parma_oh' => [
            'label' => 'Parma, OH',
            'lat'   => 41.4048,
            'lon'   => -81.7229,
            'zip'   => '44130'
        ],
        'sellersburg_in' => [
            'label' => 'Sellersburg, IN',
            'lat'   => 38.3981,
            'lon'   => -85.7541,
            'zip'   => '47172'
        ],
        'newhall_ca' => [
            'label' => 'Newhall, CA',
            'lat'   => 34.3798765,
            'lon'   => -118.5291917,
            'zip'   => '91321'
        ]
    ]
];
