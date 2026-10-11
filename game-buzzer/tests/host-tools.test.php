<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/lib.php';
$checks = 0;
function check(bool $ok, string $message): void { global $checks; $checks++; if (!$ok) throw new Exception($message); }
function rejects(callable $fn, int $code): void { try { $fn(); } catch (RuntimeException $e) { check($e->getCode() === $code, $e->getMessage()); return; } throw new Exception('Expected rejection.'); }
$config = ['data_dir' => sys_get_temp_dir() . '/gb-host-tools-' . bin2hex(random_bytes(6))];
$r = gb_new(['title' => 'Host rehearsal']);
function action(string $name, array $extra = []): array { global $r, $config; return gb_apply($r, $name, $extra + ['host' => $r['host'], 'operation' => gb_token()], $config); }
try {
    foreach (['practice', 'demo', 'resetScores', 'topQuestions'] as $name) rejects(fn()=>gb_apply($r, $name, ['enabled'=>true, 'operation'=>gb_token()], $config), 403);
    // Popular custom questions are eligible; reading the poll adds no votes.
    $leader = gb_token();
    $poll = gb_leader_question_state($config, ['leader'=>$leader, 'kind'=>'suggest', 'prompt'=>'Custom top question?', 'a'=>'One', 'b'=>'Two']);
    $oldVersion = gb_view($r, [])['surveyVersion'];
    action('topQuestions');
    check(in_array('Custom top question?', array_column($r['questions'], 'prompt'), true), 'Custom top favorite missing');
    check(count($r['questions']) === 10, 'Top ten size');
    $pollAfter = gb_leader_question_state($config, ['leader'=>$leader, 'kind'=>'state']);
    check($pollAfter['questions'][0]['count'] === 1, 'Loading questions created votes');
    rejects(fn()=>action('vote', ['voter'=>gb_token(), 'answers'=>array_fill(0,10,'a'), 'surveyVersion'=>$oldVersion]),409);
    $phone = gb_token();
    action('join', ['device'=>$phone, 'key'=>$r['teams'][0]['join']]);
    $r['teams'][0]['score'] = 71;
    action('practice', ['enabled'=>true]);
    action('arm', ['duration'=>10]);
    $r['openAt'] = microtime(true)-1;
    action('buzz', ['device'=>$phone, 'round'=>$r['round']]);
    action('closeBuzz', ['round'=>$r['round']]);
    action('award', ['team'=>0, 'round'=>$r['round']]);
    rejects(fn()=>action('adjust', ['team'=>0, 'delta'=>1]),409);
    check($r['teams'][0]['score'] === 71 && count(gb_ranking($r)) === 1, 'Practice speed/award changed score or lost rank');
    check($r['question'] === null, 'Buzzer practice opened real survey results');
    action('demo');
    check(count($r['ballots']) === 0 && $r['surveyOpen'], 'Demo changed real arrival survey');
    rejects(fn()=>action('predict', ['device'=>$phone, 'round'=>$r['round']-1, 'question'=>0, 'answer'=>'a']),409);
    action('predict', ['device'=>$phone, 'question'=>0, 'answer'=>'a']);
    action('reveal', ['question'=>0]);
    $view = gb_view($r, []);
    check($view['results']['a'] + $view['results']['b'] === 20 && $view['practice'] && $view['demoVotes'], 'Demo totals/label missing');
    check($r['teams'][0]['score'] === 71, 'Demo majority scored');
    action('question', ['question'=>3]);
    action('predict', ['device'=>$phone, 'question'=>3, 'answer'=>'b']);
    action('reveal', ['question'=>3]);
    check(gb_view($r, [])['results']['majority'] === 'tie' && $r['teams'][0]['score'] === 71, 'Demo tie scored');
    // Real votes can still arrive during practice and survive leaving it.
    action('vote', ['voter'=>gb_token(), 'answers'=>array_fill(0,10,'a')]);
    $oldRound = $r['round'];
    action('practice', ['enabled'=>false]);
    check(count($r['ballots']) === 1 && !$r['practice'] && !$r['demoVotes'] && $r['revealed'] === [] && $r['question'] === null, 'Practice contaminated live progress');
    rejects(fn()=>action('buzz', ['device'=>$phone, 'round'=>$oldRound]),409);
    rejects(fn()=>action('topQuestions'),409);
    action('closeSurvey');
    action('arm', ['duration'=>10]);
    rejects(fn()=>action('practice', ['enabled'=>true]),409);
    $r['openAt'] = microtime(true)-1;
    action('buzz', ['device'=>$phone, 'round'=>$r['round']]);
    action('closeBuzz', ['round'=>$r['round']]);
    check($r['teams'][0]['score'] === 131, 'Live speed no longer scores');
    action('predict', ['device'=>$phone, 'question'=>0, 'answer'=>'a']);
    action('reveal', ['question'=>0]);
    check($r['teams'][0]['score'] === 161, 'Live majority no longer scores');
    $realProgress = [$r['question'], $r['revealed'], $r['predictions'], $r['scored']];
    action('demo');
    action('reveal', ['question'=>0]);
    action('practice', ['enabled'=>false]);
    check([$r['question'], $r['revealed'], $r['predictions'], $r['scored']] === $realProgress, 'Live progress not restored after demo');
    $op = gb_token();
    action('resetScores', ['operation'=>$op]);
    check(array_column($r['teams'], 'score') === array_fill(0,6,0), 'Not all scores reset');
    action('reveal', ['question'=>0]);
    action('closeBuzz', ['round'=>$r['round']]);
    check($r['teams'][0]['score'] === 0, 'Reset allowed completed scoring to replay');
    action('adjust', ['team'=>0, 'delta'=>1]);
    action('resetScores', ['operation'=>$op]);
    check($r['teams'][0]['score'] === 1, 'Retried reset erased later scores');
    // Practicing first must not disable the pre-vote question loader.
    $r = gb_new(['title'=>'Practice first']);
    unset($r['practice'], $r['demoVotes'], $r['playStarted']); // legacy room compatibility
    action('demo'); action('practice', ['enabled'=>false]); action('topQuestions');
    check(count($r['questions']) === 10 && !$r['playStarted'], 'Practice prevented pre-vote top ten loading');
    echo "Passed $checks host-tool assertions.\n";
} finally {
    foreach (glob($config['data_dir'].'/*') as $file) unlink($file);
    if (is_dir($config['data_dir'])) rmdir($config['data_dir']);
}
