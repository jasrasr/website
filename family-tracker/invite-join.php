<?php
/**
 * Project: Family GPS Tracker
 * File: invite-join.php
 * Revision: 1.4.8
 * Description: Consumes expiring or limited-use invites for new or existing accounts.
 * Author: Jason Lamb / ChatGPT scaffold
 * Created: 2026-07-11
 * Modified: 2026-07-11
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/invite-store.php';
require_once __DIR__ . '/includes/notice-store.php';

init_app_storage();

try {
    $input = request_input();
    $action = str_field($input, 'action', 40);

    if ($action === 'existing_join') {
        $user = require_user();
        require_csrf();
        join_existing_account($user, $input);
    }

    if ($action === 'guest_join') {
        join_new_account($input);
    }

    fail('Unknown invite join action.', 404);
} catch (Throwable $ex) {
    error_log('Family Tracker invite-join error: ' . $ex->getMessage());
    fail('Server error. Check PHP error logs.', 500);
}

function apply_join_metadata(array $family, array $user, string $joinedAt): array
{
    $family = ensure_family_membership($family, $user, 'member');
    $userId = (string)$user['id'];
    $family['memberJoinedAt'] = is_array($family['memberJoinedAt'] ?? null) ? $family['memberJoinedAt'] : [];
    $family['memberJoinedAt'][$userId] = $family['memberJoinedAt'][$userId] ?? $joinedAt;
    $family['memberProfiles'] = is_array($family['memberProfiles'] ?? null) ? $family['memberProfiles'] : [];
    $family['memberProfiles'][$userId] = is_array($family['memberProfiles'][$userId] ?? null)
        ? $family['memberProfiles'][$userId]
        : ['nickname' => '', 'relationship' => '', 'color' => ''];
    return $family;
}

function join_existing_account(array $user, array $input): void
{
    $inviteCode = str_field($input, 'inviteCode', 40);
    $family = consume_group_invite_code($inviteCode);
    if (!$family) {
        fail('Invite is invalid, expired, revoked, or fully used.', 404);
    }

    if (family_member_role($family, $user) !== null) {
        $user = set_active_family_for_user($user, (string)$family['id']);
        ok(build_me_payload($user) + ['message' => 'You already belong to this group. It is now active.']);
    }

    $joinedAt = now_iso();
    $family = apply_join_metadata($family, $user, $joinedAt);
    write_family($family);

    $user = add_user_group_id($user, (string)$family['id']);
    $user['familyId'] = (string)$family['id'];
    $user['activeFamilyId'] = (string)$family['id'];
    $user['role'] = 'member';
    $user['updatedAt'] = $joinedAt;
    write_user($user);
    $_SESSION['active_family_id'] = (string)$family['id'];

    audit_event('join_existing_group_invite', ['userId' => $user['id'], 'familyId' => $family['id']]);
    add_group_notice((string)$family['id'], 'member_joined_group', $user['displayName'] . ' joined ' . $family['name'] . '.', (string)$user['id']);
    ok(build_me_payload($user) + ['message' => 'Joined and switched to group.']);
}

function join_new_account(array $input): void
{
    $displayName = str_field($input, 'displayName', 80);
    $username = normalize_username(str_field($input, 'username', 120));
    $password = (string)($input['password'] ?? '');
    $inviteCode = str_field($input, 'inviteCode', 40);
    $consent = bool_field($input, 'consentAccepted');
    $rememberMe = bool_field($input, 'rememberMe');

    if ($displayName === '') fail('Display name is required.', 400);
    validate_username_or_fail($username);
    validate_password_or_fail($password);
    if (!$consent) fail('Consent is required.', 400);

    $result = with_named_lock('registration', function () use ($displayName, $username, $password, $inviteCode): array {
        $family = consume_group_invite_code($inviteCode);
        if (!$family) fail('Invite is invalid, expired, revoked, or fully used.', 404);

        $indexPath = username_index_path();
        $index = read_json_file($indexPath, ['usernames' => []]);
        if (!empty($index['usernames'][$username])) fail('Username already exists.', 409);

        $createdAt = now_iso();
        $userId = new_id('usr');
        $user = [
            'id' => $userId,
            'displayName' => $displayName,
            'username' => $username,
            'passwordHash' => password_hash($password, PASSWORD_DEFAULT),
            'familyId' => $family['id'],
            'activeFamilyId' => $family['id'],
            'groupIds' => [$family['id']],
            'role' => 'member',
            'isActive' => true,
            'consentAcceptedAt' => $createdAt,
            'createdAt' => $createdAt,
            'lastLoginAt' => $createdAt,
        ];

        $family = apply_join_metadata($family, $user, $createdAt);
        write_family($family);
        write_user($user);
        $index['usernames'][$username] = $userId;
        write_json_file($indexPath, $index);

        audit_event('join_family_invite', ['userId' => $userId, 'familyId' => $family['id']]);
        add_group_notice((string)$family['id'], 'member_joined_group', $displayName . ' joined ' . $family['name'] . '.', $userId);
        return $user;
    });

    start_authenticated_session($result, $rememberMe);
    ok(build_me_payload($result) + ['message' => 'Joined group.']);
}
