<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$checks = [
    'template csv includes type, tmdb_id, and JAG sample row' => [
        'file' => $root . '/includes/functions.php',
        'needles' => ['function app_import_template_csv', "'type','title','year','tmdb_id','status','rating','season','episode','notes'", 'JAG', '4376'],
    ],
    'import helpers capture supplied fields and partial overwrite support' => [
        'file' => $root . '/includes/functions.php',
        'needles' => ['function app_import_supplied_fields', 'function app_import_apply_uploaded_fields', "'supplied_fields'"],
    ],
    'tmdb helpers expose guided match builder' => [
        'file' => $root . '/includes/functions.php',
        'needles' => ['function app_import_match_review_item', "'match_status'", "'match_candidates'"],
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
echo "Import template and matching checks passed." . PHP_EOL;
