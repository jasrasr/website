<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$checks = [
    'import review shows duplicate skip default and overwrite option' => [
        'file' => $root . '/import.php',
        'needles' => ['Duplicates are skipped by default', 'overwrite_matches', 'Overwrite matching items'],
    ],
    'staged review stores match metadata' => [
        'file' => $root . '/import.php',
        'needles' => ["'match_status'", "'match_candidates'", "'supplied_fields'"],
    ],
    'confirm flow supports partial overwrite into existing items' => [
        'file' => $root . '/import.php',
        'needles' => ['app_import_apply_uploaded_fields', '$updated++', '$skipped++'],
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
echo "Import review flow checks passed." . PHP_EOL;
