<?php
/* File Name: config.local.example.php
Author: Jason Lamb (with help from ChatGPT)
Description: Safe template for optional external AI interpretation; copy to config.local.php on Hostinger.
Revision: 1.0.1
Created Date: 2026-10-21
Modified Date: 2026-10-21
Changelog: 1.0.1 - Configure rolling AI request limits and stable-ID exemptions. 1.0.0 - Adopt removable .example suffix without altering target filename. */
declare(strict_types=1);

// Copy this to config.local.php by removing ONLY '.example'.
// Set the actual key on Hostinger; never commit credentials or expose this config publicly.
return [
    'endpoint' => 'https://api.openai.com/v1/chat/completions',
    'model' => 'gpt-4o-mini',
    'api_key' => 'REPLACE_WITH_PRIVATE_KEY',
    // Applies only to optional paid AI; local parsing and saves remain unlimited.
    // Non-demo CENTRAL Super Admins are automatically exempt after project authorization.
    'request_limits' => [
        'per_minute' => 5,
        'per_day' => 50, // Rolling 24 hours, not a calendar-day reset.
        // Optional immutable central user IDs, never display names or request values.
        'exempt_user_ids' => [],
    ],
];
