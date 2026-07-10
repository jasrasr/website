<?php
/**
 * Project: Family GPS Tracker
 * File: notices.php
 * Revision: 1.4.3
 * Description: Server-stored active-group notices with per-user dismissal state.
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
    $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

    if ($method === 'POST') {
        require_csrf();
        dismiss_notice($user, request_input());
    }

    ok(['notices' => unread_family_notices($user)]);
} catch (Throwable $ex) {
    error_log('Family Tracker notices error: ' . $ex->getMessage());
    fail('Server error. Check PHP error logs.', 500);
}

function active_notice_family(array $user): array
{
    $family = current_family_for_user($user);
    if (!$family) {
        fail('Active group not found.', 404);
    }
    return $family;
}

function notice_id_for_member(array $member, string $familyId): string
{
    return 'member_joined_' . safe_id($familyId) . '_' . safe_id((string)($member['id'] ?? ''));
}

function user_created_time(array $user): int
{
    $created = isset($user['createdAt']) ? strtotime((string)$user['createdAt']) : false;
    return $created ?: 0;
}

function unread_family_notices(array $user): array
{
    $family = active_notice_family($user);
    $familyId = (string)$family['id'];
    $currentUserId = (string)$user['id'];
    $currentUserCreated = user_created_time($user);
    $state = read_group_notice_state($familyId);
    $dismissed = dismissed_notice_lookup($state, $currentUserId);
    $notices = [];

    foreach ($state['notices'] as $notice) {
        $noticeId = safe_id((string)($notice['id'] ?? ''));
        if ($noticeId === '' || isset($dismissed[$noticeId])) {
            continue;
        }
        if (($notice['actorUserId'] ?? '') === $currentUserId) {
            continue;
        }
        $notices[] = [
            'id' => $noticeId,
            'type' => $notice['type'] ?? 'group_notice',
            'message' => $notice['message'] ?? 'Group notice.',
            'createdAt' => $notice['createdAt'] ?? null,
        ];
    }

    foreach (list_json_records('users') as $member) {
        if (empty($member['isActive']) || family_member_role($family, $member) === null) {
            continue;
        }
        if ((string)($member['id'] ?? '') === $currentUserId) {
            continue;
        }
        $joinedAt = user_created_time($member);
        if ($joinedAt <= $currentUserCreated) {
            continue;
        }

        $noticeId = notice_id_for_member($member, $familyId);
        if (isset($dismissed[$noticeId])) {
            continue;
        }

        $displayName = (string)($member['displayName'] ?? $member['username'] ?? 'A group member');
        $notices[] = [
            'id' => $noticeId,
            'type' => 'member_joined',
            'message' => $displayName . ' joined this group.',
            'displayName' => $displayName,
            'createdAt' => $member['createdAt'] ?? null,
        ];
    }

    usort($notices, fn($a, $b) => strcmp((string)($b['createdAt'] ?? ''), (string)($a['createdAt'] ?? '')));
    return array_slice($notices, 0, MAX_FAMILY_NOTICES);
}

function dismiss_notice(array $user, array $input): void
{
    $noticeId = safe_id(str_field($input, 'noticeId', 120));
    if ($noticeId === '') {
        fail('Notice ID is required.', 400);
    }

    $family = active_notice_family($user);
    dismiss_group_notice_for_user((string)$family['id'], (string)$user['id'], $noticeId);
}
