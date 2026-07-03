<?php
/**
 * File: includes/vision.php
 * Project: TV Binge Board
 * Description: Optional server-side vision extraction helpers for screenshot-assisted imports.
 * Author: Jason Lamb / ChatGPT
 * Created: 2026-07-03
 * Modified: 2026-07-03
 * Revision: 1.0.0
 */
declare(strict_types=1);

require_once __DIR__ . '/tmdb.php';

function app_vision_endpoint(): string
{
    return defined('APP_VISION_ENDPOINT_LOCAL') ? trim((string)constant('APP_VISION_ENDPOINT_LOCAL')) : '';
}

function app_vision_token(): string
{
    return defined('APP_VISION_TOKEN_LOCAL') ? trim((string)constant('APP_VISION_TOKEN_LOCAL')) : '';
}

function app_vision_configured(): bool
{
    return app_vision_endpoint() !== '';
}
