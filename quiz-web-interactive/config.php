<?php
declare(strict_types=1);
return [
    'app_name' => 'Quiz Web Interactive',
    'admin_password' => getenv('QUIZ_ADMIN_PASSWORD') ?: 'change-me-now',
    'data_dir' => __DIR__ . '/data',
    'question_seconds' => 30,
    'starting_points' => 1000,
    'max_players' => 100,
];
