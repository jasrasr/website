<?php declare(strict_types=1);
/**
 * Filename: navigation-pages-test.php
 * Revision : 1.9.0
 * Description : Lightweight static verification for scoreboard navigation,
 *               documentation versions, and recent file revision headers.
 * Author : Jason Lamb (with help from Codex CLI)
 * Created Date : 2026-06-02
 * Modified Date : 2026-06-21
 * Changelog :
 * 1.0.0 initial release
 * 1.1.0 Updated access assertions for signed-in scoreboard navigation
 * 1.2.0 Expect Default label for the root scoreboard id
 * 1.3.0 Verify changelog headings include project version and date
 * 1.4.0 Bumped pinned project version assertion from v1.4.0 to v1.5.1
 * 1.5.0 Bumped pinned project version assertion to v1.6.0 (Frontlines categories release)
 * 1.6.0 Pin project documentation to v1.13.0 and verify recent login, navigation,
 *       and roster-search file revisions
 * 1.7.0 Verify auth preserves the current scoreboard page through password changes
 * 1.8.0 Pin project documentation to v1.15.0 ranked categories release
 * 1.9.0 Pin project documentation to v1.16.0 custom category ordering release
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
$readmePath = $root . '/README.md';
$changelogPagePath = $root . '/changelog.php';
$scoreboardsPagePath = $root . '/scoreboards.php';
$appJsPath = $root . '/public/app.js';
$adminShellPath = $root . '/enter-scores.php';
$authPath = $root . '/auth.php';

assertFileExistsLocal($changelogPath, 'CHANGELOG.md should exist as the single changelog content source.');
assertFileExistsLocal($readmePath, 'README.md should exist.');
assertFileExistsLocal($changelogPagePath, 'changelog.php should exist as the web changelog viewer.');
assertFileExistsLocal($scoreboardsPagePath, 'scoreboards.php should exist as the signed-in scoreboard navigation page.');

$changelog = file_get_contents($changelogPath) ?: '';
$readme = file_get_contents($readmePath) ?: '';
$changelogPage = file_get_contents($changelogPagePath) ?: '';
$scoreboardsPage = file_get_contents($scoreboardsPagePath) ?: '';
$appJs = file_get_contents($appJsPath) ?: '';
$adminShell = file_get_contents($adminShellPath) ?: '';
$auth = file_get_contents($authPath) ?: '';

assertContains($changelog, 'Current project version: **v1.16.0**', 'CHANGELOG.md should state the current project version.');
assertContains($changelog, '## v1.16.0 - 2026-06-21', 'CHANGELOG.md should document the custom category ordering release.');
assertContains($changelog, '## v1.15.0 - 2026-06-21', 'CHANGELOG.md should document the ranked categories release.');
assertContains($changelog, '## v1.14.0 - 2026-06-21', 'CHANGELOG.md should document the row-level roster search and return-flow release.');
assertContains($changelog, '## v1.13.0 - 2026-06-20', 'CHANGELOG.md should document the roster-search release.');
assertContains($changelog, '## v1.12.0 - 2026-06-20', 'CHANGELOG.md should document the login/navigation release.');
assertContains($changelog, '## v1.0.0 - 2026-06-02', 'CHANGELOG.md initial entry should remain present.');
assertContains($readme, 'Current project version: **v1.16.0**', 'README.md should match the changelog project version.');
assertContains($readme, '## Versioning', 'README.md should explain project versus per-file revisions.');
assertContains($readme, 'users-seed.sample.json', 'README.md should document first-run user seeding.');
assertContains($readme, 'Searchable roster', 'README.md should document Frontlines roster search.');
assertContains($changelogPage, "requireSignedIn('./login.php')", 'changelog.php should require a signed-in user.');
assertContains($changelogPage, 'CHANGELOG.md', 'changelog.php should render CHANGELOG.md instead of duplicating changelog content.');

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

assertContains($auth, 'authCurrentReturnPath(string $loginUrl)', 'Auth should normalize the current request into a safe return path.');
assertContains($auth, 'return=\' . rawurlencode(authCurrentReturnPath($loginUrl))', 'Forced password changes should return to the current scoreboard page.');
assertContains($auth, 'function authLoginRedirect', 'Unauthenticated page redirects should use a shared login redirect helper.');

$revisionFiles = [
    'auth.php' => 'Revision : 1.13.0',
    'login.php' => 'Revision : 1.2.0',
    'change-password.php' => 'Revision : 1.3.0',
    'frontlines/enter-scores.php' => 'Revision : 1.7.0',
    'frontlines/enter-scores-quick.php' => 'Revision : 1.6.0',
    'frontlines/teams.php' => 'Revision : 1.10.0',
    'frontlines/category-navigation.js' => 'Revision : 1.0.0',
    'frontlines/roster-search.js' => 'Revision : 1.1.0',
    'frontlines/roster-search.css' => 'Revision : 1.1.0',
];

foreach ($revisionFiles as $relativePath => $expectedRevision) {
    $path = $root . '/' . $relativePath;
    assertFileExistsLocal($path, "{$relativePath} should exist.");
    $source = file_get_contents($path) ?: '';
    assertContains($source, $expectedRevision, "{$relativePath} should contain {$expectedRevision}.");
}

echo 'PASS: navigation-pages-test.php' . PHP_EOL;

# Example Usage:
#   php .\tests\navigation-pages-test.php
