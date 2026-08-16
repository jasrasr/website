<?php
/**
 * Project: Family GPS Tracker
 * File: includes/security.php
 * Revision: 1.6.12
 * Description: Session, CSRF, authentication, persistent login, active-group membership, and owner-scoped payload helpers.
 * Author: Jason Lamb / ChatGPT scaffold
 * Created: 2026-07-06
 * Modified: 2026-08-16
 */

declare(strict_types=1);

require_once __DIR__ . '/json-store.php';

function is_https_request(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
}

function client_ip_hash(): string
{
    $raw = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    return hash('sha256', (string)$raw);
}

function user_agent_hash(): string
{
    return hash('sha256', (string)($_SERVER['HTTP_USER_AGENT'] ?? 'unknown'));
}

function request_input(): array
{
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (stripos($contentType, 'application/json') !== false) {
        $raw = file_get_contents('php://input');
        $decoded = json_decode($raw ?: '{}', true);
        return is_array($decoded) ? $decoded : [];
    }
    return $_POST;
}

function json_response(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}

function ok(array $payload = []): never
{
    json_response(['ok' => true] + $payload);
}

function fail(string $message, int $status = 400): never
{
    json_response(['ok' => false, 'error' => $message], $status);
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
    $provided = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    $expected = $_SESSION['csrf_token'] ?? '';
    if (!is_string($provided) || !is_string($expected) || $expected === '' || !hash_equals($expected, $provided)) {
        fail('Invalid CSRF token.', 403);
    }
}

function str_field(array $input, string $key, int $maxLength = 255): string
{
    $value = $input[$key] ?? '';
    if (!is_scalar($value)) {
        return '';
    }
    return substr(trim((string)$value), 0, $maxLength);
}

function bool_field(array $input, string $key): bool
{
    $value = $input[$key] ?? false;
    return filter_var($value, FILTER_VALIDATE_BOOLEAN);
}

function float_field(array $input, string $key, float $min, float $max): ?float
{
    $value = $input[$key] ?? null;
    if ($value === null || $value === '') {
        return null;
    }
    if (!is_numeric($value)) {
        return null;
    }
    $float = (float)$value;
    return ($float >= $min && $float <= $max) ? $float : null;
}

function clear_session_cookie(): void
{
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], (bool)$params['secure'], (bool)$params['httponly']);
    }
}

function persistent_login_cookie_options(int $expires): array
{
    return [
        'expires' => $expires,
        'path' => '/',
        'secure' => is_https_request(),
        'httponly' => true,
        'samesite' => 'Lax',
    ];
}

function create_persistent_login(array $user): void
{
    $selector = bin2hex(random_bytes(9));
    $validator = bin2hex(random_bytes(32));
    $expires = time() + REMEMBER_ME_LIFETIME_SECONDS;
    write_json_file(remember_token_path($selector), [
        'selector' => $selector,
        'validatorHash' => hash('sha256', $validator),
        'userId' => $user['id'],
        'createdAt' => now_iso(),
        'lastUsedAt' => now_iso(),
        'expiresAt' => gmdate('c', $expires),
        'userAgentHash' => user_agent_hash(),
    ]);
    setcookie(REMEMBER_COOKIE_NAME, $selector . ':' . $validator, persistent_login_cookie_options($expires));
}

function clear_persistent_login_cookie(): void
{
    setcookie(REMEMBER_COOKIE_NAME, '', persistent_login_cookie_options(time() - 3600));
}

function parse_persistent_login_cookie(): ?array
{
    $cookie = $_COOKIE[REMEMBER_COOKIE_NAME] ?? '';
    if (!is_string($cookie) || !str_contains($cookie, ':')) {
        return null;
    }
    [$selector, $validator] = explode(':', $cookie, 2);
    $selector = safe_id($selector);
    if ($selector === '' || $validator === '') {
        return null;
    }
    return [$selector, $validator];
}

function consume_persistent_login(): ?array
{
    $parsed = parse_persistent_login_cookie();
    if (!$parsed) {
        return null;
    }
    [$selector, $validator] = $parsed;
    $path = remember_token_path($selector);
    $record = read_json_file($path, []);
    $expiresAt = isset($record['expiresAt']) ? strtotime((string)$record['expiresAt']) : false;
    if (!$record || !$expiresAt || $expiresAt < time()) {
        delete_json_file($path);
        clear_persistent_login_cookie();
        return null;
    }
    if (!hash_equals((string)($record['validatorHash'] ?? ''), hash('sha256', $validator))) {
        delete_json_file($path);
        clear_persistent_login_cookie();
        return null;
    }
    if (!hash_equals((string)($record['userAgentHash'] ?? ''), user_agent_hash())) {
        return null;
    }
    $user = read_user((string)($record['userId'] ?? ''));
    if (!$user || empty($user['isActive'])) {
        delete_json_file($path);
        clear_persistent_login_cookie();
        return null;
    }
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['csrf_token'] = bin2hex(random_bytes(24));
    $record['lastUsedAt'] = now_iso();
    write_json_file($path, $record);
    return $user;
}

function revoke_current_persistent_login(): void
{
    $parsed = parse_persistent_login_cookie();
    if ($parsed) {
        delete_json_file(remember_token_path((string)$parsed[0]));
    }
    clear_persistent_login_cookie();
}

function start_authenticated_session(array $user, bool $rememberMe = false): void
{
    session_regenerate_id(true);
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['csrf_token'] = bin2hex(random_bytes(24));
    if ($rememberMe) {
        create_persistent_login($user);
    } else {
        revoke_current_persistent_login();
    }
}

function logout_current_session(): void
{
    revoke_current_persistent_login();
    $_SESSION = [];
    clear_session_cookie();
    session_destroy();
}

function current_user(): ?array
{
    $userId = $_SESSION['user_id'] ?? '';
    if (!is_string($userId) || $userId === '') {
        return consume_persistent_login();
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

function user_group_ids(array $user): array
{
    $ids = [];
    if (!empty($user['familyId'])) {
        $ids[] = safe_id((string)$user['familyId']);
    }
    foreach (($user['groupIds'] ?? []) as $id) {
        if (is_string($id) && $id !== '') {
            $ids[] = safe_id($id);
        }
    }
    return array_values(array_unique(array_filter($ids)));
}

function add_user_group_id(array $user, string $familyId): array
{
    $ids = user_group_ids($user);
    $ids[] = safe_id($familyId);
    $user['groupIds'] = array_values(array_unique(array_filter($ids)));
    return $user;
}

function family_member_role(array $family, array $user): ?string
{
    $userId = (string)($user['id'] ?? '');
    $familyId = (string)($family['id'] ?? '');
    if ($userId === '' || $familyId === '') {
        return null;
    }
    if (($family['ownerUserId'] ?? '') === $userId) {
        return 'owner';
    }
    $roles = $family['memberRoles'] ?? [];
    if (is_array($roles) && isset($roles[$userId]) && is_string($roles[$userId])) {
        return $roles[$userId] === 'owner' ? 'owner' : 'member';
    }
    $memberIds = $family['memberIds'] ?? [];
    if (is_array($memberIds) && in_array($userId, $memberIds, true)) {
        return 'member';
    }
    if (($user['familyId'] ?? '') === $familyId || in_array($familyId, user_group_ids($user), true)) {
        return (($user['role'] ?? '') === 'owner' && ($family['ownerUserId'] ?? '') === $userId) ? 'owner' : 'member';
    }
    return null;
}

function ensure_family_membership(array $family, array $user, string $role = 'member'): array
{
    $userId = (string)($user['id'] ?? '');
    if ($userId === '') {
        return $family;
    }
    $family['memberIds'] = array_values(array_unique(array_filter(array_merge($family['memberIds'] ?? [], [$userId]))));
    $roles = $family['memberRoles'] ?? [];
    if (!is_array($roles)) {
        $roles = [];
    }
    if (($family['ownerUserId'] ?? '') === $userId || $role === 'owner') {
        $roles[$userId] = 'owner';
    } elseif (empty($roles[$userId])) {
        $roles[$userId] = 'member';
    }
    $family['memberRoles'] = $roles;
    $family['updatedAt'] = now_iso();
    return $family;
}

function active_family_id_for_user(array $user): string
{
    $ids = user_group_ids($user);
    $candidates = [
        $_SESSION['active_family_id'] ?? '',
        $user['activeFamilyId'] ?? '',
        $user['familyId'] ?? '',
    ];
    foreach ($candidates as $candidate) {
        if (is_string($candidate) && in_array(safe_id($candidate), $ids, true)) {
            return safe_id($candidate);
        }
    }
    return $ids[0] ?? '';
}

function current_family_for_user(array $user): ?array
{
    $familyId = active_family_id_for_user($user);
    if ($familyId === '') {
        return null;
    }
    $family = read_family($familyId);
    if (!$family || family_member_role($family, $user) === null) {
        return null;
    }
    $_SESSION['active_family_id'] = $familyId;
    return $family;
}

function set_active_family_for_user(array $user, string $familyId): array
{
    $familyId = safe_id($familyId);
    $family = read_family($familyId);
    if (!$family || family_member_role($family, $user) === null) {
        fail('You are not a member of that group.', 403);
    }
    $user = add_user_group_id($user, $familyId);
    $user['familyId'] = $familyId;
    $user['activeFamilyId'] = $familyId;
    $user['role'] = family_member_role($family, $user) ?? 'member';
    write_user($user);
    $_SESSION['active_family_id'] = $familyId;
    return $user;
}

function public_user_for_family(array $user, array $family): array
{
    $public = public_user($user);
    $public['role'] = family_member_role($family, $user) ?? ($public['role'] ?? 'member');
    $public['activeFamilyId'] = $family['id'] ?? ($public['familyId'] ?? '');
    return $public;
}

function require_owner(array $user): void
{
    $family = current_family_for_user($user);
    if (!$family || family_member_role($family, $user) !== 'owner') {
        fail('Owner permission required for the active group.', 403);
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
            'mustChangePassword' => false,
        ];
    }

    $family = current_family_for_user($user);
    $isOwner = $family && family_member_role($family, $user) === 'owner';
    return [
        'authenticated' => true,
        'csrfToken' => $csrf,
        'user' => $family ? public_user_for_family($user, $family) : public_user($user),
        'family' => $family ? public_family($family, true, $isOwner) : null,
        'mustChangePassword' => !empty($user['mustChangePassword']),
    ];
}
