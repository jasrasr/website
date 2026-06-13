<?php declare(strict_types=1);
/**
 * Filename: navigation-pages-test.php
 * Revision : 1.2.0
 * Description : Lightweight static verification for scoreboard navigation and changelog pages.
 * Author : Jason Lamb (with help from Codex CLI)
 * Created Date : 2026-06-02
 * Modified Date : 2026-06-13
 * Changelog :
 * 1.0.0 initial release
 * 1.1.0 Updated access assertions for signed-in scoreboard navigation
 * 1.2.0 Expect Default label for the root scoreboard id
 */

function assertContains(string $haystack, string $needle, string $message): void
{
    if (strpos($haystack, $needle) === false) {
        fwrite(STDERR, "FAIL: {$message}" . PHP_EOL);
        exit(1);
    }
}

function assertFileExistsLocal(string $path, string $message): void
{
    if (!is_file($path)) {
        fwrite(STDERR, "FAIL: {$message}" . PHP_EOL);
        exit(1);
    }
}

$root = dirname(__DIR__);

$changelogPath = $root . '/CHANGELOG.md';
$changelogPagePath = $root . '/changelog.php';
$scoreboardsPagePath = $root . '/scoreboards.php';
$appJsPath = $root . '/public/app.js';
$adminShellPath = $root . '/enter-scores.php';

assertFileExistsLocal($changelogPath, 'CHANGELOG.md should exist as the single changelog content source.');
assertFileExistsLocal($changelogPagePath, 'changelog.php should exist as the web changelog viewer.');
assertFileExistsLocal($scoreboardsPagePath, 'scoreboards.php should exist as the signed-in scoreboard navigation page.');

$changelogPage = file_get_contents($changelogPagePath) ?: '';
$scoreboardsPage = file_get_contents($scoreboardsPagePath) ?: '';
$appJs = file_get_contents($appJsPath) ?: '';
$adminShell = file_get_contents($adminShellPath) ?: '';

assertContains($changelogPage, "requireSignedIn('./login.php')", 'changelog.php should require a signed-in user.');
assertContains($changelogPage, "CHANGELOG.md", 'changelog.php should render CHANGELOG.md instead of duplicating changelog content.');

assertContains($scoreboardsPage, "requireSignedIn('./login.php')", 'scoreboards.php should require a signed-in user.');
assertContains($scoreboardsPage, '$visibleScoreboards', 'scoreboards.php should filter the list to the user\'s accessible scoreboards.');
foreach (['Default', 'Collide', 'Youth', 'Frontlines'] as $label) {
    assertContains($scoreboardsPage, $label, "scoreboards.php should list {$label}.");
}

assertContains($adminShell, 'data-changelog-url', 'Admin shell should provide a changelog URL to shared JS.');
assertContains($adminShell, 'data-scoreboards-url', 'Admin shell should provide a scoreboards URL to shared JS.');
assertContains($appJs, 'dataset.changelogUrl', 'Shared admin app should read the changelog URL from page data.');
assertContains($appJs, 'dataset.scoreboardsUrl', 'Shared admin app should read the scoreboards URL from page data.');
assertContains($appJs, 'role === \'admin\'', 'Manage Users link should be rendered only for admin users.');
assertContains($appJs, '>Changelog<', 'Admin footer should link to the changelog page.');
assertContains($appJs, '>Scoreboards<', 'Admin footer should link to the scoreboards navigation page.');

echo 'PASS: navigation-pages-test.php' . PHP_EOL;

# Example Usage:
#   php .\tests\navigation-pages-test.php
