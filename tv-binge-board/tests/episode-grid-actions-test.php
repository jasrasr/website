<?php
/**
 * File: tests/episode-grid-actions-test.php
 * Project: TV Binge Board
 * Description: Static regression checks for season-level episode actions and anchor-preserving redirects on the item page.
 * Author: Jason Lamb / ChatGPT
 * Created: 2026-07-02
 * Modified: 2026-07-02
 * Revision: 1.0.1
 */
declare(strict_types=1);

$root = dirname(__DIR__);
$checks = [
    'item page exposes season-level action forms' => [
        'file' => $root . '/item.php',
        'needles' => ['name="action" value="mark_season_watched"', 'name="action" value="mark_season_unwatched"', 'class="season-actions"'],
    ],
    'item page preserves season anchor after episode toggle' => [
        'file' => $root . '/item.php',
        'needles' => ["'season=' . \$seasonNumber", "'#season-' . \$seasonNumber", "\$requestedSeason = max(0, (int)(\$_GET['season'] ?? 1));"],
    ],
    'toggle endpoint supports season-level actions' => [
        'file' => $root . '/api/toggle-episode.php',
        'needles' => ["\$action = (string)(\$_POST['action'] ?? 'toggle_episode');", "case 'mark_season_watched':", "case 'mark_season_unwatched':"],
    ],
];

$failures = [];
foreach ($checks as $label => $check) {
    $contents = file_get_contents($check['file']);
    if ($contents === false) {
        $failures[] = $label . ': could not read ' . $check['file'];
        continue;
    }
    foreach ($check['needles'] as $needle) {
        if (!str_contains($contents, $needle)) {
            $failures[] = $label . ': missing ' . $needle;
        }
    }
}

if ($failures) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}

echo 'Episode grid action checks passed.' . PHP_EOL;

// Example Usage:
//   php .\tests\episode-grid-actions-test.php
