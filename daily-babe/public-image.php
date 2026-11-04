<?php

declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

$slug = strtolower(trim((string) ($_GET['baby'] ?? '')));
$id = (string) ($_GET['id'] ?? '');
if (preg_match('/^[a-f0-9]{24}$/', $id) !== 1) {
    http_response_code(404);
    exit;
}

$gallery = public_gallery_by_slug($slug);
if ($gallery === null) {
    http_response_code(404);
    exit;
}

$babyId = (string) $gallery['babyId'];
if (gallery_visibility($gallery['settings']) === 'unlisted' && !unlisted_gallery_access_granted($babyId)) {
    http_response_code(404);
    exit;
}
$entries = json_read('central/babies/' . $babyId . '/data/entries.json', ['records' => []]);
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

$path = baby_root($babyId) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . basename((string) ($match['filename'] ?? ''));
if (!is_file($path)) {
    http_response_code(404);
    exit;
}

header('Content-Type: ' . (string) ($match['mimeType'] ?? 'application/octet-stream'));
header('Content-Length: ' . filesize($path));
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header('Content-Disposition: inline');
readfile($path);

