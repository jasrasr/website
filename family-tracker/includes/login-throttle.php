<?php
/**
 * Project: Family GPS Tracker
 * File: includes/login-throttle.php
 * Revision: 1.5.4
 * Description: File-backed login throttling by normalized username and privacy-preserving IP hash.
 * Author: Jason Lamb / ChatGPT scaffold
 * Created: 2026-07-11
 * Modified: 2026-07-11
 */

declare(strict_types=1);

require_once __DIR__ . '/json-store.php';

function login_client_ip(): string
{
    $forwarded = (string)($_SERVER['HTTP_X_FORWARDED_FOR'] ?? '');
    if ($forwarded !== '') {
        $parts = array_map('trim', explode(',', $forwarded));
        if ($parts && filter_var($parts[0], FILTER_VALIDATE_IP)) return $parts[0];
    }
    $remote = (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    return filter_var($remote, FILTER_VALIDATE_IP) ? $remote : 'unknown';
}

function login_throttle_key(string $username): string
{
    $usernameHash = hash('sha256', normalize_username($username));
    $ipHash = hash('sha256', login_client_ip());
    return substr(hash('sha256', $usernameHash . ':' . $ipHash), 0, 32);
}

function login_throttle_path(string $username): string
{
    return DATA_DIR . '/locks/login_' . login_throttle_key($username) . '.json';
}

function login_throttle_state(string $username): array
{
    $state = read_json_file(login_throttle_path($username), ['failures' => [], 'blockedUntil' => null]);
    $failures = is_array($state['failures'] ?? null) ? $state['failures'] : [];
    $cutoff = time() - LOGIN_THROTTLE_WINDOW_SECONDS;
    $state['failures'] = array_values(array_filter($failures, fn($value) => is_numeric($value) && (int)$value >= $cutoff));
    return $state;
}

function enforce_login_throttle(string $username): void
{
    $state = login_throttle_state($username);
    $blockedUntil = isset($state['blockedUntil']) ? strtotime((string)$state['blockedUntil']) : false;
    if ($blockedUntil && $blockedUntil > time()) {
        $retry = max(1, $blockedUntil - time());
        header('Retry-After: ' . $retry);
        fail('Too many login attempts. Try again in ' . $retry . ' seconds.', 429, ['retryAfterSeconds' => $retry]);
    }
}

function record_login_failure(string $username): void
{
    with_named_lock('login_throttle_' . login_throttle_key($username), function () use ($username): void {
        $state = login_throttle_state($username);
        $state['failures'][] = time();
        if (count($state['failures']) >= LOGIN_THROTTLE_MAX_FAILURES) {
            $state['blockedUntil'] = gmdate('c', time() + LOGIN_THROTTLE_BLOCK_SECONDS);
        }
        $state['updatedAt'] = now_iso();
        write_json_file(login_throttle_path($username), $state);
    });
}

function clear_login_throttle(string $username): void
{
    delete_json_file(login_throttle_path($username));
}

function cleanup_login_throttle_records(): int
{
    $count = 0;
    foreach (glob(DATA_DIR . '/locks/login_*.json') ?: [] as $path) {
        $modified = filemtime($path);
        if ($modified !== false && $modified < time() - 86400) {
            if (@unlink($path)) $count++;
        }
    }
    return $count;
}
