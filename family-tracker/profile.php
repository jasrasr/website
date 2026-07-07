<?php
/**
 * Project: Family GPS Tracker
 * File: profile.php
 * Revision: 1.3.6
 * Description: JSON endpoint for updating the signed-in user's profile fields.
 * Author: Jason Lamb / ChatGPT scaffold
 * Created: 2026-07-06
 * Modified: 2026-07-06
 */

declare(strict_types=1);
require_once __DIR__ . '/includes/security.php';

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

    $user['displayName'] = $displayName;
    $user['updatedAt'] = now_iso();
    write_user($user);

    audit_event('update_display_name', ['userId' => $user['id']]);
    ok(build_me_payload($user) + ['message' => 'Display name updated.']);
} catch (Throwable $ex) {
    error_log('Family Tracker profile error: ' . $ex->getMessage());
    fail('Server error. Check PHP error logs.', 500);
}
