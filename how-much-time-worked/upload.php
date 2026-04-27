<?php
/*
    Filename    : upload.php
    Revision    : 1.2.0
    Description : Handles photo upload, runs OCR if configured, saves parsed JSON, redirects to review
    Author      : Jason Lamb (with help from Claude Code CLI)
    Created     : 2026-04-27
    Modified    : 2026-04-27
    Changelog   :
    1.0.0 initial release
    1.1.0 added OCR.Space support via secrets.php
    1.2.0 removed unused fields
*/
require_once __DIR__ . '/config.php';
ensureAppFolders();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

if (!isset($_FILES['slip']) || $_FILES['slip']['error'] !== UPLOAD_ERR_OK) {
    die('Upload failed. Check file size and try again.');
}

if ($_FILES['slip']['size'] > MAX_UPLOAD_BYTES) {
    die('Upload failed. File is too large.');
}

$originalName = $_FILES['slip']['name'];
$extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
global $allowedExtensions;

if (!in_array($extension, $allowedExtensions, true)) {
    die('Invalid file type. Use JPG, PNG, or WEBP.');
}

$stamp = date('Ymd-His');
$safeName = 'clockout-' . $stamp . '-' . bin2hex(random_bytes(4)) . '.' . $extension;
$target = UPLOAD_DIR . '/' . $safeName;

if (!move_uploaded_file($_FILES['slip']['tmp_name'], $target)) {
    die('Could not save uploaded file.');
}

$ocrText = '';
if (OCR_MODE === 'tesseract') {
    $cmd = 'tesseract ' . escapeshellarg($target) . ' stdout 2>&1';
    $ocrText = shell_exec($cmd) ?: '';
} elseif (OCR_MODE === 'ocrspace' && OCRSPACE_API_KEY !== '') {
    $post = [
        'apikey' => OCRSPACE_API_KEY,
        'language' => 'eng',
        'isOverlayRequired' => 'false',
        'file' => new CURLFile($target),
    ];
    $ch = curl_init('https://api.ocr.space/parse/image');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $post,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 60,
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
    $data = json_decode($response ?: '', true);
    $ocrText = $data['ParsedResults'][0]['ParsedText'] ?? '';
}

$parsed = parseClockSlipText($ocrText);
$query = http_build_query(['file' => $safeName]);
file_put_contents(DATA_DIR . '/' . $safeName . '.ocr.txt', $ocrText);
file_put_contents(DATA_DIR . '/' . $safeName . '.parsed.json', json_encode($parsed, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

header('Location: review.php?' . $query);
exit;
?>
