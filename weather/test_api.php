<?php
$configFile = __DIR__ . '/config.php';

if (!is_file($configFile)) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Missing config.php. Copy config.example.php to config.php and add the OpenWeather API key.']);
    exit;
}

$config = require $configFile;
$apiKey = $config['api_key'] ?? '';

if ($apiKey === '' || $apiKey === 'ENTER-API-HERE') {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'OpenWeather API key is not configured in config.php.']);
    exit;
}

$url = "https://api.openweathermap.org/data/2.5/weather?q=Newhall,California,US&units=imperial&appid=" . urlencode($apiKey);

$error = null;
set_error_handler(function ($severity, $message) use (&$error) {
    $error = $message;
});

$context = stream_context_create(['http' => ['ignore_errors' => true]]);
$response = file_get_contents($url, false, $context);
restore_error_handler();

$status = null;
if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $m)) {
    $status = (int)$m[1];
}

if ($response === false) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => $error ?: 'API fetch failed']);
    exit;
}

if ($status && $status >= 400) {
    http_response_code($status);
}

header('Content-Type: application/json');
echo $response;
