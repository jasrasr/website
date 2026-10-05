<?php
declare(strict_types=1);
return [
    // Keep this directory private. Apache/LiteSpeed uses data/.htaccess.
    // For nginx, deny /user-management/data/ or move storage outside the web root.
    'data_path' => __DIR__ . '/data',
    'base_path' => '/user-management/',
    'cookie_name' => 'JASR_IDENTITY',
    'cookie_path' => '/',
    'secure_cookie' => true, // HTTPS required. Set false ONLY for local HTTP tests.
    'timeout' => 1800,
    // Optional browser setup: choose a random secret of at least 32 characters.
    // Empty disables browser setup; CLI setup works without it. Remove after setup.
    'setup_key' => '',
];
