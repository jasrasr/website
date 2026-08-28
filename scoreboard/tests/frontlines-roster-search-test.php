<?php declare(strict_types=1);
/**
 * Filename: frontlines-roster-search-test.php
 * Revision : 1.1.0
 * Description : Static verification for the Frontlines roster search UI and assets.
 * Author : Jason Lamb (with help from ChatGPT)
 * Created Date : 2026-06-20
 * Modified Date : 2026-06-21
 * Changelog :
 * 1.0.0 Initial roster search wiring checks
 * 1.1.0 Verify search can narrow visible cards to matching people/sponsors
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
$pagePath = $root . '/frontlines/teams.php';
$scriptPath = $root . '/frontlines/roster-search.js';
$stylePath = $root . '/frontlines/roster-search.css';

assertFileExistsLocal($pagePath, 'Frontlines roster page should exist.');
assertFileExistsLocal($scriptPath, 'Roster search JavaScript should exist.');
assertFileExistsLocal($stylePath, 'Roster search CSS should exist.');

$page = file_get_contents($pagePath) ?: '';
$script = file_get_contents($scriptPath) ?: '';
$style = file_get_contents($stylePath) ?: '';

assertContains($page, 'id="roster-search-input"', 'Roster page should render a search input.');
assertContains($page, 'id="roster-search-clear"', 'Roster page should render a clear button.');
assertContains($page, 'id="roster-search-status"', 'Roster page should render an accessible result status.');
assertContains($page, 'id="roster-search-empty"', 'Roster page should render a no-results state.');
assertContains($page, 'roster-search.js', 'Roster page should load roster-search.js.');
assertContains($page, 'roster-search.css', 'Roster page should load roster-search.css.');

assertContains($script, "document.querySelectorAll('.roster-card')", 'Search script should index roster cards.');
assertContains($script, "querySelectorAll('[data-roster-search-item]')", 'Search should index individual roster people and sponsor rows.');
assertContains($script, 'rosterSearchMatchOnly', 'Search should mark cards as match-only while a query is active.');
assertContains($script, 'item.hidden = !itemMatches', 'Search should hide nonmatching rows inside visible matching team cards.');
assertContains($script, 'tokens.every', 'Search should support multiple search terms.');
assertContains($script, 'card.hidden', 'Search should hide nonmatching team cards.');
assertContains($script, "event.key === 'Escape'", 'Escape should clear the search.');

assertContains($style, '.roster-search-row', 'Search controls should have responsive layout styles.');
assertContains($style, '.roster-card[hidden]', 'Hidden search results should be removed from layout.');
assertContains($style, '.roster-card[data-roster-search-match-only="true"]', 'Match-only cards should suppress nonmatching roster sections.');
assertContains($style, '@media (max-width: 720px)', 'Search controls should adapt on mobile.');

echo 'PASS: frontlines-roster-search-test.php' . PHP_EOL;

# Example Usage:
#   php .\tests\frontlines-roster-search-test.php
