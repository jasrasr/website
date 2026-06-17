<?php declare(strict_types=1);
/**
 * Filename: change-password-test.php
 * Revision : 1.1.0
 * Description : Lightweight verification for signed-in user password changes.
 * Author : Jason Lamb (with help from Codex CLI)
 * Created Date : 2026-05-28
 * Modified Date : 2026-06-13
 * Changelog :
 * 1.0.0 initial release
 * 1.1.0 Verify forced-change flag clearing and first-run credential cleanup
 */

require __DIR__ . '/../auth.php';

function assertTrue(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}" . PHP_EOL);
        exit(1);
    }
}

$originalUsersJson = is_file(USERS_FILE) ? file_get_contents(USERS_FILE) : null;
$originalFirstRunCredentials = is_file(FIRST_RUN_CREDENTIALS_FILE) ? file_get_contents(FIRST_RUN_CREDENTIALS_FILE) : null;
$originalSessionStatus = session_status();

try {
    if ($originalSessionStatus === PHP_SESSION_NONE) {
        session_start();
    }

    $testUser = makeUser('selftest-user', 'old-password', 'scorer', ['root'], true);
    saveUsers([$testUser]);
    file_put_contents(
        FIRST_RUN_CREDENTIALS_FILE,
        "CVC Scoreboard - First-Run Credentials\n\nselftest-user: old-password\nother-user: keep-password\n",
        LOCK_EX
    );
    $_SESSION[AUTH_SESSION] = [
        'id' => $testUser['id'],
        'username' => $testUser['username'],
        'role' => $testUser['role'],
        'scoreboards' => $testUser['scoreboards'],
        'must_change_password' => true,
    ];

    $result = changeCurrentUserPassword($testUser['id'], 'old-password', 'new-password');
    $newLogin = attemptLogin('selftest-user', 'new-password');
    $updatedCredentials = file_get_contents(FIRST_RUN_CREDENTIALS_FILE) ?: '';

    assertTrue($result['ok'] === true, 'Password change should succeed with the current password.');
    assertTrue(attemptLogin('selftest-user', 'old-password') === null, 'Old password should no longer work.');
    assertTrue($newLogin !== null, 'New password should work.');
    assertTrue(!authPasswordChangeRequired($newLogin), 'Forced password-change flag should clear after password update.');
    assertTrue(($_SESSION[AUTH_SESSION]['username'] ?? '') === 'selftest-user', 'Session should remain signed in.');
    assertTrue(($_SESSION[AUTH_SESSION]['must_change_password'] ?? true) === false, 'Session should no longer require password change.');
    assertTrue(strpos($updatedCredentials, 'selftest-user: old-password') === false, 'Used first-run credential should be removed.');
    assertTrue(strpos($updatedCredentials, 'other-user: keep-password') !== false, 'Other first-run credentials should remain.');

    $wrongPasswordResult = changeCurrentUserPassword($testUser['id'], 'wrong-password', 'another-password');

    assertTrue($wrongPasswordResult['ok'] === false, 'Password change should fail with the wrong current password.');
    assertTrue(attemptLogin('selftest-user', 'another-password') === null, 'Wrong-current-password attempt should not update the password.');

    echo 'PASS: change-password-test.php' . PHP_EOL;
} finally {
    if ($originalUsersJson === null) {
        if (is_file(USERS_FILE)) {
            unlink(USERS_FILE);
        }
    } else {
        file_put_contents(USERS_FILE, $originalUsersJson, LOCK_EX);
    }

    if ($originalFirstRunCredentials === null) {
        if (is_file(FIRST_RUN_CREDENTIALS_FILE)) {
            unlink(FIRST_RUN_CREDENTIALS_FILE);
        }
    } else {
        file_put_contents(FIRST_RUN_CREDENTIALS_FILE, $originalFirstRunCredentials, LOCK_EX);
    }
}

# Example Usage:
#   php .\tests\change-password-test.php
