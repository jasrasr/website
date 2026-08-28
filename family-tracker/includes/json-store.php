<?php
/**
 * Project: Family GPS Tracker
 * File: includes/json-store.php
 * Revision: 1.6.6
 * Description: JSON read/write helpers, public payload shaping, and owner-visible active invite metadata.
 * Author: Jason Lamb / ChatGPT scaffold
 * Created: 2026-07-06
 * Modified: 2026-08-02
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/profile-helpers.php';

function now_iso(): string
{
    return gmdate('c');
}

function ensure_dir(string $dir): void
{
    if (!is_dir($dir)) {
        mkdir($dir, 0750, true);
    }
}

function init_app_storage(): void
{
    foreach (['users', 'families', 'locations', 'trails', 'notices', 'persistent_logins', 'locks', 'audit'] as $folder) {
        ensure_dir(DATA_DIR . '/' . $folder);
    }
}

function safe_id(string $id): string
{
    return preg_replace('/[^a-zA-Z0-9_\-]/', '', $id) ?? '';
}

function new_id(string $prefix): string
{
    return $prefix . '_' . bin2hex(random_bytes(8));
}

function read_json_file(string $path, array $default = []): array
{
    if (!is_file($path)) {
        return $default;
    }

    $raw = file_get_contents($path);
    if ($raw === false || trim($raw) === '') {
        return $default;
    }

    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : $default;
}

function write_json_file(string $path, array $data): void
{
    ensure_dir(dirname($path));
    $tmp = $path . '.tmp.' . bin2hex(random_bytes(4));
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

    if ($json === false) {
        throw new RuntimeException('Unable to encode JSON.');
    }

    if (file_put_contents($tmp, $json . PHP_EOL, LOCK_EX) === false) {
        throw new RuntimeException('Unable to write JSON file.');
    }

    chmod($tmp, 0640);
    if (!rename($tmp, $path)) {
        @unlink($tmp);
        throw new RuntimeException('Unable to move JSON file into place.');
    }
}

function delete_json_file(string $path): void
{
    if (is_file($path)) {
        unlink($path);
    }
}

function with_named_lock(string $name, callable $callback)
{
    ensure_dir(DATA_DIR . '/locks');
    $lockName = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $name) ?: 'default';
    $lockPath = DATA_DIR . '/locks/' . $lockName . '.lock';
    $handle = fopen($lockPath, 'c');

    if ($handle === false) {
        throw new RuntimeException('Unable to open lock file.');
    }

    try {
        if (!flock($handle, LOCK_EX)) {
            throw new RuntimeException('Unable to lock file.');
        }

        return $callback();
    } finally {
        flock($handle, LOCK_UN);
        fclose($handle);
    }
}

function user_path(string $userId): string
{
    return DATA_DIR . '/users/' . safe_id($userId) . '.json';
}

function family_path(string $familyId): string
{
    return DATA_DIR . '/families/' . safe_id($familyId) . '.json';
}

function location_path(string $userId): string
{
    return DATA_DIR . '/locations/' . safe_id($userId) . '.json';
}

function trail_path(string $userId): string
{
    return DATA_DIR . '/trails/' . safe_id($userId) . '.json';
}

function family_notices_path(string $familyId): string
{
    return DATA_DIR . '/notices/' . safe_id($familyId) . '.json';
}

function remember_token_path(string $selector): string
{
    return DATA_DIR . '/persistent_logins/' . safe_id($selector) . '.json';
}

function username_index_path(): string
{
    return DATA_DIR . '/users/_index.json';
}

function normalize_username(string $username): string
{
    $username = trim(strtolower($username));
    $username = preg_replace('/\s+/', '', $username) ?? '';
    return $username;
}

function read_user(string $userId): ?array
{
    $user = read_json_file(user_path($userId), []);
    return $user ? $user : null;
}

function write_user(array $user): void
{
    if (empty($user['id'])) {
        throw new InvalidArgumentException('User ID is required.');
    }
    write_json_file(user_path((string)$user['id']), $user);
}

function read_family(string $familyId): ?array
{
    $family = read_json_file(family_path($familyId), []);
    return $family ? $family : null;
}

function write_family(array $family): void
{
    if (empty($family['id'])) {
        throw new InvalidArgumentException('Family ID is required.');
    }
    write_json_file(family_path((string)$family['id']), $family);
}

function list_json_records(string $folder): array
{
    $dir = DATA_DIR . '/' . $folder;
    if (!is_dir($dir)) {
        return [];
    }

    $records = [];
    foreach (glob($dir . '/*.json') ?: [] as $path) {
        if (basename($path) === '_index.json') {
            continue;
        }
        $record = read_json_file($path, []);
        if ($record) {
            $records[] = $record;
        }
    }
    return $records;
}

function public_user(array $user): array
{
    return [
        'id' => $user['id'] ?? '',
        'displayName' => $user['displayName'] ?? '',
        'username' => $user['username'] ?? '',
        'familyId' => $user['familyId'] ?? '',
        'role' => $user['role'] ?? 'member',
        'createdAt' => $user['createdAt'] ?? null,
        'lastLoginAt' => $user['lastLoginAt'] ?? null,
        'lastLocationAt' => $user['lastLocationAt'] ?? null,
        'isActive' => $user['isActive'] ?? true,
        'profile' => public_profile_preferences($user),
    ];
}

function public_family(array $family, bool $includeInviteMeta = false, bool $includeOwnerInviteCode = false): array
{
    $out = [
        'id' => $family['id'] ?? '',
        'name' => $family['name'] ?? '',
        'ownerUserId' => $family['ownerUserId'] ?? '',
        'createdAt' => $family['createdAt'] ?? null,
    ];

    if ($includeInviteMeta) {
        $out['inviteCodeLast4'] = $family['inviteCodeLast4'] ?? null;
        $out['inviteCodeCreatedAt'] = $family['inviteCodeCreatedAt'] ?? null;
        $out['inviteCodeHidden'] = !empty($family['inviteCodeHidden']);
    }

    if ($includeOwnerInviteCode) {
        $out['inviteCode'] = (string)($family['inviteCodePlain'] ?? '');
    }

    return $out;
}

function audit_event(string $event, array $data = []): void
{
    $date = gmdate('Y-m-d');
    $path = DATA_DIR . '/audit/' . $date . '.json';
    with_named_lock('audit_' . $date, function () use ($path, $event, $data): void {
        $log = read_json_file($path, ['events' => []]);
        $log['events'][] = [
            'time' => now_iso(),
            'event' => $event,
            'data' => $data,
        ];
        if (count($log['events']) > 1000) {
            $log['events'] = array_slice($log['events'], -1000);
        }
        write_json_file($path, $log);
    });
}
