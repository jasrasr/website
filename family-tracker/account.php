<?php
/**
 * Project: Family GPS Tracker
 * File: account.php
 * Revision: 1.4.3
 * Description: Signed-in account utilities for password changes, persistent-login device management, and data export.
 * Author: Jason Lamb / ChatGPT scaffold
 * Created: 2026-07-09
 * Modified: 2026-07-09
 */

declare(strict_types=1);
require_once __DIR__ . '/includes/security.php';

init_app_storage();

try {
    $user = require_user();
    $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    $action = $_GET['action'] ?? '';
    $action = is_string($action) ? trim($action) : '';

    if ($method === 'GET') {
        if ($action === 'export_my_data') {
            ok(['export' => export_user_data($user)]);
        }
        ok(['csrfToken' => ensure_csrf_token(), 'devices' => persistent_login_devices($user)]);
    }

    require_csrf();
    $input = request_input();
    $action = str_field($input, 'action', 60);

    switch ($action) {
        case 'change_password':
            change_password($user, $input);
            break;
        case 'revoke_device':
            revoke_device($user, $input);
            break;
        case 'revoke_all_devices':
            revoke_all_devices($user);
            break;
        default:
            fail('Unknown account action.', 404);
    }
} catch (Throwable $ex) {
    error_log('Family Tracker account error: ' . $ex->getMessage());
    fail('Server error. Check PHP error logs.', 500);
}

function current_selector(): string
{
    $parsed = parse_persistent_login_cookie();
    return $parsed ? (string)$parsed[0] : '';
}

function persistent_login_devices(array $user): array
{
    $current = current_selector();
    $devices = [];
    foreach (list_json_records('persistent_logins') as $record) {
        if (($record['userId'] ?? '') !== ($user['id'] ?? '')) {
            continue;
        }
        $selector = safe_id((string)($record['selector'] ?? ''));
        if ($selector === '') {
            continue;
        }
        $expires = isset($record['expiresAt']) ? strtotime((string)$record['expiresAt']) : false;
        if (!$expires || $expires < time()) {
            delete_json_file(remember_token_path($selector));
            continue;
        }
        $devices[] = [
            'selector' => $selector,
            'createdAt' => $record['createdAt'] ?? null,
            'lastUsedAt' => $record['lastUsedAt'] ?? null,
            'expiresAt' => $record['expiresAt'] ?? null,
            'current' => $selector === $current,
            'userAgentHash' => substr((string)($record['userAgentHash'] ?? ''), 0, 12),
        ];
    }
    usort($devices, fn($a, $b) => strcmp((string)($b['lastUsedAt'] ?? ''), (string)($a['lastUsedAt'] ?? '')));
    return $devices;
}

function revoke_all_devices(array $user): void
{
    $count = 0;
    foreach (persistent_login_devices($user) as $device) {
        delete_json_file(remember_token_path((string)$device['selector']));
        $count++;
    }
    clear_persistent_login_cookie();
    audit_event('revoke_all_devices', ['userId' => $user['id'], 'count' => $count]);
    ok(['csrfToken' => ensure_csrf_token(), 'devices' => [], 'message' => 'All remembered devices were revoked.']);
}

function revoke_device(array $user, array $input): void
{
    $selector = safe_id(str_field($input, 'selector', 80));
    if ($selector === '') {
        fail('Device selector is required.', 400);
    }
    $record = read_json_file(remember_token_path($selector), []);
    if (!$record || (($record['userId'] ?? '') !== ($user['id'] ?? ''))) {
        fail('Device not found.', 404);
    }
    delete_json_file(remember_token_path($selector));
    if ($selector === current_selector()) {
        clear_persistent_login_cookie();
    }
    audit_event('revoke_device', ['userId' => $user['id'], 'selector' => $selector]);
    ok(['csrfToken' => ensure_csrf_token(), 'devices' => persistent_login_devices($user), 'message' => 'Remembered device revoked.']);
}

function change_password(array $user, array $input): void
{
    $currentPassword = (string)($input['currentPassword'] ?? '');
    $newPassword = (string)($input['newPassword'] ?? '');
    $confirmPassword = (string)($input['confirmPassword'] ?? '');

    if (!password_verify($currentPassword, (string)($user['passwordHash'] ?? ''))) {
        fail('Current password is incorrect.', 401);
    }
    if ($newPassword !== $confirmPassword) {
        fail('New password and confirmation do not match.', 400);
    }
    validate_password_or_fail($newPassword);

    $user['passwordHash'] = password_hash($newPassword, PASSWORD_DEFAULT);
    $user['passwordChangedAt'] = now_iso();
    $user['updatedAt'] = now_iso();
    write_user($user);

    $revoked = 0;
    foreach (persistent_login_devices($user) as $device) {
        delete_json_file(remember_token_path((string)$device['selector']));
        $revoked++;
    }
    clear_persistent_login_cookie();

    audit_event('change_password', ['userId' => $user['id'], 'rememberedDevicesRevoked' => $revoked]);
    ok(['csrfToken' => ensure_csrf_token(), 'devices' => [], 'message' => 'Password changed. Remembered devices were revoked.']);
}

function export_user_data(array $user): array
{
    $groupIds = user_group_ids($user);
    $groups = [];
    foreach ($groupIds as $groupId) {
        $family = read_family($groupId);
        if ($family && family_member_role($family, $user) !== null) {
            $groups[] = public_family($family, true) + ['role' => family_member_role($family, $user)];
        }
    }

    return [
        'exportedAt' => now_iso(),
        'appRevision' => APP_REVISION,
        'user' => public_user($user),
        'groups' => $groups,
        'latestLocation' => read_json_file(location_path((string)$user['id']), []),
        'trail' => read_json_file(trail_path((string)$user['id']), ['points' => []]),
        'rememberedDevices' => persistent_login_devices($user),
    ];
}
