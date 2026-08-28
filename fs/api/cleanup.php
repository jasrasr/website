<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$root = dirname(__DIR__);
$configFile = $root . '/config.local.php';
$legacyConfigFile = dirname($root) . '/FS/config.local.php';
if (!is_file($configFile) && is_file($legacyConfigFile)) {
    $configFile = $legacyConfigFile;
}

function respond(int $status, array $payload): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit;
}

function writeJsonAtomically(string $path, array $payload): void
{
    $temporary = tempnam(dirname($path), 'fs-');
    if ($temporary === false) throw new RuntimeException('Unable to create temporary data file.');
    $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
    if (file_put_contents($temporary, $json, LOCK_EX) === false || !rename($temporary, $path)) {
        @unlink($temporary);
        throw new RuntimeException('Unable to save tracker data.');
    }
    @chmod($path, 0640);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(405, ['ok' => false, 'error' => 'Use POST for cleanup actions.']);
}
if (!is_file($configFile)) respond(503, ['ok' => false, 'error' => 'Missing config.local.php.']);

$config = require $configFile;
if (!is_array($config)) respond(500, ['ok' => false, 'error' => 'Invalid local configuration.']);

$authorization = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? '');
$providedToken = str_starts_with($authorization, 'Bearer ') ? substr($authorization, 7) : '';
$expectedToken = (string) ($config['collector_token'] ?? '');
if ($expectedToken === '' || !hash_equals($expectedToken, $providedToken)) {
    respond(401, ['ok' => false, 'error' => 'Unauthorized cleanup request.']);
}

$snapshotFile = $root . '/storage/api-snapshots.json';
if (!is_file($snapshotFile)) respond(404, ['ok' => false, 'error' => 'No API snapshot file exists.']);

try {
    $raw = (string) file_get_contents($snapshotFile);
    $snapshots = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($snapshots) || !isset($snapshots['entries']) || !is_array($snapshots['entries'])) {
        throw new RuntimeException('Invalid API snapshot file.');
    }

    $kept = [];
    $removed = [];
    foreach ($snapshots['entries'] as $entry) {
        $isInvalidZero = is_array($entry)
            && ($entry['source'] ?? '') === 'freshservice-api'
            && (int) ($entry['unresolved'] ?? -1) === 0;
        if ($isInvalidZero) $removed[] = $entry;
        else $kept[] = $entry;
    }

    if (!$removed) respond(200, ['ok' => true, 'removed' => 0, 'message' => 'No zero-count API snapshots found.']);

    $timezone = new DateTimeZone((string) ($config['timezone'] ?? 'America/New_York'));
    $stamp = (new DateTimeImmutable('now', $timezone))->format('Ymd-His');
    $backupFile = $root . '/storage/api-snapshots.backup-' . $stamp . '.json';
    if (file_put_contents($backupFile, $raw, LOCK_EX) === false) {
        throw new RuntimeException('Unable to create snapshot backup.');
    }
    @chmod($backupFile, 0640);

    $snapshots['entries'] = $kept;
    writeJsonAtomically($snapshotFile, $snapshots);

    respond(200, [
        'ok' => true,
        'removed' => count($removed),
        'remaining' => count($kept),
        'backup' => basename($backupFile),
    ]);
} catch (Throwable $exception) {
    respond(500, ['ok' => false, 'error' => $exception->getMessage()]);
}
