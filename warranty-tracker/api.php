<?php

declare(strict_types=1);

require_once __DIR__ . '/../1-Framework/bootstrap.php';
require_once __DIR__ . '/lib/WarrantyStore.php';

header('Content-Type: application/json; charset=utf-8');
$requestId = bin2hex(random_bytes(6));
$runtimeDirectory = __DIR__ . '/storage/runtime';
$runtimePath = $runtimeDirectory . '/warranties.json';
if (!is_file($runtimePath) && is_file(__DIR__ . '/storage/warranties.json')) {
    if (!is_dir($runtimeDirectory) && !mkdir($runtimeDirectory, 0775, true) && !is_dir($runtimeDirectory)) throw new RuntimeException('Warranty runtime storage could not be created.');
    if (!copy(__DIR__ . '/storage/warranties.json', $runtimePath)) throw new RuntimeException('Warranty runtime data could not be initialized.');
}
$store = new WarrantyStore($runtimePath);
$auth = \Jasr\Framework\SharedIdentity::connect();

function respond(bool $success, string $message, mixed $data = null, array $errors = [], int $status = 200): never
{
    global $requestId;
    http_response_code($status);
    echo json_encode(['success' => $success, 'message' => $message, 'data' => $data, 'errors' => $errors, 'meta' => ['timestamp' => gmdate(DATE_ATOM), 'requestId' => $requestId]], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    exit;
}

try {
    $user = $auth->requireProject('warranty-tracker', 'viewer', true);
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if ($method === 'GET') {
        respond(true, 'Warranties loaded.', $store->visibleFor((string) $user['id']));
    }
    if (!$auth->validCsrf($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null)) respond(false, 'Security token is invalid.', null, [['code' => 'CSRF_INVALID', 'message' => 'Security token is invalid.']], 403);
    $input = json_decode((string) file_get_contents('php://input'), true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($input)) {
        respond(false, 'Invalid request.', null, [['code' => 'INVALID_BODY', 'message' => 'A JSON object is required.']], 400);
    }
    if ($method === 'DELETE') {
        $id = trim((string) ($input['id'] ?? ''));
        if ($id === '') respond(false, 'Warranty ID is required.', null, [['code' => 'VALIDATION_FAILED', 'field' => 'id', 'message' => 'Warranty ID is required.']], 422);
        $deleted = $store->delete($id, (string) $user['id']);
        respond($deleted, $deleted ? 'Warranty deleted.' : 'Warranty not found.', null, $deleted ? [] : [['code' => 'NOT_FOUND', 'message' => 'The warranty record does not exist.']], $deleted ? 200 : 404);
    }
    if ($method !== 'POST') respond(false, 'Method not allowed.', null, [], 405);

    $errors = [];
    foreach (['product', 'purchaseDate'] as $field) {
        if (trim((string) ($input[$field] ?? '')) === '') $errors[] = ['code' => 'VALIDATION_FAILED', 'field' => $field, 'message' => "$field is required."];
    }
    foreach (['purchaseDate'] as $field) {
        if (isset($input[$field]) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $input[$field])) $errors[] = ['code' => 'VALIDATION_FAILED', 'field' => $field, 'message' => 'Use YYYY-MM-DD.'];
    }
    $months = (int) ($input['warrantyDurationMonths'] ?? 0);
    if ($months < 1) $months = (int) ($input['customWarrantyMonths'] ?? 0);
    if ($months < 1 || $months > 1200) $errors[] = ['code' => 'VALIDATION_FAILED', 'field' => 'warrantyDurationMonths', 'message' => 'Enter a warranty term of 1 to 1200 months.'];
    if (!$errors) {
        $input['warrantyDurationMonths'] = $months;
        $input['warrantyEndDate'] = (new DateTimeImmutable($input['purchaseDate']))->modify("+{$months} months")->format('Y-m-d');
    }
    foreach (['retailPrice', 'paidPrice', 'warrantyCost'] as $field) {
        if (isset($input[$field]) && $input[$field] !== '' && (!is_numeric($input[$field]) || (float) $input[$field] < 0)) {
            $errors[] = ['code' => 'VALIDATION_FAILED', 'field' => $field, 'message' => "$field must be zero or greater."];
        }
    }
    foreach (['productPhoto', 'receiptPhoto'] as $field) {
        if (($input[$field] ?? '') !== '' && (!is_string($input[$field]) || strlen($input[$field]) > 4194304 || !preg_match('#^data:image/(jpeg|png|gif|webp);base64,[A-Za-z0-9+/=]+$#', $input[$field]))) $errors[] = ['code' => 'VALIDATION_FAILED', 'field' => $field, 'message' => 'Upload a valid image smaller than 3 MB.'];
    }
    if ($errors) respond(false, 'Please correct the highlighted fields.', null, $errors, 422);
    respond(true, 'Warranty saved.', $store->save($input, (string) $user['id']));
} catch (JsonException) {
    respond(false, 'Invalid JSON request.', null, [['code' => 'INVALID_JSON', 'message' => 'The request contains invalid JSON.']], 400);
} catch (Throwable $exception) {
    error_log('Warranty tracker [' . $requestId . ']: ' . $exception->getMessage());
    respond(false, 'The request could not be completed.', null, [['code' => 'SERVER_ERROR', 'message' => 'Try again or use the request ID when checking logs.']], 500);
}
