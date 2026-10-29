<?php
$_SERVER['SCRIPT_NAME'] = '/tv-binge-board/api/delete-media.php';
require_once __DIR__ . '/../includes/functions.php';

$fallback = '../watchlist.php';
foreach (['../watchlist.php', '../admin/site-settings.php?u=john',
          '/tv-binge-board/item.php?uid=123#season-1'] as $location) {
    if (app_safe_return_location($location, $fallback) !== $location) {
        throw new RuntimeException("Expected local return path: $location");
    }
}

foreach (['https://evil.example/', '//evil.example/', "\\\\evil.example\\path",
          '../../other-app/', '../admin/../../other-app/', '/other-app/page.php',
          "../watchlist.php\r\nLocation: https://evil.example/"] as $location) {
    if (app_safe_return_location($location, $fallback) !== $fallback) {
        throw new RuntimeException("Expected safe fallback for: $location");
    }
}

echo "Redirect checks passed.\n";
