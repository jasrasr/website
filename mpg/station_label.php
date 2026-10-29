<?php

function stationLabel(array $loc): string {
    $brand = trim((string)($loc['brand'] ?? ''));
    if ($brand === '') $brand = trim((string)($loc['name'] ?? ''));
    $nickname = trim((string)($loc['nickname'] ?? ''));
    $intersection = trim((string)($loc['intersection'] ?? ''));
    $street = trim((string)($loc['street'] ?? ''));
    $city = trim((string)($loc['city'] ?? ''));

    $title = $nickname !== '' ? $nickname : ($brand !== '' ? $brand : 'Station');
    $place = $intersection !== '' ? $intersection : $street;
    if ($place === '') $place = $city;
    if ($place === '' && isset($loc['latitude'], $loc['longitude'])) {
        $place = $loc['latitude'] . ', ' . $loc['longitude'];
    }
    return $place !== '' ? $title . ' — ' . $place : $title;
}
