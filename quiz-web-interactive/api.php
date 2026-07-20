<?php
declare(strict_types=1);
require __DIR__ . '/lib.php';

$action = $_GET['action'] ?? '';
$data = request_json();

try {
    switch ($action) {
        case 'admin_login':
            start_session();
            if (!hash_equals((string)config('admin_password'), (string)($data['password'] ?? ''))) json_response(['ok'=>false,'error'=>'Invalid password.'],403);
            $_SESSION['quiz_admin'] = true;
            json_response(['ok'=>true]);

        case 'admin_logout':
            start_session(); unset($_SESSION['quiz_admin']); json_response(['ok'=>true]);

        case 'save_quiz':
            require_admin_api();
            $title = trim((string)($data['title'] ?? ''));
            $questions = $data['questions'] ?? [];
            if ($title === '' || !is_array($questions) || count($questions) < 1) json_response(['ok'=>false,'error'=>'A title and at least one question are required.'],422);
            $normalized = [];
            foreach ($questions as $i => $q) {
                $text = trim((string)($q['text'] ?? ''));
                $choices = array_map(fn($v)=>trim((string)$v), $q['choices'] ?? []);
                $correct = (int)($q['correct_index'] ?? -1);
                if ($text === '' || count($choices) !== 4 || in_array('', $choices, true) || $correct < 0 || $correct > 3) json_response(['ok'=>false,'error'=>'Every question needs text, four answers, and one correct answer.'],422);
                $sourceId = clean_id((string)($q['source_question_id'] ?? ''));
                if (!empty($q['add_to_bank'])) {
                    $bankQuestion = save_bank_question(['text'=>$text,'choices'=>$choices,'correct_index'=>$correct]);
                    $sourceId = $bankQuestion['id'];
                }
                if ($sourceId !== '') increment_bank_question_usage($sourceId);
                $item = ['id'=>'q'.($i+1),'text'=>$text,'choices'=>$choices,'correct_index'=>$correct];
                if ($sourceId !== '') $item['source_question_id'] = $sourceId;
                $normalized[] = $item;
            }
            $id = clean_id((string)($data['id'] ?? '')) ?: new_id('quiz');
            $existing = read_quiz($id);
            $quiz = ['id'=>$id,'title'=>$title,'created_at'=>$existing['created_at'] ?? gmdate('c'),'updated_at'=>gmdate('c'),'questions'=>$normalized];
            save_quiz($quiz);
            json_response(['ok'=>true,'quiz'=>$quiz]);

        case 'list_quizzes':
            require_admin_api();
            $items = array_map(fn($q)=>['id'=>$q['id'],'title'=>$q['title'],'question_count'=>count($q['questions'] ?? []),'updated_at'=>$q['updated_at'] ?? null], list_quizzes());
            json_response(['ok'=>true,'quizzes'=>$items]);

        case 'list_question_bank':
            require_admin_api();
            $search = trim((string)($_GET['search'] ?? $data['search'] ?? ''));
            $items = array_map(fn($q)=>[
                'id'=>$q['id'],
                'text'=>$q['text'],
                'choices'=>$q['choices'],
                'correct_index'=>(int)$q['correct_index'],
                'times_used'=>(int)($q['times_used'] ?? 0),
                'updated_at'=>$q['updated_at'] ?? null,
            ], list_bank_questions($search));
            json_response(['ok'=>true,'questions'=>$items]);

        case 'save_question_bank':
            require_admin_api();
            $question = save_bank_question($data);
            json_response(['ok'=>true,'question'=>$question]);

        case 'launch_quiz':
            require_admin_api();
            $quiz = read_quiz(clean_id((string)($data['quiz_id'] ?? '')));
            if (!$quiz) json_response(['ok'=>false,'error'=>'Quiz not found.'],404);
            $game = create_game_session($quiz);
            json_response(['ok'=>true,'code'=>$game['code']]);

        case 'join':
            $code = clean_code((string)($data['code'] ?? ''));
            $team = trim((string)($data['team_name'] ?? ''));
            $color = preg_match('/^#[0-9A-Fa-f]{6}$/',(string)($data['color'] ?? '')) ? (string)$data['color'] : '#3366ff';
            if ($team === '' || mb_strlen($team) > 30) json_response(['ok'=>false,'error'=>'Enter a team name up to 30 characters.'],422);
            $pid = player_id();
            $game = mutate_game($code, function(array $game) use ($pid,$team,$color) {
                $phase = (string)($game['state']['phase'] ?? 'lobby');
                if ($phase === 'finished') throw new RuntimeException('This game has finished.');
                if (count($game['players'] ?? []) >= (int)config('max_players') && !isset($game['players'][$pid])) throw new RuntimeException('This game is full.');
                foreach ($game['players'] as $existing) if (strcasecmp($existing['team_name'],$team)===0 && $existing['id']!==$pid) throw new RuntimeException('That team name is already in use.');
                $game['players'][$pid] = ['id'=>$pid,'team_name'=>$team,'color'=>$color,'avatar'=>null,'score'=>(int)($game['players'][$pid]['score'] ?? 0),'answered_question'=>null,'last_correct'=>null,'last_points'=>0];
                return $game;
            });
            start_session(); $_SESSION['game_code']=$code;
            json_response(['ok'=>true,'game'=>public_game($game)]);

        case 'state':
            $code = clean_code((string)($_GET['code'] ?? $data['code'] ?? ''));
            $game = read_game_finalized($code);
            if (!$game) json_response(['ok'=>false,'error'=>'Game not found.'],404);
            json_response(['ok'=>true,'game'=>public_game($game,!empty($_GET['display']))]);

        case 'start_question':
            require_admin_api();
            $code = clean_code((string)($data['code'] ?? ''));
            $game = mutate_game($code, function(array $game) {
                $next = (int)($game['state']['question_index'] ?? -1)+1;
                if (!isset($game['questions'][$next])) throw new RuntimeException('There are no more questions.');
                foreach ($game['players'] as &$p) {$p['answered_question']=null;$p['last_correct']=null;$p['last_points']=0;} unset($p);
                $now = microtime(true);
                $duration = (int)($game['question_seconds'] ?? config('question_seconds'));
                $game['state']=['phase'=>'question','question_index'=>$next,'started_at'=>$now,'ends_at'=>$now+$duration];
                return $game;
            });
            json_response(['ok'=>true,'game'=>public_game($game)]);

        case 'show_results':
            require_admin_api();
            $game = mutate_game(clean_code((string)($data['code'] ?? '')), function(array $game) {
                if (($game['state']['question_index'] ?? -1)<0) throw new RuntimeException('No question has started.');
                $game['state']['phase']='results'; $game['state']['ends_at']=null; return $game;
            });
            json_response(['ok'=>true,'game'=>public_game($game)]);

        case 'finish_game':
            require_admin_api();
            $game = mutate_game(clean_code((string)($data['code'] ?? '')), function(array $game) {$game['state']['phase']='finished';$game['state']['ends_at']=null;return $game;});
            json_response(['ok'=>true,'game'=>public_game($game)]);

        case 'reset_game':
            require_admin_api();
            $code = clean_code((string)($data['code'] ?? ''));
            $game = mutate_game($code, function(array $game) {
                $game['players'] = [];
                $game['answers'] = [];
                $game['state'] = ['phase'=>'lobby','question_index'=>-1,'started_at'=>null,'ends_at'=>null];
                return $game;
            });
            json_response(['ok'=>true,'game'=>public_game($game)]);

        case 'answer':
            $code = clean_code((string)($data['code'] ?? ''));
            $choice = (int)($data['choice'] ?? -1);
            $pid = player_id();
            if ($choice < 0 || $choice > 3) json_response(['ok'=>false,'error'=>'Invalid answer.'],422);
            $result=['correct'=>false,'points'=>0];
            $game = mutate_game($code, function(array $game) use ($pid,$choice,&$result) {
                $game = finalize_if_expired($game);
                if (($game['state']['phase'] ?? '') !== 'question') throw new RuntimeException('Answers are not being accepted.');
                if (!isset($game['players'][$pid])) throw new RuntimeException('You are not registered for this game.');
                $q=current_question($game); if(!$q) throw new RuntimeException('Question not found.');
                if (($game['players'][$pid]['answered_question'] ?? null)===$q['id']) throw new RuntimeException('Your answer is already locked in.');
                $now=microtime(true); if($now >= (float)$game['state']['ends_at']) throw new RuntimeException('Time expired.');
                $correct=$choice===(int)$q['correct_index'];
                $duration=(int)($game['question_seconds'] ?? config('question_seconds'));
                $remaining=max(0.0,(float)$game['state']['ends_at']-$now);
                $points=$correct ? max(1,(int)round((int)($game['starting_points'] ?? config('starting_points'))*($remaining/$duration))) : 0;
                $game['players'][$pid]['answered_question']=$q['id'];
                $game['players'][$pid]['last_correct']=$correct;
                $game['players'][$pid]['last_points']=$points;
                $game['players'][$pid]['score']+=$points;
                $game['answers'][$q['id']][$pid]=['choice'=>$choice,'correct'=>$correct,'points'=>$points,'answered_at'=>$now];
                $result=['correct'=>$correct,'points'=>$points];
                return $game;
            });
            json_response(['ok'=>true,'result'=>$result,'game'=>public_game($game)]);

        default: json_response(['ok'=>false,'error'=>'Unknown API action.'],404);
    }
} catch (Throwable $e) {
    json_response(['ok'=>false,'error'=>$e->getMessage()],400);
}
