<?php
/* File Name: ai-limits.test.php
Author: Jason Lamb (with help from ChatGPT)
Description: Regression tests for rolling quotas, identity exemptions, concurrency and config denial.
Revision: 1.0.0
Created Date: 2026-10-21
Modified Date: 2026-10-21
Changelog: 1.0.0 - Exercise persisted AI limits and private configuration filename protection. */
declare(strict_types=1);
require dirname(__DIR__, 2) . '/1-Framework/core/JsonStore.php';
require dirname(__DIR__) . '/ai-limits.php';

function check(bool $ok, string $message): void
{
    if (!$ok) throw new RuntimeException($message);
}
function rejects(callable $operation): void
{
    try { $operation(); } catch (Throwable $e) { return; }
    throw new RuntimeException('Expected operation to fail closed.');
}
$member = ['id' => 'member-1', 'role' => 'user', 'demo' => false];
$small = workout_ai_limits(['request_limits' => ['per_minute' => 2, 'per_day' => 3]]);
if (($argv[1] ?? '') === '--worker') {
    echo workout_ai_reserve($argv[2], array_replace($member, ['id'=>'concurrent-member']), $small, 200000);
    exit;
}
$temp = sys_get_temp_dir() . '/workout-ai-tests-' . bin2hex(random_bytes(8));
if (!mkdir($temp, 0700)) throw new RuntimeException('Test storage unavailable.');
try {
    $defaults = workout_ai_limits([]);
    check($defaults['per_minute'] === 5 && $defaults['per_day'] === 50, 'Legacy config defaults.');
    foreach ([['per_minute'=>0], ['per_day'=>'50'], ['exempt_user_ids'=>['']], ['exempt_user_ids'=>'member-1']] as $bad) {
        rejects(fn() => workout_ai_limits(['request_limits'=>$bad]));
    }
    check(workout_ai_reserve($temp, $member, $small, 100000) === 0, 'First request allowed.');
    check(workout_ai_reserve($temp, $member, $small, 100001) === 0, 'Second request allowed.');
    check(workout_ai_reserve($temp, $member, $small, 100002) === 58, 'Minute limit and retry time.');
    check(workout_ai_reserve($temp, $member, $small, 100060) === 0, 'Exact minute boundary expires.');
    check(workout_ai_reserve($temp, $member, $small, 100120) === 86280, 'Daily limit survives minute expiry.');
    check(workout_ai_reserve($temp, $member, $small, 186400) === 0, 'Exact rolling-day boundary expires.');
    $other = array_replace($member, ['id'=>'member-2']);
    check(workout_ai_reserve($temp, $other, $small, 100002) === 0, 'Accounts have independent budgets.');
    // Repeated attempts have no success/refund step; failed provider calls remain counted.
    $admin = array_replace($member, ['id'=>'central-admin', 'role'=>'super_admin']);
    check(workout_ai_exempt($admin, $defaults), 'Central Super Admin exempt.');
    check(!workout_ai_exempt(array_replace($admin, ['demo'=>true]), $defaults), 'Demo Super Admin limited.');
    check(!workout_ai_exempt(array_replace($member, ['role'=>'admin', 'projects'=>['workout-tracker'=>'super_admin']]), $defaults), 'Project/admin roles do not bypass.');
    $explicit = workout_ai_limits(['request_limits'=>['exempt_user_ids'=>['member-1']]]);
    check(workout_ai_exempt($member, $explicit), 'Stable ID exemption.');
    check(!workout_ai_exempt(array_replace($other, ['username'=>'member-1', 'name'=>'member-1']), $explicit), 'Names cannot claim exemption.');
    check(!workout_ai_exempt(array_replace($member, ['demo'=>true]), $explicit), 'Explicit exemption cannot exempt demo accounts.');
    for ($i=0; $i<55; ++$i) check(workout_ai_reserve($temp, $admin, $defaults, 100002) === 0, 'Super Admin repeated requests allowed.');
    check(!file_exists($temp.'/ai-requests-'.hash('sha256', $admin['id']).'.json'), 'Exempt requests create no counters.');
    $badUser = array_replace($member, ['id'=>'corrupt']);
    $badPath = $temp.'/ai-requests-'.hash('sha256', $badUser['id']).'.json';
    file_put_contents($badPath, '{invalid');
    rejects(fn() => workout_ai_reserve($temp, $badUser, $defaults, 100002));
    check(file_get_contents($badPath) === '{invalid', 'Corrupt state preserved; no quota reset.');
    set_error_handler(static function(int $code, string $message): never { throw new ErrorException($message, 0, $code); });
    try { rejects(fn() => workout_ai_reserve($temp.'/missing', $member, $defaults, 100002)); }
    finally { restore_error_handler(); }

    // Concurrent independent PHP processes must reserve at most two attempts.
    $workers = [];
    for ($i=0; $i<8; ++$i) {
        $pipes = [];
        $process = proc_open([PHP_BINARY, __FILE__, '--worker', $temp], [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']], $pipes);
        if (!is_resource($process)) throw new RuntimeException('Could not start concurrent test.');
        fclose($pipes[0]);
        $workers[] = [$process, $pipes];
    }
    $allowed = 0;
    foreach ($workers as [$process, $pipes]) {
        $out = stream_get_contents($pipes[1]);
        $err = stream_get_contents($pipes[2]);
        fclose($pipes[1]); fclose($pipes[2]);
        check(proc_close($process) === 0, 'Worker failed: '.$err);
        if (trim($out) === '0') ++$allowed;
        else check(trim($out) === '60', 'Concurrent retry time.');
    }
    check($allowed === 2, 'Atomic reservation prevents concurrent quota bypass.');

    $htaccess = file_get_contents(dirname(__DIR__).'/.htaccess');
    check(preg_match('/<FilesMatch "([^"]+)">/', $htaccess, $match) === 1, 'Deny expression exists.');
    $pattern = '%'.$match[1].'%';
    foreach (['config.local.php','config.local.example.php','config.local.php.placeholder','config.local.php.bak','config.local.php~','.config.local.php.swp','#config.local.php#','CONFIG.LOCAL.PHP.BAK','config.local-backup.zip'] as $filename) {
        check(preg_match($pattern, $filename) === 1, 'Must deny '.$filename);
    }
    foreach (['index.php','app.js','ai-limits.php','README.md'] as $filename) check(preg_match($pattern, $filename) === 0, 'Public app file remains reachable.');
    echo "Passed AI limit, exemption, concurrency, failure and config-filename regressions.\n";
} finally {
    foreach (glob($temp.'/*') ?: [] as $file) unlink($file);
    rmdir($temp);
}
