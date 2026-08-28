<?php
/**
 * File: includes/auth.php
 * Project: TV Binge Board
 * Description: Session, persistent remember-me authentication, authorization, account, settings, logging, and CSRF helpers.
 * Author: Jason Lamb / ChatGPT
 * Created: 2026-07-02
 * Modified: 2026-07-03
 * Revision: 1.5.11
 */
declare(strict_types=1);


require_once __DIR__ . '/json-store.php';

function app_cookie_secure(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name(APP_SESSION_NAME);
    session_set_cookie_params([
        'lifetime' => APP_SESSION_COOKIE_SECONDS,
        'path' => '/',
        'secure' => app_cookie_secure(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function app_accounts_path(): string { return APP_DATA_DIR . DIRECTORY_SEPARATOR . 'accounts.json'; }
function app_settings_path(): string { return APP_DATA_DIR . DIRECTORY_SEPARATOR . 'settings.json'; }
function app_activity_path(): string { return APP_DATA_DIR . DIRECTORY_SEPARATOR . 'activity-log.json'; }
function app_login_attempts_path(): string { return APP_DATA_DIR . DIRECTORY_SEPARATOR . 'login-attempts.json'; }
function app_remember_tokens_path(): string { return APP_DATA_DIR . DIRECTORY_SEPARATOR . 'remember-tokens.json'; }

function app_default_accounts(): array
{
    return ['_meta' => app_json_meta('Application user accounts and password hashes.'), 'users' => []];
}

function app_default_settings(): array
{
    return [
        '_meta' => app_json_meta('Site-wide application settings.'),
        'public_registration_enabled' => true,
        'updated_at' => date(DATE_ATOM),
    ];
}

function app_get_settings(): array
{
    $settings = app_load_json(app_settings_path(), app_default_settings());
    return array_merge(app_default_settings(), $settings);
}

function app_save_settings(array $settings): void
{
    $settings['_meta']['updated_at'] = date(DATE_ATOM);
    $settings['updated_at'] = date(DATE_ATOM);
    app_save_json(app_settings_path(), $settings);
}

function app_public_registration_enabled(): bool
{
    return !empty(app_get_settings()['public_registration_enabled']);
}

function app_get_accounts(): array
{
    $accounts = app_load_json(app_accounts_path(), app_default_accounts());
    if (!isset($accounts['users']) || !is_array($accounts['users'])) {
        $accounts['users'] = [];
    }
    foreach ($accounts['users'] as &$user) {
        $user['disabled'] = (bool)($user['disabled'] ?? false);
        $user['can_track'] = (bool)($user['can_track'] ?? (($user['role'] ?? '') !== 'admin'));
    }
    unset($user);
    return $accounts;
}

function app_save_accounts(array $accounts): void
{
    $accounts['_meta']['updated_at'] = date(DATE_ATOM);
    $accounts['_meta']['version'] = APP_VERSION;
    app_save_json(app_accounts_path(), $accounts);
}

function app_log_activity(string $actor, string $action, string $target = '', array $details = []): void
{
    $log = app_load_json(app_activity_path(), [
        '_meta' => app_json_meta('Administrative and account activity log.'),
        'events' => [],
    ]);
    if (!isset($log['events']) || !is_array($log['events'])) {
        $log['events'] = [];
    }
    $log['events'][] = [
        'at' => date(DATE_ATOM),
        'actor' => app_sanitize_username($actor),
        'action' => $action,
        'target' => app_sanitize_username($target),
        'details' => $details,
    ];
    $log['events'] = array_slice($log['events'], -500);
    $log['_meta']['updated_at'] = date(DATE_ATOM);
    app_save_json(app_activity_path(), $log);
}

function app_activity_events(int $limit = 100): array
{
    $log = app_load_json(app_activity_path(), ['events' => []]);
    $events = is_array($log['events'] ?? null) ? $log['events'] : [];
    return array_slice(array_reverse($events), 0, $limit);
}

function app_sanitize_username(string $username): string
{
    $username = strtolower(trim($username));
    return preg_replace('/[^a-z0-9._-]/', '', $username) ?? '';
}

function app_find_user(string $username): ?array
{
    $username = app_sanitize_username($username);
    foreach (app_get_accounts()['users'] as $user) {
        if (($user['username'] ?? '') === $username) {
            return $user;
        }
    }
    return null;
}

function app_update_account(array $updatedUser): void
{
    $accounts = app_get_accounts();
    foreach ($accounts['users'] as $index => $user) {
        if (($user['username'] ?? '') === ($updatedUser['username'] ?? '')) {
            $accounts['users'][$index] = $updatedUser;
            app_save_accounts($accounts);
            return;
        }
    }
    throw new RuntimeException('Account not found: ' . ($updatedUser['username'] ?? 'unknown'));
}

function app_create_user(string $username, string $password, string $displayName = ''): array
{
    $username = app_sanitize_username($username);
    if ($username === '' || strlen($username) < 3) {
        throw new InvalidArgumentException('Username must be at least 3 valid characters.');
    }
    if (strlen($password) < 8) {
        throw new InvalidArgumentException('Password must be at least 8 characters.');
    }
    if (app_find_user($username) !== null) {
        throw new InvalidArgumentException('Username already exists.');
    }

    $accounts = app_get_accounts();
    $user = [
        'id' => bin2hex(random_bytes(8)),
        'username' => $username,
        'display_name' => trim($displayName) !== '' ? trim($displayName) : $username,
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        'role' => 'user',
        'can_track' => true,
        'disabled' => false,
        'public_share_enabled' => false,
        'created_at' => date(DATE_ATOM),
        'last_login_at' => null,
        'password_changed_at' => date(DATE_ATOM),
    ];
    $accounts['users'][] = $user;
    app_save_accounts($accounts);
    app_seed_user_files($username, $user['display_name']);
    app_log_activity('system', 'user-created', $username, ['source' => 'registration']);
    return $user;
}

function app_login_client_ip(): string
{
    return trim((string)($_SERVER['REMOTE_ADDR'] ?? ''));
}

function app_trusted_login_ips(): array
{
    if (defined('TRUSTED_LOGIN_IPS_LOCAL') && is_array(TRUSTED_LOGIN_IPS_LOCAL)) {
        return array_values(array_filter(array_map(static fn($ip) => trim((string)$ip), TRUSTED_LOGIN_IPS_LOCAL), static fn($ip) => $ip !== ''));
    }
    return [];
}

function app_login_is_trusted_client(): bool
{
    $ip = app_login_client_ip();
    return $ip !== '' && in_array($ip, app_trusted_login_ips(), true);
}

function app_login_key(string $username): string
{
    $ip = app_login_client_ip();
    return sha1(app_sanitize_username($username) . '|' . ($ip !== '' ? $ip : 'unknown'));
}

function app_login_is_limited(string $username): bool
{
    if (app_login_is_trusted_client()) {
        return false;
    }
    $attempts = app_load_json(app_login_attempts_path(), ['attempts' => []]);
    $key = app_login_key($username);
    $entry = $attempts['attempts'][$key] ?? null;
    if (!is_array($entry)) {
        return false;
    }
    $last = strtotime((string)($entry['last_failed_at'] ?? '')) ?: 0;
    if ($last + APP_LOGIN_LOCKOUT_SECONDS < time()) {
        return false;
    }
    return (int)($entry['count'] ?? 0) >= APP_MAX_LOGIN_FAILURES;
}

function app_record_login_failure(string $username): void
{
    if (app_login_is_trusted_client()) {
        return;
    }
    $attempts = app_load_json(app_login_attempts_path(), ['_meta' => app_json_meta('Rate-limiting login attempts.'), 'attempts' => []]);
    $key = app_login_key($username);
    $entry = $attempts['attempts'][$key] ?? ['count' => 0];
    $last = strtotime((string)($entry['last_failed_at'] ?? '')) ?: 0;
    if ($last + APP_LOGIN_LOCKOUT_SECONDS < time()) {
        $entry['count'] = 0;
    }
    $entry['count'] = ((int)($entry['count'] ?? 0)) + 1;
    $entry['last_failed_at'] = date(DATE_ATOM);
    $entry['username'] = app_sanitize_username($username);
    $attempts['attempts'][$key] = $entry;
    $attempts['_meta']['updated_at'] = date(DATE_ATOM);
    app_save_json(app_login_attempts_path(), $attempts);
}

function app_clear_login_failures(string $username): void
{
    $attempts = app_load_json(app_login_attempts_path(), ['attempts' => []]);
    unset($attempts['attempts'][app_login_key($username)]);
    app_save_json(app_login_attempts_path(), $attempts);
}

function app_remember_cookie_name(): string
{
    return APP_SESSION_NAME . '_remember';
}

function app_remember_cookie_options(int $expires): array
{
    return [
        'expires' => $expires,
        'path' => '/',
        'secure' => app_cookie_secure(),
        'httponly' => true,
        'samesite' => 'Lax',
    ];
}

function app_default_remember_tokens(): array
{
    return ['_meta' => app_json_meta('Long-lived remember-me token hashes.'), 'tokens' => []];
}

function app_get_remember_tokens(): array
{
    $store = app_load_json(app_remember_tokens_path(), app_default_remember_tokens());
    if (!isset($store['tokens']) || !is_array($store['tokens'])) { $store['tokens'] = []; }
    return $store;
}

function app_save_remember_tokens(array $store): void
{
    $store['_meta']['updated_at'] = date(DATE_ATOM);
    $store['_meta']['version'] = APP_VERSION;
    app_save_json(app_remember_tokens_path(), $store);
}

function app_remember_user_agent_hash(): string
{
    return hash('sha256', (string)($_SERVER['HTTP_USER_AGENT'] ?? ''));
}

function app_remember_prune_store(array $store): array
{
    $now = time();
    $store['tokens'] = array_values(array_filter($store['tokens'] ?? [], static function ($token) use ($now) {
        if (!is_array($token)) { return false; }
        $expires = strtotime((string)($token['expires_at'] ?? '')) ?: 0;
        return $expires > $now;
    }));
    return $store;
}

function app_remember_parse_cookie(): ?array
{
    $value = (string)($_COOKIE[app_remember_cookie_name()] ?? '');
    if ($value === '' || !str_contains($value, ':')) { return null; }
    [$selector, $validator] = explode(':', $value, 2);
    if (!ctype_xdigit($selector) || !ctype_xdigit($validator) || strlen($selector) < 16 || strlen($validator) < 40) { return null; }
    return ['selector' => $selector, 'validator' => $validator];
}

function app_set_remember_cookie(string $selector, string $validator, int $expires): void
{
    setcookie(app_remember_cookie_name(), $selector . ':' . $validator, app_remember_cookie_options($expires));
    $_COOKIE[app_remember_cookie_name()] = $selector . ':' . $validator;
}

function app_clear_remember_cookie(): void
{
    setcookie(app_remember_cookie_name(), '', app_remember_cookie_options(time() - 42000));
    unset($_COOKIE[app_remember_cookie_name()]);
}

function app_issue_remember_token(string $username): void
{
    $username = app_sanitize_username($username);
    if ($username === '') { return; }
    $selector = bin2hex(random_bytes(12));
    $validator = bin2hex(random_bytes(32));
    $expires = time() + APP_REMEMBER_ME_SECONDS;
    $store = app_remember_prune_store(app_get_remember_tokens());
    $store['tokens'][] = [
        'selector' => $selector,
        'username' => $username,
        'validator_hash' => hash('sha256', $validator),
        'created_at' => date(DATE_ATOM),
        'last_used_at' => date(DATE_ATOM),
        'expires_at' => date(DATE_ATOM, $expires),
        'user_agent_hash' => app_remember_user_agent_hash(),
    ];
    $perUser = [];
    foreach ($store['tokens'] as $token) {
        $tokenUser = (string)($token['username'] ?? '');
        $perUser[$tokenUser] = ($perUser[$tokenUser] ?? 0) + 1;
    }
    if (($perUser[$username] ?? 0) > APP_REMEMBER_ME_MAX_TOKENS_PER_USER) {
        $seen = 0;
        $store['tokens'] = array_values(array_filter(array_reverse($store['tokens']), static function ($token) use ($username, &$seen) {
            if ((string)($token['username'] ?? '') !== $username) { return true; }
            $seen++;
            return $seen <= APP_REMEMBER_ME_MAX_TOKENS_PER_USER;
        }));
        $store['tokens'] = array_reverse($store['tokens']);
    }
    app_save_remember_tokens($store);
    app_set_remember_cookie($selector, $validator, $expires);
}

function app_revoke_remember_tokens_for_user(string $username): void
{
    $username = app_sanitize_username($username);
    $store = app_get_remember_tokens();
    $store['tokens'] = array_values(array_filter($store['tokens'] ?? [], static fn($token) => !is_array($token) || (string)($token['username'] ?? '') !== $username));
    app_save_remember_tokens($store);
}

function app_forget_current_remember_token(): void
{
    $parsed = app_remember_parse_cookie();
    if ($parsed !== null) {
        $store = app_get_remember_tokens();
        $store['tokens'] = array_values(array_filter($store['tokens'] ?? [], static fn($token) => !is_array($token) || (string)($token['selector'] ?? '') !== $parsed['selector']));
        app_save_remember_tokens($store);
    }
    app_clear_remember_cookie();
}

function app_remember_authenticate(): ?array
{
    $parsed = app_remember_parse_cookie();
    if ($parsed === null) { return null; }
    $store = app_remember_prune_store(app_get_remember_tokens());
    $matchedIndex = null;
    foreach ($store['tokens'] as $index => $token) {
        if (is_array($token) && (string)($token['selector'] ?? '') === $parsed['selector']) {
            $matchedIndex = $index;
            break;
        }
    }
    if ($matchedIndex === null) {
        app_clear_remember_cookie();
        app_save_remember_tokens($store);
        return null;
    }
    $token = $store['tokens'][$matchedIndex];
    if (!hash_equals((string)($token['validator_hash'] ?? ''), hash('sha256', $parsed['validator']))) {
        unset($store['tokens'][$matchedIndex]);
        app_save_remember_tokens($store);
        app_clear_remember_cookie();
        app_log_activity('system', 'remember-token-rejected', (string)($token['username'] ?? ''), ['reason' => 'validator-mismatch']);
        return null;
    }
    $user = app_find_user((string)($token['username'] ?? ''));
    if (!$user || !empty($user['disabled'])) {
        app_revoke_remember_tokens_for_user((string)($token['username'] ?? ''));
        app_clear_remember_cookie();
        return null;
    }
    $newValidator = bin2hex(random_bytes(32));
    $expires = time() + APP_REMEMBER_ME_SECONDS;
    $store['tokens'][$matchedIndex]['validator_hash'] = hash('sha256', $newValidator);
    $store['tokens'][$matchedIndex]['last_used_at'] = date(DATE_ATOM);
    $store['tokens'][$matchedIndex]['expires_at'] = date(DATE_ATOM, $expires);
    $store['tokens'][$matchedIndex]['user_agent_hash'] = app_remember_user_agent_hash();
    app_save_remember_tokens($store);
    app_set_remember_cookie($parsed['selector'], $newValidator, $expires);
    session_regenerate_id(true);
    $_SESSION['user'] = $user['username'];
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    app_log_activity('system', 'remember-login', $user['username']);
    return $user;
}

function app_login(string $username, string $password, bool $remember = false): bool
{
    if (app_login_is_limited($username)) {
        return false;
    }
    $user = app_find_user($username);
    if ($user === null || !password_verify($password, $user['password_hash'] ?? '')) {
        app_record_login_failure($username);
        return false;
    }
    if (!empty($user['disabled'])) {
        app_log_activity($user['username'], 'login-blocked-disabled', $user['username']);
        return false;
    }

    session_regenerate_id(true);
    $_SESSION['user'] = $user['username'];
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    app_clear_login_failures($username);

    if ($remember) {
        app_issue_remember_token($user['username']);
    } else {
        app_forget_current_remember_token();
    }

    $user['last_login_at'] = date(DATE_ATOM);
    app_update_account($user);
    app_seed_user_files($user['username'], $user['display_name'] ?? $user['username']);
    return true;
}

function app_change_password(string $username, string $currentPassword, string $newPassword): bool
{
    $user = app_find_user($username);
    if (!$user || !password_verify($currentPassword, $user['password_hash'] ?? '')) {
        return false;
    }
    if (strlen($newPassword) < 8) {
        throw new InvalidArgumentException('New password must be at least 8 characters.');
    }
    $user['password_hash'] = password_hash($newPassword, PASSWORD_DEFAULT);
    $user['password_changed_at'] = date(DATE_ATOM);
    app_update_account($user);
    app_revoke_remember_tokens_for_user($username);
    app_clear_remember_cookie();
    app_log_activity($username, 'password-changed', $username, ['remember_tokens_revoked' => true]);
    return true;
}

function app_admin_reset_password(string $actor, string $targetUsername, string $newPassword): void
{
    if (strlen($newPassword) < 8) {
        throw new InvalidArgumentException('New password must be at least 8 characters.');
    }
    $user = app_find_user($targetUsername);
    if (!$user) {
        throw new InvalidArgumentException('Target account not found.');
    }
    $user['password_hash'] = password_hash($newPassword, PASSWORD_DEFAULT);
    $user['password_changed_at'] = date(DATE_ATOM);
    app_update_account($user);
    app_revoke_remember_tokens_for_user($targetUsername);
    app_log_activity($actor, 'admin-password-reset', $targetUsername, ['remember_tokens_revoked' => true]);
}

function app_logout(): void
{
    app_forget_current_remember_token();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires' => time() - 42000,
            'path' => (string)($params['path'] ?? '/'),
            'domain' => (string)($params['domain'] ?? ''),
            'secure' => (bool)($params['secure'] ?? false),
            'httponly' => (bool)($params['httponly'] ?? true),
            'samesite' => (string)($params['samesite'] ?? 'Lax'),
        ]);
    }
    session_destroy();
}

function app_current_user(): ?array
{
    if (empty($_SESSION['user'])) {
        return app_remember_authenticate();
    }
    $user = app_find_user((string)$_SESSION['user']);
    if ($user && !empty($user['disabled'])) {
        app_revoke_remember_tokens_for_user((string)$user['username']);
        app_logout();
        return null;
    }
    return $user;
}

function app_base_prefix(): string
{
    $script = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
    return preg_match('#/(admin|api|tools)/#', $script) ? '../' : '';
}

function app_require_login(): array
{
    $user = app_current_user();
    if ($user === null) {
        header('Location: ' . app_base_prefix() . 'login.php');
        exit;
    }
    return $user;
}

function app_require_admin(): array
{
    $user = app_require_login();
    if (($user['role'] ?? '') !== 'admin') {
        http_response_code(403);
        exit('Forbidden');
    }
    return $user;
}

function app_is_admin(?array $user = null): bool
{
    $user = $user ?? app_current_user();
    return ($user['role'] ?? '') === 'admin';
}

function app_can_track(?array $user = null): bool
{
    $user = $user ?? app_current_user();
    return (bool)($user['can_track'] ?? false) && !app_is_admin($user) && empty($user['disabled']);
}

function app_csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return (string)$_SESSION['csrf_token'];
}

function app_verify_csrf(): void
{
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!is_string($token) || !hash_equals(app_csrf_token(), $token)) {
        http_response_code(400);
        exit('Invalid CSRF token.');
    }
}

function app_user_dir(string $username): string
{
    return APP_DATA_DIR . DIRECTORY_SEPARATOR . 'users' . DIRECTORY_SEPARATOR . app_sanitize_username($username);
}

function app_user_file(string $username, string $filename): string
{
    return app_user_dir($username) . DIRECTORY_SEPARATOR . $filename;
}

function app_seed_user_files(string $username, string $displayName = ''): void
{
    $username = app_sanitize_username($username);
    app_ensure_dir(app_user_dir($username));
    app_ensure_dir(app_user_dir($username) . DIRECTORY_SEPARATOR . 'imports');
    app_ensure_dir(app_user_dir($username) . DIRECTORY_SEPARATOR . 'uploads');

    foreach (['imports', 'uploads'] as $folder) {
        $placeholder = app_user_dir($username) . DIRECTORY_SEPARATOR . $folder . DIRECTORY_SEPARATOR . '.placeholder';
        if (!is_file($placeholder)) {
            file_put_contents($placeholder, 'Folder intentionally kept for runtime files.' . PHP_EOL);
        }
    }

    $profile = app_user_file($username, 'profile.json');
    if (!is_file($profile)) {
        app_save_json($profile, [
            '_meta' => app_json_meta('User profile, sharing, avatar, and privacy preferences.'),
            'username' => $username,
            'display_name' => $displayName !== '' ? $displayName : $username,
            'bio' => '',
            'avatar_url' => '',
            'public_share_enabled' => false,
            'created_at' => date(DATE_ATOM),
        ]);
    }

    $library = app_user_file($username, 'library.json');
    if (!is_file($library)) {
        app_save_json($library, [
            '_meta' => app_json_meta('Tracked shows, movies, statuses, ratings, notes, and episode progress.'),
            'items' => [],
        ]);
    }

    $connections = app_user_file($username, 'connections.json');
    if (!is_file($connections)) {
        app_save_json($connections, [
            '_meta' => app_json_meta('User-to-user sharing connections and pending requests.'),
            'connections' => [],
            'incoming_requests' => [],
            'outgoing_requests' => [],
        ]);
    }
}
