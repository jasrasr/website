<?php
/**
 * File: api/refresh-artwork.php
 * Project: TV Binge Board
 * Description: Downloads or refreshes local TMDB artwork cache for one item or a full user library, including posters, season posters, and episode stills.
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

$scope = (string)($_POST['scope'] ?? 'item');
$uid = (string)($_POST['uid'] ?? '');
$force = !empty($_POST['force']);
$includeEpisodes = !empty($_POST['include_episodes']) || $scope === 'item';
$includeSeasons = !empty($_POST['include_seasons']) || $includeEpisodes || $scope === 'item';
$library = app_library($targetUser);
$stats = ['items' => 0, 'posters' => 0, 'season_posters' => 0, 'episode_stills' => 0, 'failed' => 0];

try {
    foreach ($library['items'] as $index => $item) {
        if ($scope === 'item' && ($item['uid'] ?? '') !== $uid) { continue; }
        $tmdbId = (int)($item['tmdb_id'] ?? 0);
        $type = (string)($item['type'] ?? '');
        if ($tmdbId <= 0 || !in_array($type, ['movie', 'tv'], true)) { continue; }
        try {
            $updated = app_tmdb_cache_item_artwork($item, $force, $includeSeasons, $includeEpisodes);
            $itemStats = $updated['_artwork_stats'] ?? [];
            unset($updated['_artwork_stats']);
            $library['items'][$index] = $updated;
            $stats['items']++;
            $stats['posters'] += (int)($itemStats['posters'] ?? 0);
            $stats['season_posters'] += (int)($itemStats['season_posters'] ?? 0);
            $stats['episode_stills'] += (int)($itemStats['episode_stills'] ?? 0);
            $stats['failed'] += (int)($itemStats['failed'] ?? 0);
        } catch (Throwable $ex) {
            $stats['failed']++;
        }
    }
    app_save_library($targetUser, $library);
    app_log_activity((string)$user['username'], 'tmdb-local-artwork-refresh', $targetUser, $stats + ['scope' => $scope, 'uid' => $uid]);
    app_flash('Artwork cache complete. Items: ' . $stats['items'] . '. Posters: ' . $stats['posters'] . '. Season posters: ' . $stats['season_posters'] . '. Episode stills: ' . $stats['episode_stills'] . '. Failed: ' . $stats['failed'] . '.', $stats['failed'] > 0 ? 'warning' : 'success');
} catch (Throwable $ex) {
    app_flash('Artwork cache failed: ' . $ex->getMessage(), 'danger');
}

header('Location: ' . (string)($_POST['redirect'] ?? '../watchlist.php'));
exit;
