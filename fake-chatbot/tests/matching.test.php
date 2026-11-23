<?php
/*
File Name: matching.test.php
Revision: 2.0.3
Description: PHP regression coverage for deterministic ranked matches and framework persistence.
Author: Jason Lamb (with help from ChatGPT)
Created Date: 2026-11-20
Modified Date: 2026-11-22
Changelog: 2.0.3 - Cover visitor-only anonymous history clearing and link exclusion. 2.0.2 - Cover duplicate trigger detection across punctuation variants. 2.0.1 - Cover duplicate all-words and one-word phrase invalid inputs. 2.0.0 - Validate potato specificity, safe expansions, editable rules, and JsonStore atomic writes.
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
fc_check(fc_trigger_score('a b c', 'phrase', 'a b c', -20) > fc_trigger_score('a b c', 'phrase', 'a b', 20), 'longer phrase beats maximum priority difference');
fc_check(fc_trigger_score('a b', 'phrase', 'a b', 20) > fc_trigger_score('a b', 'phrase', 'a b', -20), 'priority breaks specificity ties');
fc_check(fc_trigger_score('potato', 'all', 'potato potato', 0) === null, 'repeated all-words do not masquerade as multiple distinct terms');
$linkResult = fc_link_history_records([
    ['session' => 'browser', 'timestamp' => 99, 'question' => 'expired'],
    ['session' => 'browser', 'timestamp' => 100, 'question' => 'recent'],
    ['session' => 'other', 'timestamp' => 99, 'question' => 'other expired'],
    ['session' => 'other', 'timestamp' => 1, 'userId' => 'existing', 'question' => 'linked'],
], 'browser', 'new-user', 100);
fc_check($linkResult['count'] === 1 && count($linkResult['records']) === 2, 'linking prunes expired anonymous history and retains linked entries');
fc_check(($linkResult['records'][0]['userId'] ?? null) === 'new-user' && ($linkResult['records'][1]['userId'] ?? null) === 'existing', 'only recent browser history is linked');
$now = time();
$cleared = fc_hide_guest_history([
    ['id' => 'own', 'session' => 'browser', 'timestamp' => $now],
    ['id' => 'other', 'session' => 'other', 'timestamp' => $now],
    ['id' => 'linked', 'session' => 'browser', 'userId' => 'member', 'timestamp' => $now],
], 'browser');
fc_check(count($cleared) === 3 && !empty($cleared[0]['hiddenFromBrowser']) && empty($cleared[1]['hiddenFromBrowser']) && empty($cleared[2]['hiddenFromBrowser']), 'clearing keeps all admin records and hides only this browser\'s anonymous entry');
$visible = fc_recent($cleared, 'browser', 'member');
fc_check(count($visible) === 1 && ($visible[0]['id'] ?? null) === 'linked', 'cleared anonymous entry disappears from visitor history while linked history remains');
$linkedAfterClear = fc_link_history_records($cleared, 'browser', 'member', $now - 90 * 86400);
fc_check($linkedAfterClear['count'] === 0 && empty($linkedAfterClear['records'][0]['userId']), 'cleared anonymous entry cannot become account-linked later');
fc_check(str_contains(fc_expand('Time {{time}}, literal $(Get-Date)'), '$(Get-Date)'), 'PowerShell code is not evaluated');
$rule = fc_validate_rule(['name'=>'Test','triggers'=>"phrase: potato salad\nall: potato salad\nkeyword: potato",'replies'=>"Answer A\nAnswer B",'priority'=>'5','enabled'=>'1']);
fc_check(count($rule['triggers']) === 3 && count($rule['replies']) === 2, 'admin rule parser accepts multi-trigger multi-answer forms');
$existing = fc_validate_rule(['name'=>'Mostly polite','triggers'=>"exact: what is 'mostly polite'?",'replies'=>'An answer']);
fc_check(fc_existing_trigger_rule_id([$existing], 'exact', 'WHAT IS mostly polite!!!') === $existing['id'], 'equivalent exact trigger detects duplicate despite punctuation');
fc_check(fc_existing_trigger_rule_id([$existing], 'phrase', 'what is mostly polite') === null, 'different trigger types remain distinct');
try {
    fc_validate_rule(['name'=>'Bad','triggers'=>'keyword: potato salad','replies'=>'Not valid']);
    fc_check(false, 'keyword cannot contain spaces');
} catch (InvalidArgumentException $expected) {
    fc_check(true, 'invalid multiword keyword rejected');
}
try {
    fc_validate_rule(['name'=>'Bad','triggers'=>'phrase: potato','replies'=>'Not valid']);
    fc_check(false, 'one-word phrases cannot bypass keyword tier');
} catch (InvalidArgumentException $expected) {
    fc_check(true, 'one-word phrases are rejected');
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
