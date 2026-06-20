<?php declare(strict_types=1);
/**
 * Filename: runtime-samples-test.php
 * Revision : 1.4.0
 * Description : Verifies public-safe runtime sample files, default teams, first-run users,
 *               and preservation of an existing users.json without touching live data.
 * Author : Jason Lamb (with help from Codex CLI)
 * Created Date : 2026-06-02
 * Modified Date : 2026-06-20
 * Changelog :
 * 1.0.0 initial release
 * 1.1.0 Verify data-folder hardening and forced first-run password changes
 * 1.2.0 Verify root sample team labels use Team 1 through Team 6
 * 1.3.0 Verify first-run admin/scorer users with forced temporary password changes
 * 1.4.0 Run auth initialization in an isolated temp copy and verify existing users.json is never overwritten
 */

function assertTrue(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}" . PHP_EOL);
        exit(1);
    }
}

function removeTestTree(string $path): void
{
    if (!is_dir($path)) {
        return;
    }

    $items = scandir($path);
    if ($items === false) {
        return;
    }

    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }

        $itemPath = $path . DIRECTORY_SEPARATOR . $item;
        if (is_dir($itemPath)) {
            removeTestTree($itemPath);
        } else {
            @unlink($itemPath);
        }
    }

    @rmdir($path);
}

$root = dirname(__DIR__);
$requiredSamples = [
    'data/README.md',
    'data/.htaccess',
    'data/scores.sample.json',
    'data/users.sample.json',
    'data/users-seed.sample.json',
    'data/audit.sample.json',
    'collide/data/.htaccess',
    'collide/data/scores.sample.json',
    'collide/data/audit.sample.json',
    'youth/data/.htaccess',
    'youth/data/scores.sample.json',
    'youth/data/audit.sample.json',
    'frontlines/data/.htaccess',
    'frontlines/data/scores.sample.json',
    'frontlines/data/audit.sample.json',
    'first-run-credentials.txt.sample',
];

foreach ($requiredSamples as $relativePath) {
    $path = $root . '/' . $relativePath;
    assertTrue(is_file($path), "{$relativePath} should exist as a public-safe sample.");

    if (str_ends_with($relativePath, '.json')) {
        $decoded = json_decode(file_get_contents($path) ?: '', true);
        assertTrue(is_array($decoded), "{$relativePath} should contain valid JSON.");
    }
}

$rootScoresSample = json_decode(file_get_contents($root . '/data/scores.sample.json') ?: '', true);
$rootTeamNames = array_map(fn($team) => $team['name'] ?? '', $rootScoresSample['teams'] ?? []);
assertTrue($rootTeamNames === ['Team 1', 'Team 2', 'Team 3', 'Team 4', 'Team 5', 'Team 6'], 'Root scores sample should use Team 1 through Team 6 labels.');

$usersSample = json_decode(file_get_contents($root . '/data/users-seed.sample.json') ?: '', true);
$sampleUsers = $usersSample['users'] ?? [];
assertTrue(array_column($sampleUsers, 'username') === ['admin', 'scorer'], 'Users seed sample should define admin and scorer first-run users.');

$firstRunSample = file_get_contents($root . '/first-run-credentials.txt.sample') ?: '';
assertTrue(strpos($firstRunSample, 'admin: password') !== false, 'first-run credentials sample should show the temporary admin login.');
assertTrue(strpos($firstRunSample, 'scorer: password') !== false, 'first-run credentials sample should show the temporary scorer login.');
assertTrue(strpos($firstRunSample, 'Users must change this password') !== false, 'first-run credentials sample should document forced password changes.');

$tempRoot = sys_get_temp_dir() . '/cvc-scoreboard-runtime-test-' . bin2hex(random_bytes(6));
$tempData = $tempRoot . '/data';

try {
    assertTrue(mkdir($tempData, 0775, true), 'Temporary test data directory should be created.');
    assertTrue(copy($root . '/auth.php', $tempRoot . '/auth.php'), 'auth.php should be copied into the isolated test directory.');
    assertTrue(copy($root . '/data/users-seed.sample.json', $tempData . '/users-seed.sample.json'), 'users-seed.sample.json should be copied into the isolated test directory.');

    require $tempRoot . '/auth.php';

    $preservedPayload = json_encode([
        'users' => [[
            'id' => 'existing-user',
            'username' => 'existing-admin',
            'password_hash' => 'existing-hash',
            'role' => 'admin',
            'scoreboards' => ALL_SCOREBOARDS,
        ]],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;

    file_put_contents(USERS_FILE, $preservedPayload, LOCK_EX);
    ensureUsersFile();
    assertTrue(file_get_contents(USERS_FILE) === $preservedPayload, 'An existing users.json must be left byte-for-byte unchanged.');

    unlink(USERS_FILE);
    ensureUsersFile();

    $generatedUsers = json_decode(file_get_contents(USERS_FILE) ?: '', true);
    $users = $generatedUsers['users'] ?? [];
    assertTrue(count($users) === 2, 'First run should create exactly the users defined by users-seed.sample.json.');

    $usersByName = [];
    foreach ($users as $user) {
        $usersByName[$user['username'] ?? ''] = $user;
    }

    foreach (['admin' => 'admin', 'scorer' => 'scorer'] as $username => $role) {
        assertTrue(isset($usersByName[$username]), "First run should create {$username}.");
        assertTrue(($usersByName[$username]['role'] ?? '') === $role, "{$username} should have the {$role} role.");
        assertTrue(($usersByName[$username]['scoreboards'] ?? []) === ALL_SCOREBOARDS, "{$username} should have all scoreboard access.");
        assertTrue(!empty($usersByName[$username]['must_change_password']), "{$username} should be forced to change password.");
        assertTrue(password_verify(DEFAULT_FIRST_RUN_PASSWORD, $usersByName[$username]['password_hash'] ?? ''), "{$username} should use the temporary first-run password.");
    }

    $generatedCredentials = file_get_contents(FIRST_RUN_CREDENTIALS_FILE) ?: '';
    assertTrue(strpos($generatedCredentials, 'admin: password') !== false, 'Generated first-run credentials should include admin.');
    assertTrue(strpos($generatedCredentials, 'scorer: password') !== false, 'Generated first-run credentials should include scorer.');
} finally {
    removeTestTree($tempRoot);
}

echo 'PASS: runtime-samples-test.php' . PHP_EOL;

# Example Usage:
#   php .\tests\runtime-samples-test.php
