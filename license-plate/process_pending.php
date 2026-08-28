<?php
/*
    License Plate Photo Logger
    Revision: 1.2.28
    Description: Pending-photo reprocessor backed by the shared saved-entry scan helper.
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
$result = processSavedLogEntry($id, true);
$httpStatus = (int)($result['http_status'] ?? 500);
unset($result['http_status']);

http_response_code($httpStatus);
echo json_encode($result);
