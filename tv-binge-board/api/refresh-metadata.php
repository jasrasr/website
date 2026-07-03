<?php
/**
 * File: api/refresh-metadata.php
 * Project: TV Binge Board
 * Description: Refreshes TMDB metadata for one library item, including TV season and episode metadata, so newly released episodes appear in the episode grid.
 * Author: Jason Lamb / ChatGPT
 * Created: 2026-07-03
 * Modified: 2026-07-03
 * Revision: 1.0.0
 */
declare(strict_types=1);

require_once __DIR__ . '/../includes/tmdb.php';
$user = app_require_login();
app_verify_csrf();

$targetUser = app_is_admin($user) ? app_sanitize_username((string)($_POST['target_user'] ?? '')) : (string)$user['username'];
if ($targetUser === '' || !app_find_user($targetUser)) { http_response_code(400); exit('Invalid target user.'); }
if (!app_is_admin($user) && !app_can_track($user)) { http_response_code(403); exit('This account cannot track media.'); }

$uid = (string)($_POST['uid'] ?? '');
$library = app_library($targetUser);
$index = app_find_media_index($library, $uid);
if ($index === null) { http_response_code(404); exit('Item not found.'); }

$item = $library['items'][$index];
$type = (string)($item['type'] ?? '');
$tmdbId = (int)($item['tmdb_id'] ?? 0);
$seasonCount = 0;
$episodeCount = 0;

try {
    if ($tmdbId <= 0 || !in_array($type, ['movie', 'tv'], true)) {
        throw new RuntimeException('This item is not linked to TMDB yet.');
    }
    $details = app_tmdb_details($type, $tmdbId, true);
    $updated = app_apply_tmdb_details_to_item($item, $details, false);
    if ($type === 'tv') {
        foreach (($updated['seasons'] ?? []) as $season) {
            if (!is_array($season)) { continue; }
            $seasonNumber = (int)($season['season_number'] ?? 0);
            if ($seasonNumber <= 0) { continue; }
            try {
                $seasonDetails = app_tmdb_season_details($tmdbId, $seasonNumber, true);
                $seasonCount++;
                $episodeCount += count((array)($seasonDetails['episodes'] ?? []));
            } catch (Throwable $seasonEx) {
            }
        }
    }
    $updated['metadata_checked_for_new_episodes_at'] = date(DATE_ATOM);
    $library['items'][$index] = $updated;
    app_save_library($targetUser, $library);
    app_log_activity((string)$user['username'], 'tmdb-metadata-refresh', $targetUser, ['uid' => $uid, 'type' => $type, 'tmdb_id' => $tmdbId, 'seasons' => $seasonCount, 'episodes' => $episodeCount]);
    if ($type === 'tv') {
        app_flash('TMDB metadata refreshed. Checked ' . $seasonCount . ' season(s) and ' . $episodeCount . ' episode record(s). Newly available episodes will appear in the episode grid.', 'success');
    } else {
        app_flash('TMDB metadata refreshed.', 'success');
    }
} catch (Throwable $ex) {
    app_flash('TMDB metadata refresh failed: ' . $ex->getMessage(), 'danger');
}

header('Location: ' . (string)($_POST['redirect'] ?? '../watchlist.php'));
exit;
