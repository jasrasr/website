<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/config.php';

$name = $_GET['file'] ?? '';
if (!is_string($name) || $name === '' || basename($name) !== $name || !preg_match('/^clockout-[A-Za-z0-9-]+\.(?:jpe?g|png|webp)$/i', $name)) {
    http_response_code(404);
    exit;
}
$path = UPLOAD_DIR . '/' . $name;
if (!is_file($path)) {
    http_response_code(404);
    exit;
}
$mime = mime_content_type($path);
if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
    http_response_code(404);
    exit;
}
header('Content-Type: ' . $mime);
header('Content-Disposition: inline; filename="clockout-photo"');
header('Cache-Control: private, no-store');
header('X-Content-Type-Options: nosniff');
readfile($path);
