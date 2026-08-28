<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$checks = [
    'import review exposes inline match controls' => [
        'file' => $root . '/import.php',
        'needles' => ['Needs match', 'Find match', 'data-match-query'],
    ],
    'match search endpoint exists' => [
        'file' => $root . '/api/import-match-search.php',
        'needles' => ['app_require_login', 'app_tmdb_search', 'Content-Type: application/json'],
    ],
    'app js wires inline row matching' => [
        'file' => $root . '/assets/js/app.js',
        'needles' => ['.import-match', 'data-find-match', 'api/import-match-search.php'],
    ],
];
$failures = [];
foreach ($checks as $label => $check) {
    $contents = file_get_contents($check['file']);
    if ($contents === false) { $failures[] = $label . ': could not read file'; continue; }
    foreach ($check['needles'] as $needle) {
        if (!str_contains($contents, $needle)) { $failures[] = $label . ': missing ' . $needle; }
    }
}
if ($failures) { fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL); exit(1); }
echo "Import inline match checks passed." . PHP_EOL;
