<?php
/**
 * File Name: config.local.example.php
 * Author: Jason Lamb (with help from ChatGPT)
 * Description: Host-only Twelve Data quote provider settings. Copy to ignored config.local.php and insert YOUR key on Hostinger.
 * Revision: 2.0.0
 * Created Date: 2026-10-29
 * Modified Date: 2026-11-22
 * Changelog: 2.0.0 - Document private Twelve Data API credentials and free-tier quota controls; no real keys committed. 1.0.0 - Optional future private configuration template.
 */
declare(strict_types=1);
return [
    // Required to enable automatic current stock prices. Create a free key
    // at https://twelvedata.com/ . Never put the key in JS/HTML or GitHub.
    'twelve_data_api_key' => '',

    // Basic tier is currently 8 API credits/minute and 800/day; each stock
    // consumes one credit even when symbols are requested in a single batch.
    // The defaults below reserve 100 daily credits for other API requests.
    'market_quote_credits_per_minute' => 8,
    'market_quote_credits_per_day' => 700,
    'market_quote_cache_seconds' => 1800, // 30 min / stock
    'market_quote_stale_seconds' => 259200, // 72h; weekends/holidays may be marked stale
];
