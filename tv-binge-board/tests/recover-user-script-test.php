<?php
/**
 * File: tests/recover-user-script-test.php
 * Project: TV Binge Board
 * Description: Regression checks for the CLI user recovery tool.
 * Author: Jason Lamb / ChatGPT
 * Created: 2026-07-02
 * Modified: 2026-07-02
 * Revision: 1.0.0
 */
declare(strict_types=1);

$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
require_once dirname(__DIR__) . '/includes/auth.php';

$accountsPath = app_accounts_path();
$attemptsPath = app_login_attempts_path();
$accountsOriginal = is_file($accountsPath) ? file_get_contents($accountsPath) : false;
$attemptsOriginal = is_file($attemptsPath) ? file_get_contents($attemptsPath) : false;
$username = 'recovertest';
$displayName = 'Recover Test';
$password = 'RecoverPass123';
$key = sha1($username . '|12.6.64.130');

app_save_json($accountsPath, [
    '_meta' => app_json_meta('Application user accounts and password hashes.'),
    'users' => [],
]);
app_save_json($attemptsPath, [
    '_meta' => app_json_meta('Rate-limiting login attempts.'),
    'attempts' => [
        $key => [
            'count' => 99,
            'last_failed_at' => date(DATE_ATOM),
            'username' => $username,
        ],
    ],
]);

$command = 'php ' . escapeshellarg(dirname(__DIR__) . '/tools/recover-user.php')
    . ' --username=' . escapeshellarg($username)
    . ' --password=' . escapeshellarg($password)
    . ' --display-name=' . escapeshellarg($displayName)
    . ' --clear-lockout-ip=' . escapeshellarg('12.6.64.130');

$output = [];
$code = 0;
exec($command, $output, $code);

$failures = [];
if ($code !== 0) {
    $failures[] = 'Recovery tool exited with non-zero status: ' . $code;
}
$user = app_find_user($username);
if ($user === null) {
    $failures[] = 'Recovery tool did not create the requested user.';
} else {
    if (($user['display_name'] ?? '') !== $displayName) {
        $failures[] = 'Recovery tool did not save the expected display name.';
    }
    if (!password_verify($password, (string)($user['password_hash'] ?? ''))) {
        $failures[] = 'Recovery tool did not set the requested password.';
    }
}
$attempts = app_load_json($attemptsPath, ['attempts' => []]);
if (isset($attempts['attempts'][$key])) {
    $failures[] = 'Recovery tool did not clear the specified lockout entry.';
}

if ($accountsOriginal === false) {
    @unlink($accountsPath);
} else {
    file_put_contents($accountsPath, $accountsOriginal);
}
if ($attemptsOriginal === false) {
    @unlink($attemptsPath);
} else {
    file_put_contents($attemptsPath, $attemptsOriginal);
}

if ($failures) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}

echo 'User recovery tool checks passed.' . PHP_EOL;

// Example Usage:
//   php .\tests\recover-user-script-test.php
