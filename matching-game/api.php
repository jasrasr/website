<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

function matching_reply(int $status, string $message, mixed $data = null): never
{
    http_response_code($status);
    echo json_encode(['success' => $status < 400, 'message' => $message, 'data' => $data], JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

$identity = null;
try {
    require_once dirname(__DIR__) . '/1-Framework/bootstrap.php';
    $identity = \Jasr\Framework\SharedIdentity::connect();
} catch (Throwable $error) {
    // Public game remains available when identity is not configured.
}
$user = $identity?->user();
$canSave = $user !== null && $identity->can('matching-game', 'member');
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$path = __DIR__ . '/data/scores.json';
$empty = ['games' => 0, 'recent' => []];

if ($method === 'GET') {
    if (!$canSave) matching_reply(200, 'Public game is ready.', ['available' => false, 'record' => null]);
    try {
        $record = \Jasr\Framework\JsonStore::read($path)['records'][(string) $user['id']] ?? $empty;
        matching_reply(200, 'Saved games loaded.', ['available' => true, 'record' => $record]);
    } catch (Throwable $error) {
        matching_reply(500, 'Saved games are temporarily unavailable.');
    }
}
if ($method !== 'POST') {
    header('Allow: GET, POST');
    matching_reply(405, 'Method not allowed.');
}
if ($user === null) matching_reply(401, 'Sign in to save game history.');
if (!$canSave) matching_reply(403, 'This account does not have Matching Game access.');
if ((int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 4096) matching_reply(413, 'Game details are too large.');
$body = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($body) || !$identity->validCsrf($body['csrf'] ?? null)) matching_reply(403, 'Refresh the page and try again.');

$gameId = $body['gameId'] ?? null;
$rows = $body['rows'] ?? null;
$columns = $body['columns'] ?? null;
$elapsedMs = $body['elapsedMs'] ?? null;
$attempts = $body['attempts'] ?? null;
$mode = $body['mode'] ?? 'colors';
if (!is_string($gameId) || preg_match('/^[a-zA-Z0-9-]{8,80}$/D', $gameId) !== 1
    || !in_array($mode, ['colors', 'letters', 'numbers'], true)
    || !is_int($rows) || $rows < 2 || $rows > 6
    || !is_int($columns) || $columns < 2 || $columns > 6 || ($rows * $columns) % 2 !== 0
    || !is_int($elapsedMs) || $elapsedMs < 0 || $elapsedMs > 86400000
    || !is_int($attempts) || $attempts < intdiv($rows * $columns, 2) || $attempts > 10000) {
    matching_reply(422, 'The game result is not valid.');
}

$userId = (string) $user['id'];
try {
    $records = \Jasr\Framework\JsonStore::update($path, static function (array $records) use ($userId, $gameId, $mode, $rows, $columns, $elapsedMs, $attempts): array {
        $record = $records[$userId] ?? ['games' => 0, 'recent' => []];
        if (!isset($record['recent']) || !is_array($record['recent'])) $record['recent'] = [];
        foreach ($record['recent'] ?? [] as $entry) {
            if (($entry['gameId'] ?? null) === $gameId) return $records;
        }
        $record['games'] = (int) ($record['games'] ?? 0) + 1;
        array_unshift($record['recent'], [
            'gameId' => $gameId,
            'mode' => $mode,
            'rows' => $rows,
            'columns' => $columns,
            'elapsedMs' => $elapsedMs,
            'attempts' => $attempts,
            'playedAt' => gmdate(DATE_ATOM),
        ]);
        $record['recent'] = array_slice($record['recent'], 0, 50);
        $records[$userId] = $record;
        return $records;
    });
    matching_reply(200, 'Game saved.', ['record' => $records[$userId]]);
} catch (Throwable $error) {
    matching_reply(500, 'Game history could not be saved.');
}
