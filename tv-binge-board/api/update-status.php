<?php
/**
 * File: api/update-status.php
 * Project: TV Binge Board
 * Description: Updates status, rating, notes, manual TV totals, and last watched episode for a media item.
 * Author: Jason Lamb / ChatGPT
 * Created: 2026-07-02
 * Modified: 2026-07-02
 * Revision: 1.4.4
 */
declare(strict_types=1);


require_once __DIR__ . '/../includes/functions.php';
$user = app_require_login();
app_verify_csrf();
$targetUser = app_is_admin($user) ? app_sanitize_username((string)($_POST['target_user'] ?? '')) : (string)$user['username'];
if ($targetUser === '' || !app_find_user($targetUser)) { http_response_code(400); exit('Invalid target user.'); }
if (!app_is_admin($user) && !app_can_track($user)) { http_response_code(403); exit('This account cannot track media.'); }
$uid = (string)($_POST['uid'] ?? '');
$library = app_library($targetUser);
$index = app_find_media_index($library, $uid);
if ($index === null) { http_response_code(404); exit('Item not found.'); }
$status = (string)($_POST['status'] ?? $library['items'][$index]['status'] ?? 'watchlist');
if (!array_key_exists($status, app_statuses())) { $status = 'watchlist'; }
$library['items'][$index]['status'] = $status;
$rating = trim((string)($_POST['rating'] ?? ''));
$library['items'][$index]['rating'] = $rating === '' ? null : max(1, min(10, (int)$rating));
$library['items'][$index]['notes'] = trim((string)($_POST['notes'] ?? ''));
if (($library['items'][$index]['type'] ?? '') === 'tv' && empty($library['items'][$index]['tmdb_id'])) {
    $library['items'][$index]['total_seasons'] = trim((string)($_POST['total_seasons'] ?? '')) === '' ? ($library['items'][$index]['total_seasons'] ?? null) : max(0, (int)$_POST['total_seasons']);
    $library['items'][$index]['total_episodes'] = trim((string)($_POST['total_episodes'] ?? '')) === '' ? ($library['items'][$index]['total_episodes'] ?? null) : max(0, (int)$_POST['total_episodes']);
}
$season = trim((string)($_POST['season'] ?? ''));
$episode = trim((string)($_POST['episode'] ?? ''));
if (($library['items'][$index]['type'] ?? '') === 'tv' && $season !== '' && $episode !== '') {
    $selectedSeason = max(0, (int)$season);
    $selectedEpisode = max(0, (int)$episode);
    $entry = ['season' => $selectedSeason, 'episode' => $selectedEpisode, 'watched_at' => date(DATE_ATOM)];
    $trimmedEpisodes = [];
    foreach (($library['items'][$index]['episodes'] ?? []) as $existingEntry) {
        if (!is_array($existingEntry)) { continue; }
        $existingSeason = (int)($existingEntry['season'] ?? 0);
        $existingEpisode = (int)($existingEntry['episode'] ?? 0);
        if ($existingSeason < $selectedSeason || ($existingSeason === $selectedSeason && $existingEpisode <= $selectedEpisode)) {
            $trimmedEpisodes[$existingSeason . '-' . $existingEpisode] = $existingEntry;
        }
    }
    $trimmedEpisodes[$selectedSeason . '-' . $selectedEpisode] = $entry;
    ksort($trimmedEpisodes, SORT_NATURAL);
    $library['items'][$index]['episodes'] = array_values($trimmedEpisodes);
    $library['items'][$index]['last_episode'] = $entry;
}
$library['items'][$index]['updated_at'] = date(DATE_ATOM);
app_save_library($targetUser, $library);
app_log_activity((string)$user['username'], 'media-status-updated', $targetUser, ['uid' => $uid, 'status' => $status]);
app_flash('List item saved.', 'success');
header('Location: ../watchlist.php');
exit;
