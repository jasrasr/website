<?php
declare(strict_types=1);
require __DIR__ . '/../lib.php';
$dir = sys_get_temp_dir() . '/gb-suggestions-' . bin2hex(random_bytes(8));
$config = ['data_dir' => $dir];
$author = str_repeat('a', 48);
$other = str_repeat('b', 48);
function check(bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); }
try {
    gb_leader_question_state($config, ['leader' => $author, 'kind' => 'vote', 'selected' => ['q001']]);
    $result = gb_leader_question_state($config, ['leader' => $author, 'kind' => 'suggest', 'prompt' => 'Custom?', 'a' => 'Yes', 'b' => 'No']);
    $id = $result['suggestedId'];
    check(is_string($id), 'New question ID returned');
    check($result['selected'] === ['q001', $id], 'Previous favorite retained and suggestion selected');
    $reload = gb_leader_question_state($config, ['leader' => $author, 'kind' => 'state']);
    check($reload['selected'] === ['q001', $id], 'Auto favorite persists after reload');
    $public = gb_leader_question_state($config, ['leader' => $other, 'kind' => 'state']);
    $counts = array_column($public['questions'], 'count', 'id');
    check($counts[$id] === 1 && $counts['q001'] === 1, 'Shared question has one pick without changing existing counts');
    check($public['selected'] === [], 'Other browser does not inherit author favorites');
    check(array_column($public['questions'], 'number', 'id')[$id] === 101, 'First custom question is 101');
    $next = gb_leader_question_state($config, ['leader' => $other, 'kind' => 'suggest', 'prompt' => 'Second?', 'a' => 'A', 'b' => 'B']);
    check(array_column($next['questions'], 'number', 'id')[$next['suggestedId']] === 102, 'Next custom question is 102');
    check(array_column($next['questions'], 'number', 'id')[$id] === 101, 'Original number stays stable');
    check(count($next['questions']) === 102, 'Shared list includes bank and both suggestions');
    $activity = $result['activity'][0];
    check($activity['selected'] === 2 && $activity['suggestions'] === 1, 'Activity reflects automatic favorite');
    $removed = gb_leader_question_state($config, ['leader' => $author, 'kind' => 'vote', 'selected' => ['q001']]);
    check(array_column($removed['questions'], 'count', 'id')[$id] === 0, 'Author can remove the automatic favorite');
    echo "PASS: custom suggestion auto-favorite, persistence, preserved votes, public visibility, browser isolation, activity and removal\n";
} finally {
    foreach (glob($dir . '/*') ?: [] as $file) unlink($file);
    if (is_dir($dir)) rmdir($dir);
}
