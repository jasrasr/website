<?php

declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

if (!is_authenticated()) {
    http_response_code(401);
    exit;
}

$id = (string) ($_GET['id'] ?? '');
if (!preg_match('/^[a-f0-9]{24}$/', $id)) {
    http_response_code(404);
    exit;
}

$entries = tracker_entries();
$match = null;
foreach ($entries['records'] ?? [] as $record) {
    if (($record['id'] ?? '') === $id) {
        $match = $record;
        break;
    }
}
if ($match === null) {
    http_response_code(404);
    exit;
}

$path = storage_path('uploads/' . basename((string) $match['filename']));
if (!is_file($path)) {
    http_response_code(404);
    exit;
}

header('Content-Type: ' . (string) $match['mimeType']);
header('Content-Length: ' . filesize($path));
header('Cache-Control: private, max-age=86400');
header('X-Content-Type-Options: nosniff');
readfile($path);

