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

function autoRespond(int $status, array $payload): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit;
}

if (!is_file($configFile)) autoRespond(503, ['ok' => false, 'error' => 'Missing config.local.php.']);
$config = require $configFile;
if (!is_array($config)) autoRespond(500, ['ok' => false, 'error' => 'Invalid local configuration.']);

$storageDirectory = $root . '/storage';
if (!is_dir($storageDirectory) && !mkdir($storageDirectory, 0750, true) && !is_dir($storageDirectory)) {
    autoRespond(500, ['ok' => false, 'error' => 'Unable to create storage directory.']);
}

$lockHandle = fopen($storageDirectory . '/auto-pull.lock', 'c');
if ($lockHandle === false) autoRespond(500, ['ok' => false, 'error' => 'Unable to open auto-pull lock.']);
if (!flock($lockHandle, LOCK_EX | LOCK_NB)) {
    fclose($lockHandle);
    autoRespond(200, ['ok' => true, 'pulled' => false, 'reason' => 'A pull is already running.']);
}

try {
    $timezone = new DateTimeZone((string) ($config['timezone'] ?? 'America/New_York'));
    $now = new DateTimeImmutable('now', $timezone);
    $stateFile = $storageDirectory . '/api-state.json';
    $state = is_file($stateFile) ? json_decode((string) file_get_contents($stateFile), true) : null;
    $lastRunValue = is_array($state) ? ($state['lastRun'] ?? null) : null;
    $lastRun = is_string($lastRunValue) && $lastRunValue !== '' ? new DateTimeImmutable($lastRunValue) : null;
    $nextRun = $lastRun?->add(new DateInterval('PT1H'));

    if ($nextRun !== null && $now < $nextRun) {
        flock($lockHandle, LOCK_UN);
        fclose($lockHandle);
        autoRespond(200, [
            'ok' => true,
            'pulled' => false,
            'lastRun' => $lastRun?->setTimezone($timezone)->format(DateTimeInterface::ATOM),
            'nextEligibleRun' => $nextRun->setTimezone($timezone)->format(DateTimeInterface::ATOM),
        ]);
    }

    define('FRESHSERVICE_INTERNAL_COLLECT', true);
    require __DIR__ . '/collect.php';
} catch (Throwable $exception) {
    flock($lockHandle, LOCK_UN);
    fclose($lockHandle);
    autoRespond(500, ['ok' => false, 'error' => $exception->getMessage()]);
}
