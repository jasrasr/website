<?php
/**
 * Project: Family GPS Tracker
 * File: member-management.php
 * Revision: 1.6.12
 * Description: Active-group member metadata, temporary disable/restore, leave-group, owner-assisted password reset, and removal/permanent-deletion endpoints.
 * Author: Jason Lamb / ChatGPT scaffold
 * Created: 2026-07-09
 * Modified: 2026-08-16
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/notice-store.php';

init_app_storage();

try {
    $user = require_user();
    $family = current_family_for_user($user);
    if (!$family) {
        fail('Active group not found.', 404);
    }

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
        ok(management_payload($user, $family));
    }

    require_csrf();
    $input = request_input();
    $action = str_field($input, 'action', 60);

    switch ($action) {
        case 'update_member_profile':
            update_member_profile($user, $family, $input);
            break;
        case 'disable_member':
            disable_member_in_group($user, $family, $input);
            break;
        case 'restore_member':
            restore_member_to_group($user, $family, $input);
            break;
        case 'remove_member':
            remove_member_from_group($user, $family, $input);
            break;
        case 'delete_member':
            delete_member_account($user, $family, $input);
            break;
        case 'reset_member_password':
            reset_member_password($user, $family, $input);
            break;
        case 'leave_group':
            leave_active_group($user, $family);
            break;
        default:
            fail('Unknown member-management action.', 404);
    }
} catch (Throwable $ex) {
    error_log('Family Tracker member-management error: ' . $ex->getMessage());
    fail('Server error. Check PHP error logs.', 500);
}

function require_active_group_owner(array $user, array $family): void
{
    if (family_member_role($family, $user) !== 'owner') {
        fail('Owner permission required for the active group.', 403);
    }
}

function profile_for_member(array $family, string $userId): array
{
    $profiles = is_array($family['memberProfiles'] ?? null) ? $family['memberProfiles'] : [];
    $profile = is_array($profiles[$userId] ?? null) ? $profiles[$userId] : [];
    return [
        'nickname' => trim((string)($profile['nickname'] ?? '')),
        'relationship' => trim((string)($profile['relationship'] ?? '')),
        'color' => valid_member_color((string)($profile['color'] ?? '')),
    ];
}

function valid_member_color(string $color): string
{
    $color = trim($color);
    return preg_match('/^#[0-9a-fA-F]{6}$/', $color) ? strtoupper($color) : '';
}

function joined_at_for_member(array $family, array $member): ?string
{
    $joined = is_array($family['memberJoinedAt'] ?? null) ? $family['memberJoinedAt'] : [];
    $userId = (string)($member['id'] ?? '');
    return $joined[$userId] ?? ($member['createdAt'] ?? null);
}

function suspended_member_ids(array $family): array
{
    $ids = is_array($family['suspendedMemberIds'] ?? null) ? $family['suspendedMemberIds'] : [];
    return array_values(array_unique(array_filter(array_map(fn($id) => safe_id((string)$id), $ids))));
}

function is_suspended_member(array $family, string $memberId): bool
{
    return in_array($memberId, suspended_member_ids($family), true);
}

function require_known_group_member(array $family, string $memberId): array
{
    $member = read_user($memberId);
    if (!$member || (family_member_role($family, $member) === null && !is_suspended_member($family, $memberId))) {
        fail('Member not found in active group.', 404);
    }
    return $member;
}

function management_members(array $family): array
{
    $members = [];
    $labelCounts = [];
    $ids = array_values(array_unique(array_merge(
        array_map('strval', is_array($family['memberIds'] ?? null) ? $family['memberIds'] : []),
        suspended_member_ids($family),
        [(string)($family['ownerUserId'] ?? '')]
    )));

    foreach ($ids as $memberId) {
        if ($memberId === '') continue;
        $member = read_user($memberId);
        if (!$member || empty($member['isActive'])) continue;
        $public = public_user($member);
        $public['role'] = (($family['ownerUserId'] ?? '') === $memberId) ? 'owner' : 'member';
        $profile = profile_for_member($family, $memberId);
        $displayLabel = $profile['nickname'] !== '' ? $profile['nickname'] : (string)($public['displayName'] ?: $public['username']);
        $public['groupProfile'] = $profile;
        $public['displayLabel'] = $displayLabel;
        $public['joinedAt'] = joined_at_for_member($family, $member);
        $public['isDisabledInGroup'] = is_suspended_member($family, $memberId);
        $labelCounts[strtolower($displayLabel)] = ($labelCounts[strtolower($displayLabel)] ?? 0) + 1;
        $members[] = $public;
    }

    foreach ($members as &$member) {
        $member['hasDuplicateDisplayLabel'] = ($labelCounts[strtolower((string)$member['displayLabel'])] ?? 0) > 1;
    }
    unset($member);
    usort($members, fn($a, $b) => ((int)($a['isDisabledInGroup'] ?? false) <=> (int)($b['isDisabledInGroup'] ?? false)) ?: strcasecmp((string)$a['displayLabel'], (string)$b['displayLabel']));
    return $members;
}

function management_payload(array $user, array $family): array
{
    return [
        'csrfToken' => ensure_csrf_token(),
        'family' => public_family($family, true) + ['role' => family_member_role($family, $user)],
        'currentUserId' => $user['id'],
        'isOwner' => family_member_role($family, $user) === 'owner',
        'canLeave' => family_member_role($family, $user) !== 'owner',
        'members' => management_members($family),
    ];
}

function update_member_profile(array $user, array $family, array $input): void
{
    require_active_group_owner($user, $family);
    $memberId = safe_id(str_field($input, 'memberId', 80));
    if ($memberId === '') fail('Member ID is required.', 400);
    require_known_group_member($family, $memberId);

    $family['memberProfiles'] = is_array($family['memberProfiles'] ?? null) ? $family['memberProfiles'] : [];
    $family['memberProfiles'][$memberId] = [
        'nickname' => str_field($input, 'nickname', 80),
        'relationship' => str_field($input, 'relationship', 40),
        'color' => valid_member_color(str_field($input, 'color', 20)),
        'updatedAt' => now_iso(),
    ];
    $family['updatedAt'] = now_iso();
    write_family($family);
    audit_event('update_member_profile', ['userId' => $user['id'], 'memberId' => $memberId, 'familyId' => $family['id']]);
    ok(management_payload($user, $family) + ['message' => 'Member profile updated.']);
}

function remove_group_from_user(array $member, string $familyId): array
{
    $ids = array_values(array_filter(user_group_ids($member), fn($id) => $id !== $familyId));
    $member['groupIds'] = $ids;
    if (($member['familyId'] ?? '') === $familyId) $member['familyId'] = $ids[0] ?? '';
    if (($member['activeFamilyId'] ?? '') === $familyId) $member['activeFamilyId'] = $ids[0] ?? '';
    $member['updatedAt'] = now_iso();
    return $member;
}

function add_group_to_user(array $member, string $familyId): array
{
    $member = add_user_group_id($member, $familyId);
    if (empty($member['familyId'])) $member['familyId'] = $familyId;
    if (empty($member['activeFamilyId'])) $member['activeFamilyId'] = $familyId;
    $member['updatedAt'] = now_iso();
    return $member;
}

function disable_member_in_group(array $user, array $family, array $input): void
{
    require_active_group_owner($user, $family);
    $memberId = safe_id(str_field($input, 'memberId', 80));
    if ($memberId === '' || $memberId === (string)$user['id'] || $memberId === (string)($family['ownerUserId'] ?? '')) {
        fail('The active-group owner cannot be disabled.', 400);
    }
    $member = require_known_group_member($family, $memberId);
    $family['memberIds'] = array_values(array_filter($family['memberIds'] ?? [], fn($id) => (string)$id !== $memberId));
    $roles = is_array($family['memberRoles'] ?? null) ? $family['memberRoles'] : [];
    unset($roles[$memberId]);
    $family['memberRoles'] = $roles;
    $family['suspendedMemberIds'] = array_values(array_unique(array_merge(suspended_member_ids($family), [$memberId])));
    $family['updatedAt'] = now_iso();
    $member = remove_group_from_user($member, (string)$family['id']);
    write_family($family);
    write_user($member);
    $name = (string)($member['displayName'] ?? $member['username'] ?? 'A member');
    audit_event('disable_member_in_group', ['userId' => $user['id'], 'memberId' => $memberId, 'familyId' => $family['id']]);
    add_group_notice((string)$family['id'], 'member_disabled', $name . ' was temporarily disabled in ' . $family['name'] . '.', (string)$user['id']);
    ok(management_payload($user, $family) + ['message' => 'Member temporarily disabled.']);
}

function restore_member_to_group(array $user, array $family, array $input): void
{
    require_active_group_owner($user, $family);
    $memberId = safe_id(str_field($input, 'memberId', 80));
    if ($memberId === '' || !is_suspended_member($family, $memberId)) fail('Disabled member not found.', 404);
    $member = require_known_group_member($family, $memberId);
    $family['suspendedMemberIds'] = array_values(array_filter(suspended_member_ids($family), fn($id) => $id !== $memberId));
    $family['memberIds'] = array_values(array_unique(array_merge($family['memberIds'] ?? [], [$memberId])));
    $family['memberRoles'] = is_array($family['memberRoles'] ?? null) ? $family['memberRoles'] : [];
    $family['memberRoles'][$memberId] = 'member';
    $family['updatedAt'] = now_iso();
    $member = add_group_to_user($member, (string)$family['id']);
    write_family($family);
    write_user($member);
    $name = (string)($member['displayName'] ?? $member['username'] ?? 'A member');
    audit_event('restore_member_to_group', ['userId' => $user['id'], 'memberId' => $memberId, 'familyId' => $family['id']]);
    add_group_notice((string)$family['id'], 'member_restored', $name . ' was restored to ' . $family['name'] . '.', (string)$user['id']);
    ok(management_payload($user, $family) + ['message' => 'Member restored to active group.']);
}

function remove_member_from_group(array $user, array $family, array $input): void
{
    require_active_group_owner($user, $family);
    $memberId = safe_id(str_field($input, 'memberId', 80));
    if ($memberId === '' || $memberId === (string)$user['id']) fail('You cannot remove yourself from the active group here.', 400);
    $member = require_known_group_member($family, $memberId);
    $name = (string)($member['displayName'] ?? $member['username'] ?? 'A member');
    $familyId = (string)$family['id'];
    $family['memberIds'] = array_values(array_filter($family['memberIds'] ?? [], fn($id) => (string)$id !== $memberId));
    $family['suspendedMemberIds'] = array_values(array_filter(suspended_member_ids($family), fn($id) => $id !== $memberId));
    foreach (['memberRoles', 'memberProfiles', 'memberJoinedAt', 'memberCheckIns', 'memberTrips', 'memberLocationStates'] as $key) {
        $values = is_array($family[$key] ?? null) ? $family[$key] : [];
        unset($values[$memberId]);
        $family[$key] = $values;
    }
    $family['updatedAt'] = now_iso();
    $member = remove_group_from_user($member, $familyId);
    write_family($family);
    write_user($member);
    audit_event('remove_member_from_group', ['userId' => $user['id'], 'memberId' => $memberId, 'familyId' => $familyId]);
    add_group_notice($familyId, 'member_removed', $name . ' was removed from ' . $family['name'] . '.', (string)$user['id']);
    ok(management_payload($user, $family) + ['message' => 'Member removed from active group.']);
}

function group_member_count(array $family): int
{
    $ids = is_array($family['memberIds'] ?? null) ? $family['memberIds'] : [];
    return count(array_values(array_unique(array_filter(array_map('strval', $ids)))));
}

function remove_user_from_group_record(array $family, string $userId): array
{
    $family['memberIds'] = array_values(array_filter($family['memberIds'] ?? [], fn($id) => (string)$id !== $userId));
    $family['suspendedMemberIds'] = array_values(array_filter(suspended_member_ids($family), fn($id) => $id !== $userId));
    foreach (['memberRoles', 'memberProfiles', 'memberJoinedAt', 'memberCheckIns', 'memberTrips', 'memberLocationStates', 'geofenceStates'] as $key) {
        $values = is_array($family[$key] ?? null) ? $family[$key] : [];
        unset($values[$userId]);
        $family[$key] = $values;
    }
    $family['updatedAt'] = now_iso();
    return $family;
}

function delete_member_account(array $user, array $family, array $input): void
{
    require_active_group_owner($user, $family);
    $memberId = safe_id(str_field($input, 'memberId', 80));
    if ($memberId === '' || $memberId === (string)$user['id']) {
        fail('Use Delete My Account in Account settings to delete your own account.', 400);
    }
    $member = require_known_group_member($family, $memberId);
    $username = normalize_username((string)($member['username'] ?? ''));
    $confirmation = normalize_username(str_field($input, 'confirmation', 120));
    if ($username === '' || $confirmation === '' || !hash_equals($username, $confirmation)) {
        fail("Type the member's exact username to confirm permanent account deletion.", 400);
    }

    $blocking = [];
    foreach (user_group_ids($member) as $groupId) {
        $group = read_family($groupId);
        if (!$group) continue;
        if (family_member_role($group, $member) === 'owner' && group_member_count($group) > 1) {
            $blocking[] = (string)($group['name'] ?? 'Unnamed group');
        }
    }
    if ($blocking) {
        fail('This member owns other groups with other members. They must transfer ownership or delete these groups first: ' . implode(', ', $blocking) . '.', 409);
    }

    $displayName = (string)($member['displayName'] ?? $member['username'] ?? 'A member');
    $actorName = (string)($user['displayName'] ?? $user['username'] ?? 'An owner');
    foreach (user_group_ids($member) as $groupId) {
        $group = read_family($groupId);
        if (!$group) continue;
        if (family_member_role($group, $member) === 'owner') {
            delete_json_file(family_notices_path($groupId));
            delete_json_file(family_path($groupId));
            continue;
        }
        $group = remove_user_from_group_record($group, $memberId);
        write_family($group);
        add_group_notice($groupId, 'member_account_deleted', $displayName . '\'s account was permanently deleted by ' . $actorName . '.', (string)$user['id']);
    }

    if (is_suspended_member($family, $memberId) || in_array($memberId, $family['memberIds'] ?? [], true)) {
        $family = remove_user_from_group_record($family, $memberId);
        write_family($family);
    }

    foreach (list_json_records('persistent_logins') as $record) {
        if (($record['userId'] ?? '') !== $memberId) continue;
        $selector = safe_id((string)($record['selector'] ?? ''));
        if ($selector !== '') delete_json_file(remember_token_path($selector));
    }

    $indexPath = username_index_path();
    $index = read_json_file($indexPath, ['usernames' => []]);
    if ($username !== '' && ($index['usernames'][$username] ?? '') === $memberId) {
        unset($index['usernames'][$username]);
        write_json_file($indexPath, $index);
    }

    delete_json_file(location_path($memberId));
    delete_json_file(trail_path($memberId));
    delete_json_file(user_path($memberId));

    audit_event('delete_member_account', ['userId' => $user['id'], 'memberId' => $memberId]);

    $refreshedFamily = read_family((string)$family['id']);
    if (!$refreshedFamily) {
        ok(['csrfToken' => ensure_csrf_token(), 'message' => $displayName . '\'s account was permanently deleted.', 'reload' => true]);
    }
    ok(management_payload($user, $refreshedFamily) + ['message' => $displayName . '\'s account was permanently deleted.']);
}

function generate_temporary_password(): string
{
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789';
    $password = '';
    for ($i = 0; $i < 12; $i++) {
        $password .= $alphabet[random_int(0, strlen($alphabet) - 1)];
    }
    return $password;
}

function reset_member_password(array $user, array $family, array $input): void
{
    require_active_group_owner($user, $family);
    $memberId = safe_id(str_field($input, 'memberId', 80));
    if ($memberId === '' || $memberId === (string)$user['id']) {
        fail('Use Change Password in Account settings to reset your own password.', 400);
    }
    $member = require_known_group_member($family, $memberId);
    $temporaryPassword = generate_temporary_password();
    $member['passwordHash'] = password_hash($temporaryPassword, PASSWORD_DEFAULT);
    $member['passwordChangedAt'] = now_iso();
    $member['mustChangePassword'] = true;
    $member['updatedAt'] = now_iso();
    write_user($member);

    foreach (list_json_records('persistent_logins') as $record) {
        if (($record['userId'] ?? '') !== $memberId) continue;
        $selector = safe_id((string)($record['selector'] ?? ''));
        if ($selector !== '') delete_json_file(remember_token_path($selector));
    }

    $name = (string)($member['displayName'] ?? $member['username'] ?? 'A member');
    audit_event('reset_member_password', ['userId' => $user['id'], 'memberId' => $memberId, 'familyId' => $family['id']]);
    add_group_notice((string)$family['id'], 'member_password_reset', $name . '\'s password was reset by an owner.', (string)$user['id']);
    ok(management_payload($user, $family) + [
        'message' => 'Temporary password generated for ' . $name . '. Share it off-app; they must change it after logging in.',
        'temporaryPassword' => $temporaryPassword,
        'resetMemberId' => $memberId,
    ]);
}

function leave_active_group(array $user, array $family): void
{
    if (family_member_role($family, $user) === 'owner') fail('Transfer ownership or delete the group before leaving.', 400);
    $familyId = (string)$family['id'];
    $userId = (string)$user['id'];
    $family['memberIds'] = array_values(array_filter($family['memberIds'] ?? [], fn($id) => (string)$id !== $userId));
    foreach (['memberRoles', 'memberProfiles', 'memberJoinedAt', 'memberCheckIns', 'memberTrips', 'memberLocationStates'] as $key) {
        $values = is_array($family[$key] ?? null) ? $family[$key] : [];
        unset($values[$userId]);
        $family[$key] = $values;
    }
    $family['updatedAt'] = now_iso();
    $user = remove_group_from_user($user, $familyId);
    write_family($family);
    write_user($user);
    unset($_SESSION['active_family_id']);
    audit_event('leave_group', ['userId' => $userId, 'familyId' => $familyId]);
    add_group_notice($familyId, 'member_left', ($user['displayName'] ?? $user['username'] ?? 'A member') . ' left ' . $family['name'] . '.', $userId);
    ok(['csrfToken' => ensure_csrf_token(), 'message' => 'You left the group.', 'nextGroupId' => $user['activeFamilyId'] ?? '']);
}
