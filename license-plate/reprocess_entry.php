<?php
/*
    License Plate Photo Logger
    Revision: 1.0.0
    Description: Reprocess any saved log entry photo using the current scanner mode and update duplicate plate flags.
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

$result = processSavedLogEntry($id, false);
$httpStatus = (int)($result['http_status'] ?? 500);
unset($result['http_status']);

http_response_code($httpStatus);
echo json_encode($result);
