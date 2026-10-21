<?php
/* File Name: config.local.example.php
Author: Jason Lamb (with help from ChatGPT)
Description: Safe template for optional external AI interpretation; copy to config.local.php on Hostinger.
Revision: 1.0.0
Created Date: 2026-10-21
Modified Date: 2026-10-21
Changelog: 1.0.0 - Adopt removable .example suffix without altering target filename. */
declare(strict_types=1);

// Copy this to config.local.php by removing ONLY '.example'.
// Set the actual key on Hostinger; never commit credentials or expose this config publicly.
return [
    'endpoint' => 'https://api.openai.com/v1/chat/completions',
    'model' => 'gpt-4o-mini',
    'api_key' => 'REPLACE_WITH_PRIVATE_KEY',
];
