<?php
/*
    Project      : AI Writing Tool
    File         : config/config.example.php
    Revision     : 1.0.0
    Created      : 2026-06-01
    Updated      : 2026-06-01
    Description  : Example configuration. Copy this file to config.php and insert your real API key.

    Security:
    - Do not commit config.php to GitHub.
    - Do not put your API key in app.js or index.php.
*/

return [
    // Required. Keep this server-side only.
    'openai_api_key' => 'PASTE-YOUR-OPENAI-API-KEY-HERE',

    // Change this if you prefer a different model available to your OpenAI account.
    'openai_model' => 'gpt-4.1-mini',

    // Prevents giant accidental submissions. Raise only if you know you need more.
    'max_input_characters' => 12000,

    // Basic per-IP rate limit for api/suggest.php. Set to 0 to disable.
    'rate_limit_per_hour' => 60,

    // Server request timeout.
    'timeout_seconds' => 45
];
