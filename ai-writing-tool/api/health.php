<?php
/*
    Project      : AI Writing Tool
    File         : api/health.php
    Revision     : 1.0.0
    Created      : 2026-06-01
    Updated      : 2026-06-01
    Description  : Lightweight server check that verifies PHP, cURL, config presence, and writable rate-limit storage.
*/

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

$configPath = dirname(__DIR__) . '/config/config.php';
$rateLimitFolder = dirname(__DIR__) . '/data/rate-limit';

$response = [
    'ok' => true,
    'php_version' => PHP_VERSION,
    'curl_loaded' => extension_loaded('curl'),
    'config_exists' => is_file($configPath),
    'rate_limit_folder_writable' => is_dir($rateLimitFolder) && is_writable($rateLimitFolder),
    'timestamp' => date(DATE_ATOM)
];

if (!$response['curl_loaded'] || !$response['config_exists']) {
    $response['ok'] = false;
}

echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
