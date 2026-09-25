<?php
// File: config.sample.php
// Purpose: Provides sample finance tracker user configuration.
// Revision: 1.1
// Revision Log:
// - 2026-05-15: Added revision metadata to the file header.

return [
    // Keep legacy until every required account has been linked and validated.
    // 'shared' disables local password login and requires an explicit central-ID mapping.
    'authentication' => 'legacy',
    'users' => [
        'student' => [
            'password' => 'budget123',
        ],
        'parent' => [
            'password' => 'budget123',
        ],
    ],
];
