<?php
/*
    License Plate Photo Logger
    Revision: 1.2.2
    Description: Pending-photo reprocessor with duplicate recalculation and clarity preservation.
*/
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
$plateState = normalizeUsStateName((string)($scan['state'] ?? ''));
$entries[$entryIndex]['plate'] = $value;
$entries[$entryIndex]['plate_normalized'] = $value;
$entries[$entryIndex]['plate_state'] = $plateState;
$entries[$entryIndex]['confidence'] = normalizeConfidenceValue($scan['confidence'] ?? 0);
$entries[$entryIndex]['raw_text'] = (string)($scan['raw_text'] ?? '');
$entries[$entryIndex]['error'] = '';
$entries[$entryIndex]['scan_status'] = 'complete';
$entries[$entryIndex]['processed_at'] = date('c');
if (!isset($entries[$entryIndex]['clarity_score'])) {
    $entries[$entryIndex]['clarity_score'] = computeImageClarityScore($imagePath, $mimeType);
}

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
recalculatePlateClarityFlags($entries);

writeJsonFile(LOG_FILE, $entries);

$hash = (string)($entries[$entryIndex]['sha256'] ?? '');
if ($hash !== '') {
    $hashIndex = readHashIndex();
    if (isset($hashIndex[$hash]) && is_array($hashIndex[$hash])) {
        $hashIndex[$hash]['plate'] = $value;
        $hashIndex[$hash]['plate_state'] = $entries[$entryIndex]['plate_state'];
        $hashIndex[$hash]['confidence'] = $entries[$entryIndex]['confidence'];
        $hashIndex[$hash]['processed_at'] = $entries[$entryIndex]['processed_at'];
        $hashIndex[$hash]['scan_mode'] = $entries[$entryIndex]['scan_mode'];
        $hashIndex[$hash]['clarity_score'] = $entries[$entryIndex]['clarity_score'] ?? 0;
        $hashIndex[$hash]['best_plate_photo'] = $entries[$entryIndex]['best_plate_photo'] ?? false;
        writeJsonFile(HASH_INDEX_FILE, $hashIndex);
    }
}

echo json_encode([
    'id' => $id,
    'status' => 'complete',
    'plate' => $value,
    'plate_state' => $entries[$entryIndex]['plate_state'] ?? '',
    'confidence' => $entries[$entryIndex]['confidence'],
    'clarity_score' => $entries[$entryIndex]['clarity_score'] ?? 0,
    'best_plate_photo' => $entries[$entryIndex]['best_plate_photo'] ?? false,
    'duplicate_plate' => $entries[$entryIndex]['duplicate_plate'],
    'plate_count' => $value !== '' ? ($totals[$value] ?? 0) : 0,
    'scan_attempts' => $attempts,
]);
