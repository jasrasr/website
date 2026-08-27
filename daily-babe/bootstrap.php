<?php

declare(strict_types=1);

const BABY_TRACKER_VERSION = '1.1.0';

$configFile = __DIR__ . '/config/config.php';
$config = require is_file($configFile) ? $configFile : __DIR__ . '/config/config.example.php';
if (!is_array($config)) {
    throw new RuntimeException('Application configuration must return an array.');
}

date_default_timezone_set((string) ($config['timezone'] ?? 'America/New_York'));
ini_set('session.use_strict_mode', '1');
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Lax');
if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
    ini_set('session.cookie_secure', '1');
}
session_name((string) ($config['session_name'] ?? 'baby_daily_session'));
session_start();

$storageRoot = rtrim((string) ($config['storage_path'] ?? (__DIR__ . '/storage')), DIRECTORY_SEPARATOR);
foreach (['data', 'uploads', 'logs', 'backups'] as $folder) {
    $path = $storageRoot . DIRECTORY_SEPARATOR . $folder;
    if (!is_dir($path) && !mkdir($path, 0750, true) && !is_dir($path)) {
        throw new RuntimeException('Unable to initialize application storage.');
    }
}

function storage_path(string $relative = ''): string
{
    global $storageRoot;
    return $storageRoot . ($relative === '' ? '' : DIRECTORY_SEPARATOR . ltrim($relative, '/\\'));
}

function json_read(string $relative, array $default): array
{
    $path = storage_path($relative);
    if (!is_file($path)) {
        return $default;
    }
    $handle = fopen($path, 'rb');
    if ($handle === false) {
        throw new RuntimeException('Unable to read application data.');
    }
    try {
        if (!flock($handle, LOCK_SH)) {
            throw new RuntimeException('Unable to lock application data.');
        }
        $raw = stream_get_contents($handle);
        flock($handle, LOCK_UN);
    } finally {
        fclose($handle);
    }
    $decoded = json_decode((string) $raw, true, 512, JSON_THROW_ON_ERROR);
    return is_array($decoded) ? $decoded : $default;
}

function json_write(string $relative, array $data): void
{
    $path = storage_path($relative);
    $directory = dirname($path);
    if (!is_dir($directory)) {
        mkdir($directory, 0750, true);
    }
    $temporary = tempnam($directory, '.write-');
    if ($temporary === false) {
        throw new RuntimeException('Unable to prepare application data.');
    }
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
    $handle = fopen($temporary, 'wb');
    if ($handle === false) {
        throw new RuntimeException('Unable to write application data.');
    }
    try {
        if (!flock($handle, LOCK_EX) || fwrite($handle, $json) === false) {
            throw new RuntimeException('Unable to save application data.');
        }
        fflush($handle);
        flock($handle, LOCK_UN);
    } finally {
        fclose($handle);
    }
    chmod($temporary, 0640);
    if (!rename($temporary, $path)) {
        unlink($temporary);
        throw new RuntimeException('Unable to finalize application data.');
    }
}

function request_id(): string
{
    static $id;
    return $id ??= bin2hex(random_bytes(8));
}

function audit_event(string $event, array $details = []): void
{
    $entry = json_encode([
        'timestamp' => gmdate('c'),
        'requestId' => request_id(),
        'event' => $event,
        'details' => $details,
    ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
    file_put_contents(storage_path('logs/audit-' . date('Y-m') . '.jsonl'), $entry, FILE_APPEND | LOCK_EX);
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return (string) $_SESSION['csrf'];
}

function require_csrf(): void
{
    $provided = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf'] ?? '';
    if (!is_string($provided) || !hash_equals(csrf_token(), $provided)) {
        json_response(false, 'Your session expired. Refresh and try again.', null, [['code' => 'CSRF_FAILED']], 419);
    }
}

function is_configured(): bool
{
    $auth = json_read('data/auth.json', []);
    return isset($auth['passwordHash']);
}

function is_authenticated(): bool
{
    return !empty($_SESSION['authenticated']);
}

function require_auth(): void
{
    if (!is_authenticated()) {
        json_response(false, 'Please sign in.', null, [['code' => 'AUTH_REQUIRED']], 401);
    }
}

function json_response(bool $success, string $message, mixed $data = null, array $errors = [], int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data,
        'errors' => $errors,
        'meta' => ['timestamp' => gmdate('c'), 'requestId' => request_id()],
    ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    exit;
}

function tracker_settings(): array
{
    global $config;
    return json_read('data/settings.json', [
        'schemaVersion' => 1,
        'babyName' => '',
        'dueDate' => (string) ($config['due_date'] ?? '2026-11-02'),
        'birthDate' => '',
        'birthTime' => '',
        'birthLengthInches' => '',
        'birthWeightPounds' => '',
        'birthWeightOunces' => '',
        'updatedAt' => gmdate('c'),
    ]);
}

function tracker_entries(): array
{
    return json_read('data/entries.json', ['schemaVersion' => 1, 'updatedAt' => gmdate('c'), 'records' => []]);
}

function valid_date(string $date): bool
{
    $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    return $parsed !== false && $parsed->format('Y-m-d') === $date;
}
