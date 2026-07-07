<?php
/**
 * Project: Family GPS Tracker
 * File: includes/security.php
 * Revision: 1.0.0
 * Description: Request, validation, session, and response helpers.
 * Author: Jason Lamb / ChatGPT scaffold
 * Created: 2026-07-06
 * Modified: 2026-07-06
 */

declare(strict_types=1);

require_once __DIR__ . '/json-store.php';

function json_response(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;
}

function ok(array $payload = []): never
{
    json_response(['ok' => true] + $payload);
}

function fail(string $message, int $status = 400, array $extra = []): never
{
    json_response(['ok' => false, 'error' => $message] + $extra, $status);
}

function request_input(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || trim($raw) === '') {
        return $_POST ?: [];
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        fail('Invalid JSON request body.', 400);
    }
    return $decoded;
}

function str_field(array $input, string $key, int $maxLength = 120): string
{
    $value = isset($input[$key]) ? trim((string)$input[$key]) : '';
    $value = preg_replace('/[\x00-\x1F\x7F]/u', '', $value) ?? '';
    if (strlen($value) > $maxLength) {
        $value = substr($value, 0, $maxLength);
    }
    return $value;
}

function bool_field(array $input, string $key): bool
{
    return filter_var($input[$key] ?? false, FILTER_VALIDATE_BOOL);
}

function float_field(array $input, string $key, ?float $min = null, ?float $max = null): ?float
{
    if (!isset($input[$key]) || $input[$key] === '' || $input[$key] === null) {
        return null;
    }

    if (!is_numeric($input[$key])) {
        fail("Invalid numeric value for {$key}.", 400);
    }

    $value = (float)$input[$key];
    if ($min !== null && $value < $min) {
        fail("{$key} is below the allowed range.", 400);
    }
    if ($max !== null && $value > $max) {
        fail("{$key} is above the allowed range.", 400);
    }
    return $value;
}

function ensure_csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(24));
    }
    return (string)$_SESSION['csrf_token'];
}

function require_csrf(): void
{
    $expected = ensure_csrf_token();
    $provided = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!is_string($provided) || !hash_equals($expected, $provided)) {
        fail('Invalid or missing CSRF token.', 403);
    }
}

function current_user(): ?array
{
    $userId = $_SESSION['user_id'] ?? '';
    if (!is_string($userId) || $userId === '') {
        return null;
    }

    $user = read_user($userId);
    if (!$user || empty($user['isActive'])) {
        return null;
    }
    return $user;
}

function require_user(): array
{
    $user = current_user();
    if (!$user) {
        fail('Authentication required.', 401);
    }
    return $user;
}

function require_owner(array $user): void
{
    if (($user['role'] ?? '') !== 'owner') {
        fail('Owner permission required.', 403);
    }
}

function generate_invite_code(): string
{
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $code = '';
    for ($i = 0; $i < 10; $i++) {
        $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
    }
    return substr($code, 0, 5) . '-' . substr($code, 5);
}

function normalize_invite_code(string $code): string
{
    $code = strtoupper(trim($code));
    return preg_replace('/[^A-Z0-9]/', '', $code) ?? '';
}

function find_family_by_invite_code(string $inputCode): ?array
{
    $normalized = normalize_invite_code($inputCode);
    if ($normalized === '') {
        return null;
    }

    foreach (list_json_records('families') as $family) {
        $hash = $family['inviteCodeHash'] ?? '';
        if (is_string($hash) && $hash !== '' && password_verify($normalized, $hash)) {
            return $family;
        }
    }
    return null;
}

function validate_password_or_fail(string $password): void
{
    if (strlen($password) < MIN_PASSWORD_LENGTH) {
        fail('Password must be at least ' . MIN_PASSWORD_LENGTH . ' characters.', 400);
    }
}

function validate_username_or_fail(string $username): void
{
    if ($username === '' || strlen($username) < 3) {
        fail('Username must be at least 3 characters.', 400);
    }
    if (!preg_match('/^[a-z0-9._@+\-]+$/', $username)) {
        fail('Username may contain letters, numbers, dot, dash, underscore, plus, or @.', 400);
    }
}

function build_me_payload(?array $user): array
{
    $csrf = ensure_csrf_token();
    if (!$user) {
        return [
            'authenticated' => false,
            'csrfToken' => $csrf,
            'user' => null,
            'family' => null,
        ];
    }

    $family = !empty($user['familyId']) ? read_family((string)$user['familyId']) : null;
    return [
        'authenticated' => true,
        'csrfToken' => $csrf,
        'user' => public_user($user),
        'family' => $family ? public_family($family, true) : null,
    ];
}
