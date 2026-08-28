<?php
/**
 * File: api/add-media.php
 * Project: TV Binge Board
 * Description: Adds a movie or TV show to a user library, enriches TMDB items, and locally caches poster art.
 * Author: Jason Lamb / ChatGPT
 * Created: 2026-07-02
 * Modified: 2026-07-02
 * Revision: 1.4.2
 */
declare(strict_types=1);

require_once __DIR__ . '/../includes/tmdb.php';
$user = app_require_login();
app_verify_csrf();

$targetUser = app_is_admin($user) ? app_sanitize_username((string)($_POST['target_user'] ?? '')) : (string)$user['username'];
if ($targetUser === '' || !app_find_user($targetUser)) { http_response_code(400); exit('Invalid target user.'); }
if (!app_is_admin($user) && !app_can_track($user)) { http_response_code(403); exit('This account cannot track media.'); }

$type = (string)($_POST['type'] ?? 'movie');
$type = in_array($type, ['movie', 'tv'], true) ? $type : 'movie';
$title = trim((string)($_POST['title'] ?? ''));
$tmdbId = isset($_POST['tmdb_id']) && $_POST['tmdb_id'] !== '' ? (int)$_POST['tmdb_id'] : null;
if ($title === '' && (!$tmdbId || $tmdbId <= 0)) { http_response_code(400); exit('Title is required.'); }

$details = [];
if ($tmdbId !== null && $tmdbId > 0) {
    try {
        $details = app_tmdb_details($type, $tmdbId);
        if ($title === '' && !empty($details['title'])) { $title = (string)$details['title']; }
    } catch (Throwable $ex) {
        app_flash('Added with basic TMDB search data. Detail refresh failed: ' . $ex->getMessage(), 'warning');
    }
}

$uid = app_make_media_uid($type, $tmdbId, $title);
$library = app_library($targetUser);
$index = app_find_media_index($library, $uid);
$item = [
    'uid' => $uid,
    'source' => $tmdbId ? 'tmdb' : 'manual',
    'tmdb_id' => $tmdbId,
    'tmdb_url' => $tmdbId ? app_tmdb_external_url($type, $tmdbId) : '',
    'type' => $type,
    'title' => $title,
    'year' => trim((string)($_POST['year'] ?? '')),
    'poster_path' => trim((string)($_POST['poster_path'] ?? '')),
    'poster_url' => trim((string)($_POST['poster_url'] ?? '')),
    'overview' => trim((string)($_POST['overview'] ?? '')),
    'status' => array_key_exists((string)($_POST['status'] ?? 'watchlist'), app_statuses()) ? (string)($_POST['status'] ?? 'watchlist') : 'watchlist',
    'rating' => null,
    'notes' => '',
    'episodes' => [],
    'total_seasons' => ($_POST['total_seasons'] ?? '') === '' ? null : max(0, (int)$_POST['total_seasons']),
    'total_episodes' => ($_POST['total_episodes'] ?? '') === '' ? null : max(0, (int)$_POST['total_episodes']),
    'created_at' => date(DATE_ATOM),
    'updated_at' => date(DATE_ATOM),
];
if ($details !== []) {
    $item = app_apply_tmdb_details_to_item($item, $details, true);
    $item['uid'] = $uid;
    $item['status'] = array_key_exists((string)($_POST['status'] ?? 'watchlist'), app_statuses()) ? (string)($_POST['status'] ?? 'watchlist') : 'watchlist';
}

if (!empty($item['tmdb_id'])) {
    $item = app_tmdb_cache_item_artwork($item, false, false, false);
    unset($item['_artwork_stats']);
}

if ($index === null) {
    $library['items'][] = $item;
    app_flash($details !== [] ? 'Added with TMDB details and local poster cache.' : 'Added to list.', 'success');
} else {
    $preserve = [
        'status' => $library['items'][$index]['status'] ?? $item['status'],
        'rating' => $library['items'][$index]['rating'] ?? null,
        'notes' => $library['items'][$index]['notes'] ?? '',
        'episodes' => $library['items'][$index]['episodes'] ?? [],
        'created_at' => $library['items'][$index]['created_at'] ?? date(DATE_ATOM),
    ];
    $library['items'][$index] = array_merge($library['items'][$index], array_filter($item, static fn($v) => $v !== null && $v !== ''));
    foreach ($preserve as $key => $value) { $library['items'][$index][$key] = $value; }
    if (!empty($library['items'][$index]['tmdb_id'])) {
        $library['items'][$index] = app_tmdb_cache_item_artwork($library['items'][$index], false, false, false);
        unset($library['items'][$index]['_artwork_stats']);
    }
    $library['items'][$index]['updated_at'] = date(DATE_ATOM);
    app_flash('Existing TMDB item updated.', 'success');
}
app_save_library($targetUser, $library);
app_log_activity((string)$user['username'], 'media-added-or-updated', $targetUser, ['uid' => $uid, 'tmdb_id' => $tmdbId]);
header('Location: ' . (string)($_POST['redirect'] ?? '../watchlist.php'));
exit;
