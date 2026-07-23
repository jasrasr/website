<?php
require_once __DIR__ . '/config.php';
ensureAppFolders();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Use POST.']);
    exit;
}

$payload = json_decode(file_get_contents('php://input') ?: '', true);
$id = trim((string)($payload['id'] ?? $_POST['id'] ?? ''));
$entries = readLogEntries();
$entryIndex = null;

foreach ($entries as $index => $entry) {
    if (($entry['id'] ?? '') === $id) {
        $entryIndex = $index;
        break;
    }
}

if ($entryIndex === null) {
    http_response_code(404);
    echo json_encode(['error' => 'Entry not found.']);
    exit;
}

$entry = $entries[$entryIndex];
$status = $entry['scan_status'] ?? (empty($entry['error']) ? 'complete' : 'pending');
if ($status !== 'pending') {
    http_response_code(409);
    echo json_encode(['error' => 'Entry is not pending.']);
    exit;
}

$storedFile = basename((string)($entry['stored_file'] ?? ''));
$imagePath = UPLOAD_DIR . '/' . $storedFile;
if ($storedFile === '' || !is_file($imagePath)) {
    http_response_code(404);
    echo json_encode(['error' => 'Stored image not found.']);
    exit;
}

$mimeType = mime_content_type($imagePath) ?: 'application/octet-stream';
$scan = scanImage($imagePath, $mimeType);
$scanError = trim((string)($scan['error'] ?? ''));
$attempts = (int)($entry['scan_attempts'] ?? 1) + 1;

$entries[$entryIndex]['scan_attempts'] = $attempts;
$entries[$entryIndex]['last_scan_attempt_at'] = date('c');
$entries[$entryIndex]['scan_mode'] = SCAN_MODE;

if ($scanError !== '') {
    $entries[$entryIndex]['scan_status'] = 'pending';
    $entries[$entryIndex]['error'] = $scanError;
    writeJsonFile(LOG_FILE, $entries);
    http_response_code(422);
    echo json_encode(['id' => $id, 'status' => 'pending', 'error' => $scanError, 'scan_attempts' => $attempts]);
    exit;
}

$value = normalizePlateText((string)($scan['plate'] ?? ''));
$entries[$entryIndex]['plate'] = $value;
$entries[$entryIndex]['plate_normalized'] = $value;
$entries[$entryIndex]['confidence'] = (int)($scan['confidence'] ?? 0);
$entries[$entryIndex]['raw_text'] = (string)($scan['raw_text'] ?? '');
$entries[$entryIndex]['error'] = '';
$entries[$entryIndex]['scan_status'] = 'complete';
$entries[$entryIndex]['processed_at'] = date('c');

$totals = [];
foreach ($entries as $candidate) {
    $candidateStatus = $candidate['scan_status'] ?? (empty($candidate['error']) ? 'complete' : 'pending');
    $candidateValue = normalizePlateText((string)($candidate['plate_normalized'] ?? $candidate['plate'] ?? ''));
    if ($candidateStatus === 'complete' && $candidateValue !== '') {
        $totals[$candidateValue] = ($totals[$candidateValue] ?? 0) + 1;
    }
}
foreach ($entries as $index => $candidate) {
    $candidateValue = normalizePlateText((string)($candidate['plate_normalized'] ?? $candidate['plate'] ?? ''));
    $entries[$index]['duplicate_plate'] = $candidateValue !== '' && ($totals[$candidateValue] ?? 0) > 1;
}

writeJsonFile(LOG_FILE, $entries);

$hash = (string)($entries[$entryIndex]['sha256'] ?? '');
if ($hash !== '') {
    $hashIndex = readHashIndex();
    if (isset($hashIndex[$hash]) && is_array($hashIndex[$hash])) {
        $hashIndex[$hash]['plate'] = $value;
        $hashIndex[$hash]['confidence'] = $entries[$entryIndex]['confidence'];
        $hashIndex[$hash]['processed_at'] = $entries[$entryIndex]['processed_at'];
        writeJsonFile(HASH_INDEX_FILE, $hashIndex);
    }
}

echo json_encode([
    'id' => $id,
    'status' => 'complete',
    'plate' => $value,
    'confidence' => $entries[$entryIndex]['confidence'],
    'duplicate_plate' => $entries[$entryIndex]['duplicate_plate'],
    'plate_count' => $value !== '' ? ($totals[$value] ?? 0) : 0,
    'scan_attempts' => $attempts,
]);
