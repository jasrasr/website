<?php
$apiKey = 'YOUR_API_KEY_HERE';

$url = "https://api.openweathermap.org/data/2.5/weather?q=Newhall,California,US&units=imperial&appid=$apiKey";

$response = file_get_contents($url);

header('Content-Type: application/json');
echo $response;
