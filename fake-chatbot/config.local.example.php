<?php
/*
File Name: config.local.example.php
Revision: 2.0.0
Description: Safe configuration defaults; copy to ignored config.local.php only when overriding.
Author: Jason Lamb (with help from ChatGPT)
Created Date: 2026-11-20
Modified Date: 2026-11-20
Changelog: 2.0.0 - Specify private writable storage, site cookie path and anonymous limits.
*/
declare(strict_types=1);
return [
    // Canonical location after Hostinger repository sync. Change for local testing.
    'base_path' => '/github/fake-chatbot/',
    'storage_path' => __DIR__ . '/storage',
    'per_minute' => 20,
    'per_day' => 300
];
