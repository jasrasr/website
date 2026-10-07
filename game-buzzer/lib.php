<?php
declare(strict_types=1);

function gb_fail(string $message, int $status = 422): never {
    throw new RuntimeException($message, $status);
}
function gb_string(array $in, string $key, int $max = 100): string {
    $value = $in[$key] ?? '';
    if (!is_string($value) || strlen($value) > $max || trim($value) === '') gb_fail('Invalid ' . $key . '.');
    return trim($value);
}
function gb_token(): string { return bin2hex(random_bytes(24)); }
function gb_config(): array {
    $path = __DIR__ . '/config.local.php';
    $c = is_file($path) ? require $path : [];
    if (!is_array($c)) gb_fail('Invalid server configuration.', 503);
    $c['data_dir'] = $c['data_dir'] ?? __DIR__ . '/data';
    return $c;
}
function gb_dir(array $config): string {
    $dir = $config['data_dir'];
    if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) gb_fail('Storage unavailable.', 503);
    return $dir;
}
function gb_leader_question_state(array $config, array $in): array {
    $token = gb_string($in, 'leader', 48);
    if (!preg_match('/^[a-f0-9]{48}$/D', $token)) gb_fail('Invalid leader token.');
    $fingerprint = substr(hash('sha256', $token), 0, 12);
    $kind = gb_string($in, 'kind', 20);
    if (!in_array($kind, ['state', 'vote', 'suggest'], true)) gb_fail('Unknown question-poll action.');

    $dir = gb_dir($config);
    $lock = fopen($dir . '/leader-questions.lock', 'c+');
    if (!$lock || !flock($lock, LOCK_EX)) gb_fail('Question poll busy. Please retry.', 503);
    $path = $dir . '/leader-questions.json';
    $tmp = null;
    try {
        $store = ['votes' => [], 'custom' => [], 'activity' => []];
        if (is_file($path)) {
            $loaded = json_decode((string)file_get_contents($path), true, 32, JSON_THROW_ON_ERROR);
            if (!is_array($loaded) || !is_array($loaded['votes'] ?? null) || !is_array($loaded['custom'] ?? null)) gb_fail('Question poll storage is invalid.', 503);
            $store = $loaded + ['activity' => []];
            if (!is_array($store['activity'])) $store['activity'] = [];
            $normalizedVotes = [];
            foreach ($store['votes'] as $id => $selected) {
                $normalizedId = preg_match('/^[a-f0-9]{48}$/D', (string)$id) ? substr(hash('sha256', (string)$id), 0, 12) : (string)$id;
                $normalizedVotes[$normalizedId] = $selected;
            }
            $store['votes'] = $normalizedVotes;
        }

        $bankRows = json_decode((string)file_get_contents(__DIR__ . '/question-bank.json'), true, 32, JSON_THROW_ON_ERROR);
        if (!is_array($bankRows) || count($bankRows) !== 100) gb_fail('Question bank is invalid.', 503);
        $questions = [];
        foreach ($bankRows as $i => $row) {
            if (!is_array($row) || count($row) !== 3) gb_fail('Question bank is invalid.', 503);
            $questions[] = ['id' => 'q' . str_pad((string)($i + 1), 3, '0', STR_PAD_LEFT), 'prompt' => $row[0], 'a' => $row[1], 'b' => $row[2], 'custom' => false];
        }
        foreach ($store['custom'] as $question) {
            if (is_array($question) && isset($question['id'], $question['prompt'], $question['a'], $question['b'])) {
                $questions[] = ['id' => $question['id'], 'prompt' => $question['prompt'], 'a' => $question['a'], 'b' => $question['b'], 'custom' => true];
            }
        }

        $changed = false;
        if ($kind === 'vote') {
            $selected = $in['selected'] ?? null;
            if (!is_array($selected) || !array_is_list($selected)) gb_fail('Choose valid questions.');
            foreach ($selected as $id) if (!is_string($id)) gb_fail('Choose valid questions.');
            if (count(array_unique($selected)) !== count($selected)) gb_fail('Choose each question only once.');
            $valid = array_column($questions, 'id');
            foreach ($selected as $id) if (!in_array($id, $valid, true)) gb_fail('One of the selected questions is unavailable.');
            $store['votes'][$fingerprint] = $selected;
            $activity = $store['activity'][$fingerprint] ?? ['votes' => 0, 'suggestions' => 0, 'lastSeen' => 0];
            $activity['votes']++;
            $activity['selected'] = count($selected);
            $activity['lastSeen'] = time();
            $store['activity'][$fingerprint] = $activity;
            $changed = true;
        } elseif ($kind === 'suggest') {
            $question = [
                'id' => 'c' . bin2hex(random_bytes(8)),
                'prompt' => gb_string($in, 'prompt', 200),
                'a' => gb_string($in, 'a', 120),
                'b' => gb_string($in, 'b', 120),
                'by' => $fingerprint,
            ];
            $store['custom'][] = $question;
            $questions[] = $question + ['custom' => true];
            $activity = $store['activity'][$fingerprint] ?? ['votes' => 0, 'suggestions' => 0, 'lastSeen' => 0];
            $activity['suggestions']++;
            $activity['lastSeen'] = time();
            $store['activity'][$fingerprint] = $activity;
            $changed = true;
        }

        if ($changed) {
            $tmp = tempnam($dir, '.leader-questions-');
            $body = json_encode($store, JSON_THROW_ON_ERROR);
            if (!$tmp || file_put_contents($tmp, $body, LOCK_EX) !== strlen($body) || !rename($tmp, $path)) gb_fail('Unable to save question poll.', 503);
            $tmp = null;
        }

        $counts = [];
        foreach ($store['votes'] as $selected) if (is_array($selected)) foreach ($selected as $id) $counts[$id] = ($counts[$id] ?? 0) + 1;
        foreach ($questions as &$question) $question['count'] = $counts[$question['id']] ?? 0;
        unset($question);
        usort($questions, fn($a, $b) => $b['count'] <=> $a['count'] ?: strcmp($a['id'], $b['id']));
        $activity = [];
        foreach ($store['activity'] as $id => $entry) {
            $votes = (int)($entry['votes'] ?? 0);
            $suggestions = (int)($entry['suggestions'] ?? 0);
            $activity[] = ['fingerprint' => (string)$id, 'votes' => $votes, 'suggestions' => $suggestions,
                'selected' => (int)($entry['selected'] ?? 0), 'lastSeen' => (int)($entry['lastSeen'] ?? 0),
                'flagged' => $votes >= 20 || $suggestions >= 5];
        }
        usort($activity, fn($a, $b) => (($b['votes'] + $b['suggestions']) <=> ($a['votes'] + $a['suggestions'])) ?: strcmp($a['fingerprint'], $b['fingerprint']));
        return ['questions' => $questions, 'selected' => $store['votes'][$fingerprint] ?? [], 'fingerprint' => $fingerprint, 'activity' => $activity];
    } finally {
        if ($tmp !== null && is_file($tmp)) unlink($tmp);
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}
// Separate stable lock + atomic rename: readers never see a truncated room.
// PHP guard also protects room files if .htaccess is not honored.
function gb_room(array $config, string $id, callable $fn, ?array $initial = null): array {
    if (!preg_match('/^[a-f0-9]{12}$/D', $id)) gb_fail('Invalid room code.');
    $dir = gb_dir($config);
    $lock = fopen($dir . '/' . $id . '.lock.php', 'c+');
    if (!$lock || !flock($lock, LOCK_EX)) gb_fail('Room busy. Please retry.', 503);
    $path = $dir . '/' . $id . '.php';
    $tmp = null;
    try {
        if ($initial !== null) {
            if (is_file($path)) gb_fail('Room already exists.', 409);
            $room = $initial;
        } else {
            if (!is_file($path)) gb_fail('Room not found.', 404);
            $raw = file_get_contents($path);
            $room = json_decode(substr($raw, strlen("<?php exit; ?>\n")), true, 512, JSON_THROW_ON_ERROR);
            if ($room['expires'] < time()) gb_fail('This room has expired. Create a new game.', 410);
        }
        $before = $room;
        $result = $fn($room);
        if ($room !== $before || $initial !== null) {
            $room['revision']++;
            $body = "<?php exit; ?>\n" . json_encode($room, JSON_THROW_ON_ERROR);
            $tmp = tempnam($dir, '.room-');
            if (!$tmp || file_put_contents($tmp, $body) !== strlen($body) || !rename($tmp, $path)) gb_fail('Unable to save room.', 503);
            $tmp = null;
        }
        return $result;
    } finally {
        if ($tmp !== null && is_file($tmp)) unlink($tmp);
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}
function gb_new(array $in): array {
    $questions = $in['questions'] ?? json_decode(file_get_contents(__DIR__ . '/questions.json'), true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($questions) || count($questions) !== 10 || !array_is_list($questions)) gb_fail('Provide exactly ten questions.');
    foreach ($questions as &$q) {
        if (!is_array($q)) gb_fail('Invalid question.');
        $q = ['prompt' => gb_string($q, 'prompt', 200), 'a' => gb_string($q, 'a', 120), 'b' => gb_string($q, 'b', 120)];
    }
    unset($q);
    shuffle($questions);
    $teams = [];
    foreach (['6th grade boys', '6th grade girls', '7th grade boys', '7th grade girls', '8th grade boys', '8th grade girls'] as $i => $name) {
        $teams[] = ['id' => $i, 'name' => $name, 'join' => gb_token(), 'score' => 0];
    }
    return ['id' => bin2hex(random_bytes(6)), 'host' => gb_token(), 'title' => gb_string($in, 'title', 80),
        'expires' => time() + 86400, 'revision' => 0, 'teams' => $teams, 'devices' => [],
        'questions' => $questions, 'ballots' => [], 'surveyOpen' => true, 'question' => null,
        'revealed' => [], 'predictions' => [], 'predictionOpen' => false, 'scored' => [],
        'round' => 0, 'openAt' => null, 'closeAt' => null, 'buzzes' => [], 'speedScored' => false, 'awarded' => [], 'operations' => []];
}
function gb_host(array $room, array $in): bool {
    return is_string($in['host'] ?? null) && hash_equals($room['host'], $in['host']);
}
function gb_device(array $room, array $in): array {
    $token = gb_string($in, 'device', 48);
    if (!isset($room['devices'][$token])) gb_fail('Join a team on this phone first.', 403);
    return $room['devices'][$token];
}
function gb_ranking(array $room): array {
    $rows = array_values($room['buzzes']);
    usort($rows, fn($a, $b) => $a['at'] <=> $b['at']);
    return array_map(function ($row) use ($room, $rows) {
        return ['team' => $row['team'], 'elapsedMs' => round(($row['at'] - $room['openAt']) * 1000),
            'gapMs' => round(($row['at'] - $rows[0]['at']) * 1000)];
    }, $rows);
}
function gb_score_speed(array &$room): void {
    if (!empty($room['speedScored'])) return;
    $points = [60, 50, 40, 30, 20, 10];
    foreach (gb_ranking($room) as $place => $entry) {
        if (isset($points[$place])) $room['teams'][$entry['team']]['score'] += $points[$place];
    }
    $room['speedScored'] = true;
}
function gb_results(array $room, int $q): array {
    $a = 0; $b = 0;
    foreach ($room['ballots'] as $ballot) { if ($ballot[$q] === 'a') $a++; else $b++; }
    return ['a' => $a, 'b' => $b, 'majority' => $a === $b ? 'tie' : ($a > $b ? 'a' : 'b')];
}
function gb_view(array $r, array $in): array {
    $host = gb_host($r, $in);
    $device = $r['devices'][$in['device'] ?? ''] ?? null;
    $q = $r['question'];
    $revealed = $q !== null && in_array($q, $r['revealed'], true);
    $v = ['id' => $r['id'], 'title' => $r['title'], 'serverNow' => microtime(true), 'expires' => $r['expires'],
        'teams' => array_map(fn($t) => ['id' => $t['id'], 'name' => $t['name'], 'score' => $t['score']], $r['teams']),
        'surveyOpen' => $r['surveyOpen'], 'ballotCount' => count($r['ballots']), 'questions' => $r['questions'],
        'question' => $q, 'predictionOpen' => $r['predictionOpen'], 'revealed' => $revealed,
        'results' => $revealed ? gb_results($r, $q) : null,
        'predictionTeams' => $q === null ? [] : array_map('intval', array_keys($r['predictions'][$q] ?? [])),
        'predictions' => $revealed ? ($r['predictions'][$q] ?? []) : null,
        'myTeam' => $device['team'] ?? null,
        'myPrediction' => $device !== null && $q !== null ? ($r['predictions'][$q][$device['team']] ?? null) : null,
        'round' => $r['round'], 'openAt' => $r['openAt'], 'closeAt' => $r['closeAt'], 'ranking' => gb_ranking($r),
        'speedScored' => !empty($r['speedScored']),
        'surveySubmitted' => isset($r['ballots'][$in['voter'] ?? ''])];
    if ($host) {
        $v['teamLinks'] = array_map(fn($t) => ['team' => $t['id'], 'key' => $t['join']], $r['teams']);
        $v['devices'] = array_map(fn($t) => count(array_filter($r['devices'], fn($d) => $d['team'] === $t['id'])), $r['teams']);
    }
    return $v;
}
function gb_apply(array &$r, string $action, array $in): array {
    if ($action === 'state') return gb_view($r, $in);
    if ($action === 'join') {
        $key = gb_string($in, 'key', 48);
        $team = null;
        foreach ($r['teams'] as $t) if (hash_equals($t['join'], $key)) $team = $t['id'];
        if ($team === null) gb_fail('Invalid team invitation.', 403);
        $token = gb_string($in, 'device', 48);
        if (!preg_match('/^[a-f0-9]{48}$/D', $token)) gb_fail('Invalid device token.');
        if (isset($r['devices'][$token])) {
            if ($r['devices'][$token]['team'] !== $team) gb_fail('This phone is already on another team.', 409);
        } else {
            if (count(array_filter($r['devices'], fn($d) => $d['team'] === $team)) >= 12) gb_fail('This team already has 12 phones.');
            $r['devices'][$token] = ['team' => $team];
        }
    } elseif ($action === 'vote') {
        if (!$r['surveyOpen']) gb_fail('The arrival survey is closed.', 409);
        $voter = gb_string($in, 'voter', 48);
        if (!preg_match('/^[a-f0-9]{48}$/D', $voter)) gb_fail('Invalid ballot token.');
        $answers = $in['answers'] ?? [];
        if (!is_array($answers) || !array_is_list($answers) || count($answers) !== 10 || array_diff($answers, ['a', 'b'])) gb_fail('Answer all ten questions with A or B.');
        if (isset($r['ballots'][$voter]) && $r['ballots'][$voter] !== $answers) gb_fail('Your answers are already locked.', 409);
        if (!isset($r['ballots'][$voter]) && count($r['ballots']) >= 300) gb_fail('Survey is full.');
        $r['ballots'][$voter] = $answers;
    } elseif ($action === 'buzz') {
        $d = gb_device($r, $in);
        $now = microtime(true); // Server acceptance time, inside the room lock.
        if (($in['round'] ?? null) !== $r['round'] || $r['openAt'] === null || $now < $r['openAt'] || $now >= $r['closeAt']) gb_fail('Buzzer is closed or this round has changed.', 409);
        if (!isset($r['buzzes'][$d['team']])) $r['buzzes'][$d['team']] = ['team' => $d['team'], 'at' => $now];
    } elseif ($action === 'predict') {
        $d = gb_device($r, $in);
        $q = $r['question'];
        if (!$r['predictionOpen'] || $q === null || ($in['question'] ?? null) !== $q) gb_fail('Predictions are closed or the question changed.', 409);
        $answer = $in['answer'] ?? '';
        if (!in_array($answer, ['a', 'b'], true)) gb_fail('Choose A or B.');
        if (isset($r['predictions'][$q][$d['team']]) && $r['predictions'][$q][$d['team']] !== $answer) gb_fail('Your team prediction is already locked.', 409);
        $r['predictions'][$q][$d['team']] = $answer;
    } else {
        if (!gb_host($r, $in)) gb_fail('Host access required.', 403);
        $op = gb_string($in, 'operation', 48);
        if (in_array($op, $r['operations'], true)) return gb_view($r, $in);
        if (count($r['operations']) >= 3000) gb_fail('Start a fresh room for more rounds.');
        switch ($action) {
            case 'closeSurvey':
                if (!count($r['ballots'])) gb_fail('Collect at least one survey first.');
                $r['surveyOpen'] = false;
                break;
            case 'question':
                if ($r['surveyOpen']) gb_fail('Close the arrival survey first.');
                $q = $in['question'] ?? null;
                if (!is_int($q) || $q < 0 || $q > 9) gb_fail('Invalid question.');
                if ($r['question'] === null && $r['round'] > 0) gb_score_speed($r);
                $r['question'] = $q;
                $r['predictionOpen'] = !in_array($q, $r['revealed'], true);
                $r['closeAt'] = microtime(true);
                break;
            case 'reveal':
                $q = $r['question'];
                if ($q === null || ($in['question'] ?? null) !== $q) gb_fail('Select the current question first.');
                $r['predictionOpen'] = false;
                if (!in_array($q, $r['revealed'], true)) $r['revealed'][] = $q;
                if (!in_array($q, $r['scored'], true)) {
                    $result = gb_results($r, $q);
                    foreach ($r['predictions'][$q] ?? [] as $team => $answer) {
                        if ($result['majority'] === 'tie' || $answer === $result['majority']) $r['teams'][$team]['score']++;
                    }
                    $r['scored'][] = $q;
                }
                break;
            case 'arm':
                $duration = $in['duration'] ?? 15;
                if (!is_int($duration) || $duration < 5 || $duration > 60) gb_fail('Choose a 5–60 second round.');
                $r['round']++; $r['buzzes'] = []; $r['speedScored'] = false; $r['awarded'] = [];
                $r['question'] = null; $r['predictionOpen'] = false;
                $r['openAt'] = microtime(true) + 3;
                $r['closeAt'] = $r['openAt'] + $duration;
                break;
            case 'closeBuzz':
                if (($in['round'] ?? null) !== $r['round']) gb_fail('Round changed.', 409);
                $r['closeAt'] = microtime(true);
                gb_score_speed($r);
                if ($r['question'] === null && !$r['surveyOpen']) {
                    foreach (array_keys($r['questions']) as $q) {
                        if (!in_array($q, $r['revealed'], true)) {
                            $r['question'] = $q;
                            $r['predictionOpen'] = true;
                            break;
                        }
                    }
                }
                break;
            case 'award':
                $team = $in['team'] ?? null;
                if (($in['round'] ?? null) !== $r['round'] || !is_int($team) || !isset($r['buzzes'][$team])) gb_fail('Select a team that buzzed in this round.');
                if (microtime(true) < ($r['closeAt'] ?? 0)) gb_fail('Close the buzzer before awarding points.');
                if (!in_array($team, $r['awarded'], true)) { $r['teams'][$team]['score']++; $r['awarded'][] = $team; }
                break;
            case 'adjust':
                $team = $in['team'] ?? null; $delta = $in['delta'] ?? null;
                if (!is_int($team) || !isset($r['teams'][$team]) || !in_array($delta, [-1, 1], true)) gb_fail('Invalid score adjustment.');
                $r['teams'][$team]['score'] += $delta;
                break;
            default: gb_fail('Unknown action.');
        }
        $r['operations'][] = $op;
    }
    return gb_view($r, $in);
}
