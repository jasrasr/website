<?php
/*
    Filename    : upload.php
    Revision    : 1.2.1
    Description : Handles photo upload, runs OCR if configured, saves parsed JSON, redirects to review
    Author      : Jason Lamb (with help from Claude Code CLI)
    Created     : 2026-04-27
    Modified    : 2026-04-27
    Changelog   :
    1.0.0 initial release
    1.1.0 added OCR.Space support via secrets.php
    1.2.0 removed unused fields
    1.2.1 resize image before OCR to stay under free plan 1.5 MB limit
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
    // Resize to max 1200px and re-encode as JPEG to stay under the 1.5 MB free plan limit.
    $ocrTarget = $target;
    $imgInfo = @getimagesize($target);
    if ($imgInfo) {
        $srcW = $imgInfo[0];
        $srcH = $imgInfo[1];
        $maxDim = 1200;
        if ($srcW > $maxDim || $srcH > $maxDim) {
            $scale = min($maxDim / $srcW, $maxDim / $srcH);
            $newW = (int)round($srcW * $scale);
            $newH = (int)round($srcH * $scale);
        } else {
            $newW = $srcW;
            $newH = $srcH;
        }
        $src = match($imgInfo[2]) {
            IMAGETYPE_JPEG => imagecreatefromjpeg($target),
            IMAGETYPE_PNG  => imagecreatefrompng($target),
            IMAGETYPE_WEBP => imagecreatefromwebp($target),
            default        => false,
        };
        if ($src !== false) {
            $dst = imagecreatetruecolor($newW, $newH);
            imagecopyresampled($dst, $src, 0, 0, 0, 0, $newW, $newH, $srcW, $srcH);
            $ocrTarget = DATA_DIR . '/' . $safeName . '.ocr.jpg';
            imagejpeg($dst, $ocrTarget, 80);
            imagedestroy($src);
            imagedestroy($dst);
        }
    }
    $post = [
        'apikey' => OCRSPACE_API_KEY,
        'language' => 'eng',
        'isOverlayRequired' => 'false',
        'file' => new CURLFile($ocrTarget, 'image/jpeg'),
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
    // Clean up the temporary resized file if one was created.
    if ($ocrTarget !== $target && file_exists($ocrTarget)) {
        unlink($ocrTarget);
    }
}

$parsed = parseClockSlipText($ocrText);
$query = http_build_query(['file' => $safeName]);
file_put_contents(DATA_DIR . '/' . $safeName . '.ocr.txt', $ocrText);
file_put_contents(DATA_DIR . '/' . $safeName . '.parsed.json', json_encode($parsed, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

header('Location: review.php?' . $query);
exit;
?>
