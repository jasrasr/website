<?php
/**
 * File: tests/search-autosuggest-test.php
 * Project: TV Binge Board
 * Description: Static regression checks for live TMDB autosuggest wiring on the search page.
 * Author: Jason Lamb / ChatGPT
 * Created: 2026-07-02
 * Modified: 2026-07-02
 * Revision: 1.0.0
 */
declare(strict_types=1);

$root = dirname(__DIR__);
$checks = [
    'search input exposes autosuggest status target' => [
        'file' => $root . '/search.php',
        'needles' => ['id="searchQuery"', 'autocomplete="off"', 'id="searchStatus"'],
    ],
    'app js debounces live tmdb suggestions' => [
        'file' => $root . '/assets/js/app.js',
        'needles' => ['searchQuery.addEventListener', 'setTimeout', 'clearTimeout', 'autosuggestDelay', 'latestSuggestRequest'],
    ],
    'app js handles short queries and stale responses' => [
        'file' => $root . '/assets/js/app.js',
        'needles' => ['Keep typing', 'requestId !== latestSuggestRequest', 'Searching TMDB'],
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

echo 'Search autosuggest checks passed.' . PHP_EOL;

// Example Usage:
//   php .\tests\search-autosuggest-test.php