<?php
declare(strict_types=1);

$config = require __DIR__ . '/config.php';

function config(?string $key = null): mixed {
    global $config;
    return $key === null ? $config : ($config[$key] ?? null);
}

function ensure_storage(): void {
    foreach (['games', 'quizzes', 'locks'] as $dir) {
        $path = config('data_dir') . '/' . $dir;
        if (!is_dir($path) && !mkdir($path, 0775, true) && !is_dir($path)) {
            throw new RuntimeException('Unable to initialize storage.');
        }
    }
}

function start_session(): void {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        // PHP session names must contain only letters and numbers on some hosts.
        session_name('QuizWebInteractive');
        session_start();
    }
}

function json_response(array $payload, int $status = 200): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function request_json(): array {
    $raw = file_get_contents('php://input') ?: '';
    $data = json_decode($raw, true);
    return is_array($data) ? $data : $_POST;
}

function clean_code(string $code): string {
    return strtoupper(preg_replace('/[^A-Z0-9]/', '', $code) ?? '');
}

function clean_id(string $id): string {
    return strtolower(preg_replace('/[^a-z0-9-]/i', '', $id) ?? '');
}

function game_path(string $code): string { return config('data_dir') . '/games/' . clean_code($code) . '.json'; }
function quiz_path(string $id): string { return config('data_dir') . '/quizzes/' . clean_id($id) . '.json'; }
function lock_path(string $scope): string { return config('data_dir') . '/locks/' . hash('sha256', $scope) . '.lock'; }

function with_lock(string $scope, int $operation, callable $callback): mixed {
    ensure_storage();
    $fp = fopen(lock_path($scope), 'c+');
    if (!$fp) throw new RuntimeException('Unable to open storage lock.');
    try {
        if (!flock($fp, $operation)) throw new RuntimeException('Unable to lock storage.');
        return $callback();
    } finally {
        flock($fp, LOCK_UN);
        fclose($fp);
    }
}

function decode_file(string $path): ?array {
    if (!is_file($path)) return null;
    $raw = file_get_contents($path);
    if ($raw === false || $raw === '') return null;
    $value = json_decode($raw, true);
    return is_array($value) ? $value : null;
}

function atomic_write(string $path, array $value): void {
    $dir = dirname($path);
    $tmp = tempnam($dir, '.write-');
    if ($tmp === false) throw new RuntimeException('Unable to create temporary storage file.');
    $json = json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    try {
        if (file_put_contents($tmp, $json, LOCK_EX) === false) throw new RuntimeException('Unable to write storage file.');
        @chmod($tmp, 0664);
        if (!rename($tmp, $path)) throw new RuntimeException('Unable to commit storage update.');
    } finally {
        if (is_file($tmp)) @unlink($tmp);
    }
}

function read_game(string $code): ?array {
    $code = clean_code($code);
    return with_lock('game:' . $code, LOCK_SH, fn() => decode_file(game_path($code)));
}

function read_game_finalized(string $code): ?array {
    $code = clean_code($code);
    return with_lock('game:' . $code, LOCK_EX, function() use ($code) {
        $game = decode_file(game_path($code));
        if (!$game) return null;
        $updated = finalize_if_expired($game);
        if ($updated !== $game) {
            $updated['revision'] = (int)($game['revision'] ?? 0) + 1;
            $updated['updated_at'] = gmdate('c');
            atomic_write(game_path($code), $updated);
        }
        return $updated;
    });
}

function write_game(string $code, array $game): void {
    $code = clean_code($code);
    with_lock('game:' . $code, LOCK_EX, fn() => atomic_write(game_path($code), $game));
}

function mutate_game(string $code, callable $callback): array {
    $code = clean_code($code);
    return with_lock('game:' . $code, LOCK_EX, function() use ($code, $callback) {
        $game = decode_file(game_path($code));
        if (!is_array($game)) throw new RuntimeException('Game not found.');
        $updated = $callback($game);
        $updated['revision'] = (int)($game['revision'] ?? 0) + 1;
        $updated['updated_at'] = gmdate('c');
        atomic_write(game_path($code), $updated);
        return $updated;
    });
}

function read_quiz(string $id): ?array {
    $id = clean_id($id);
    return with_lock('quiz:' . $id, LOCK_SH, fn() => decode_file(quiz_path($id)));
}

function save_quiz(array $quiz): void {
    $id = clean_id((string)$quiz['id']);
    with_lock('quiz:' . $id, LOCK_EX, fn() => atomic_write(quiz_path($id), $quiz));
}

function list_quizzes(): array {
    ensure_storage();
    $items = [];
    foreach (glob(config('data_dir') . '/quizzes/*.json') ?: [] as $path) {
        $quiz = decode_file($path);
        if ($quiz) $items[] = $quiz;
    }
    usort($items, fn($a, $b) => strcmp((string)($b['updated_at'] ?? ''), (string)($a['updated_at'] ?? '')));
    return $items;
}

function new_id(string $prefix): string { return $prefix . '-' . gmdate('YmdHis') . '-' . bin2hex(random_bytes(3)); }

function create_game_session(array $quiz): array {
    return with_lock('code-allocation', LOCK_EX, function() use ($quiz) {
        $alphabet = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';
        do {
            $code = '';
            for ($i = 0; $i < 6; $i++) $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        } while (is_file(game_path($code)));

        $game = [
            'code' => $code,
            'quiz_id' => $quiz['id'],
            'title' => $quiz['title'],
            'created_at' => gmdate('c'),
            'updated_at' => gmdate('c'),
            'revision' => 1,
            'question_seconds' => (int)config('question_seconds'),
            'starting_points' => (int)config('starting_points'),
            'questions' => $quiz['questions'],
            'players' => [],
            'answers' => [],
            'state' => ['phase' => 'lobby', 'question_index' => -1, 'started_at' => null, 'ends_at' => null],
        ];
        atomic_write(game_path($code), $game);
        return $game;
    });
}

function player_id(): string {
    start_session();
    if (empty($_SESSION['player_id'])) $_SESSION['player_id'] = bin2hex(random_bytes(16));
    return (string)$_SESSION['player_id'];
}
function admin_logged_in(): bool { start_session(); return !empty($_SESSION['quiz_admin']); }
function require_admin_api(): void { if (!admin_logged_in()) json_response(['ok' => false, 'error' => 'Admin authentication required.'], 401); }
function current_question(array $game): ?array { $i = (int)($game['state']['question_index'] ?? -1); return $game['questions'][$i] ?? null; }

function finalize_if_expired(array $game): array {
    if (($game['state']['phase'] ?? '') !== 'question') return $game;
    $endsAt = (float)($game['state']['ends_at'] ?? 0);
    if ($endsAt > 0 && microtime(true) >= $endsAt) {
        $game['state']['phase'] = 'results';
        $game['state']['ends_at'] = null;
    }
    return $game;
}

function public_game(array $game, bool $forDisplay = false): array {
    $state = $game['state'];
    $question = current_question($game);
    $phase = $state['phase'] ?? 'lobby';
    $safeQuestion = null;
    if ($question) {
        $safeQuestion = ['id'=>$question['id'],'text'=>$question['text'],'choices'=>$question['choices']];
        if (in_array($phase, ['results','finished'], true)) $safeQuestion['correct_index'] = $question['correct_index'];
    }
    $players = array_values($game['players'] ?? []);
    usort($players, fn($a,$b) => ($b['score'] <=> $a['score']) ?: strcmp($a['team_name'],$b['team_name']));
    $players = array_map(fn($p) => [
        'id'=>$p['id'],'team_name'=>$p['team_name'],'color'=>$p['color'],'avatar'=>$p['avatar'] ?? null,
        'score'=>(int)$p['score'],'answered_current'=>($p['answered_question'] ?? null) === ($question['id'] ?? null),
        'last_correct'=>$p['last_correct'] ?? null,'last_points'=>(int)($p['last_points'] ?? 0),
    ], $players);
    return [
        'code'=>$game['code'],'quiz_id'=>$game['quiz_id'],'title'=>$game['title'],'phase'=>$phase,
        'question_index'=>(int)($state['question_index'] ?? -1),'question_number'=>max(0,(int)($state['question_index'] ?? -1)+1),
        'question_count'=>count($game['questions'] ?? []),'question'=>$safeQuestion,'ends_at'=>$state['ends_at'],
        'server_time'=>microtime(true),'duration'=>(int)($game['question_seconds'] ?? config('question_seconds')),
        'players'=>$players,'display_mode'=>$forDisplay,'revision'=>(int)($game['revision'] ?? 0),
    ];
}
