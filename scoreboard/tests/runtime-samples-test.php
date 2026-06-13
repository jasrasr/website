<?php declare(strict_types=1);
/**
 * Filename: runtime-samples-test.php
 * Revision : 1.2.0
 * Description : Verifies public-safe runtime sample files, default teams, and random first-run password behavior.
 * Author : Jason Lamb (with help from Codex CLI)
 * Created Date : 2026-06-02
 * Modified Date : 2026-06-13
 * Changelog :
 * 1.0.0 initial release
 * 1.1.0 Verify data-folder hardening and forced first-run password changes
 * 1.2.0 Verify root sample team labels use Team 1 through Team 6
 */

function assertTrue(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}" . PHP_EOL);
        exit(1);
    }
}

$root = dirname(__DIR__);
$requiredSamples = [
    'data/README.md',
    'data/.htaccess',
    'data/scores.sample.json',
    'data/users.sample.json',
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

$auth = file_get_contents($root . '/auth.php') ?: '';
assertTrue(strpos($auth, "bin2hex(random_bytes(8))") !== false, 'auth.php should generate random first-run passwords.');
assertTrue(strpos($auth, "'cvc-' . \$username") === false, 'auth.php should not define predictable cvc-[username] passwords.');
assertTrue(strpos($auth, 'makeUser($username, $password, $role, $scoreboards, true)') !== false, 'First-run users should be forced to change generated passwords.');
assertTrue(strpos($auth, 'removeFirstRunCredential') !== false, 'Used first-run credentials should be removed after password change.');

$firstRunSample = file_get_contents($root . '/first-run-credentials.txt.sample') ?: '';
assertTrue(strpos($firstRunSample, 'cvc-jason') === false, 'first-run credentials sample should not show a usable default admin password.');
assertTrue(strpos($firstRunSample, 'Users must change these passwords') !== false, 'first-run credentials sample should document forced password changes.');

echo 'PASS: runtime-samples-test.php' . PHP_EOL;

# Example Usage:
#   php .\tests\runtime-samples-test.php
