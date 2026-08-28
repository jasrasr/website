<?php declare(strict_types=1);
/**
 * Filename: frontlines-rankings-test.php
 * Revision : 1.0.0
 * Description : Static verification for the protected Frontlines full rankings page and navigation.
 * Author : Jason Lamb (with help from ChatGPT)
 * Created Date : 2026-06-23
 * Modified Date : 2026-06-23
 * Changelog :
 * 1.0.0 Initial protected full rankings page wiring checks
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
$rankingsPath = $root . '/frontlines/rankings.php';
$scoreboardsPath = $root . '/scoreboards.php';
$fullEntryPath = $root . '/frontlines/enter-scores.php';
$quickEntryPath = $root . '/frontlines/enter-scores-quick.php';
$rosterPath = $root . '/frontlines/teams.php';
$navHelperPath = $root . '/frontlines/category-navigation.js';

foreach ([$rankingsPath, $scoreboardsPath, $fullEntryPath, $quickEntryPath, $rosterPath, $navHelperPath] as $path) {
    assertFileExistsLocal($path, "{$path} should exist.");
}

$rankings = file_get_contents($rankingsPath) ?: '';
$scoreboards = file_get_contents($scoreboardsPath) ?: '';
$fullEntry = file_get_contents($fullEntryPath) ?: '';
$quickEntry = file_get_contents($quickEntryPath) ?: '';
$roster = file_get_contents($rosterPath) ?: '';
$navHelper = file_get_contents($navHelperPath) ?: '';

assertContains($rankings, 'Revision : 1.0.0', 'rankings.php should have a versioned script header.');
assertContains($rankings, "requireAuth('frontlines', '../login.php')", 'rankings.php should be restricted to signed-in Frontlines users.');
assertContains($rankings, 'usort($teams', 'rankings.php should sort teams server-side.');
assertContains($rankings, 'score_changed_at', 'rankings.php should use score_changed_at as a tie-breaker.');
assertContains($rankings, 'Full Rankings', 'rankings.php should identify the page as Full Rankings.');
assertContains($rankings, 'ordinalPlace($place)', 'rankings.php should show sequential 1st-to-12th style places.');
assertContains($rankings, './enter-scores-category.php', 'rankings.php should link to Add Category Score.');
assertContains($rankings, '../scoreboards.php', 'rankings.php should link back to Scoreboards.');

assertContains($scoreboards, 'Revision : 1.6.0', 'scoreboards.php should be revised for Full Rankings navigation.');
assertContains($scoreboards, "'rankings' => './frontlines/rankings.php'", 'scoreboards.php should expose Frontlines rankings.');
assertContains($scoreboards, 'Full Rankings', 'scoreboards.php should render the Full Rankings label.');

assertContains($fullEntry, 'Revision : 1.8.0', 'Frontlines full entry should be revised for Full Rankings.');
assertContains($fullEntry, './rankings.php', 'Frontlines full entry should link to Full Rankings.');
assertContains($quickEntry, 'Revision : 1.7.0', 'Frontlines quick entry should be revised for Full Rankings.');
assertContains($quickEntry, './rankings.php', 'Frontlines quick entry should link to Full Rankings.');
assertContains($roster, 'Revision : 1.11.0', 'Frontlines roster should be revised for signed-in Full Rankings navigation.');
assertContains($roster, '$hasFrontlinesAccess', 'Frontlines roster should gate Full Rankings link to signed-in Frontlines users.');

assertContains($navHelper, 'Revision : 1.1.0', 'Frontlines navigation helper should be revised for rankings links.');
assertContains($navHelper, "const rankingsPath = 'rankings.php'", 'Navigation helper should know the rankings path.');
assertContains($navHelper, "const rankingsLabel = 'Full Rankings'", 'Navigation helper should normalize the Full Rankings label.');

echo 'PASS: frontlines-rankings-test.php' . PHP_EOL;

# Example Usage:
#   php .\tests\frontlines-rankings-test.php
