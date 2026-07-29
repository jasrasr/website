<?php

declare(strict_types=1);

/**
 * Copy this file to config.php for local or deployed configuration.
 * Never commit production secrets to the repository.
 */

return [
    'app' => [
        'name' => 'JASR Application',
        'environment' => 'development',
        'debug' => false,
        'timezone' => 'America/New_York',
    ],
    'storage' => [
        'path' => dirname(__DIR__) . '/storage/data',
    ],
    'modules' => [
        'authentication' => false,
        'logging' => true,
        'audit' => false,
    ],
];
