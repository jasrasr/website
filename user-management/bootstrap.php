<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/1-Framework/bootstrap.php';
require_once __DIR__ . '/src/Permissions.php';
require_once __DIR__ . '/src/Directory.php';
require_once __DIR__ . '/src/Auth.php';
require_once __DIR__ . '/src/AccountLinkAdapter.php';
require_once __DIR__ . '/src/AccountLinks.php';

function jasr_users_config(): array
{
    $defaults = require __DIR__ . '/config.local.example.php';
    $file = __DIR__ . '/config.local.php';
    $local = is_file($file) ? require $file : [];
    if (!is_array($local)) throw new RuntimeException('Invalid user management configuration.');
    $config = array_replace($defaults, $local);
    if (!is_bool($config['debug']) || !is_array($config['rate_limit_exempt_ips'])) {
        throw new RuntimeException('Invalid development configuration.');
    }
    if ($config['debug']) {
        error_reporting(E_ALL);
        ini_set('display_errors', '1');
    }
    if (!preg_match('~^/(?:[a-zA-Z0-9_-]+/)*$~D', $config['base_path']) ||
        !preg_match('~^/(?:[a-zA-Z0-9_-]+/)*$~D', $config['cookie_path'])) {
        throw new RuntimeException('Invalid identity URL paths.');
    }
    return $config;
}

function jasr_users_auth(): \Jasr\Users\Auth
{
    static $auth;
    return $auth ??= new \Jasr\Users\Auth(jasr_users_config());
}
