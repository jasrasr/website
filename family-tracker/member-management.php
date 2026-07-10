<?php
/**
 * Project: Family GPS Tracker
 * File: member-management.php
 * Revision: 1.4.4
 * Description: Owner-only active-group member metadata and group-removal endpoint.
 * Author: Jason Lamb / ChatGPT scaffold
 * Created: 2026-07-09
 * Modified: 2026-07-09
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
        case 'remove_member':
            remove_member_from_group($user, $family, $input);
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

function management_members(array $family): array
{
    $members = [];
    $labelCounts = [];
    foreach (list_json_records('users') as $member) {
        if (empty($member['isActive']) || family_member_role($family, $member) === null) {
            continue;
        }
        $public = public_user_for_family($member, $family);
        $profile = profile_for_member($family, (string)$member['id']);
        $displayLabel = $profile['nickname'] !== '' ? $profile['nickname'] : (string)($public['displayName'] ?: $public['username']);
        $public['groupProfile'] = $profile;
        $public['displayLabel'] = $displayLabel;
        $public['joinedAt'] = joined_at_for_member($family, $member);
        $labelCounts[strtolower($displayLabel)] = ($labelCounts[strtolower($displayLabel)] ?? 0) + 1;
        $members[] = $public;
    }
    foreach ($members as &$member) {
        $member['hasDuplicateDisplayLabel'] = ($labelCounts[strtolower((string)$member['displayLabel'])] ?? 0) > 1;
    }
    unset($member);
    usort($members, fn($a, $b) => strcasecmp((string)$a['displayLabel'], (string)$b['displayLabel']));
    return $members;
}

function management_payload(array $user, array $family): array
{
    return [
        'csrfToken' => ensure_csrf_token(),
        'family' => public_family($family, true) + ['role' => family_member_role($family, $user)],
        'currentUserId' => $user['id'],
        'isOwner' => family_member_role($family, $user) === 'owner',
        'members' => management_members($family),
    ];
}

function update_member_profile(array $user, array $family, array $input): void
{
    require_active_group_owner($user, $family);
    $memberId = safe_id(str_field($input, 'memberId', 80));
    if ($memberId === '' || family_member_role($family, ['id' => $memberId, 'groupIds' => [(string)$family['id']]]) === null) {
        fail('Member not found in active group.', 404);
    }

    $nickname = str_field($input, 'nickname', 80);
    $relationship = str_field($input, 'relationship', 40);
    $color = valid_member_color(str_field($input, 'color', 20));

    $family['memberProfiles'] = is_array($family['memberProfiles'] ?? null) ? $family['memberProfiles'] : [];
    $family['memberProfiles'][$memberId] = [
        'nickname' => $nickname,
        'relationship' => $relationship,
        'color' => $color,
        'updatedAt' => now_iso(),
    ];
    $family['updatedAt'] = now_iso();
    write_family($family);

    audit_event('update_member_profile', ['userId' => $user['id'], 'memberId' => $memberId, 'familyId' => $family['id']]);
    add_group_notice((string)$family['id'], 'member_profile_updated', $user['displayName'] . ' updated a member profile in ' . $family['name'] . '.', (string)$user['id']);
    ok(management_payload($user, $family) + ['message' => 'Member profile updated.']);
}

function remove_group_from_user(array $member, string $familyId): array
{
    $ids = array_values(array_filter(user_group_ids($member), fn($id) => $id !== $familyId));
    $member['groupIds'] = $ids;
    if (($member['familyId'] ?? '') === $familyId) {
        $member['familyId'] = $ids[0] ?? '';
    }
    if (($member['activeFamilyId'] ?? '') === $familyId) {
        $member['activeFamilyId'] = $ids[0] ?? '';
    }
    if (($_SESSION['active_family_id'] ?? '') === $familyId && ($member['id'] ?? '') === ($_SESSION['user_id'] ?? '')) {
        $_SESSION['active_family_id'] = $member['activeFamilyId'] ?? '';
    }
    $member['updatedAt'] = now_iso();
    return $member;
}

function remove_member_from_group(array $user, array $family, array $input): void
{
    require_active_group_owner($user, $family);
    $memberId = safe_id(str_field($input, 'memberId', 80));
    if ($memberId === '' || $memberId === (string)$user['id']) {
        fail('You cannot remove yourself from the active group here.', 400);
    }
    $member = read_user($memberId);
    if (!$member || family_member_role($family, $member) === null) {
        fail('Member not found in active group.', 404);
    }

    $displayName = (string)($member['displayName'] ?? $member['username'] ?? 'A member');
    $familyId = (string)$family['id'];
    $family['memberIds'] = array_values(array_filter($family['memberIds'] ?? [], fn($id) => (string)$id !== $memberId));
    $roles = is_array($family['memberRoles'] ?? null) ? $family['memberRoles'] : [];
    unset($roles[$memberId]);
    $family['memberRoles'] = $roles;
    $profiles = is_array($family['memberProfiles'] ?? null) ? $family['memberProfiles'] : [];
    unset($profiles[$memberId]);
    $family['memberProfiles'] = $profiles;
    $joined = is_array($family['memberJoinedAt'] ?? null) ? $family['memberJoinedAt'] : [];
    unset($joined[$memberId]);
    $family['memberJoinedAt'] = $joined;
    $family['updatedAt'] = now_iso();

    $member = remove_group_from_user($member, $familyId);
    write_family($family);
    write_user($member);

    audit_event('remove_member_from_group', ['userId' => $user['id'], 'memberId' => $memberId, 'familyId' => $familyId]);
    add_group_notice($familyId, 'member_removed', $displayName . ' was removed from ' . $family['name'] . '.', (string)$user['id']);
    ok(management_payload($user, $family) + ['message' => 'Member removed from active group.']);
}
