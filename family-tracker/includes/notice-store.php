<?php
/**
 * Project: Family GPS Tracker
 * File: includes/notice-store.php
 * Revision: 1.4.3
 * Description: Shared server-stored group notice helpers.
 * Author: Jason Lamb / ChatGPT scaffold
 * Created: 2026-07-09
 * Modified: 2026-07-09
 */

declare(strict_types=1);

require_once __DIR__ . '/json-store.php';

function group_notice_state_path(string $familyId): string
{
    return family_notices_path($familyId);
}

function read_group_notice_state(string $familyId): array
{
    $state = read_json_file(group_notice_state_path($familyId), ['notices' => [], 'dismissedBy' => []]);
    $state['notices'] = is_array($state['notices'] ?? null) ? $state['notices'] : [];
    $state['dismissedBy'] = is_array($state['dismissedBy'] ?? null) ? $state['dismissedBy'] : [];
    return $state;
}

function write_group_notice_state(string $familyId, array $state): void
{
    write_json_file(group_notice_state_path($familyId), $state);
}

function add_group_notice(string $familyId, string $type, string $message, ?string $actorUserId = null): void
{
    $familyId = safe_id($familyId);
    if ($familyId === '' || trim($message) === '') {
        return;
    }

    with_named_lock('notices_' . $familyId, function () use ($familyId, $type, $message, $actorUserId): void {
        $state = read_group_notice_state($familyId);
        array_unshift($state['notices'], [
            'id' => 'notice_' . bin2hex(random_bytes(10)),
            'type' => safe_id($type) ?: 'group_notice',
            'message' => substr(trim($message), 0, 240),
            'actorUserId' => $actorUserId ? safe_id($actorUserId) : null,
            'createdAt' => now_iso(),
        ]);
        $state['notices'] = array_slice($state['notices'], 0, 100);
        $state['updatedAt'] = now_iso();
        write_group_notice_state($familyId, $state);
    });
}

function dismissed_notice_lookup(array $state, string $userId): array
{
    $dismissed = $state['dismissedBy'][$userId] ?? [];
    return is_array($dismissed) ? array_flip($dismissed) : [];
}

function dismiss_group_notice_for_user(string $familyId, string $userId, string $noticeId): void
{
    $familyId = safe_id($familyId);
    $userId = safe_id($userId);
    $noticeId = safe_id($noticeId);
    if ($familyId === '' || $userId === '' || $noticeId === '') {
        return;
    }

    with_named_lock('notices_' . $familyId, function () use ($familyId, $userId, $noticeId): void {
        $state = read_group_notice_state($familyId);
        $state['dismissedBy'][$userId] = is_array($state['dismissedBy'][$userId] ?? null) ? $state['dismissedBy'][$userId] : [];
        if (!in_array($noticeId, $state['dismissedBy'][$userId], true)) {
            $state['dismissedBy'][$userId][] = $noticeId;
        }
        $state['updatedAt'] = now_iso();
        write_group_notice_state($familyId, $state);
    });
}
