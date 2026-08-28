<?php
/**
 * Project: Family GPS Tracker
 * File: presence.php
 * Revision: 1.5.0
 * Description: Active-group check-ins, trip/ETA sharing, and recent member activity endpoint.
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
        ok(presence_payload($user, $family));
    }

    require_csrf();
    $input = request_input();
    $action = str_field($input, 'action', 40);

    switch ($action) {
        case 'check_in':
            save_check_in($user, $family, $input);
            break;
        case 'start_trip':
            start_trip_share($user, $family, $input);
            break;
        case 'end_trip':
            end_trip_share($user, $family);
            break;
        default:
            fail('Unknown presence action.', 404);
    }
} catch (Throwable $ex) {
    error_log('Family Tracker presence error: ' . $ex->getMessage());
    fail('Server error. Check PHP error logs.', 500);
}

function presence_members(array $family): array
{
    $items = [];
    $checkIns = is_array($family['memberCheckIns'] ?? null) ? $family['memberCheckIns'] : [];
    $trips = is_array($family['memberTrips'] ?? null) ? $family['memberTrips'] : [];

    foreach (list_json_records('users') as $member) {
        if (empty($member['isActive']) || family_member_role($family, $member) === null) {
            continue;
        }
        $id = (string)$member['id'];
        $items[] = [
            'id' => $id,
            'displayName' => $member['displayName'] ?? $member['username'] ?? 'Member',
            'username' => $member['username'] ?? '',
            'role' => family_member_role($family, $member) ?? 'member',
            'checkIn' => is_array($checkIns[$id] ?? null) ? $checkIns[$id] : null,
            'trip' => is_array($trips[$id] ?? null) ? $trips[$id] : null,
        ];
    }

    usort($items, fn($a, $b) => strcasecmp((string)$a['displayName'], (string)$b['displayName']));
    return $items;
}

function presence_activity(array $family, int $limit = 20): array
{
    $state = read_group_notice_state((string)$family['id']);
    $allowed = ['check_in', 'trip_started', 'trip_ended'];
    $items = [];
    foreach ($state['notices'] ?? [] as $notice) {
        if (!in_array((string)($notice['type'] ?? ''), $allowed, true)) {
            continue;
        }
        $items[] = [
            'id' => $notice['id'] ?? '',
            'type' => $notice['type'] ?? 'activity',
            'message' => $notice['message'] ?? 'Member activity.',
            'createdAt' => $notice['createdAt'] ?? null,
        ];
        if (count($items) >= $limit) {
            break;
        }
    }
    return $items;
}

function presence_payload(array $user, array $family, array $extra = []): array
{
    return [
        'csrfToken' => ensure_csrf_token(),
        'currentUserId' => $user['id'],
        'family' => public_family($family, true),
        'members' => presence_members($family),
        'activity' => presence_activity($family),
    ] + $extra;
}

function valid_check_in_status(string $status): string
{
    $allowed = ['im_ok', 'on_my_way', 'arrived', 'need_help'];
    return in_array($status, $allowed, true) ? $status : '';
}

function check_in_label(string $status): string
{
    return [
        'im_ok' => "I'm OK",
        'on_my_way' => 'On My Way',
        'arrived' => 'Arrived',
        'need_help' => 'Need Help',
    ][$status] ?? 'Checked In';
}

function save_check_in(array $user, array $family, array $input): void
{
    $status = valid_check_in_status(str_field($input, 'status', 30));
    $note = str_field($input, 'note', 160);
    if ($status === '') {
        fail('Choose a valid check-in status.', 400);
    }

    $family['memberCheckIns'] = is_array($family['memberCheckIns'] ?? null) ? $family['memberCheckIns'] : [];
    $family['memberCheckIns'][$user['id']] = [
        'status' => $status,
        'label' => check_in_label($status),
        'note' => $note,
        'updatedAt' => now_iso(),
    ];
    $family['updatedAt'] = now_iso();
    write_family($family);

    $message = $user['displayName'] . ' checked in: ' . check_in_label($status) . ($note !== '' ? ' — ' . $note : '') . '.';
    add_group_notice((string)$family['id'], 'check_in', $message, (string)$user['id']);
    audit_event('member_check_in', ['userId' => $user['id'], 'familyId' => $family['id'], 'status' => $status]);
    ok(presence_payload($user, $family, ['message' => 'Check-in shared.']));
}

function start_trip_share(array $user, array $family, array $input): void
{
    $destination = str_field($input, 'destination', 100);
    $etaMinutes = (int)($input['etaMinutes'] ?? 0);
    $note = str_field($input, 'note', 160);
    if ($destination === '') {
        fail('Destination is required.', 400);
    }
    if ($etaMinutes < 1 || $etaMinutes > 1440) {
        fail('ETA must be between 1 and 1440 minutes.', 400);
    }

    $startedAt = now_iso();
    $family['memberTrips'] = is_array($family['memberTrips'] ?? null) ? $family['memberTrips'] : [];
    $family['memberTrips'][$user['id']] = [
        'destination' => $destination,
        'etaMinutes' => $etaMinutes,
        'estimatedArrivalAt' => gmdate('c', time() + ($etaMinutes * 60)),
        'note' => $note,
        'startedAt' => $startedAt,
        'updatedAt' => $startedAt,
        'active' => true,
    ];
    $family['updatedAt'] = $startedAt;
    write_family($family);

    $message = $user['displayName'] . ' is on the way to ' . $destination . ' — ETA ' . $etaMinutes . ' min' . ($note !== '' ? ' — ' . $note : '') . '.';
    add_group_notice((string)$family['id'], 'trip_started', $message, (string)$user['id']);
    audit_event('trip_started', ['userId' => $user['id'], 'familyId' => $family['id'], 'destination' => $destination, 'etaMinutes' => $etaMinutes]);
    ok(presence_payload($user, $family, ['message' => 'Trip sharing started.']));
}

function end_trip_share(array $user, array $family): void
{
    $trips = is_array($family['memberTrips'] ?? null) ? $family['memberTrips'] : [];
    $trip = is_array($trips[$user['id']] ?? null) ? $trips[$user['id']] : null;
    if (!$trip || empty($trip['active'])) {
        fail('No active trip found.', 400);
    }

    $destination = (string)($trip['destination'] ?? 'destination');
    $trip['active'] = false;
    $trip['endedAt'] = now_iso();
    $trip['updatedAt'] = $trip['endedAt'];
    $family['memberTrips'][$user['id']] = $trip;
    $family['updatedAt'] = now_iso();
    write_family($family);

    add_group_notice((string)$family['id'], 'trip_ended', $user['displayName'] . ' ended trip sharing for ' . $destination . '.', (string)$user['id']);
    audit_event('trip_ended', ['userId' => $user['id'], 'familyId' => $family['id'], 'destination' => $destination]);
    ok(presence_payload($user, $family, ['message' => 'Trip sharing ended.']));
}
