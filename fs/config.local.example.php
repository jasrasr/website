<?php
declare(strict_types=1);

// Copy this file to config.local.php and replace every placeholder.
// config.local.php is intentionally ignored by Git.
return [
    'domain' => 'yourcompany.freshservice.com',
    'api_key' => 'PASTE_YOUR_FRESHSERVICE_API_KEY_HERE',
    'agent_id' => 123456789,
    // Agent IDs whose new tickets feed the By-Organization report (api/collect-org.php,
    // index-org.php). Not linked from the main dashboard; kept separate from `agent_id` above.
    'org_agent_ids' => [123456789, 234567890],
    'workspace_id' => 0,
    'collector_token' => 'REPLACE_WITH_A_LONG_RANDOM_SECRET',
    'timezone' => 'America/New_York',
    'resolved_status_ids' => [4, 5],
    // Optional fallback/overrides if the API key cannot read ticket-field metadata.
    'status_labels' => [],
    // Private review workspace: /fs/review/. Never reuse collector_token.
    'review_token' => 'REPLACE_WITH_A_SEPARATE_RANDOM_SECRET_AT_LEAST_32_CHARACTERS',
    // Explicitly enable only after choosing an approved API account for ticket content.
    'review_ai_enabled' => false,
    'review_openai_key' => '',
    'review_model' => '', // A model available to your API project with Structured Outputs.

];
