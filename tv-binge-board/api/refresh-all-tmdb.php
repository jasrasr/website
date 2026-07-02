<?php
/**
 * File: api/refresh-all-tmdb.php
 * Project: TV Binge Board
 * Description: Refreshes TMDB metadata and local poster cache for every linked item in a user library.
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

$library = app_library($targetUser);
$refreshed = 0;
$failed = 0;
foreach ($library['items'] as $index => $item) {
    $tmdbId = (int)($item['tmdb_id'] ?? 0);
    $type = (string)($item['type'] ?? '');
    if ($tmdbId <= 0 || !in_array($type, ['movie', 'tv'], true)) { continue; }
    try {
        $details = app_tmdb_details($type, $tmdbId, true);
        $updated = app_apply_tmdb_details_to_item($item, $details, true);
        $updated['uid'] = (string)($item['uid'] ?? app_make_media_uid($type, $tmdbId, (string)($details['title'] ?? '')));
        $updated = app_tmdb_cache_item_artwork($updated, false, false, false);
        unset($updated['_artwork_stats']);
        $library['items'][$index] = $updated;
        $refreshed++;
    } catch (Throwable $ex) {
        $failed++;
    }
}
app_save_library($targetUser, $library);
app_log_activity((string)$user['username'], 'tmdb-library-refresh', $targetUser, ['refreshed' => $refreshed, 'failed' => $failed]);
app_flash('TMDB refresh complete. Refreshed metadata/posters: ' . $refreshed . '. Failed: ' . $failed . '.', $failed > 0 ? 'warning' : 'success');
header('Location: ' . (string)($_POST['redirect'] ?? '../watchlist.php'));
exit;
