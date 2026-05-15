<?php
require_once __DIR__ . '/config.php';
ensureAppFolders();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Use POST.']);
    exit;
}

if (!isset($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['error' => 'Upload failed.']);
    exit;
}

if ($_FILES['photo']['size'] > MAX_UPLOAD_BYTES) {
    http_response_code(400);
    echo json_encode(['error' => 'File is too large.']);
    exit;
}

$originalName = (string)$_FILES['photo']['name'];
$extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
$tmpName = (string)$_FILES['photo']['tmp_name'];
$mimeType = (string)($_FILES['photo']['type'] ?? '');
if (function_exists('finfo_open')) {
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    if ($finfo !== false) {
        $detectedMime = finfo_file($finfo, $tmpName);
        finfo_close($finfo);
        if (is_string($detectedMime) && $detectedMime !== '') {
            $mimeType = $detectedMime;
        }
    }
}
global $allowedExtensions, $allowedMimeTypes;

if (!in_array($extension, $allowedExtensions, true) || !in_array($mimeType, $allowedMimeTypes, true)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid file type. Use JPG, PNG, WEBP, HEIC, or HEIF.']);
    exit;
}

$hash = hash_file('sha256', $tmpName);
if (!is_string($hash) || $hash === '') {
    http_response_code(500);
    echo json_encode(['error' => 'Could not hash uploaded file.']);
    exit;
}
$hashIndex = readHashIndex();
$entries = readLogEntries();
$existingFileEntry = $hashIndex[$hash] ?? null;
$duplicateFile = is_array($existingFileEntry);

$stamp = date('Ymd-His');
$safeBase = preg_replace('/[^A-Za-z0-9._-]+/', '_', pathinfo($originalName, PATHINFO_FILENAME));
$safeBase = trim((string)$safeBase, '._-') ?: 'plate';
$safeName = $stamp . '-' . bin2hex(random_bytes(4)) . '-' . substr($safeBase, 0, 60) . '.' . $extension;
$target = UPLOAD_DIR . '/' . $safeName;

if (!$duplicateFile && !move_uploaded_file($tmpName, $target)) {
    http_response_code(500);
    echo json_encode(['error' => 'Could not save uploaded file.']);
    exit;
}

if ($duplicateFile) {
    $target = UPLOAD_DIR . '/' . ($existingFileEntry['stored_file'] ?? '');
}

$scan = $duplicateFile
    ? [
        'plate' => (string)($existingFileEntry['plate'] ?? ''),
        'confidence' => (int)($existingFileEntry['confidence'] ?? 0),
        'raw_text' => '',
        'error' => '',
    ]
    : scanImage($target, $mimeType);

$plate = normalizePlateText((string)($scan['plate'] ?? ''));
$countsBefore = plateCounts($entries);
$duplicatePlate = $plate !== '' && isset($countsBefore[$plate]);
$plateCount = ($countsBefore[$plate] ?? 0) + ($plate !== '' ? 1 : 0);

$entry = [
    'id' => bin2hex(random_bytes(8)),
    'processed_at' => date('c'),
    'original_file' => $originalName,
    'stored_file' => basename($target),
    'sha256' => $hash,
    'plate' => $plate,
    'plate_normalized' => $plate,
    'confidence' => (int)($scan['confidence'] ?? 0),
    'scan_mode' => SCAN_MODE,
    'duplicate_file' => $duplicateFile,
    'duplicate_of' => $existingFileEntry['id'] ?? '',
    'duplicate_plate' => $duplicatePlate,
    'raw_text' => (string)($scan['raw_text'] ?? ''),
    'error' => (string)($scan['error'] ?? ''),
];

$entries[] = $entry;
writeJsonFile(LOG_FILE, $entries);

if (!$duplicateFile) {
    $hashIndex[$hash] = [
        'id' => $entry['id'],
        'stored_file' => $entry['stored_file'],
        'original_file' => $entry['original_file'],
        'plate' => $plate,
        'confidence' => $entry['confidence'],
        'processed_at' => $entry['processed_at'],
    ];
    writeJsonFile(HASH_INDEX_FILE, $hashIndex);
}

echo json_encode([
    'id' => $entry['id'],
    'plate' => $plate,
    'confidence' => $entry['confidence'],
    'status' => $entry['error'] !== '' ? $entry['error'] : 'Logged',
    'duplicate_file' => $duplicateFile,
    'duplicate_plate' => $duplicatePlate,
    'plate_count' => $plateCount,
    'stored_file' => $entry['stored_file'],
]);
