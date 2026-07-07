<?php
/**
 * Project: Family GPS Tracker
 * File: notices.php
 * Revision: 1.2.1
 * Description: Server-stored family notices with per-user dismissal state.
 * Author: Jason Lamb / ChatGPT scaffold
 * Created: 2026-07-06
 * Modified: 2026-07-06
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/security.php';

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

function notice_state_path_for_user(array $user): string
{
    return family_notices_path((string)$user['familyId']);
}

function notice_id_for_member(array $member): string
{
    return 'member_joined_' . safe_id((string)($member['id'] ?? ''));
}

function read_notice_state(array $user): array
{
    return read_json_file(notice_state_path_for_user($user), ['dismissedBy' => []]);
}

function write_notice_state(array $user, array $state): void
{
    write_json_file(notice_state_path_for_user($user), $state);
}

function user_created_time(array $user): int
{
    $created = isset($user['createdAt']) ? strtotime((string)$user['createdAt']) : false;
    return $created ?: 0;
}

function unread_family_notices(array $user): array
{
    $familyId = (string)$user['familyId'];
    $currentUserId = (string)$user['id'];
    $currentUserCreated = user_created_time($user);
    $state = read_notice_state($user);
    $dismissed = $state['dismissedBy'][$currentUserId] ?? [];
    $dismissed = is_array($dismissed) ? array_flip($dismissed) : [];
    $notices = [];

    foreach (list_json_records('users') as $member) {
        if (($member['familyId'] ?? '') !== $familyId || empty($member['isActive'])) {
            continue;
        }
        if ((string)($member['id'] ?? '') === $currentUserId) {
            continue;
        }
        $joinedAt = user_created_time($member);
        if ($joinedAt <= $currentUserCreated) {
            continue;
        }

        $noticeId = notice_id_for_member($member);
        if (isset($dismissed[$noticeId])) {
            continue;
        }

        $displayName = (string)($member['displayName'] ?? $member['username'] ?? 'A family member');
        $notices[] = [
            'id' => $noticeId,
            'type' => 'member_joined',
            'message' => $displayName . ' joined the family tracker.',
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

    with_named_lock('notices_' . (string)$user['familyId'], function () use ($user, $noticeId): void {
        $state = read_notice_state($user);
        $userId = (string)$user['id'];
        $state['dismissedBy'] = is_array($state['dismissedBy'] ?? null) ? $state['dismissedBy'] : [];
        $state['dismissedBy'][$userId] = is_array($state['dismissedBy'][$userId] ?? null) ? $state['dismissedBy'][$userId] : [];
        if (!in_array($noticeId, $state['dismissedBy'][$userId], true)) {
            $state['dismissedBy'][$userId][] = $noticeId;
        }
        $state['updatedAt'] = now_iso();
        write_notice_state($user, $state);
    });
}
