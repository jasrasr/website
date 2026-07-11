<?php
/**
 * Project: Family GPS Tracker
 * File: owner-admin.php
 * Revision: 1.4.7
 * Description: Owner-only active-group administration API for settings, ownership transfer, audit history, activity, and export.
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
    $family = current_family_for_user($user);
    if (!$family) {
        fail('Active group not found.', 404);
    }
    if (family_member_role($family, $user) !== 'owner') {
        fail('Owner permission required for the active group.', 403);
    }

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
        $action = is_string($_GET['action'] ?? null) ? trim((string)$_GET['action']) : '';
        if ($action === 'export_group') {
            ok(['export' => group_export($family)]);
        }
        ok(owner_payload($user, $family));
    }

    require_csrf();
    $input = request_input();
    $action = str_field($input, 'action', 60);

    switch ($action) {
        case 'update_group_settings':
            update_group_settings($user, $family, $input);
            break;
        case 'transfer_ownership':
            transfer_group_ownership($user, $family, $input);
            break;
        default:
            fail('Unknown owner administration action.', 404);
    }
} catch (Throwable $ex) {
    error_log('Family Tracker owner-admin error: ' . $ex->getMessage());
    fail('Server error. Check PHP error logs.', 500);
}

function owner_members(array $family): array
{
    $members = [];
    foreach (list_json_records('users') as $member) {
        if (empty($member['isActive']) || family_member_role($family, $member) === null) {
            continue;
        }
        $public = public_user_for_family($member, $family);
        $profiles = is_array($family['memberProfiles'] ?? null) ? $family['memberProfiles'] : [];
        $profile = is_array($profiles[$member['id']] ?? null) ? $profiles[$member['id']] : [];
        $public['displayLabel'] = trim((string)($profile['nickname'] ?? '')) ?: ($public['displayName'] ?: $public['username']);
        $public['joinedAt'] = ($family['memberJoinedAt'][$member['id']] ?? null) ?: ($member['createdAt'] ?? null);
        $members[] = $public;
    }
    usort($members, fn($a, $b) => strcasecmp((string)$a['displayLabel'], (string)$b['displayLabel']));
    return $members;
}

function audit_events_for_family(string $familyId, int $limit = 100): array
{
    $events = [];
    $files = glob(DATA_DIR . '/audit/*.json') ?: [];
    rsort($files, SORT_STRING);
    foreach ($files as $path) {
        $log = read_json_file($path, ['events' => []]);
        foreach (array_reverse($log['events'] ?? []) as $entry) {
            $data = is_array($entry['data'] ?? null) ? $entry['data'] : [];
            if (($data['familyId'] ?? '') !== $familyId) {
                continue;
            }
            $events[] = [
                'time' => $entry['time'] ?? null,
                'event' => $entry['event'] ?? 'unknown',
                'data' => $data,
            ];
            if (count($events) >= $limit) {
                return $events;
            }
        }
    }
    return $events;
}

function activity_for_family(array $family, int $limit = 50): array
{
    $familyId = (string)$family['id'];
    $state = read_group_notice_state($familyId);
    $items = [];
    foreach ($state['notices'] ?? [] as $notice) {
        $items[] = [
            'time' => $notice['createdAt'] ?? null,
            'type' => $notice['type'] ?? 'notice',
            'message' => $notice['message'] ?? 'Group activity.',
        ];
    }
    usort($items, fn($a, $b) => strcmp((string)($b['time'] ?? ''), (string)($a['time'] ?? '')));
    return array_slice($items, 0, $limit);
}

function owner_payload(array $user, array $family): array
{
    return [
        'csrfToken' => ensure_csrf_token(),
        'family' => public_family($family, true) + [
            'role' => 'owner',
            'description' => (string)($family['description'] ?? ''),
            'color' => (string)($family['color'] ?? '#4ADE80'),
        ],
        'currentUserId' => $user['id'],
        'members' => owner_members($family),
        'activity' => activity_for_family($family),
        'audit' => audit_events_for_family((string)$family['id']),
    ];
}

function valid_group_color(string $color): string
{
    $color = strtoupper(trim($color));
    return preg_match('/^#[0-9A-F]{6}$/', $color) ? $color : '#4ADE80';
}

function update_group_settings(array $user, array $family, array $input): void
{
    $name = str_field($input, 'name', 80);
    $description = str_field($input, 'description', 240);
    $color = valid_group_color(str_field($input, 'color', 20));
    if ($name === '') {
        fail('Group name is required.', 400);
    }

    $oldName = (string)($family['name'] ?? 'Group');
    $family['name'] = $name;
    $family['description'] = $description;
    $family['color'] = $color;
    $family['updatedAt'] = now_iso();
    write_family($family);

    audit_event('update_group_settings', ['userId' => $user['id'], 'familyId' => $family['id']]);
    add_group_notice((string)$family['id'], 'group_settings_updated', $user['displayName'] . ' updated settings for ' . $oldName . '.', (string)$user['id']);
    ok(owner_payload($user, $family) + ['message' => 'Group settings updated.']);
}

function transfer_group_ownership(array $user, array $family, array $input): void
{
    $newOwnerId = safe_id(str_field($input, 'memberId', 80));
    if ($newOwnerId === '' || $newOwnerId === (string)$user['id']) {
        fail('Choose another active group member.', 400);
    }
    $newOwner = read_user($newOwnerId);
    if (!$newOwner || family_member_role($family, $newOwner) === null) {
        fail('Selected member is not in the active group.', 404);
    }

    $oldOwnerId = (string)$user['id'];
    $family['ownerUserId'] = $newOwnerId;
    $roles = is_array($family['memberRoles'] ?? null) ? $family['memberRoles'] : [];
    $roles[$oldOwnerId] = 'member';
    $roles[$newOwnerId] = 'owner';
    $family['memberRoles'] = $roles;
    $family['updatedAt'] = now_iso();
    write_family($family);

    if (($user['familyId'] ?? '') === ($family['id'] ?? '')) {
        $user['role'] = 'member';
        write_user($user);
    }
    if (($newOwner['familyId'] ?? '') === ($family['id'] ?? '')) {
        $newOwner['role'] = 'owner';
        write_user($newOwner);
    }

    $newLabel = (string)($newOwner['displayName'] ?? $newOwner['username'] ?? 'A member');
    audit_event('transfer_group_ownership', ['userId' => $oldOwnerId, 'memberId' => $newOwnerId, 'familyId' => $family['id']]);
    add_group_notice((string)$family['id'], 'ownership_transferred', $user['displayName'] . ' transferred ownership of ' . $family['name'] . ' to ' . $newLabel . '.', $oldOwnerId);
    ok(['message' => 'Ownership transferred. Reloading the app is recommended.']);
}

function group_export(array $family): array
{
    $members = owner_members($family);
    $memberIds = array_map(fn($m) => (string)$m['id'], $members);
    $locations = [];
    $trails = [];
    foreach ($memberIds as $memberId) {
        $location = read_json_file(location_path($memberId), []);
        if (($location['familyId'] ?? '') === ($family['id'] ?? '')) {
            $locations[$memberId] = $location;
        }
        $trail = read_json_file(trail_path($memberId), ['points' => []]);
        if (($trail['familyId'] ?? '') === ($family['id'] ?? '')) {
            $trails[$memberId] = $trail;
        }
    }
    return [
        'exportedAt' => now_iso(),
        'appRevision' => APP_REVISION,
        'family' => $family,
        'members' => $members,
        'locations' => $locations,
        'trails' => $trails,
        'activity' => activity_for_family($family, 100),
        'audit' => audit_events_for_family((string)$family['id'], 250),
    ];
}
