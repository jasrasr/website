<?php
/**
 * Project: Family GPS Tracker
 * File: groups.php
 * Revision: 1.4.3
 * Description: JSON endpoint for multi-circle/group creation, joining, renaming, listing, switching, and group notices.
 * Author: Jason Lamb / ChatGPT scaffold
 * Created: 2026-07-06
 * Modified: 2026-07-09
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/notice-store.php';

init_app_storage();

try {
    $user = require_user();
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
        ok(groups_payload($user));
    }

    require_csrf();
    $input = request_input();
    $action = str_field($input, 'action', 40);

    switch ($action) {
        case 'create_group':
            handle_create_group($user, $input);
            break;
        case 'join_group':
            handle_join_existing_group($user, $input);
            break;
        case 'switch_group':
            handle_switch_group($user, $input);
            break;
        case 'rename_group':
            handle_rename_group($user, $input);
            break;
        default:
            fail('Unknown group action.', 404);
    }
} catch (Throwable $ex) {
    error_log('Family Tracker groups error: ' . $ex->getMessage());
    fail('Server error. Check PHP error logs.', 500);
}

function groups_payload(array $user, array $extra = []): array
{
    $user = normalize_group_memberships($user);
    $activeId = active_family_id_for_user($user);
    $groups = [];

    foreach (list_json_records('families') as $family) {
        $role = family_member_role($family, $user);
        if ($role === null) {
            continue;
        }
        $public = public_family($family, true);
        $public['role'] = $role;
        $public['isActive'] = ((string)$family['id'] === $activeId);
        $groups[] = $public;
    }

    usort($groups, function ($a, $b) {
        if (($a['isActive'] ?? false) !== ($b['isActive'] ?? false)) {
            return ($a['isActive'] ?? false) ? -1 : 1;
        }
        return strcasecmp((string)($a['name'] ?? ''), (string)($b['name'] ?? ''));
    });

    return [
        'csrfToken' => ensure_csrf_token(),
        'user' => public_user($user),
        'activeGroupId' => $activeId,
        'groups' => $groups,
    ] + $extra;
}

function normalize_group_memberships(array $user): array
{
    $changed = false;
    $ids = user_group_ids($user);

    foreach (list_json_records('families') as $family) {
        if (family_member_role($family, $user) !== null) {
            $ids[] = (string)$family['id'];
        }
    }

    $ids = array_values(array_unique(array_filter(array_map('safe_id', $ids))));
    if (($user['groupIds'] ?? []) !== $ids) {
        $user['groupIds'] = $ids;
        $changed = true;
    }
    if (empty($user['familyId']) && $ids) {
        $user['familyId'] = $ids[0];
        $user['activeFamilyId'] = $ids[0];
        $changed = true;
    }
    if ($changed) {
        write_user($user);
    }
    return $user;
}

function handle_create_group(array $user, array $input): void
{
    $name = str_field($input, 'groupName', 80);
    if ($name === '') {
        fail('Group name is required.', 400);
    }

    $result = with_named_lock('groups_' . $user['id'], function () use ($user, $name): array {
        $latestUser = read_user((string)$user['id']) ?: $user;
        $familyId = new_id('fam');
        $inviteCode = generate_invite_code();
        $inviteNormalized = normalize_invite_code($inviteCode);
        $createdAt = now_iso();

        $family = [
            'id' => $familyId,
            'name' => $name,
            'type' => 'group',
            'ownerUserId' => $latestUser['id'],
            'memberIds' => [$latestUser['id']],
            'memberRoles' => [$latestUser['id'] => 'owner'],
            'inviteCodeHash' => password_hash($inviteNormalized, PASSWORD_DEFAULT),
            'inviteCodeLast4' => substr($inviteNormalized, -4),
            'inviteCodeCreatedAt' => $createdAt,
            'createdAt' => $createdAt,
            'updatedAt' => $createdAt,
        ];

        $latestUser = add_user_group_id($latestUser, $familyId);
        $latestUser['familyId'] = $familyId;
        $latestUser['activeFamilyId'] = $familyId;
        $latestUser['role'] = 'owner';
        $latestUser['updatedAt'] = $createdAt;

        write_family($family);
        write_user($latestUser);
        $_SESSION['active_family_id'] = $familyId;

        audit_event('create_group', ['userId' => $latestUser['id'], 'familyId' => $familyId]);
        add_group_notice($familyId, 'group_created', $latestUser['displayName'] . ' created the group ' . $name . '.', (string)$latestUser['id']);
        return [$latestUser, $inviteCode];
    });

    [$updatedUser, $inviteCode] = $result;
    ok(groups_payload($updatedUser, [
        'oneTimeInviteCode' => $inviteCode,
        'message' => 'Group created. Save or copy the invite code now.',
    ]));
}

function handle_join_existing_group(array $user, array $input): void
{
    $inviteCode = str_field($input, 'inviteCode', 40);
    if ($inviteCode === '') {
        fail('Invite code is required.', 400);
    }

    $updatedUser = with_named_lock('groups_' . $user['id'], function () use ($user, $inviteCode): array {
        $family = find_family_by_invite_code($inviteCode);
        if (!$family) {
            fail('Invite code not found.', 404);
        }

        $latestUser = read_user((string)$user['id']) ?: $user;
        $family = ensure_family_membership($family, $latestUser, 'member');
        write_family($family);

        $latestUser = add_user_group_id($latestUser, (string)$family['id']);
        $latestUser['familyId'] = (string)$family['id'];
        $latestUser['activeFamilyId'] = (string)$family['id'];
        $latestUser['role'] = family_member_role($family, $latestUser) ?? 'member';
        $latestUser['updatedAt'] = now_iso();
        write_user($latestUser);
        $_SESSION['active_family_id'] = (string)$family['id'];

        audit_event('join_existing_group', ['userId' => $latestUser['id'], 'familyId' => $family['id']]);
        add_group_notice((string)$family['id'], 'member_joined_group', $latestUser['displayName'] . ' joined ' . $family['name'] . '.', (string)$latestUser['id']);
        return $latestUser;
    });

    ok(groups_payload($updatedUser, ['message' => 'Joined and switched to group.']));
}

function handle_switch_group(array $user, array $input): void
{
    $groupId = str_field($input, 'groupId', 80);
    if ($groupId === '') {
        fail('Group ID is required.', 400);
    }
    $updatedUser = set_active_family_for_user($user, $groupId);
    audit_event('switch_group', ['userId' => $updatedUser['id'], 'familyId' => $groupId]);
    ok(groups_payload($updatedUser, ['message' => 'Active group switched.']));
}

function handle_rename_group(array $user, array $input): void
{
    $groupId = str_field($input, 'groupId', 80);
    $name = str_field($input, 'groupName', 80);
    if ($groupId === '' || $name === '') {
        fail('Group ID and group name are required.', 400);
    }

    $updatedUser = read_user((string)$user['id']) ?: $user;
    $family = read_family($groupId);
    if (!$family || family_member_role($family, $updatedUser) !== 'owner') {
        fail('Owner permission required for that group.', 403);
    }

    $oldName = (string)($family['name'] ?? 'group');
    $family['name'] = $name;
    $family['updatedAt'] = now_iso();
    write_family($family);
    audit_event('rename_group', ['userId' => $updatedUser['id'], 'familyId' => $groupId]);
    add_group_notice($groupId, 'group_renamed', $updatedUser['displayName'] . ' renamed ' . $oldName . ' to ' . $name . '.', (string)$updatedUser['id']);

    ok(groups_payload($updatedUser, ['message' => 'Group name updated.']));
}
