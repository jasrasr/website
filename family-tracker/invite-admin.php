<?php
/**
 * Project: Family GPS Tracker
 * File: invite-admin.php
 * Revision: 1.4.8
 * Description: Owner-only API for creating, listing, and revoking expiring or limited-use group invites.
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
    $user = require_user();
    $family = current_family_for_user($user);
    if (!$family || family_member_role($family, $user) !== 'owner') {
        fail('Owner permission required for the active group.', 403);
    }

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
        ok(invite_admin_payload($family));
    }

    require_csrf();
    $input = request_input();
    $action = str_field($input, 'action', 40);

    if ($action === 'create_invite') {
        $label = str_field($input, 'label', 80);
        $expiry = str_field($input, 'expiry', 20);
        $maxUses = max(0, min(100, (int)($input['maxUses'] ?? 0)));
        $seconds = match ($expiry) {
            '1h' => 3600,
            '24h' => 86400,
            '7d' => 604800,
            default => 0,
        };
        [$family, $invite, $code] = create_group_invite($family, $user, $label, $seconds, $maxUses);
        audit_event('create_group_invite', ['userId' => $user['id'], 'familyId' => $family['id'], 'inviteId' => $invite['id']]);
        add_group_notice((string)$family['id'], 'invite_created', $user['displayName'] . ' created a group invite.', (string)$user['id']);
        ok(invite_admin_payload($family) + ['oneTimeInviteCode' => $code, 'message' => 'Invite created. Copy the full code now.']);
    }

    if ($action === 'revoke_invite') {
        $inviteId = str_field($input, 'inviteId', 80);
        $family = revoke_group_invite($family, $inviteId);
        audit_event('revoke_group_invite', ['userId' => $user['id'], 'familyId' => $family['id'], 'inviteId' => $inviteId]);
        add_group_notice((string)$family['id'], 'invite_revoked', $user['displayName'] . ' revoked a group invite.', (string)$user['id']);
        ok(invite_admin_payload($family) + ['message' => 'Invite revoked.']);
    }

    fail('Unknown invite action.', 404);
} catch (Throwable $ex) {
    error_log('Family Tracker invite-admin error: ' . $ex->getMessage());
    fail('Server error. Check PHP error logs.', 500);
}

function invite_admin_payload(array $family): array
{
    $invites = array_map('public_invite_record', invite_records($family));
    usort($invites, fn($a, $b) => strcmp((string)($b['createdAt'] ?? ''), (string)($a['createdAt'] ?? '')));
    return [
        'csrfToken' => ensure_csrf_token(),
        'family' => public_family($family, true),
        'invites' => $invites,
    ];
}
