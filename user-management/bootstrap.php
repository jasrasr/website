<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/1-Framework/bootstrap.php';
require_once __DIR__ . '/src/Directory.php';
require_once __DIR__ . '/src/Auth.php';

function jasr_users_config(): array
{
    $defaults = require __DIR__ . '/config.example.php';
    $file = __DIR__ . '/config.local.php';
    $local = is_file($file) ? require $file : [];
    if (!is_array($local)) throw new RuntimeException('Invalid user management configuration.');
    $config = array_replace($defaults, $local);
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
