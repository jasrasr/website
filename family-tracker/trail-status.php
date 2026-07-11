<?php
/**
 * Project: Family GPS Tracker
 * File: trail-status.php
 * Revision: 1.5.1
 * Description: Active-group trail retention, cleanup, and live/stale transition monitoring.
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

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
        ok(status_payload($user, $family));
    }

    require_csrf();
    $input = request_input();
    $action = str_field($input, 'action', 50);

    switch ($action) {
        case 'monitor_status':
            monitor_location_states($user, $family);
            break;
        case 'save_retention':
            save_retention($user, $family, $input);
            break;
        case 'cleanup_group_trails':
            cleanup_group_trails($user, $family);
            break;
        default:
            fail('Unknown trail/status action.', 404);
    }
} catch (Throwable $ex) {
    error_log('Family Tracker trail-status error: ' . $ex->getMessage());
    fail('Server error. Check PHP error logs.', 500);
}

function retention_hours(array $family): int
{
    $value = (int)($family['trailRetentionHours'] ?? 168);
    return in_array($value, [24, 168, 720, 2160], true) ? $value : 168;
}

function group_member_statuses(array $family): array
{
    $now = time();
    $items = [];
    foreach (list_json_records('users') as $member) {
        if (empty($member['isActive']) || family_member_role($family, $member) === null) {
            continue;
        }
        $location = read_json_file(location_path((string)$member['id']), []);
        $status = 'missing';
        $ageSeconds = null;
        if ($location && (($location['familyId'] ?? '') === ($family['id'] ?? '') || empty($location['familyId']))) {
            $time = isset($location['serverTime']) ? strtotime((string)$location['serverTime']) : false;
            $ageSeconds = $time ? max(0, $now - $time) : null;
            $status = ($ageSeconds !== null && $ageSeconds <= LOCATION_STALE_SECONDS) ? 'live' : 'stale';
        }
        $items[] = [
            'id' => $member['id'],
            'displayName' => $member['displayName'] ?? $member['username'] ?? 'Member',
            'status' => $status,
            'ageSeconds' => $ageSeconds,
        ];
    }
    return $items;
}

function trail_point_count(array $family): int
{
    $count = 0;
    foreach (group_member_statuses($family) as $member) {
        $trail = read_json_file(trail_path((string)$member['id']), ['points' => []]);
        if (($trail['familyId'] ?? '') === ($family['id'] ?? '')) {
            $count += count(is_array($trail['points'] ?? null) ? $trail['points'] : []);
        }
    }
    return $count;
}

function status_payload(array $user, array $family, array $extra = []): array
{
    $statuses = group_member_statuses($family);
    $counts = ['live' => 0, 'stale' => 0, 'missing' => 0];
    foreach ($statuses as $item) {
        $counts[$item['status']] = ($counts[$item['status']] ?? 0) + 1;
    }
    return [
        'csrfToken' => ensure_csrf_token(),
        'role' => family_member_role($family, $user),
        'family' => public_family($family, true),
        'retentionHours' => retention_hours($family),
        'trailPointCount' => trail_point_count($family),
        'counts' => $counts,
        'members' => $statuses,
    ] + $extra;
}

function trim_trail_file(string $userId, string $familyId, int $hours): int
{
    $path = trail_path($userId);
    $trail = read_json_file($path, ['points' => []]);
    if (($trail['familyId'] ?? '') !== $familyId) {
        return 0;
    }
    $points = is_array($trail['points'] ?? null) ? $trail['points'] : [];
    $before = count($points);
    $cutoff = time() - ($hours * 3600);
    $points = array_values(array_filter($points, function ($point) use ($cutoff) {
        $time = isset($point['serverTime']) ? strtotime((string)$point['serverTime']) : false;
        return $time && $time >= $cutoff;
    }));
    if (count($points) !== $before) {
        $trail['points'] = $points;
        $trail['updatedAt'] = now_iso();
        write_json_file($path, $trail);
    }
    return $before - count($points);
}

function cleanup_for_family(array $family): int
{
    $removed = 0;
    $hours = retention_hours($family);
    foreach (group_member_statuses($family) as $member) {
        $removed += trim_trail_file((string)$member['id'], (string)$family['id'], $hours);
    }
    return $removed;
}

function monitor_location_states(array $user, array $family): void
{
    $previous = is_array($family['memberLocationStates'] ?? null) ? $family['memberLocationStates'] : [];
    $changed = false;
    foreach (group_member_statuses($family) as $item) {
        $id = (string)$item['id'];
        $old = is_array($previous[$id] ?? null) ? (string)($previous[$id]['status'] ?? '') : '';
        $new = (string)$item['status'];
        if ($old !== '' && $old !== $new) {
            if ($old === 'live' && $new === 'stale') {
                add_group_notice((string)$family['id'], 'location_stale', $item['displayName'] . ' location became stale.', $id);
            } elseif ($new === 'live' && in_array($old, ['stale', 'missing'], true)) {
                add_group_notice((string)$family['id'], 'location_restored', $item['displayName'] . ' started sharing a current location again.', $id);
            }
        }
        if ($old !== $new) {
            $changed = true;
        }
        $previous[$id] = ['status' => $new, 'checkedAt' => now_iso()];
    }
    if ($changed || empty($family['memberLocationStates'])) {
        $family['memberLocationStates'] = $previous;
        $family['updatedAt'] = now_iso();
        write_family($family);
    }
    $removed = trim_trail_file((string)$user['id'], (string)$family['id'], retention_hours($family));
    ok(status_payload($user, $family, ['message' => 'Location status checked.', 'removedTrailPoints' => $removed]));
}

function save_retention(array $user, array $family, array $input): void
{
    if (family_member_role($family, $user) !== 'owner') {
        fail('Owner permission required.', 403);
    }
    $hours = (int)($input['retentionHours'] ?? 0);
    if (!in_array($hours, [24, 168, 720, 2160], true)) {
        fail('Choose a valid retention period.', 400);
    }
    $family['trailRetentionHours'] = $hours;
    $family['updatedAt'] = now_iso();
    write_family($family);
    audit_event('trail_retention_updated', ['userId' => $user['id'], 'familyId' => $family['id'], 'hours' => $hours]);
    add_group_notice((string)$family['id'], 'trail_retention_updated', $user['displayName'] . ' changed trail retention to ' . $hours . ' hours.', (string)$user['id']);
    ok(status_payload($user, $family, ['message' => 'Trail retention updated.']));
}

function cleanup_group_trails(array $user, array $family): void
{
    if (family_member_role($family, $user) !== 'owner') {
        fail('Owner permission required.', 403);
    }
    $removed = cleanup_for_family($family);
    audit_event('cleanup_group_trails', ['userId' => $user['id'], 'familyId' => $family['id'], 'removedPoints' => $removed]);
    add_group_notice((string)$family['id'], 'trail_cleanup', $user['displayName'] . ' cleaned old trail points for ' . $family['name'] . '.', (string)$user['id']);
    ok(status_payload($user, $family, ['message' => 'Trail cleanup complete.', 'removedTrailPoints' => $removed]));
}
