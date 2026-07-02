<?php
/**
 * File: tools/recover-user.php
 * Project: TV Binge Board
 * Description: Command-line recovery helper to create or reset a user and clear login lockouts.
 * Author: Jason Lamb / ChatGPT
 * Created: 2026-07-02
 * Modified: 2026-07-02
 * Revision: 1.0.0
 */
declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('This script is CLI-only.' . PHP_EOL);
}

$options = getopt('', ['username:', 'password:', 'display-name::', 'clear-lockout-ip::', 'role::']);
$username = app_sanitize_username((string)($options['username'] ?? ''));
$password = (string)($options['password'] ?? '');
$displayName = trim((string)($options['display-name'] ?? ''));
$clearLockoutIp = trim((string)($options['clear-lockout-ip'] ?? ''));
$role = strtolower(trim((string)($options['role'] ?? 'user')));

if ($username === '' || strlen($username) < 3) {
    fwrite(STDERR, "Usage: php tools/recover-user.php --username=<name> --password=<password> [--display-name=<name>] [--clear-lockout-ip=<ip>] [--role=user|admin]\n");
    exit(1);
}
if (strlen($password) < 8) {
    fwrite(STDERR, "Password must be at least 8 characters.\n");
    exit(1);
}
if (!in_array($role, ['user', 'admin'], true)) {
    fwrite(STDERR, "Role must be user or admin.\n");
    exit(1);
}

$user = app_find_user($username);
if ($user === null) {
    $user = app_create_user($username, $password, $displayName);
    if ($role === 'admin') {
        $user['role'] = 'admin';
        $user['can_track'] = false;
        app_update_account($user);
    }
    fwrite(STDOUT, "Created user {$username}.\n");
} else {
    $user['display_name'] = $displayName !== '' ? $displayName : (string)($user['display_name'] ?? $username);
    $user['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
    $user['password_changed_at'] = date(DATE_ATOM);
    $user['disabled'] = false;
    $user['role'] = $role;
    $user['can_track'] = $role !== 'admin';
    app_update_account($user);
    app_log_activity('system', 'cli-user-recovered', $username, ['role' => $role]);
    fwrite(STDOUT, "Updated user {$username}.\n");
}

$attempts = app_load_json(app_login_attempts_path(), ['attempts' => []]);
if (!isset($attempts['attempts']) || !is_array($attempts['attempts'])) {
    $attempts['attempts'] = [];
}

$removed = 0;
if ($clearLockoutIp !== '') {
    $key = sha1($username . '|' . $clearLockoutIp);
    if (isset($attempts['attempts'][$key])) {
        unset($attempts['attempts'][$key]);
        $removed++;
    }
} else {
    foreach (array_keys($attempts['attempts']) as $key) {
        $entry = $attempts['attempts'][$key] ?? null;
        if (($entry['username'] ?? '') === $username) {
            unset($attempts['attempts'][$key]);
            $removed++;
        }
    }
}

if ($removed > 0) {
    app_save_json(app_login_attempts_path(), $attempts);
}
fwrite(STDOUT, "Cleared {$removed} lockout entr" . ($removed === 1 ? 'y' : 'ies') . " for {$username}.\n");

// Example Usage:
//   php .\tools\recover-user.php --username=jasrasr --password=ChangeMe123 --display-name="Jason"
//   php .\tools\recover-user.php --username=jasrasr --password=ChangeMe123 --clear-lockout-ip=12.6.64.130
