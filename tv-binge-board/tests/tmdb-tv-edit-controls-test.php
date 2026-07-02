<?php
/**
 * File: tests/tmdb-tv-edit-controls-test.php
 * Project: TV Binge Board
 * Description: Static regression checks for TMDB-backed TV edit controls using bounded dropdowns instead of freeform totals.
 * Author: Jason Lamb / ChatGPT
 * Created: 2026-07-02
 * Modified: 2026-07-02
 * Revision: 1.0.2
 */
declare(strict_types=1);

$root = dirname(__DIR__);
$checks = [
    'functions expose bounded tv progress helpers' => [
        'file' => $root . '/includes/functions.php',
        'needles' => ['function app_tv_season_options', 'function app_tv_episode_options', 'data-episode-options'],
    ],
    'tmdb tv edit form uses dropdowns and hides manual totals' => [
        'file' => $root . '/includes/functions.php',
        'needles' => ['<select name="season" data-episode-options=', '<select name="episode">', 'empty($item[' . "'tmdb_id'" . '])'],
    ],
    'update endpoint only accepts manual totals for non tmdb tv items' => [
        'file' => $root . '/api/update-status.php',
        'needles' => ['empty($library[' . "'items'" . '][$index][' . "'tmdb_id'" . '])', '$library[' . "'items'" . '][$index][' . "'total_seasons'" . ']', '$library[' . "'items'" . '][$index][' . "'total_episodes'" . ']'],
    ],
    'app js syncs episode options to selected season' => [
        'file' => $root . '/assets/js/app.js',
        'needles' => ['data-episode-options', 'syncEpisodeOptions', 'select[name="episode"]'],
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

echo 'TMDB TV edit control checks passed.' . PHP_EOL;

// Example Usage:
//   php .\tests\tmdb-tv-edit-controls-test.php
