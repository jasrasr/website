<?php
/**
 * Project: Family GPS Tracker
 * File: trails.php
 * Revision: 0.2.0
 * Description: Shared family trail-history endpoint.
 * Author: Jason Lamb / ChatGPT scaffold
 * Created: 2026-07-06
 * Modified: 2026-07-06
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/security.php';

init_app_storage();

try {
    $user = require_user();
    $familyId = (string)$user['familyId'];
    $family = read_family($familyId);
    if (!$family) {
        fail('Family not found.', 404);
    }

    $memberId = safe_id(is_string($_GET['memberId'] ?? null) ? (string)$_GET['memberId'] : '');
    $minutesRaw = isset($_GET['minutes']) ? (int)$_GET['minutes'] : DEFAULT_TRAIL_MINUTES;
    $minutes = max(15, min(MAX_TRAIL_LOOKBACK_MINUTES, $minutesRaw));
    $cutoff = time() - ($minutes * 60);

    $trails = [];
    foreach (list_json_records('users') as $member) {
        if (($member['familyId'] ?? '') !== $familyId || empty($member['isActive'])) {
            continue;
        }
        if ($memberId !== '' && (string)$member['id'] !== $memberId) {
            continue;
        }

        $trail = read_json_file(trail_path((string)$member['id']), ['points' => []]);
        $points = [];
        foreach (($trail['points'] ?? []) as $point) {
            if (!is_array($point) || ($point['familyId'] ?? '') !== $familyId) {
                continue;
            }
            $pointTime = isset($point['serverTime']) ? strtotime((string)$point['serverTime']) : false;
            if (!$pointTime || $pointTime < $cutoff) {
                continue;
            }
            $points[] = [
                'latitude' => isset($point['latitude']) ? (float)$point['latitude'] : null,
                'longitude' => isset($point['longitude']) ? (float)$point['longitude'] : null,
                'accuracy' => isset($point['accuracy']) ? (float)$point['accuracy'] : null,
                'speedMps' => isset($point['speedMps']) ? (float)$point['speedMps'] : null,
                'heading' => isset($point['heading']) ? (float)$point['heading'] : null,
                'serverTime' => $point['serverTime'] ?? null,
                'clientTime' => $point['clientTime'] ?? null,
            ];
        }

        $trails[] = [
            'member' => public_user($member),
            'points' => array_values($points),
            'pointCount' => count($points),
        ];
    }

    usort($trails, fn($a, $b) => strcasecmp((string)($a['member']['displayName'] ?? ''), (string)($b['member']['displayName'] ?? '')));

    ok([
        'family' => public_family($family, true),
        'trails' => $trails,
        'minutes' => $minutes,
        'serverTime' => now_iso(),
        'maxTrailPointsPerUser' => MAX_TRAIL_POINTS,
    ]);
} catch (Throwable $ex) {
    error_log('Family Tracker trails error: ' . $ex->getMessage());
    fail('Server error. Check PHP error logs.', 500);
}
