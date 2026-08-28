<?php

declare(strict_types=1);

require_once __DIR__ . '/../1-Framework/bootstrap.php';
require_once __DIR__ . '/lib/WarrantyStore.php';
require_once __DIR__ . '/lib/Auth.php';

header('Content-Type: application/json; charset=utf-8');
$requestId = bin2hex(random_bytes(6));
$store = new WarrantyStore(__DIR__ . '/storage/warranties.json');
$auth = new Auth(__DIR__ . '/storage/users.json', __DIR__ . '/storage/invites.json');

function respond(bool $success, string $message, mixed $data = null, array $errors = [], int $status = 200): never
{
    global $requestId;
    http_response_code($status);
    echo json_encode(['success' => $success, 'message' => $message, 'data' => $data, 'errors' => $errors, 'meta' => ['timestamp' => gmdate(DATE_ATOM), 'requestId' => $requestId]], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    exit;
}

try {
    $user = $auth->user();
    if ($user === null) respond(false, 'Sign in is required.', null, [['code' => 'AUTH_REQUIRED', 'message' => 'Sign in is required.']], 401);
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if ($method === 'GET') {
        respond(true, 'Warranties loaded.', $store->visibleFor((string) $user['id']));
    }
    $auth->verifyCsrf($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);
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
    foreach (['product', 'purchaseDate', 'warrantyEndDate'] as $field) {
        if (trim((string) ($input[$field] ?? '')) === '') $errors[] = ['code' => 'VALIDATION_FAILED', 'field' => $field, 'message' => "$field is required."];
    }
    foreach (['purchaseDate', 'warrantyEndDate'] as $field) {
        if (isset($input[$field]) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $input[$field])) $errors[] = ['code' => 'VALIDATION_FAILED', 'field' => $field, 'message' => 'Use YYYY-MM-DD.'];
    }
    if (($input['warrantyEndDate'] ?? '') < ($input['purchaseDate'] ?? '')) $errors[] = ['code' => 'VALIDATION_FAILED', 'field' => 'warrantyEndDate', 'message' => 'Warranty end date cannot precede purchase date.'];
    foreach (['itemCost', 'warrantyCost'] as $field) {
        if (isset($input[$field]) && $input[$field] !== '' && (!is_numeric($input[$field]) || (float) $input[$field] < 0)) {
            $errors[] = ['code' => 'VALIDATION_FAILED', 'field' => $field, 'message' => "$field must be zero or greater."];
        }
    }
    if ($errors) respond(false, 'Please correct the highlighted fields.', null, $errors, 422);
    respond(true, 'Warranty saved.', $store->save($input, (string) $user['id']));
} catch (JsonException) {
    respond(false, 'Invalid JSON request.', null, [['code' => 'INVALID_JSON', 'message' => 'The request contains invalid JSON.']], 400);
} catch (Throwable $exception) {
    error_log('Warranty tracker [' . $requestId . ']: ' . $exception->getMessage());
    respond(false, 'The request could not be completed.', null, [['code' => 'SERVER_ERROR', 'message' => 'Try again or use the request ID when checking logs.']], 500);
}
