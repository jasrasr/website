<?php
/*
    License Plate Photo Logger
    Revision: 1.2.12
    Description: Soft-delete endpoint that removes a log entry, archives its photo when possible, and records a deletion audit trail.
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
$reason = trim((string)($payload['reason'] ?? ''));

if ($id === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Entry id is required.']);
    exit;
}

if ($reason === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Delete reason is required.']);
    exit;
}

$auditEntry = softDeleteLogEntry($id, $reason);
if ($auditEntry === null) {
    http_response_code(404);
    echo json_encode(['error' => 'Entry not found or could not be deleted.']);
    exit;
}

echo json_encode([
    'deleted' => true,
    'audit_id' => $auditEntry['audit_id'] ?? '',
    'deleted_at' => $auditEntry['deleted_at'] ?? '',
    'deleted_reason' => $auditEntry['deleted_reason'] ?? '',
    'file_moved' => !empty($auditEntry['file_moved']),
    'file_note' => $auditEntry['file_note'] ?? '',
]);
