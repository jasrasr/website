<?php
/**
 * Project: Family GPS Tracker
 * File: tests/profile-preferences-test.php
 * Revision: 1.0.0
 * Description: Verifies member-controlled profile preferences and generated avatar defaults.
 * Author: Jason Lamb / ChatGPT scaffold
 * Created: 2026-07-14
 * Modified: 2026-07-14
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/profile-helpers.php';

function assert_same($expected, $actual, string $message): void
{
    if ($expected !== $actual) {
        fwrite(STDERR, $message . PHP_EOL . 'Expected: ' . var_export($expected, true) . PHP_EOL . 'Actual: ' . var_export($actual, true) . PHP_EOL);
        exit(1);
    }
}

$user = [
    'id' => 'usr_test123',
    'displayName' => 'Jason Lamb',
    'username' => 'jason',
    'profile' => [
        'nickname' => '  Jase  ',
        'avatarUrl' => ' javascript:alert(1) ',
        'avatarMode' => 'uploaded',
    ],
];

$profile = user_profile_preferences($user);
assert_same('Jase', $profile['nickname'], 'Nickname should be trimmed.');
assert_same('', $profile['avatarUrl'], 'Unsafe avatar URLs should be rejected.');
assert_same('generated', $profile['avatarMode'], 'Invalid uploaded avatar without URL should fall back to generated mode.');
assert_same('JL', $profile['avatarInitials'], 'Generated avatars should derive initials from the display name.');
assert_same(true, str_starts_with($profile['avatarColor'], '#'), 'Generated avatar color should be hex-like.');

$updated = apply_user_profile_preferences($user, [
    'displayName' => 'Jason Lamb',
    'nickname' => 'Jay',
    'avatarMode' => 'picture',
    'avatarUrl' => 'https://example.com/avatar.png',
]);
assert_same('Jay', $updated['profile']['nickname'], 'Updated nickname should be stored under user profile.');
assert_same('picture', $updated['profile']['avatarMode'], 'Picture avatar mode should be stored.');
assert_same('https://example.com/avatar.png', $updated['profile']['avatarUrl'], 'HTTPS avatar URL should be stored.');

$public = public_profile_preferences($updated);
assert_same('Jay', $public['nickname'], 'Public profile should expose the nickname.');
assert_same('picture', $public['avatarMode'], 'Public profile should expose avatar mode.');
assert_same('https://example.com/avatar.png', $public['avatarUrl'], 'Public profile should expose safe avatar URLs.');

echo "profile-preferences-test passed\n";