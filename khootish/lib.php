<?php
declare(strict_types=1);

$config = require __DIR__ . '/config.php';

function config(?string $key = null): mixed {
    global $config;
    return $key === null ? $config : ($config[$key] ?? null);
}

function ensure_storage(): void {
    foreach (['games', 'quizzes', 'questions', 'history', 'locks'] as $dir) {
        $path = config('data_dir') . '/' . $dir;
        if (!is_dir($path) && !mkdir($path, 0775, true) && !is_dir($path)) {
            throw new RuntimeException('Unable to initialize storage.');
        }
    }
}

function start_session(): void {
    if (session_status() !== PHP_SESSION_ACTIVE) {
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

function clean_code(string $code): string { return preg_replace('/\D/', '', $code) ?? ''; }
function clean_id(string $id): string { return strtolower(preg_replace('/[^a-z0-9-]/i', '', $id) ?? ''); }
function game_path(string $code): string { return config('data_dir') . '/games/' . clean_code($code) . '.json'; }
function quiz_path(string $id): string { return config('data_dir') . '/quizzes/' . clean_id($id) . '.json'; }
function question_path(string $id): string { return config('data_dir') . '/questions/' . clean_id($id) . '.json'; }
function history_path(string $id): string { return config('data_dir') . '/history/' . clean_id($id) . '.json'; }
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

function normalize_question_text(string $value): string {
    $value = mb_strtolower(trim($value));
    return preg_replace('/\s+/u', ' ', $value) ?? $value;
}

function question_fingerprint(string $text, array $choices): string {
    $parts = [normalize_question_text($text)];
    foreach ($choices as $choice) $parts[] = normalize_question_text((string)$choice);
    return hash('sha256', implode('|', $parts));
}

function read_bank_question(string $id): ?array {
    $id = clean_id($id);
    return with_lock('question:' . $id, LOCK_SH, fn() => decode_file(question_path($id)));
}

function list_bank_questions(string $search = ''): array {
    ensure_storage();
    $needle = normalize_question_text($search);
    $items = [];
    foreach (glob(config('data_dir') . '/questions/*.json') ?: [] as $path) {
        $question = decode_file($path);
        if (!$question) continue;
        if ($needle !== '') {
            $haystack = normalize_question_text((string)($question['text'] ?? '') . ' ' . implode(' ', $question['choices'] ?? []));
            if (mb_strpos($haystack, $needle) === false) continue;
        }
        $items[] = $question;
    }
    usort($items, fn($a, $b) => strcmp((string)($b['updated_at'] ?? ''), (string)($a['updated_at'] ?? '')));
    return $items;
}

function save_bank_question(array $question): array {
    $text = trim((string)($question['text'] ?? ''));
    $choices = array_map(fn($v) => trim((string)$v), $question['choices'] ?? []);
    $correct = (int)($question['correct_index'] ?? -1);
    if ($text === '' || count($choices) !== 4 || in_array('', $choices, true) || $correct < 0 || $correct > 3) {
        throw new RuntimeException('Question Bank entries require question text, four answers, and one correct answer.');
    }
    $fingerprint = question_fingerprint($text, $choices);
    return with_lock('question-bank', LOCK_EX, function() use ($question, $text, $choices, $correct, $fingerprint) {
        foreach (glob(config('data_dir') . '/questions/*.json') ?: [] as $path) {
            $existing = decode_file($path);
            if (($existing['fingerprint'] ?? '') === $fingerprint) return $existing;
        }
        $id = clean_id((string)($question['id'] ?? '')) ?: new_id('question');
        $saved = ['id'=>$id,'text'=>$text,'choices'=>$choices,'correct_index'=>$correct,'fingerprint'=>$fingerprint,'created_at'=>gmdate('c'),'updated_at'=>gmdate('c'),'times_used'=>0];
        atomic_write(question_path($id), $saved);
        return $saved;
    });
}

function increment_bank_question_usage(string $id): void {
    $id = clean_id($id);
    if ($id === '') return;
    with_lock('question:' . $id, LOCK_EX, function() use ($id) {
        $question = decode_file(question_path($id));
        if (!$question) return;
        $question['times_used'] = (int)($question['times_used'] ?? 0) + 1;
        $question['updated_at'] = gmdate('c');
        atomic_write(question_path($id), $question);
    });
}

function new_id(string $prefix): string { return $prefix . '-' . gmdate('YmdHis') . '-' . bin2hex(random_bytes(3)); }

function create_game_data(array $quiz, string $code): array {
    $now = gmdate('c');
    return [
        'code'=>$code,'session_id'=>new_id('game'),'quiz_id'=>$quiz['id'],'title'=>$quiz['title'],
        'created_at'=>$now,'updated_at'=>$now,'started_at'=>null,'finished_at'=>null,'archived_at'=>null,'archive_id'=>null,
        'revision'=>1,'question_seconds'=>(int)config('question_seconds'),'starting_points'=>(int)config('starting_points'),
        'questions'=>$quiz['questions'],'players'=>[],'answers'=>[],
        'state'=>['phase'=>'lobby','question_index'=>-1,'started_at'=>null,'ends_at'=>null],
    ];
}

function create_game_session(array $quiz): array {
    return with_lock('code-allocation', LOCK_EX, function() use ($quiz) {
        do { $code = (string)random_int(100000, 999999); } while (is_file(game_path($code)));
        $game = create_game_data($quiz, $code);
        atomic_write(game_path($code), $game);
        return $game;
    });
}

function ranked_players(array $game): array {
    $players = array_values($game['players'] ?? []);
    usort($players, fn($a,$b) => ((int)$b['score'] <=> (int)$a['score']) ?: strcmp((string)$a['team_name'], (string)$b['team_name']));
    $ranked = [];
    $previousScore = null;
    $placement = 0;
    foreach ($players as $index => $player) {
        $score = (int)($player['score'] ?? 0);
        if ($previousScore === null || $score !== $previousScore) $placement = $index + 1;
        $previousScore = $score;
        $ranked[] = ['placement'=>$placement,'team_name'=>$player['team_name'],'color'=>$player['color'],'score'=>$score,'player_id'=>$player['id']];
    }
    return $ranked;
}

function archive_game(array $game): array {
    if (!empty($game['archive_id'])) return $game;
    $archiveId = clean_id((string)($game['session_id'] ?? '')) ?: new_id('game');
    $finishedAt = (string)($game['finished_at'] ?? gmdate('c'));
    $questionStats = [];
    foreach ($game['questions'] ?? [] as $question) {
        $answers = array_values($game['answers'][$question['id']] ?? []);
        $correctCount = count(array_filter($answers, fn($a) => !empty($a['correct'])));
        $responseTimes = [];
        $questionStart = null;
        foreach ($answers as $answer) {
            if (isset($answer['response_time'])) $responseTimes[] = (float)$answer['response_time'];
        }
        $questionStats[] = [
            'question_id'=>$question['id'],'source_question_id'=>$question['source_question_id'] ?? null,'text'=>$question['text'],
            'answer_count'=>count($answers),'correct_count'=>$correctCount,
            'accuracy'=>count($answers) ? round(($correctCount / count($answers)) * 100, 1) : null,
            'average_response_time'=>$responseTimes ? round(array_sum($responseTimes) / count($responseTimes), 3) : null,
            'answers'=>$game['answers'][$question['id']] ?? [],
        ];
    }
    $archive = [
        'id'=>$archiveId,'code'=>$game['code'],'quiz_id'=>$game['quiz_id'],'title'=>$game['title'],
        'created_at'=>$game['created_at'],'started_at'=>$game['started_at'] ?? null,'finished_at'=>$finishedAt,
        'duration_seconds'=>!empty($game['started_at']) ? max(0, strtotime($finishedAt)-strtotime((string)$game['started_at'])) : 0,
        'team_count'=>count($game['players'] ?? []),'rankings'=>ranked_players($game),'question_stats'=>$questionStats,
    ];
    with_lock('history:' . $archiveId, LOCK_EX, fn() => atomic_write(history_path($archiveId), $archive));
    $game['archive_id'] = $archiveId;
    $game['archived_at'] = gmdate('c');
    $game['finished_at'] = $finishedAt;
    return $game;
}

function list_game_history(string $search = ''): array {
    ensure_storage();
    $needle = normalize_question_text($search);
    $items = [];
    foreach (glob(config('data_dir') . '/history/*.json') ?: [] as $path) {
        $item = decode_file($path);
        if (!$item) continue;
        if ($needle !== '') {
            $teams = implode(' ', array_map(fn($r)=>(string)($r['team_name'] ?? ''), $item['rankings'] ?? []));
            $haystack = normalize_question_text(($item['title'] ?? '') . ' ' . ($item['code'] ?? '') . ' ' . ($item['finished_at'] ?? '') . ' ' . $teams);
            if (mb_strpos($haystack, $needle) === false) continue;
        }
        $items[] = $item;
    }
    usort($items, fn($a,$b)=>strcmp((string)($b['finished_at'] ?? ''),(string)($a['finished_at'] ?? '')));
    return $items;
}

function game_statistics(array $history): array {
    $teams = [];
    $questions = [];
    $totalScores = 0;
    $scoreCount = 0;
    $highest = null;
    foreach ($history as $game) {
        foreach ($game['rankings'] ?? [] as $ranking) {
            $key = normalize_question_text((string)$ranking['team_name']);
            $teams[$key] ??= ['team_name'=>$ranking['team_name'],'games'=>0,'wins'=>0,'total_score'=>0,'placement_total'=>0];
            $teams[$key]['games']++;
            $teams[$key]['wins'] += ((int)$ranking['placement'] === 1 ? 1 : 0);
            $teams[$key]['total_score'] += (int)$ranking['score'];
            $teams[$key]['placement_total'] += (int)$ranking['placement'];
            $totalScores += (int)$ranking['score']; $scoreCount++;
            if ($highest === null || (int)$ranking['score'] > (int)$highest['score']) $highest = $ranking;
        }
        foreach ($game['question_stats'] ?? [] as $q) {
            $key = (string)($q['source_question_id'] ?: $q['text']);
            $questions[$key] ??= ['text'=>$q['text'],'times_used'=>0,'answers'=>0,'correct'=>0,'response_total'=>0.0,'response_count'=>0];
            $questions[$key]['times_used']++;
            $questions[$key]['answers'] += (int)$q['answer_count'];
            $questions[$key]['correct'] += (int)$q['correct_count'];
            if ($q['average_response_time'] !== null && (int)$q['answer_count'] > 0) {
                $questions[$key]['response_total'] += (float)$q['average_response_time'] * (int)$q['answer_count'];
                $questions[$key]['response_count'] += (int)$q['answer_count'];
            }
        }
    }
    $teamRows = array_values(array_map(function($t){
        $t['average_score'] = $t['games'] ? round($t['total_score']/$t['games'],1) : 0;
        $t['average_placement'] = $t['games'] ? round($t['placement_total']/$t['games'],2) : 0;
        unset($t['placement_total']); return $t;
    }, $teams));
    usort($teamRows, fn($a,$b)=>($b['wins']<=>$a['wins']) ?: ($b['total_score']<=>$a['total_score']));
    $questionRows = array_values(array_map(function($q){
        $q['accuracy'] = $q['answers'] ? round(($q['correct']/$q['answers'])*100,1) : null;
        $q['average_response_time'] = $q['response_count'] ? round($q['response_total']/$q['response_count'],3) : null;
        unset($q['response_total'],$q['response_count']); return $q;
    }, $questions));
    usort($questionRows, fn($a,$b)=>(($a['accuracy'] ?? 101)<=>($b['accuracy'] ?? 101)));
    return ['games_played'=>count($history),'average_score'=>$scoreCount?round($totalScores/$scoreCount,1):0,'highest_score'=>$highest,'teams'=>$teamRows,'questions'=>$questionRows];
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
    $players = array_map(fn($p) => ['id'=>$p['id'],'team_name'=>$p['team_name'],'color'=>$p['color'],'avatar'=>$p['avatar'] ?? null,'score'=>(int)$p['score'],'answered_current'=>($p['answered_question'] ?? null) === ($question['id'] ?? null),'last_correct'=>$p['last_correct'] ?? null,'last_points'=>(int)($p['last_points'] ?? 0)], $players);
    return ['code'=>$game['code'],'quiz_id'=>$game['quiz_id'],'title'=>$game['title'],'phase'=>$phase,'question_index'=>(int)($state['question_index'] ?? -1),'question_number'=>max(0,(int)($state['question_index'] ?? -1)+1),'question_count'=>count($game['questions'] ?? []),'question'=>$safeQuestion,'ends_at'=>$state['ends_at'],'server_time'=>microtime(true),'duration'=>(int)($game['question_seconds'] ?? config('question_seconds')),'players'=>$players,'display_mode'=>$forDisplay,'revision'=>(int)($game['revision'] ?? 0),'archive_id'=>$game['archive_id'] ?? null];
}
