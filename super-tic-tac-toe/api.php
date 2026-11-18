<?php
declare(strict_types=1);
/* File Name: api.php | Revision: 1.0.0 | Modified: 2026-11-18 */
require_once dirname(__DIR__) . '/1-Framework/bootstrap.php';
use Jasr\Framework\JsonStore;
use Jasr\Framework\SharedIdentity;

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
function game_reply(int $status, bool $success, string $message, mixed $data = null): never
{
    http_response_code($status);
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data,
        'errors' => [],
        'meta' => ['timestamp' => gmdate(DATE_ATOM), 'requestId' => bin2hex(random_bytes(8))],
    ], JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}
function game_auth(): ?\Jasr\Users\Auth
{
    try { return SharedIdentity::connect(); }
    catch (Throwable $error) { return null; }
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$auth = game_auth();
$user = $auth?->user();
$canHistory = $user !== null && $auth->can('super-tic-tac-toe', 'member');
$path = __DIR__ . '/data/scores.json';

if ($method === 'GET') {
    if (!$canHistory) game_reply(200, true, 'Public game is ready.', ['available' => false, 'record' => null]);
    try {
        $record = JsonStore::read($path)['records'][(string) $user['id']] ?? [
            'games' => 0, 'wins' => 0, 'losses' => 0, 'draws' => 0, 'recent' => [],
        ];
        game_reply(200, true, 'Score history loaded.', ['available' => true, 'record' => $record]);
    } catch (Throwable $error) {
        game_reply(500, false, 'Score history is temporarily unavailable.');
    }
}
if ($method !== 'POST') {
    header('Allow: GET, POST');
    game_reply(405, false, 'Method not allowed.');
}
if (!$auth || !$user) game_reply(401, false, 'Sign in to record score history.');
if (!$auth->can('super-tic-tac-toe', 'member')) game_reply(403, false, 'This account does not have game history access.');
$body = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($body) || !$auth->validCsrf($body['csrf'] ?? null)) game_reply(403, false, 'Refresh the page and try again.');
$result = $body['result'] ?? '';
$mode = $body['mode'] ?? '';
$opponent = $body['opponent'] ?? '';
$symbol = $body['symbol'] ?? '';
$size = filter_var($body['size'] ?? null, FILTER_VALIDATE_INT);
if (!in_array($result, ['win', 'loss', 'draw'], true)
    || !in_array($mode, ['super', 'classic'], true)
    || !in_array($opponent, ['computer', 'human'], true)
    || !in_array($symbol, ['X', 'O'], true)
    || !in_array($size, [3, 4, 5, 9], true)
    || ($mode === 'super' && $size !== 9)
    || ($mode === 'classic' && $size === 9)) {
    game_reply(422, false, 'The game result is not valid.');
}
$userId = (string) $user['id'];
try {
    $records = JsonStore::update($path, static function (array $records) use ($userId, $result, $mode, $opponent, $symbol, $size): array {
        $record = $records[$userId] ?? ['games' => 0, 'wins' => 0, 'losses' => 0, 'draws' => 0, 'recent' => []];
        foreach (['games', 'wins', 'losses', 'draws'] as $key) if (!isset($record[$key]) || !is_int($record[$key])) $record[$key] = 0;
        if (!isset($record['recent']) || !is_array($record['recent'])) $record['recent'] = [];
        $record['games']++;
        $record[$result === 'win' ? 'wins' : ($result === 'loss' ? 'losses' : 'draws')]++;
        array_unshift($record['recent'], [
            'result' => $result,
            'mode' => $mode,
            'opponent' => $opponent,
            'symbol' => $symbol,
            'size' => $size,
            'playedAt' => gmdate(DATE_ATOM),
        ]);
        $record['recent'] = array_slice($record['recent'], 0, 50);
        $record['updatedAt'] = gmdate(DATE_ATOM);
        $records[$userId] = $record;
        return $records;
    });
    game_reply(200, true, 'Score recorded.', ['record' => $records[$userId]]);
} catch (Throwable $error) {
    game_reply(500, false, 'Score history could not be saved.');
}
