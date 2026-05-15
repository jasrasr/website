<?php
// list_json_files.php
// Revision: 1.0
header('Content-Type: application/json');
$jsonFolder = __DIR__ . '/logs';   // must match the folder in dashboard-dynamic.php

$filesRaw = @scandir($jsonFolder);
if ($filesRaw === false) {
    echo json_encode([]);
    exit;
}
$files = [];
foreach ($filesRaw as $f) {
    if (substr($f, -5) === '.json') {
        $files[] = $f;
    }
}
sort($files);
echo json_encode($files);
