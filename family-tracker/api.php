<?php
/**
 * Project: Family GPS Tracker
 * File: api.php
 * Revision: 0.1.0
 * Description: JSON API for auth, family membership, invite codes, and location updates.
 * Author: Jason Lamb / ChatGPT scaffold
 * Created: 2026-07-06
 * Modified: 2026-07-06
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/security.php';

init_app_storage();

$action = $_GET['action'] ?? '';
$action = is_string($action) ? trim($action) : '';

try {
    switch ($action) {
        case 'me':
            ok(build_me_payload(current_user()));
            break;

        case 'register_family':
            handle_register_family(request_input());
            break;

        case 'join_family':
            handle_join_family(request_input());
            break;

        case 'login':
            handle_login(request_input());
            break;

        case 'logout':
            require_csrf();
            session_destroy();
            ok(['message' => 'Logged out.']);
            break;

        case 'family_locations':
            handle_family_locations();
            break;

        case 'update_location':
            require_csrf();
            handle_update_location(request_input());
            break;

        case 'delete_my_location':
            require_csrf();
            handle_delete_my_location();
            break;

        case 'regenerate_invite':
            require_csrf();
            handle_regenerate_invite();
            break;

        default:
            fail('Unknown API action.', 404);
    }
} catch (Throwable $ex) {
    error_log('Family Tracker API error: ' . $ex->getMessage());
    fail('Server error. Check PHP error logs.', 500);
}

function handle_register_family(array $input): void
{
    $displayName = str_field($input, 'displayName', 80);
    $username = normalize_username(str_field($input, 'username', 120));
    $password = (string)($input['password'] ?? '');
    $familyName = str_field($input, 'familyName', 80);
    $consent = bool_field($input, 'consentAccepted');

    if ($displayName === '' || $familyName === '') {
        fail('Display name and family name are required.', 400);
    }
    validate_username_or_fail($username);
    validate_password_or_fail($password);
    if (!$consent) {
        fail('Consent is required. This app is not for stealth tracking.', 400);
    }

    $result = with_named_lock('registration', function () use ($displayName, $username, $password, $familyName): array {
        $indexPath = username_index_path();
        $index = read_json_file($indexPath, ['usernames' => []]);

        if (!empty($index['usernames'][$username])) {
            fail('Username already exists.', 409);
        }

        $familyId = new_id('fam');
        $userId = new_id('usr');
        $inviteCode = generate_invite_code();
        $inviteNormalized = normalize_invite_code($inviteCode);
        $createdAt = now_iso();

        $family = [
            'id' => $familyId,
            'name' => $familyName,
            'ownerUserId' => $userId,
            'inviteCodeHash' => password_hash($inviteNormalized, PASSWORD_DEFAULT),
            'inviteCodeLast4' => substr($inviteNormalized, -4),
            'inviteCodeCreatedAt' => $createdAt,
            'createdAt' => $createdAt,
            'updatedAt' => $createdAt,
        ];

        $user = [
            'id' => $userId,
            'displayName' => $displayName,
            'username' => $username,
            'passwordHash' => password_hash($password, PASSWORD_DEFAULT),
            'familyId' => $familyId,
            'role' => 'owner',
            'isActive' => true,
            'consentAcceptedAt' => $createdAt,
            'createdAt' => $createdAt,
            'lastLoginAt' => $createdAt,
        ];

        write_family($family);
        write_user($user);
        $index['usernames'][$username] = $userId;
        write_json_file($indexPath, $index);

        audit_event('register_family', ['userId' => $userId, 'familyId' => $familyId]);

        return [$user, $family, $inviteCode];
    });

    [$user, $family, $inviteCode] = $result;
    $_SESSION['user_id'] = $user['id'];
    ensure_csrf_token();

    ok(build_me_payload($user) + [
        'oneTimeInviteCode' => $inviteCode,
        'message' => 'Family tracker created. Save the invite code now.',
    ]);
}

function handle_join_family(array $input): void
{
    $displayName = str_field($input, 'displayName', 80);
    $username = normalize_username(str_field($input, 'username', 120));
    $password = (string)($input['password'] ?? '');
    $inviteCode = str_field($input, 'inviteCode', 40);
    $consent = bool_field($input, 'consentAccepted');

    if ($displayName === '') {
        fail('Display name is required.', 400);
    }
    validate_username_or_fail($username);
    validate_password_or_fail($password);
    if (!$consent) {
        fail('Consent is required. This app is not for stealth tracking.', 400);
    }

    $result = with_named_lock('registration', function () use ($displayName, $username, $password, $inviteCode): array {
        $family = find_family_by_invite_code($inviteCode);
        if (!$family) {
            fail('Invite code not found.', 404);
        }

        $indexPath = username_index_path();
        $index = read_json_file($indexPath, ['usernames' => []]);
        if (!empty($index['usernames'][$username])) {
            fail('Username already exists.', 409);
        }

        $createdAt = now_iso();
        $userId = new_id('usr');
        $user = [
            'id' => $userId,
            'displayName' => $displayName,
            'username' => $username,
            'passwordHash' => password_hash($password, PASSWORD_DEFAULT),
            'familyId' => $family['id'],
            'role' => 'member',
            'isActive' => true,
            'consentAcceptedAt' => $createdAt,
            'createdAt' => $createdAt,
            'lastLoginAt' => $createdAt,
        ];

        write_user($user);
        $index['usernames'][$username] = $userId;
        write_json_file($indexPath, $index);

        audit_event('join_family', ['userId' => $userId, 'familyId' => $family['id']]);

        return [$user, $family];
    });

    [$user] = $result;
    $_SESSION['user_id'] = $user['id'];
    ensure_csrf_token();
    ok(build_me_payload($user) + ['message' => 'Joined family tracker.']);
}

function handle_login(array $input): void
{
    $username = normalize_username(str_field($input, 'username', 120));
    $password = (string)($input['password'] ?? '');

    $index = read_json_file(username_index_path(), ['usernames' => []]);
    $userId = $index['usernames'][$username] ?? '';
    $user = is_string($userId) && $userId !== '' ? read_user($userId) : null;

    if (!$user || empty($user['passwordHash']) || !password_verify($password, (string)$user['passwordHash'])) {
        fail('Invalid username or password.', 401);
    }
    if (empty($user['isActive'])) {
        fail('Account is inactive.', 403);
    }

    $user['lastLoginAt'] = now_iso();
    write_user($user);
    $_SESSION['user_id'] = $user['id'];
    ensure_csrf_token();

    audit_event('login', ['userId' => $user['id']]);
    ok(build_me_payload($user) + ['message' => 'Logged in.']);
}

function handle_family_locations(): void
{
    $user = require_user();
    $familyId = (string)$user['familyId'];
    $family = read_family($familyId);
    if (!$family) {
        fail('Family not found.', 404);
    }

    $members = [];
    $now = time();
    foreach (list_json_records('users') as $member) {
        if (($member['familyId'] ?? '') !== $familyId || empty($member['isActive'])) {
            continue;
        }

        $public = public_user($member);
        $location = read_json_file(location_path((string)$member['id']), []);
        if ($location) {
            $serverTime = isset($location['serverTime']) ? strtotime((string)$location['serverTime']) : false;
            $ageSeconds = $serverTime ? max(0, $now - $serverTime) : null;
            $location['ageSeconds'] = $ageSeconds;
            $location['isStale'] = $ageSeconds === null || $ageSeconds > LOCATION_STALE_SECONDS;
            $public['location'] = $location;
        } else {
            $public['location'] = null;
        }
        $members[] = $public;
    }

    usort($members, fn($a, $b) => strcasecmp((string)$a['displayName'], (string)$b['displayName']));

    ok([
        'family' => public_family($family, true),
        'members' => $members,
        'serverTime' => now_iso(),
        'staleAfterSeconds' => LOCATION_STALE_SECONDS,
    ]);
}

function handle_update_location(array $input): void
{
    $user = require_user();
    $lat = float_field($input, 'latitude', -90, 90);
    $lon = float_field($input, 'longitude', -180, 180);

    if ($lat === null || $lon === null) {
        fail('Latitude and longitude are required.', 400);
    }

    $accuracy = float_field($input, 'accuracy', 0, 100000);
    $speedMps = float_field($input, 'speedMps', 0, 200);
    $heading = float_field($input, 'heading', 0, 360);
    $altitude = float_field($input, 'altitude', -500, 10000);
    $clientTime = str_field($input, 'clientTime', 40);

    $location = [
        'userId' => $user['id'],
        'familyId' => $user['familyId'],
        'latitude' => $lat,
        'longitude' => $lon,
        'accuracy' => $accuracy,
        'speedMps' => $speedMps,
        'heading' => $heading,
        'altitude' => $altitude,
        'clientTime' => $clientTime ?: null,
        'serverTime' => now_iso(),
        'source' => 'browser-geolocation',
    ];

    with_named_lock('location_' . $user['id'], function () use ($user, $location): void {
        write_json_file(location_path((string)$user['id']), $location);

        $trailPath = trail_path((string)$user['id']);
        $trail = read_json_file($trailPath, ['points' => []]);
        $trail['userId'] = $user['id'];
        $trail['familyId'] = $user['familyId'];
        $trail['updatedAt'] = $location['serverTime'];
        $trail['points'][] = $location;
        if (count($trail['points']) > MAX_TRAIL_POINTS) {
            $trail['points'] = array_slice($trail['points'], -MAX_TRAIL_POINTS);
        }
        write_json_file($trailPath, $trail);
    });

    $user['lastLocationAt'] = $location['serverTime'];
    write_user($user);

    ok(['location' => $location, 'message' => 'Location updated.']);
}

function handle_delete_my_location(): void
{
    $user = require_user();
    with_named_lock('location_' . $user['id'], function () use ($user): void {
        delete_json_file(location_path((string)$user['id']));
        delete_json_file(trail_path((string)$user['id']));
    });
    audit_event('delete_my_location', ['userId' => $user['id']]);
    ok(['message' => 'Your stored location and trail were deleted.']);
}

function handle_regenerate_invite(): void
{
    $user = require_user();
    require_owner($user);
    $family = read_family((string)$user['familyId']);
    if (!$family) {
        fail('Family not found.', 404);
    }

    $code = generate_invite_code();
    $normalized = normalize_invite_code($code);
    $family['inviteCodeHash'] = password_hash($normalized, PASSWORD_DEFAULT);
    $family['inviteCodeLast4'] = substr($normalized, -4);
    $family['inviteCodeCreatedAt'] = now_iso();
    $family['updatedAt'] = now_iso();
    write_family($family);

    audit_event('regenerate_invite', ['userId' => $user['id'], 'familyId' => $family['id']]);
    ok(['inviteCode' => $code, 'family' => public_family($family, true)]);
}
