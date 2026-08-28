<?php
/**
 * File: api/toggle-episode.php
 * Project: TV Binge Board
 * Description: Toggles individual TV episodes or updates season watch state while preserving TMDB episode artwork references and using host-friendly mode parameters.
 * Author: Jason Lamb / ChatGPT
 * Created: 2026-07-02
 * Modified: 2026-07-03
 * Revision: 1.5.10
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
$mode = (string)($_POST['mode'] ?? $_POST['action'] ?? 'toggle_episode');
$modeMap = [
    'season_watch' => 'mark_season_watched',
    'season_clear' => 'mark_season_unwatched',
    'episode_toggle' => 'toggle_episode',
];
$action = $modeMap[$mode] ?? $mode;
$episodeTitle = trim((string)($_POST['episode_title'] ?? ''));
$airDate = trim((string)($_POST['air_date'] ?? ''));
$stillPath = trim((string)($_POST['still_path'] ?? ''));
$localStillPath = trim((string)($_POST['local_still_path'] ?? ''));
$library = app_library($targetUser);
$index = app_find_media_index($library, $uid);
if ($index === null || ($library['items'][$index]['type'] ?? '') !== 'tv') { http_response_code(404); exit('TV item not found.'); }

$existingEpisodes = [];
foreach (($library['items'][$index]['episodes'] ?? []) as $entry) {
    $entrySeason = (int)($entry['season'] ?? -1);
    $entryEpisode = (int)($entry['episode'] ?? -1);
    if ($entrySeason <= 0 || $entryEpisode <= 0) { continue; }
    $existingEpisodes[$entrySeason . '-' . $entryEpisode] = $entry;
}

$activity = 'episode-watched';
$activityData = ['uid' => $uid, 'season' => $season, 'episode' => $episode];

switch ($action) {
    case 'mark_season_watched':
        $seasonEpisodes = [];
        foreach (($library['items'][$index]['seasons'] ?? []) as $seasonEntry) {
            if (!is_array($seasonEntry)) { continue; }
            if ((int)($seasonEntry['season_number'] ?? 0) !== $season) { continue; }
            $count = max(0, (int)($seasonEntry['episode_count'] ?? 0));
            for ($n = 1; $n <= $count; $n++) {
                $seasonEpisodes[] = $n;
            }
            break;
        }
        if ($seasonEpisodes === []) {
            $fallbackMax = 0;
            foreach (array_keys($existingEpisodes) as $key) {
                [$entrySeason, $entryEpisode] = array_map('intval', explode('-', $key, 2));
                if ($entrySeason === $season) { $fallbackMax = max($fallbackMax, $entryEpisode); }
            }
            for ($n = 1; $n <= $fallbackMax; $n++) {
                $seasonEpisodes[] = $n;
            }
        }
        foreach ($seasonEpisodes as $episodeNumber) {
            $key = $season . '-' . $episodeNumber;
            if (!isset($existingEpisodes[$key])) {
                $existingEpisodes[$key] = [
                    'season' => $season,
                    'episode' => $episodeNumber,
                    'title' => 'Episode ' . $episodeNumber,
                    'air_date' => '',
                    'still_path' => '',
                    'local_still_path' => '',
                    'watched_at' => date(DATE_ATOM),
                ];
            }
        }
        $library['items'][$index]['last_episode'] = [
            'season' => $season,
            'episode' => $seasonEpisodes === [] ? 0 : max($seasonEpisodes),
            'watched_at' => date(DATE_ATOM),
        ];
        $activity = 'season-watched';
        $activityData = ['uid' => $uid, 'season' => $season, 'episode_count' => count($seasonEpisodes)];
        break;

    case 'mark_season_unwatched':
        foreach (array_keys($existingEpisodes) as $key) {
            [$entrySeason] = array_map('intval', explode('-', $key, 2));
            if ($entrySeason === $season) {
                unset($existingEpisodes[$key]);
            }
        }
        $activity = 'season-unwatched';
        $activityData = ['uid' => $uid, 'season' => $season];
        break;

    case 'toggle_episode':
    default:
        $key = $season . '-' . $episode;
        if (isset($existingEpisodes[$key])) {
            unset($existingEpisodes[$key]);
            $activity = 'episode-unwatched';
        } else {
            $entry = ['season' => $season, 'episode' => $episode, 'title' => $episodeTitle, 'air_date' => $airDate, 'still_path' => $stillPath, 'local_still_path' => $localStillPath, 'watched_at' => date(DATE_ATOM)];
            $existingEpisodes[$key] = $entry;
            $library['items'][$index]['last_episode'] = $entry;
        }
        break;
}

uksort($existingEpisodes, static function (string $left, string $right): int {
    [$leftSeason, $leftEpisode] = array_map('intval', explode('-', $left, 2));
    [$rightSeason, $rightEpisode] = array_map('intval', explode('-', $right, 2));
    return [$leftSeason, $leftEpisode] <=> [$rightSeason, $rightEpisode];
});

$library['items'][$index]['episodes'] = array_values($existingEpisodes);
if (($library['items'][$index]['episodes'] ?? []) === []) {
    unset($library['items'][$index]['last_episode']);
} else {
    $lastEpisode = end($library['items'][$index]['episodes']);
    $library['items'][$index]['last_episode'] = is_array($lastEpisode) ? $lastEpisode : null;
    reset($library['items'][$index]['episodes']);
    if ($library['items'][$index]['last_episode'] === null) {
        unset($library['items'][$index]['last_episode']);
    }
}
$library['items'][$index]['updated_at'] = date(DATE_ATOM);
app_save_library($targetUser, $library);
app_log_activity((string)$user['username'], $activity, $targetUser, $activityData);
$redirect = (string)($_POST['redirect'] ?? '../item.php?uid=' . rawurlencode($uid));
if ($redirect === '' || str_starts_with($redirect, 'http://') || str_starts_with($redirect, 'https://') || str_contains($redirect, "\n") || str_contains($redirect, "\r")) {
    $redirect = '../item.php?uid=' . rawurlencode($uid);
}
header('Location: ' . $redirect);
exit;
