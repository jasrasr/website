<?php
declare(strict_types=1);
return [
    'app_name' => 'Khootish',
    'admin_password' => getenv('QUIZ_ADMIN_PASSWORD') ?: 'changemenow',
    'data_dir' => __DIR__ . '/data',
    'question_seconds' => 30,
    'starting_points' => 1000,
    'max_players' => 100,
];
