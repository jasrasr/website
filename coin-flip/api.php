<?php
/**
 * File Name: api.php
 * Author: Jason Lamb (with help from ChatGPT)
 * Description: Records coin flips and returns public counts/recent outcomes without exposing visitor metadata.
 * Revision: 1.1.0
 * Created Date: 2026-11-01
 * Modified Date: 2026-11-01
 * Changelog: 1.1.0 - Store timestamped shared flips with private IP and browser metadata.
 */
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');
header('X-Content-Type-Options: nosniff');

$historyDirectory = __DIR__ . '/data';
$historyFile = $historyDirectory . '/history.jsonl';

function respond(int $status, array $body): void
{
    http_response_code($status);
    echo json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function cleanText($value, int $limit): string
{
    if (!is_string($value)) {
        return '';
    }
    $value = trim($value);
    return substr($value, 0, $limit);
}

function readHistory(string $path): array
{
    $heads = 0;
    $tails = 0;
    $recent = [];

    if (!is_file($path) || !is_readable($path)) {
        return ['heads' => 0, 'tails' => 0, 'recent' => []];
    }

    $handle = fopen($path, 'rb');
    if ($handle === false) {
        return ['heads' => 0, 'tails' => 0, 'recent' => []];
    }

    while (($line = fgets($handle)) !== false) {
        $entry = json_decode($line, true);
        if (!is_array($entry) || !isset($entry['outcome'], $entry['timestamp'])) {
            continue;
        }
        if ($entry['outcome'] === 'heads') {
            $heads++;
        } elseif ($entry['outcome'] === 'tails') {
            $tails++;
        } else {
            continue;
        }

        $recent[] = [
            'timestamp' => (string) $entry['timestamp'],
            'outcome' => (string) $entry['outcome'],
        ];
        if (count($recent) > 50) {
            array_shift($recent);
        }
    }

    fclose($handle);
    return ['heads' => $heads, 'tails' => $tails, 'recent' => array_reverse($recent)];
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
    $counts = readHistory($historyFile);
    respond(200, [
        'success' => true,
        'heads' => $counts['heads'],
        'tails' => $counts['tails'],
        'total' => $counts['heads'] + $counts['tails'],
        'recent' => $counts['recent'],
    ]);
}

if ($method !== 'POST') {
    header('Allow: GET, POST');
    respond(405, ['success' => false, 'error' => 'Method not allowed.']);
}

$rawBody = file_get_contents('php://input');
if ($rawBody === false || strlen($rawBody) > 8192) {
    respond(413, ['success' => false, 'error' => 'Request is too large.']);
}

$payload = json_decode($rawBody, true);
if (!is_array($payload) || !in_array($payload['outcome'] ?? null, ['heads', 'tails'], true)) {
    respond(400, ['success' => false, 'error' => 'A valid flip outcome is required.']);
}

if (!is_dir($historyDirectory) && !mkdir($historyDirectory, 0750, true) && !is_dir($historyDirectory)) {
    respond(500, ['success' => false, 'error' => 'History storage is unavailable.']);
}

$browser = is_array($payload['browser'] ?? null) ? $payload['browser'] : [];
$ipAddress = $_SERVER['REMOTE_ADDR'] ?? '';
$record = [
    'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
    'outcome' => $payload['outcome'],
    'ip' => filter_var($ipAddress, FILTER_VALIDATE_IP) ? $ipAddress : '',
    'userAgent' => cleanText($_SERVER['HTTP_USER_AGENT'] ?? '', 500),
    'acceptLanguage' => cleanText($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '', 200),
    'browser' => [
        'language' => cleanText($browser['language'] ?? '', 50),
        'platform' => cleanText($browser['platform'] ?? '', 100),
        'timezone' => cleanText($browser['timezone'] ?? '', 100),
        'screen' => cleanText($browser['screen'] ?? '', 30),
    ],
];

$line = json_encode($record, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
if ($line === false) {
    respond(500, ['success' => false, 'error' => 'Could not encode flip history.']);
}

$handle = fopen($historyFile, 'ab');
if ($handle === false) {
    respond(500, ['success' => false, 'error' => 'History storage is unavailable.']);
}

if (!flock($handle, LOCK_EX)) {
    fclose($handle);
    respond(500, ['success' => false, 'error' => 'Could not lock flip history.']);
}

$written = fwrite($handle, $line . PHP_EOL);
fflush($handle);
flock($handle, LOCK_UN);
fclose($handle);

if ($written === false) {
    respond(500, ['success' => false, 'error' => 'Could not save this flip.']);
}

respond(201, ['success' => true]);
