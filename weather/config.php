<?php
// ============================================================================
// File Name    : config.php
// Author       : Jason Lamb (with help from ChatGPT)
// Created Date : 2026-01-24
// Modified Date: 2026-01-29
// Revision     : 1.6
// Description : Central configuration for the PHP weather dashboard.
//               Uses lat/lon as authoritative location identifiers.
// Changelog    :
//   Rev 1.0 - Initial configuration
//   Rev 1.1 - Updated city list
//   Rev 1.2 - Added history retention setting
//   Rev 1.3 - Added ZIP-code city support
//   Rev 1.4 - Finalized config for UI Rev 2.x
//   Rev 1.5 - Corrected city format to City,STATE,US
//   Rev 1.6 - Migrated base cities to lat/lon to eliminate ambiguity
// ============================================================================

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
            'lat' => 34.3798765,
            'lon' => -118.5291917,
            'zip' => '91321'
        ]
        
    ]
];
