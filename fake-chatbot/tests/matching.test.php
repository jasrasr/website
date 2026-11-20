<?php
/*
File Name: matching.test.php
Revision: 2.0.0
Description: PHP regression coverage for deterministic ranked matches and framework persistence.
Author: Jason Lamb (with help from ChatGPT)
Created Date: 2026-11-20
Modified Date: 2026-11-20
Changelog: 2.0.0 - Validate potato specificity, safe expansions, editable rules, and JsonStore atomic writes.
*/
declare(strict_types=1);
require_once dirname(__DIR__) . '/bootstrap.php';
function fc_check(bool $passed, string $description): void
{
    if (!$passed) {fwrite(STDERR, "FAIL: {$description}\n"); exit(1);}
    echo "PASS: {$description}\n";
}
$rules = fc_rules();
fc_check(count($rules) >= 90, 'large human-written seed library exists');
$cases = [
    'I need a potato salad recipe' => 'potato-salad',
    'potato salad' => 'potato-salad',
    'Can I use potato and celery in salad?' => 'potato-salad',
    'potato' => 'potato',
    'what is a potato' => 'potato',
    'What is Mr. Potato Head from Toy Story?' => 'mr-potato',
    'potato from Toy Story' => 'mr-potato',
    'mashed potatoes' => 'mashed-potato',
    'pineapple pizza' => 'pineapple-pizza',
    'my printer has a paper jam' => 'paper-jam',
    'I forgot password' => 'password-reset',
    'What time is it?' => 'time',
    'please tell me a joke' => 'joke',
    'salad' => 'salad',
    'Are you real AI?' => 'real-ai'
];
foreach ($cases as $question => $expected) {
    $actual = fc_match($question, $rules);
    fc_check(($actual['rule']['id'] ?? null) === $expected, $question . ' maps to ' . $expected);
}
fc_check(fc_match('this is a test', [$rules[array_search('hello', array_column($rules, 'id'))]]) === null, 'hi does not match inside this');
fc_check(fc_match('unmatchedzzzz', $rules) === null, 'unknown message is unmatched');
fc_check(fc_trigger_score('potato salad', 'phrase', 'potato salad', 0) > fc_trigger_score('potato salad', 'keyword', 'potato', 20), 'phrase tier beats boosted keyword');
fc_check(fc_trigger_score('potato salad', 'exact', 'potato salad', -20) > fc_trigger_score('potato salad', 'phrase', 'potato salad', 20), 'exact tier beats phrase tier');
fc_check(str_contains(fc_expand('Time {{time}}, literal $(Get-Date)'), '$(Get-Date)'), 'PowerShell code is not evaluated');
$rule = fc_validate_rule(['name'=>'Test','triggers'=>"phrase: potato salad\nall: potato salad\nkeyword: potato",'replies'=>"Answer A\nAnswer B",'priority'=>'5','enabled'=>'1']);
fc_check(count($rule['triggers']) === 3 && count($rule['replies']) === 2, 'admin rule parser accepts multi-trigger multi-answer forms');
try {
    fc_validate_rule(['name'=>'Bad','triggers'=>'keyword: potato salad','replies'=>'Not valid']);
    fc_check(false, 'keyword cannot contain spaces');
} catch (InvalidArgumentException $expected) {
    fc_check(true, 'invalid multiword keyword rejected');
}
$tmp = sys_get_temp_dir() . '/fake-chatbot-test-' . bin2hex(random_bytes(8)) . '.json';
try {
    \Jasr\Framework\JsonStore::update($tmp, static fn(array $records): array => ['ok' => true]);
    fc_check(\Jasr\Framework\JsonStore::read($tmp)['records']['ok'] === true, 'framework JsonStore round trip');
} finally {
    if (is_file($tmp)) unlink($tmp);
    if (is_file($tmp . '.lock')) unlink($tmp . '.lock');
}
echo "All fake chatbot matching tests passed.\n";
