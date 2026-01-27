<?php

date_default_timezone_set('America/New_York');

function loadData() {
    $file = __DIR__ . '/data/boxes.json';
    if (!file_exists($file)) {
        return ['boxes' => []];
    }
    $json = file_get_contents($file);
    return json_decode($json, true) ?: ['boxes' => []];
}

function saveData($data) {
    $file = __DIR__ . '/data/boxes.json';
    // simple lock to reduce race conditions 
    $fp = fopen($file, 'c+');
    if (flock($fp, LOCK_EX)) {
        ftruncate($fp, 0);
        fwrite($fp, json_encode($data, JSON_PRETTY_PRINT));
        fflush($fp);
        flock($fp, LOCK_UN);
    }
    fclose($fp);
}
