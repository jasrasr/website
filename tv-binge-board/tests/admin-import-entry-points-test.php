<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$checks = [
    'admin users page exposes import action per non-admin user' => [
        'file' => $root . '/admin/users.php',
        'needles' => ['../import.php?u=', '>Import</a>'],
    ],
    'import page accepts admin target-user context' => [
        'file' => $root . '/import.php',
        'needles' => ['app_is_admin($user)', '$targetUsername', 'Target user:'],
    ],
    'import page exposes sample csv download' => [
        'file' => $root . '/import.php',
        'needles' => ['sample=1', 'Download sample CSV'],
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
echo "Admin import entry point checks passed." . PHP_EOL;
