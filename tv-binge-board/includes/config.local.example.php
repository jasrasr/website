<?php
/**
 * File: includes/config.local.example.php
 * Project: TV Binge Board
 * Description: Example local-only configuration file for secrets such as TMDB credentials and optional AI vision screenshot processing.
 * Author: Jason Lamb / ChatGPT
 * Created: 2026-07-02
 * Modified: 2026-07-03
 * Revision: 1.5.6
 */
declare(strict_types=1);

// Copy this file to includes/config.local.php and add your real credentials.

// TMDB: Prefer the v4 Read Access Token if your TMDB account shows one. The app keeps it server-side.
define('TMDB_API_READ_ACCESS_TOKEN_LOCAL', 'paste-your-tmdb-read-access-token-here');

// Optional fallback for older v3 API-key style auth.
define('TMDB_API_KEY_LOCAL', 'paste-your-tmdb-v3-api-key-here');

// Optional direct screenshot image processing. This lets upload-screenshot.php process the uploaded image itself.
// The default endpoint expects an OpenAI-compatible chat completions vision model.
define('OPENAI_API_KEY_LOCAL', 'paste-your-openai-api-key-here');
define('OPENAI_VISION_MODEL_LOCAL', 'gpt-4o-mini');
// define('OPENAI_CHAT_COMPLETIONS_URL_LOCAL', 'https://api.openai.com/v1/chat/completions');

// Optional login-rate-limit bypass for a trusted office/static IP.
define('TRUSTED_LOGIN_IPS_LOCAL', ['12.6.64.130']);

if (!defined('TMDB_API_KEY') || TMDB_API_KEY === '') {
    // This constant cannot be redefined if config.php already declared it as a const.
    // The app reads TMDB_API_KEY_LOCAL and TMDB_API_READ_ACCESS_TOKEN_LOCAL first when present.
}
