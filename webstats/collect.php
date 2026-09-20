<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
use Jasr\Framework\Response;
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header('Vary: Origin');
try {
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if (!isset(config()['origins'][$origin])) fail(403, 'Origin not allowed.');
    header('Access-Control-Allow-Origin: ' . $origin);
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        header('Access-Control-Allow-Methods: POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type');
        http_response_code(204); exit;
    }
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Allow: POST, OPTIONS'); fail(405, 'POST required.'); }
    if (($_SERVER['HTTP_DNT'] ?? '') === '1' || ($_SERVER['HTTP_SEC_GPC'] ?? '') === '1') {
        Response::json(200, 'Privacy preference respected.', ['recorded' => false]);
    }
    $body = file_get_contents('php://input', false, null, 0, 8193);
    if ($body === false || strlen($body) > 8192) fail(413, 'Payload too large.');
    $input = json_decode($body, true);
    $event = is_array($input) ? normalize_event($input, $origin) : null;
    if (!$event) fail(400, 'Invalid or excluded event.');
    // Never trust X-Forwarded-For supplied by public callers.
    if (!allowance('collect:' . ($_SERVER['REMOTE_ADDR'] ?? ''), config()['events_per_ip_per_minute'], 60)) fail(429, 'Rate limited.');
    save_event($event);
    Response::json(200, 'Event accepted.', ['recorded' => true]);
} catch (OverflowException $exception) {
    fail(429, 'Daily event capacity reached.');
} catch (Throwable $exception) {
    error_log('Webstats collector: ' . get_class($exception));
    fail(503, 'Webstats unavailable.');
}
