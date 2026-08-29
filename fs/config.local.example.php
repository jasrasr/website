<?php
declare(strict_types=1);

// Copy this file to config.local.php and replace every placeholder.
// config.local.php is intentionally ignored by Git.
return [
    'domain' => 'yourcompany.freshservice.com',
    'api_key' => 'PASTE_YOUR_FRESHSERVICE_API_KEY_HERE',
    'agent_id' => 123456789,
    'workspace_id' => 0,
    'collector_token' => 'REPLACE_WITH_A_LONG_RANDOM_SECRET',
    'timezone' => 'America/New_York',
    'resolved_status_ids' => [4, 5],
    // Optional fallback/overrides if the API key cannot read ticket-field metadata.
    'status_labels' => [],
];
