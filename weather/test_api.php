<?php
$config = require __DIR__ . '/config.php';
$apiKey = $config['api_key'] ?? '';

if ($apiKey === '' || $apiKey === 'ENTER-API-HERE') {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'OpenWeather API key is not configured.']);
    exit;
}

$url = "https://api.openweathermap.org/data/2.5/weather?q=Newhall,California,US&units=imperial&appid=$apiKey";

$response = file_get_contents($url);

header('Content-Type: application/json');
echo $response;
