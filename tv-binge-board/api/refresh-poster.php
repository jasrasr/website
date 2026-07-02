<?php
/**
 * File: api/refresh-poster.php
 * Project: TV Binge Board
 * Description: Refreshes TMDB detail metadata and downloads the current TMDB poster to the local public cache.
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
$uid = (string)($_POST['uid'] ?? '');
$library = app_library($targetUser);
$index = app_find_media_index($library, $uid);
if ($index === null) { http_response_code(404); exit('Item not found.'); }
$item = $library['items'][$index];
try {
    $tmdbId = (int)($item['tmdb_id'] ?? 0);
    if ($tmdbId <= 0) { throw new RuntimeException('This item is not linked to TMDB.'); }
    $details = app_tmdb_details((string)($item['type'] ?? 'movie'), $tmdbId, true);
    $library['items'][$index] = app_apply_tmdb_details_to_item($item, $details, true);
    $library['items'][$index]['uid'] = $uid;
    $library['items'][$index] = app_tmdb_cache_item_artwork($library['items'][$index], true, false, false);
    unset($library['items'][$index]['_artwork_stats']);
    app_save_library($targetUser, $library);
    app_log_activity((string)$user['username'], 'tmdb-details-refreshed', $targetUser, ['uid' => $uid, 'tmdb_id' => $tmdbId]);
    app_flash('TMDB details refreshed and poster cached locally.', 'success');
} catch (Throwable $ex) {
    app_flash('Refresh failed: ' . $ex->getMessage(), 'danger');
}
header('Location: ' . (string)($_POST['redirect'] ?? '../watchlist.php'));
exit;
