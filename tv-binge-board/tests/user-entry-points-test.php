<?php
/**
 * File: tests/user-entry-points-test.php
 * Project: TV Binge Board
 * Description: Static regression checks for public registration, admin account creation, and logout entry points.
 * Author: Jason Lamb / ChatGPT
 * Created: 2026-07-02
 * Modified: 2026-07-02
 * Revision: 1.0.1
 */
declare(strict_types=1);

$root = dirname(__DIR__);
$checks = [
    'index.php exposes register.php to guests' => [
        'file' => $root . '/index.php',
        'needles' => ['register.php', 'app_public_registration_enabled'],
    ],
    'admin users page includes create user form' => [
        'file' => $root . '/admin/users.php',
        'needles' => ['name="action" value="create_user"', 'name="display_name"', 'name="username"', 'name="new_password"'],
    ],
    'admin user action supports create_user' => [
        'file' => $root . '/api/admin-user-action.php',
        'needles' => ["\$action === 'create_user'", 'app_create_user', "'user-created-admin'"],
    ],
    'signed-in nav exposes logout' => [
        'file' => $root . '/includes/functions.php',
        'needles' => ["app_href('logout.php')", '>Logout</a>'],
    ],
];

$failures = [];
foreach ($checks as $label => $check) {
    $contents = file_get_contents($check['file']);
    if ($contents === false) {
        $failures[] = $label . ': could not read ' . $check['file'];
        continue;
    }
    foreach ($check['needles'] as $needle) {
        if (!str_contains($contents, $needle)) {
            $failures[] = $label . ': missing ' . $needle;
        }
    }
}

if ($failures) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}

echo 'User entry point checks passed.' . PHP_EOL;

// Example Usage:
//   php .\tests\user-entry-points-test.php

