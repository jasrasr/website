<?php
/**
 * File: api/toggle-episode.php
 * Project: TV Binge Board
 * Description: Toggles an individual TV episode as watched or unwatched and preserves TMDB episode artwork references.
 * Author: Jason Lamb / ChatGPT
 * Created: 2026-07-02
 * Modified: 2026-07-02
 * Revision: 1.4.2
 */
declare(strict_types=1);


require_once __DIR__ . '/../includes/functions.php';
$user = app_require_login();
app_verify_csrf();
$targetUser = app_is_admin($user) ? app_sanitize_username((string)($_POST['target_user'] ?? '')) : (string)$user['username'];
if ($targetUser === '' || !app_find_user($targetUser)) { http_response_code(400); exit('Invalid target user.'); }
$uid = (string)($_POST['uid'] ?? '');
$season = max(0, (int)($_POST['season'] ?? 0));
$episode = max(0, (int)($_POST['episode'] ?? 0));
$episodeTitle = trim((string)($_POST['episode_title'] ?? ''));
$airDate = trim((string)($_POST['air_date'] ?? ''));
$stillPath = trim((string)($_POST['still_path'] ?? ''));
$localStillPath = trim((string)($_POST['local_still_path'] ?? ''));
$library = app_library($targetUser);
$index = app_find_media_index($library, $uid);
if ($index === null || ($library['items'][$index]['type'] ?? '') !== 'tv') { http_response_code(404); exit('TV item not found.'); }
$key = $season . '-' . $episode;
$episodes = [];
$removed = false;
foreach (($library['items'][$index]['episodes'] ?? []) as $entry) {
    if (((int)($entry['season'] ?? -1) . '-' . (int)($entry['episode'] ?? -1)) === $key) { $removed = true; continue; }
    $episodes[] = $entry;
}
if (!$removed) {
    $entry = ['season' => $season, 'episode' => $episode, 'title' => $episodeTitle, 'air_date' => $airDate, 'still_path' => $stillPath, 'local_still_path' => $localStillPath, 'watched_at' => date(DATE_ATOM)];
    $episodes[] = $entry;
    $library['items'][$index]['last_episode'] = $entry;
}
$library['items'][$index]['episodes'] = $episodes;
$library['items'][$index]['updated_at'] = date(DATE_ATOM);
app_save_library($targetUser, $library);
app_log_activity((string)$user['username'], $removed ? 'episode-unwatched' : 'episode-watched', $targetUser, ['uid' => $uid, 'season' => $season, 'episode' => $episode]);
header('Location: ' . (string)($_POST['redirect'] ?? '../item.php?uid=' . rawurlencode($uid)));
exit;
