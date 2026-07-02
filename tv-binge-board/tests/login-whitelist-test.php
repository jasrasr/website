<?php
/**
 * File: tests/login-whitelist-test.php
 * Project: TV Binge Board
 * Description: Regression checks for trusted-IP login lockout bypass.
 * Author: Jason Lamb / ChatGPT
 * Created: 2026-07-02
 * Modified: 2026-07-02
 * Revision: 1.0.0
 */
declare(strict_types=1);

$_SERVER['REMOTE_ADDR'] = '12.6.64.130';
if (!defined('TRUSTED_LOGIN_IPS_LOCAL')) {
    define('TRUSTED_LOGIN_IPS_LOCAL', ['12.6.64.130']);
}

require_once dirname(__DIR__) . '/includes/auth.php';

$path = app_login_attempts_path();
$original = is_file($path) ? file_get_contents($path) : false;
$key = app_login_key('admin');

app_save_json($path, [
    '_meta' => app_json_meta('Rate-limiting login attempts.'),
    'attempts' => [
        $key => [
            'count' => 99,
            'last_failed_at' => date(DATE_ATOM),
            'username' => 'admin',
        ],
    ],
]);

$failures = [];
if (app_login_is_limited('admin')) {
    $failures[] = 'Trusted IP should bypass login lockout.';
}

app_record_login_failure('admin');
$attempts = app_load_json($path, ['attempts' => []]);
if (($attempts['attempts'][$key]['count'] ?? null) !== 99) {
    $failures[] = 'Trusted IP should not increment login failure count.';
}

if ($original === false) {
    @unlink($path);
} else {
    file_put_contents($path, $original);
}

if ($failures) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}

echo 'Trusted login whitelist checks passed.' . PHP_EOL;

// Example Usage:
//   php .\tests\login-whitelist-test.php
