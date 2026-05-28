<?php
/*
Filename : fetch.php
Revision : 1.1
Description : Returns stored notifications as JSON for the PSNotify viewer.
Author : Jason Lamb (with help from ChatGPT)
Created Date : 2026-03-20
Modified Date : 2026-03-20
Changelog :
1.0 initial release
1.1 standardized header and changelog format
*/

require_once __DIR__ . '/common.php';

psnotify_require_view_auth();

$topic = psnotify_clean_topic((string) ($_GET['topic'] ?? DEFAULT_TOPIC));
$limit = max(1, min(200, (int) ($_GET['limit'] ?? 50)));
$since = trim((string) ($_GET['since'] ?? ''));
$showAllTopics = isset($_GET['all']) && $_GET['all'] === '1';

$items = psnotify_load_notifications();

$items = array_values(array_filter($items, function ($item) use ($topic, $since, $showAllTopics) {
    if (!$showAllTopics && (($item['topic'] ?? '') !== $topic)) {
        return false;
    }

    if ($since !== '' && strcmp((string) ($item['id'] ?? ''), $since) <= 0) {
        return false;
    }

    return true;
}));

usort($items, fn ($a, $b) => strcmp((string) ($b['id'] ?? ''), (string) ($a['id'] ?? '')));
$items = array_slice($items, 0, $limit);

psnotify_json_response([
    'ok' => true,
    'topic' => $topic,
    'count' => count($items),
    'items' => $items,
    'server_time' => date('Y-m-d h:i:s A T')
]);
