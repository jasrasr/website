<?php
/*
Filename : common.php
Revision : 1.2
Description : Shared helper functions for the self-hosted PSNotify web endpoint and viewer.
Author : Jason Lamb (with help from ChatGPT)
Created Date : 2026-03-20
Modified Date : 2026-05-27
Changelog :
1.0 initial release
1.1 standardized header and changelog format
1.2 added fail-closed config checks, publish rate limiting, message size validation, and session-based viewer auth
*/

require_once __DIR__ . '/config.php';

date_default_timezone_set(APP_TIMEZONE);

function psnotify_ensure_data_file(): void
{
    $dir = dirname(DATA_FILE);

    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }

    if (!file_exists(DATA_FILE)) {
        file_put_contents(DATA_FILE, json_encode([], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
    }
}

function psnotify_json_response(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

function psnotify_text_response(string $text, int $statusCode = 200): void
{
    http_response_code($statusCode);
    header('Content-Type: text/plain; charset=utf-8');
    echo $text;
    exit;
}

function psnotify_request_headers(): array
{
    if (function_exists('getallheaders')) {
        $headers = getallheaders();
        if (is_array($headers)) {
            return $headers;
        }
    }

    $headers = [];
    foreach ($_SERVER as $key => $value) {
        if (str_starts_with($key, 'HTTP_')) {
            $name = str_replace('_', '-', substr($key, 5));
            $headers[$name] = $value;
        }
    }

    return $headers;
}

function psnotify_header_value(string $name, string $default = ''): string
{
    $headers = psnotify_request_headers();

    foreach ($headers as $headerName => $value) {
        if (strcasecmp($headerName, $name) === 0) {
            return trim((string) $value);
        }
    }

    return $default;
}

function psnotify_is_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['SERVER_PORT'] ?? '') === '443');
}

function psnotify_start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => psnotify_is_https(),
        'httponly' => true,
        'samesite' => 'Lax'
    ]);

    session_start();
}

function psnotify_view_session_is_valid(): bool
{
    if (!REQUIRE_VIEW_KEY) {
        return true;
    }

    psnotify_start_session();

    return !empty($_SESSION['psnotify_view_allowed']);
}

function psnotify_authorize_view_session(string $key): bool
{
    psnotify_require_configured();

    if (!REQUIRE_VIEW_KEY) {
        return true;
    }

    if ($key === '' || !hash_equals(VIEW_KEY, $key)) {
        return false;
    }

    psnotify_start_session();
    $_SESSION['psnotify_view_allowed'] = true;

    return true;
}

function psnotify_ensure_storage_ready(): void
{
    psnotify_ensure_data_file();
}

function psnotify_require_configured(): void
{
    if (!PSNOTIFY_CONFIGURED) {
        psnotify_json_response([
            'ok' => false,
            'error' => 'PSNotify is not configured. Create config.local.php from config.sample.php.'
        ], 503);
    }
}

function psnotify_load_notifications(): array
{
    psnotify_ensure_storage_ready();
    $json = file_get_contents(DATA_FILE);
    $items = json_decode((string) $json, true);

    return is_array($items) ? $items : [];
}

function psnotify_save_notifications(array $items): void
{
    psnotify_ensure_storage_ready();
    file_put_contents(DATA_FILE, json_encode(array_values($items), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
}

function psnotify_load_rate_limits(): array
{
    psnotify_ensure_storage_ready();

    if (!file_exists(RATE_LIMIT_FILE)) {
        return [];
    }

    $json = file_get_contents(RATE_LIMIT_FILE);
    $items = json_decode((string) $json, true);

    return is_array($items) ? $items : [];
}

function psnotify_save_rate_limits(array $items): void
{
    psnotify_ensure_storage_ready();
    file_put_contents(RATE_LIMIT_FILE, json_encode($items, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
}

function psnotify_append_notification(array $item): void
{
    $items = psnotify_load_notifications();
    $items[] = $item;

    if (count($items) > MAX_ITEMS) {
        $items = array_slice($items, -MAX_ITEMS);
    }

    psnotify_save_notifications($items);
}

function psnotify_enforce_message_size(string $message): void
{
    if (strlen($message) > MAX_MESSAGE_BYTES) {
        psnotify_json_response([
            'ok' => false,
            'error' => 'Message body is too large.'
        ], 413);
    }
}

function psnotify_enforce_publish_rate_limit(string $topic): void
{
    if (RATE_LIMIT_SECONDS <= 0) {
        return;
    }

    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    $tokenFingerprint = hash('sha256', psnotify_header_value('X-PSNotify-Token', '') . '|' . (string) ($_POST['token'] ?? ''));
    $key = hash('sha256', $ip . '|' . $topic . '|' . $tokenFingerprint);
    $now = time();
    $items = psnotify_load_rate_limits();

    foreach ($items as $itemKey => $lastSeen) {
        if (($now - (int) $lastSeen) > 3600) {
            unset($items[$itemKey]);
        }
    }

    if (isset($items[$key]) && ($now - (int) $items[$key]) < RATE_LIMIT_SECONDS) {
        psnotify_json_response([
            'ok' => false,
            'error' => 'Too many publish requests. Try again shortly.'
        ], 429);
    }

    $items[$key] = $now;
    psnotify_save_rate_limits($items);
}

function psnotify_clean_topic(string $topic): string
{
    $topic = trim($topic);
    $topic = preg_replace('/[^A-Za-z0-9._~-]/', '-', $topic) ?? '';
    $topic = trim($topic, '-');

    return $topic !== '' ? $topic : DEFAULT_TOPIC;
}

function psnotify_split_tags(string $tagString): array
{
    $parts = preg_split('/\s*,\s*/', trim($tagString), -1, PREG_SPLIT_NO_EMPTY);
    return is_array($parts) ? array_values($parts) : [];
}

function psnotify_normalize_priority(string $priority): string
{
    $priority = strtolower(trim($priority));
    $allowed = ['default', 'min', 'low', 'high', 'max'];

    return in_array($priority, $allowed, true) ? $priority : 'default';
}

function psnotify_require_publish_auth(): void
{
    psnotify_require_configured();

    if (!REQUIRE_PUBLISH_TOKEN) {
        return;
    }

    $token = (string) ($_POST['token'] ?? psnotify_header_value('X-PSNotify-Token', ''));

    if ($token === '' || !hash_equals(PUBLISH_TOKEN, $token)) {
        psnotify_json_response([
            'ok' => false,
            'error' => 'Unauthorized publish request.'
        ], 401);
    }
}

function psnotify_require_view_auth(): void
{
    psnotify_require_configured();

    if (!REQUIRE_VIEW_KEY) {
        return;
    }

    if (psnotify_view_session_is_valid()) {
        return;
    }

    $key = (string) psnotify_header_value('X-PSNotify-View-Key', '');

    if ($key === '' || !hash_equals(VIEW_KEY, $key)) {
        psnotify_json_response([
            'ok' => false,
            'error' => 'Unauthorized viewer request.'
        ], 401);
    }
}

function psnotify_send_optional_email(array $item): void
{
    if (!ENABLE_EMAIL_FORWARD || EMAIL_TO === '' || EMAIL_FROM === '') {
        return;
    }

    $subjectTitle = trim((string) ($item['title'] ?? ''));
    $subject = EMAIL_SUBJECT_PREFIX . ($subjectTitle !== '' ? $subjectTitle : 'New notification');

    $lines = [
        'Topic : ' . ($item['topic'] ?? ''),
        'Priority : ' . ($item['priority'] ?? ''),
        'Time : ' . ($item['created_local'] ?? ''),
        '',
        (string) ($item['message'] ?? '')
    ];

    $headers = [
        'From: ' . EMAIL_FROM,
        'Reply-To: ' . EMAIL_FROM,
        'Content-Type: text/plain; charset=UTF-8'
    ];

    @mail(EMAIL_TO, $subject, implode("\n", $lines), implode("\r\n", $headers));
}

function psnotify_base_url(): string
{
    $scheme = psnotify_is_https() ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '/psnotify/index.php';
    $path = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');

    return $scheme . '://' . $host . ($path !== '' ? $path : '');
}
