<?php
/*
    License Plate Photo Logger
    Revision: 1.2.8
    Description: Log-entry update endpoint for manual plate correction, favorites, and 1-10 preference ranking.
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
if (!is_array($payload)) {
    $payload = $_POST;
}

$id = trim((string)($payload['id'] ?? ''));
if ($id === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Entry id is required.']);
    exit;
}

$changes = [
    'plate' => (string)($payload['plate'] ?? ''),
    'plate_state' => (string)($payload['plate_state'] ?? ''),
    'favorite' => $payload['favorite'] ?? false,
    'preference_rank' => $payload['preference_rank'] ?? null,
];

$entry = updateLogEntry($id, $changes);
if ($entry === null) {
    http_response_code(404);
    echo json_encode(['error' => 'Entry not found.']);
    exit;
}

$plate = normalizePlateText((string)($entry['plate'] ?? ''));
$allEntries = readLogEntries();
$plateCount = 0;
if ($plate !== '') {
    foreach ($allEntries as $candidate) {
        $candidatePlate = normalizePlateText((string)($candidate['plate_normalized'] ?? $candidate['plate'] ?? ''));
        $candidateStatus = $candidate['scan_status'] ?? (empty($candidate['error']) ? 'complete' : 'pending');
        if ($candidateStatus === 'complete' && $candidatePlate === $plate) {
            $plateCount++;
        }
    }
}

echo json_encode([
    'id' => $entry['id'] ?? '',
    'status' => $entry['scan_status'] ?? 'complete',
    'plate' => $plate,
    'plate_state' => $entry['plate_state'] ?? '',
    'confidence' => $entry['confidence'] ?? 0,
    'favorite' => !empty($entry['favorite']),
    'preference_rank' => $entry['preference_rank'] ?? null,
    'duplicate_plate' => !empty($entry['duplicate_plate']),
    'duplicate_file' => !empty($entry['duplicate_file']),
    'best_plate_photo' => !empty($entry['best_plate_photo']),
    'plate_count' => $plateCount,
    'manual_corrected' => !empty($entry['manual_corrected']),
    'scanner_label' => entryScannerLabel($entry),
    'error' => (string)($entry['error'] ?? ''),
]);
