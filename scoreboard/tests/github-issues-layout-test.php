<?php declare(strict_types=1);
/**
 * Filename: github-issues-layout-test.php
 * Revision : 1.2.0
 * Description : Static verification for GitHub issue driven scoreboard layout updates.
 * Author : Jason Lamb (with help from Codex CLI)
 * Created Date : 2026-06-03
 * Modified Date : 2026-06-13
 * Changelog :
 * 1.0.0 initial release
 * 1.1.0 Updated sort assertions for rank-aware card rendering callbacks
 * 1.2.0 Verify Add Team form labels and Enter-submit helper text
 */

function assertContains(string $haystack, string $needle, string $message): void
{
    if (strpos($haystack, $needle) === false) {
        fwrite(STDERR, "FAIL: {$message}" . PHP_EOL);
        exit(1);
    }
}

$root = dirname(__DIR__);
$appJs = file_get_contents($root . '/public/app.js') ?: '';
$quickJs = file_get_contents($root . '/public/quick-entry.js') ?: '';
$styles = file_get_contents($root . '/public/styles.css') ?: '';
$quickCss = file_get_contents($root . '/public/quick-entry.css') ?: '';

assertContains($appJs, 'sortTeamsByName', 'Admin cards should use a named A-Z team sort helper.');
assertContains($appJs, 'sortTeamsByScore', 'Viewer cards should use a named score-order helper.');
assertContains($appJs, 'sortTeamsByName(data.teams).map((team) => createAdminCard', 'Admin rendering should order team cards A-Z.');
assertContains($appJs, 'sortTeamsByScore(data.teams).map((team) => createViewerCard', 'Viewer rendering should order teams by score.');
assertContains($appJs, 'const quickValues = [1, 10, 100, 1000];', 'Full admin quick buttons should be +1, +10, +100, +1000.');
assertContains($appJs, 'Use custom amount for negative scoring.', 'Full admin should explain manual negative scoring.');
assertContains($appJs, '<span>New Team Name</span>', 'Add Team form should label the new team name field.');
assertContains($appJs, '<span>Color</span>', 'Add Team form should label the color field.');
assertContains($appJs, '<small>or press Enter</small>', 'Add Team button should show the Enter-submit helper text.');

assertContains($quickJs, 'const quickEntryValues = [1, 10, 100, 1000];', 'Quick-entry buttons should be +1, +10, +100, +1000.');
assertContains($quickJs, 'quick-manual-note', 'Quick-entry should show a note for negative manual scoring.');
assertContains($quickJs, 'Enter -1, -10, or another negative number', 'Quick-entry note should explain minus scoring.');

assertContains($styles, 'text-align: center;', 'Shared button styling should center button text.');
assertContains($styles, 'orientation: landscape', 'Viewer mobile landscape layout should have a dedicated rule.');
assertContains($styles, 'repeat(3, minmax(0, 1fr))', 'Viewer mobile landscape layout should support three columns.');
assertContains($styles, 'body.viewer-body', 'Viewer landscape rule should release viewport-height constraints.');
assertContains($styles, 'flex: none;', 'Viewer landscape grid should use normal document flow instead of squeezing rows.');
assertContains($styles, '.add-team-form', 'Shared styles should include the labeled Add Team form layout.');

assertContains($quickCss, '.quick-team-score', 'Quick-entry team score style should be present.');
assertContains($quickCss, '.quick-manual-note', 'Quick-entry negative scoring note style should be present.');

echo 'PASS: github-issues-layout-test.php' . PHP_EOL;

# Example Usage:
#   php .\tests\github-issues-layout-test.php
