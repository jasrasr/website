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

function scheduleRespond(int $status, array $payload): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit;
}

if (!is_file($configFile)) scheduleRespond(503, ['ok' => false, 'error' => 'Missing config.local.php.']);
$config = require $configFile;
if (!is_array($config)) scheduleRespond(500, ['ok' => false, 'error' => 'Invalid local configuration.']);

$authorization = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? '');
$providedToken = str_starts_with($authorization, 'Bearer ') ? substr($authorization, 7) : '';
$expectedToken = (string) ($config['collector_token'] ?? '');
if ($expectedToken === '' || !hash_equals($expectedToken, $providedToken)) {
    scheduleRespond(401, ['ok' => false, 'error' => 'Unauthorized scheduled request.']);
}

try {
    $timezone = new DateTimeZone((string) ($config['timezone'] ?? 'America/New_York'));
    $now = new DateTimeImmutable('now', $timezone);
    $dayOfWeek = (int) $now->format('N');
    $hour = (int) $now->format('G');
    $isWeekend = $dayOfWeek >= 6;
    $isWeekdayDaytime = !$isWeekend && $hour >= 6 && $hour < 18;
    $intervalHours = $isWeekend ? 12 : ($isWeekdayDaytime ? 1 : 4);

    $stateFile = $root . '/storage/api-state.json';
    $state = is_file($stateFile) ? json_decode((string) file_get_contents($stateFile), true) : null;
    $lastRunValue = is_array($state) ? ($state['lastRun'] ?? null) : null;
    $lastRun = is_string($lastRunValue) && $lastRunValue !== '' ? new DateTimeImmutable($lastRunValue) : null;
    $nextRun = $lastRun?->add(new DateInterval('PT' . $intervalHours . 'H'));

    if ($nextRun !== null && $now < $nextRun) {
        scheduleRespond(200, [
            'ok' => true,
            'pulled' => false,
            'intervalHours' => $intervalHours,
            'lastRun' => $lastRun?->setTimezone($timezone)->format(DateTimeInterface::ATOM),
            'nextEligibleRun' => $nextRun->setTimezone($timezone)->format(DateTimeInterface::ATOM),
        ]);
    }

    require __DIR__ . '/collect.php';
} catch (Throwable $exception) {
    scheduleRespond(500, ['ok' => false, 'error' => $exception->getMessage()]);
}
