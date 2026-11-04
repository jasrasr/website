<?php

declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
daily_require_user('viewer');

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

$path = baby_storage_path('uploads/' . basename((string) $match['filename']));
if (!is_file($path)) {
    http_response_code(404);
    exit;
}

$mimeType = (string) $match['mimeType'];
if (($_GET['size'] ?? '') === 'thumb') {
    $thumbnailPath = ensure_baby_thumbnail(current_baby_id(), $match);
    if ($thumbnailPath !== null) {
        $path = $thumbnailPath;
        $mimeType = 'image/webp';
    }
}

header('Content-Type: ' . $mimeType);
header('Content-Length: ' . filesize($path));
header('Cache-Control: private, no-store');
header('X-Content-Type-Options: nosniff');
readfile($path);


