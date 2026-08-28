<?php
/**
 * File: tests/deployment-safety-test.php
 * Project: TV Binge Board
 * Description: Static regression checks for runtime data protection and asset cache busting.
 * Author: Jason Lamb / ChatGPT
 * Created: 2026-07-02
 * Modified: 2026-07-02
 * Revision: 1.0.0
 */
declare(strict_types=1);

$root = dirname(__DIR__);
$failures = [];

function require_contains(string $label, string $file, array $needles, array &$failures): void
{
    $contents = file_get_contents($file);
    if ($contents === false) {
        $failures[] = $label . ': could not read ' . $file;
        return;
    }
    foreach ($needles as $needle) {
        if (!str_contains($contents, $needle)) {
            $failures[] = $label . ': missing ' . $needle;
        }
    }
}

require_contains('app version is current', $root . '/includes/config.php', ["const APP_VERSION = '1.4.3';"], $failures);
require_contains('service worker cache is current', $root . '/service-worker.js', ["tv-binge-board-rev-1.4.3"], $failures);
require_contains('page assets are versioned', $root . '/includes/functions.php', ["assets/css/app.css?v=' . rawurlencode(APP_VERSION)", "assets/js/app.js?v=' . rawurlencode(APP_VERSION)"], $failures);
require_contains('runtime data ignore rules are active', $root . '/.gitignore', [
    'data/accounts.json',
    'data/settings.json',
    'data/activity-log.json',
    'data/login-attempts.json',
    'data/users/*/library.json',
    'data/users/*/profile.json',
    'data/users/*/connections.json',
], $failures);

$tracked = [];
$gitOutput = [];
$code = 0;
exec('git -C ' . escapeshellarg($root) . ' ls-files data', $gitOutput, $code);
if ($code === 0) {
    $forbidden = [
        'data/accounts.json',
        'data/settings.json',
        'data/activity-log.json',
        'data/login-attempts.json',
        'library.json',
        'profile.json',
        'connections.json',
    ];
    foreach ($gitOutput as $trackedPath) {
        foreach ($forbidden as $forbiddenPath) {
            if ($trackedPath === $forbiddenPath || str_ends_with($trackedPath, '/' . $forbiddenPath)) {
                $tracked[] = $trackedPath;
            }
        }
    }
}
if ($tracked) {
    $failures[] = 'runtime data files are tracked: ' . implode(', ', $tracked);
}

if ($failures) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}

echo 'Deployment safety checks passed.' . PHP_EOL;

// Example Usage:
//   php .\tests\deployment-safety-test.php
