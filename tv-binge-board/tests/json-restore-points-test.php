<?php
/**
 * File: tests/json-restore-points-test.php
 * Project: TV Binge Board
 * Description: Regression checks for automatic restore-point backups before JSON files are overwritten.
 * Author: Jason Lamb / ChatGPT
 * Created: 2026-07-02
 * Modified: 2026-07-02
 * Revision: 1.0.0
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/json-store.php';

$testDir = APP_DATA_DIR . DIRECTORY_SEPARATOR . 'test-restore-points';
$target = $testDir . DIRECTORY_SEPARATOR . 'sample.json';
app_ensure_dir($testDir);
file_put_contents($target, json_encode(['version' => 'before'], JSON_PRETTY_PRINT) . PHP_EOL);
$before = file_get_contents($target);

app_save_json($target, ['version' => 'after']);

$after = file_get_contents($target);
$restoreRoot = APP_DATA_DIR . DIRECTORY_SEPARATOR . 'restore-points';
$restoreFiles = is_dir($restoreRoot)
    ? iterator_to_array(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($restoreRoot, FilesystemIterator::SKIP_DOTS)))
    : [];

$matchingRestore = null;
foreach ($restoreFiles as $file) {
    if (!$file->isFile()) {
        continue;
    }
    $path = $file->getPathname();
    if (str_ends_with(str_replace('\\', '/', $path), '/test-restore-points/sample.json')) {
        $matchingRestore = $path;
        break;
    }
}

$failures = [];
if ($after === false || !str_contains($after, '"after"')) {
    $failures[] = 'Target JSON was not overwritten with the new content.';
}
if ($matchingRestore === null) {
    $failures[] = 'No restore-point backup was created for the overwritten JSON file.';
} else {
    $backup = file_get_contents($matchingRestore);
    if ($backup !== $before) {
        $failures[] = 'Restore-point backup did not preserve the original file contents.';
    }
}

@unlink($target);
@rmdir($testDir);

if ($failures) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}

echo 'JSON restore-point checks passed.' . PHP_EOL;

// Example Usage:
//   php .\tests\json-restore-points-test.php
