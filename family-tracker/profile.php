<?php
/**
 * Project: Family GPS Tracker
 * File: profile.php
 * Revision: 1.4.3
 * Description: JSON endpoint for updating the signed-in user's profile fields and group notices.
 * Author: Jason Lamb / ChatGPT scaffold
 * Created: 2026-07-06
 * Modified: 2026-07-09
 */

declare(strict_types=1);
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/notice-store.php';
require_once __DIR__ . '/includes/profile-helpers.php';

init_app_storage();

try {
    $user = require_user();

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
        ok(build_me_payload($user));
    }

    require_csrf();
    $input = request_input();
    $displayName = str_field($input, 'displayName', 80);

    if ($displayName === '') {
        fail('Display name is required.', 400);
    }

    $oldDisplayName = (string)($user['displayName'] ?? $user['username'] ?? 'A member');
    $user['displayName'] = $displayName;
    $user = apply_user_profile_preferences($user, $input);
    $user['updatedAt'] = now_iso();
    write_user($user);

    $family = current_family_for_user($user);
    if ($family && $oldDisplayName !== $displayName) {
        add_group_notice((string)$family['id'], 'display_name_changed', $oldDisplayName . ' changed display name to ' . $displayName . '.', (string)$user['id']);
    }

    audit_event('update_profile', ['userId' => $user['id']]);
    ok(build_me_payload($user) + ['message' => 'Profile updated.']);
} catch (Throwable $ex) {
    error_log('Family Tracker profile error: ' . $ex->getMessage());
    fail('Server error. Check PHP error logs.', 500);
}
