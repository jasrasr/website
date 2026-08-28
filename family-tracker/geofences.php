<?php
/**
 * Project: Family GPS Tracker
 * File: geofences.php
 * Revision: 1.6.9
 * Description: Active-group geofence management with address persistence, editing, notification controls, and browser-driven evaluation.
 * Author: Jason Lamb / ChatGPT scaffold
 * Created: 2026-07-11
 * Modified: 2026-08-06
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/notice-store.php';

init_app_storage();

try {
    $user = require_user();
    $family = current_family_for_user($user);
    if (!$family) fail('Active group not found.', 404);

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
        ok(geofence_payload($user, $family, true));
    }

    require_csrf();
    $input = request_input();
    $action = str_field($input, 'action', 50);
    switch ($action) {
        case 'create_zone': create_zone($user, $family, $input); break;
        case 'update_zone': update_zone($user, $family, $input); break;
        case 'delete_zone': delete_zone($user, $family, $input); break;
        case 'evaluate': ok(geofence_payload($user, $family, true));
        default: fail('Unknown geofence action.', 404);
    }
} catch (Throwable $ex) {
    error_log('Family Tracker geofence error: ' . $ex->getMessage());
    fail('Server error. Check PHP error logs.', 500);
}

function normalized_zones(array $family): array
{
    $zones = is_array($family['geofences'] ?? null) ? $family['geofences'] : [];
    $normalized = [];
    foreach ($zones as $zone) {
        if (!is_array($zone) || empty($zone['id'])) continue;
        $zone['notifyArrival'] = array_key_exists('notifyArrival', $zone) ? (bool)$zone['notifyArrival'] : true;
        $zone['notifyDeparture'] = array_key_exists('notifyDeparture', $zone) ? (bool)$zone['notifyDeparture'] : true;
        $zone['address'] = trim((string)($zone['address'] ?? ''));
        $normalized[] = $zone;
    }
    return $normalized;
}

function distance_meters(float $lat1, float $lon1, float $lat2, float $lon2): float
{
    $earth = 6371000.0;
    $p1 = deg2rad($lat1);
    $p2 = deg2rad($lat2);
    $dp = deg2rad($lat2 - $lat1);
    $dl = deg2rad($lon2 - $lon1);
    $a = sin($dp / 2) ** 2 + cos($p1) * cos($p2) * sin($dl / 2) ** 2;
    return $earth * 2 * atan2(sqrt($a), sqrt(1 - $a));
}

function member_label(array $member, array $family): string
{
    $profiles = is_array($family['memberProfiles'] ?? null) ? $family['memberProfiles'] : [];
    $profile = is_array($profiles[$member['id'] ?? ''] ?? null) ? $profiles[$member['id']] : [];
    return trim((string)($profile['nickname'] ?? '')) ?: (string)($member['displayName'] ?? $member['username'] ?? 'A member');
}

function evaluate_geofences(array $family): array
{
    $zones = normalized_zones($family);
    $states = is_array($family['geofenceStates'] ?? null) ? $family['geofenceStates'] : [];
    $statusRows = [];
    $changed = false;

    foreach ($zones as $zone) {
        $zoneId = safe_id((string)$zone['id']);
        $zoneStates = is_array($states[$zoneId] ?? null) ? $states[$zoneId] : [];
        foreach (list_json_records('users') as $member) {
            if (empty($member['isActive']) || family_member_role($family, $member) === null) continue;
            $location = read_json_file(location_path((string)$member['id']), []);
            if (!$location || (($location['familyId'] ?? '') !== ($family['id'] ?? ''))) continue;
            $distance = distance_meters((float)$zone['latitude'], (float)$zone['longitude'], (float)$location['latitude'], (float)$location['longitude']);
            $inside = $distance <= (float)$zone['radiusMeters'];
            $memberId = (string)$member['id'];
            $previous = array_key_exists($memberId, $zoneStates) ? (bool)$zoneStates[$memberId] : null;
            if ($previous !== null && $previous !== $inside) {
                $notify = $inside ? (bool)$zone['notifyArrival'] : (bool)$zone['notifyDeparture'];
                if ($notify) {
                    $verb = $inside ? 'arrived at' : 'left';
                    add_group_notice((string)$family['id'], $inside ? 'geofence_arrival' : 'geofence_departure', member_label($member, $family) . ' ' . $verb . ' ' . $zone['name'] . '.', $memberId);
                }
                audit_event($inside ? 'geofence_arrival' : 'geofence_departure', [
                    'familyId' => $family['id'],
                    'userId' => $memberId,
                    'zoneId' => $zoneId,
                    'noticeEnabled' => $notify,
                ]);
            }
            if ($previous !== $inside) { $zoneStates[$memberId] = $inside; $changed = true; }
            $statusRows[] = [
                'zoneId' => $zoneId,
                'zoneName' => $zone['name'],
                'userId' => $memberId,
                'displayName' => member_label($member, $family),
                'inside' => $inside,
                'distanceMeters' => round($distance),
                'locationTime' => $location['serverTime'] ?? null,
            ];
        }
        $states[$zoneId] = $zoneStates;
    }

    if ($changed) {
        $family['geofenceStates'] = $states;
        $family['updatedAt'] = now_iso();
        write_family($family);
    }
    return $statusRows;
}

function geofence_payload(array $user, array $family, bool $evaluate): array
{
    return [
        'csrfToken' => ensure_csrf_token(),
        'role' => family_member_role($family, $user),
        'zones' => normalized_zones($family),
        'statuses' => $evaluate ? evaluate_geofences($family) : [],
        'evaluatedAt' => now_iso(),
    ];
}

function zone_fields(array $input): array
{
    $name = str_field($input, 'name', 80);
    $address = str_field($input, 'address', 180);
    $latitude = float_field($input, 'latitude', -90, 90);
    $longitude = float_field($input, 'longitude', -180, 180);
    $radius = float_field($input, 'radiusMeters', 50, 5000);
    if ($name === '' || $latitude === null || $longitude === null || $radius === null) {
        fail('Name, resolved location, and radius are required.', 400);
    }
    return [
        'name' => $name,
        'address' => $address,
        'latitude' => $latitude,
        'longitude' => $longitude,
        'radiusMeters' => round($radius),
        'notifyArrival' => bool_field($input, 'notifyArrival'),
        'notifyDeparture' => bool_field($input, 'notifyDeparture'),
    ];
}

function create_zone(array $user, array $family, array $input): void
{
    if (family_member_role($family, $user) !== 'owner') fail('Owner permission required.', 403);
    $fields = zone_fields($input);
    $zone = $fields + [
        'id' => new_id('zone'),
        'createdByUserId' => $user['id'],
        'createdAt' => now_iso(),
        'updatedAt' => now_iso(),
    ];
    $zones = normalized_zones($family);
    $zones[] = $zone;
    $family['geofences'] = $zones;
    $family['updatedAt'] = now_iso();
    write_family($family);
    add_group_notice((string)$family['id'], 'geofence_created', $user['displayName'] . ' created the place ' . $zone['name'] . '.', (string)$user['id']);
    audit_event('geofence_created', ['familyId' => $family['id'], 'userId' => $user['id'], 'zoneId' => $zone['id']]);
    ok(geofence_payload($user, $family, true) + ['message' => 'Place created.']);
}

function update_zone(array $user, array $family, array $input): void
{
    if (family_member_role($family, $user) !== 'owner') fail('Owner permission required.', 403);
    $zoneId = safe_id(str_field($input, 'zoneId', 80));
    if ($zoneId === '') fail('Place ID is required.', 400);
    $fields = zone_fields($input);
    $zones = normalized_zones($family);
    $found = false;
    foreach ($zones as &$zone) {
        if (safe_id((string)$zone['id']) !== $zoneId) continue;
        $zone = array_merge($zone, $fields, ['updatedAt' => now_iso()]);
        $found = true;
        break;
    }
    unset($zone);
    if (!$found) fail('Place not found.', 404);
    $family['geofences'] = $zones;
    $family['updatedAt'] = now_iso();
    write_family($family);
    add_group_notice((string)$family['id'], 'geofence_updated', $user['displayName'] . ' updated the place ' . $fields['name'] . '.', (string)$user['id']);
    audit_event('geofence_updated', ['familyId' => $family['id'], 'userId' => $user['id'], 'zoneId' => $zoneId]);
    ok(geofence_payload($user, $family, true) + ['message' => 'Place updated.']);
}

function delete_zone(array $user, array $family, array $input): void
{
    if (family_member_role($family, $user) !== 'owner') fail('Owner permission required.', 403);
    $zoneId = safe_id(str_field($input, 'zoneId', 80));
    $zones = normalized_zones($family);
    $deletedName = '';
    $remaining = [];
    foreach ($zones as $zone) {
        if (safe_id((string)$zone['id']) === $zoneId) $deletedName = (string)$zone['name'];
        else $remaining[] = $zone;
    }
    if ($deletedName === '') fail('Place not found.', 404);
    $family['geofences'] = $remaining;
    if (is_array($family['geofenceStates'] ?? null)) unset($family['geofenceStates'][$zoneId]);
    $family['updatedAt'] = now_iso();
    write_family($family);
    add_group_notice((string)$family['id'], 'geofence_deleted', $user['displayName'] . ' deleted the place ' . $deletedName . '.', (string)$user['id']);
    audit_event('geofence_deleted', ['familyId' => $family['id'], 'userId' => $user['id'], 'zoneId' => $zoneId]);
    ok(geofence_payload($user, $family, true) + ['message' => 'Place deleted.']);
}
