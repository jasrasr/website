<?php
/**
 * Project: Family GPS Tracker
 * File: lifecycle.php
 * Revision: 1.4.9
 * Description: Account deletion, expired-device cleanup, and privacy lifecycle actions.
 * Author: Jason Lamb / ChatGPT scaffold
 * Created: 2026-07-11
 * Modified: 2026-07-11
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/notice-store.php';

init_app_storage();

try {
    $user = require_user();
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
        ok([
            'csrfToken' => ensure_csrf_token(),
            'accountDeletionAllowed' => account_deletion_check($user),
            'expiredDeviceCount' => count_expired_devices($user),
        ]);
    }

    require_csrf();
    $input = request_input();
    $action = str_field($input, 'action', 60);

    switch ($action) {
        case 'cleanup_expired_devices':
            cleanup_expired_devices($user);
            break;
        case 'delete_account':
            delete_account($user, $input);
            break;
        default:
            fail('Unknown lifecycle action.', 404);
    }
} catch (Throwable $ex) {
    error_log('Family Tracker lifecycle error: ' . $ex->getMessage());
    fail('Server error. Check PHP error logs.', 500);
}

function family_member_count(array $family): int
{
    $ids = is_array($family['memberIds'] ?? null) ? $family['memberIds'] : [];
    return count(array_values(array_unique(array_filter(array_map('strval', $ids)))));
}

function account_deletion_check(array $user): array
{
    $blocking = [];
    foreach (user_group_ids($user) as $familyId) {
        $family = read_family($familyId);
        if (!$family || family_member_role($family, $user) === null) {
            continue;
        }
        if (family_member_role($family, $user) === 'owner' && family_member_count($family) > 1) {
            $blocking[] = [
                'familyId' => $familyId,
                'name' => $family['name'] ?? 'Unnamed group',
                'reason' => 'Transfer ownership or remove the other members first.',
            ];
        }
    }
    return ['allowed' => !$blocking, 'blockingGroups' => $blocking];
}

function count_expired_devices(array $user): int
{
    $count = 0;
    foreach (list_json_records('persistent_logins') as $record) {
        if (($record['userId'] ?? '') !== ($user['id'] ?? '')) {
            continue;
        }
        $expires = isset($record['expiresAt']) ? strtotime((string)$record['expiresAt']) : false;
        if (!$expires || $expires < time()) {
            $count++;
        }
    }
    return $count;
}

function cleanup_expired_devices(array $user): void
{
    $count = 0;
    foreach (list_json_records('persistent_logins') as $record) {
        if (($record['userId'] ?? '') !== ($user['id'] ?? '')) {
            continue;
        }
        $selector = safe_id((string)($record['selector'] ?? ''));
        $expires = isset($record['expiresAt']) ? strtotime((string)$record['expiresAt']) : false;
        if ($selector !== '' && (!$expires || $expires < time())) {
            delete_json_file(remember_token_path($selector));
            $count++;
        }
    }
    audit_event('cleanup_expired_devices', ['userId' => $user['id'], 'count' => $count]);
    ok(['csrfToken' => ensure_csrf_token(), 'expiredDeviceCount' => 0, 'message' => $count . ' expired remembered-device record(s) removed.']);
}

function remove_user_from_family_record(array $family, string $userId): array
{
    $family['memberIds'] = array_values(array_filter($family['memberIds'] ?? [], fn($id) => (string)$id !== $userId));
    foreach (['memberRoles', 'memberProfiles', 'memberJoinedAt'] as $key) {
        $values = is_array($family[$key] ?? null) ? $family[$key] : [];
        unset($values[$userId]);
        $family[$key] = $values;
    }
    $family['updatedAt'] = now_iso();
    return $family;
}

function delete_account(array $user, array $input): void
{
    $password = (string)($input['password'] ?? '');
    $confirmation = strtoupper(trim((string)($input['confirmation'] ?? '')));
    if (!password_verify($password, (string)($user['passwordHash'] ?? ''))) {
        fail('Password is incorrect.', 401);
    }
    if ($confirmation !== 'DELETE MY ACCOUNT') {
        fail('Type DELETE MY ACCOUNT exactly to confirm.', 400);
    }

    $check = account_deletion_check($user);
    if (empty($check['allowed'])) {
        fail('Account deletion is blocked by owned groups with other members.', 409, ['blockingGroups' => $check['blockingGroups']]);
    }

    $userId = (string)$user['id'];
    $username = normalize_username((string)($user['username'] ?? ''));

    foreach (user_group_ids($user) as $familyId) {
        $family = read_family($familyId);
        if (!$family || family_member_role($family, $user) === null) {
            continue;
        }
        if (family_member_role($family, $user) === 'owner' && family_member_count($family) <= 1) {
            delete_json_file(family_path($familyId));
            delete_json_file(family_notices_path($familyId));
        } else {
            $family = remove_user_from_family_record($family, $userId);
            write_family($family);
            add_group_notice($familyId, 'member_deleted_account', ($user['displayName'] ?? $username) . ' deleted their account.', $userId);
        }
    }

    foreach (list_json_records('persistent_logins') as $record) {
        if (($record['userId'] ?? '') === $userId) {
            $selector = safe_id((string)($record['selector'] ?? ''));
            if ($selector !== '') {
                delete_json_file(remember_token_path($selector));
            }
        }
    }

    $index = read_json_file(username_index_path(), ['usernames' => []]);
    if ($username !== '' && (($index['usernames'][$username] ?? '') === $userId)) {
        unset($index['usernames'][$username]);
        write_json_file(username_index_path(), $index);
    }

    audit_event('delete_account', ['userId' => $userId, 'username' => $username]);
    delete_json_file(location_path($userId));
    delete_json_file(trail_path($userId));
    delete_json_file(user_path($userId));
    logout_current_session();

    ok(['message' => 'Account and stored personal location data deleted.']);
}
