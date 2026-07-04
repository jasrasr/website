<?php
/**
 * File: watch-progress.php
 * Project: TV Binge Board
 * Description: Host-friendly watch progress endpoint for toggling episodes and season watched state without API-path or redirect-payload WAF triggers.
 * Author: Jason Lamb / ChatGPT
 * Created: 2026-07-04
 * Modified: 2026-07-04
 * Revision: 1.0.0
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/tmdb.php';

$user = app_require_login();
app_verify_csrf();

$uid = (string)($_POST['uid'] ?? '');
$season = max(0, (int)($_POST['s'] ?? 0));
$episode = max(0, (int)($_POST['e'] ?? 0));
$operation = (string)($_POST['op'] ?? 'et');
$episodeView = (string)($_POST['v'] ?? 'image');
if (!in_array($episodeView, ['image', 'text'], true)) { $episodeView = 'image'; }

$targetUser = app_is_admin($user) ? app_sanitize_username((string)($_POST['tu'] ?? '')) : (string)$user['username'];
if ($targetUser === '' || !app_find_user($targetUser) || (!app_is_admin($user) && $targetUser !== $user['username'])) {
    http_response_code(403);
    exit('Forbidden.');
}

function app_progress_redirect(string $uid, string $targetUser, array $user, int $season, string $episodeView): string
{
    $target = 'item.php?uid=' . rawurlencode($uid);
    if (app_is_admin($user)) { $target .= '&u=' . rawurlencode($targetUser); }
    $target .= '&episode_view=' . rawurlencode($episodeView) . '&season=' . max(1, $season) . '#season-' . max(1, $season);
    return $target;
}

function app_progress_season_episode_data(array $item, int $season): array
{
    $episodes = [];
    if (!empty($item['tmdb_id']) && app_tmdb_configured()) {
        try {
            $seasonDetails = app_tmdb_season_details((int)$item['tmdb_id'], $season);
            if (is_array($seasonDetails) && !empty($seasonDetails['episodes']) && is_array($seasonDetails['episodes'])) {
                return $seasonDetails['episodes'];
            }
        } catch (Throwable $ex) {}
    }

    $episodeCount = 0;
    foreach (($item['seasons'] ?? []) as $seasonEntry) {
        if (!is_array($seasonEntry)) { continue; }
        if ((int)($seasonEntry['season_number'] ?? 0) === $season) {
            $episodeCount = max(0, (int)($seasonEntry['episode_count'] ?? 0));
            break;
        }
    }
    if ($episodeCount <= 0) {
        foreach (($item['episodes'] ?? []) as $entry) {
            if (!is_array($entry)) { continue; }
            if ((int)($entry['season'] ?? 0) === $season) {
                $episodeCount = max($episodeCount, (int)($entry['episode'] ?? 0));
            }
        }
    }
    for ($n = 1; $n <= min($episodeCount, 80); $n++) {
        $episodes[] = ['season_number' => $season, 'episode_number' => $n, 'name' => 'Episode ' . $n, 'air_date' => '', 'still_path' => '', 'local_still_path' => ''];
    }
    return $episodes;
}

function app_progress_episode_entry(array $item, int $season, int $episode, array $seasonEpisodes): array
{
    $episodeData = [];
    foreach ($seasonEpisodes as $candidate) {
        if (is_array($candidate) && (int)($candidate['episode_number'] ?? 0) === $episode) {
            $episodeData = $candidate;
            break;
        }
    }
    return [
        'season' => $season,
        'episode' => $episode,
        'title' => (string)($episodeData['name'] ?? ('Episode ' . $episode)),
        'air_date' => (string)($episodeData['air_date'] ?? ''),
        'still_path' => (string)($episodeData['still_path'] ?? ''),
        'local_still_path' => (string)($episodeData['local_still_path'] ?? ''),
        'watched_at' => date(DATE_ATOM),
    ];
}

$redirect = app_progress_redirect($uid, $targetUser, $user, $season, $episodeView);
if ($uid === '' || $season <= 0) {
    http_response_code(400);
    exit('Missing item or season.');
}

$library = app_library($targetUser);
$index = app_find_media_index($library, $uid);
if ($index === null || ($library['items'][$index]['type'] ?? '') !== 'tv') {
    http_response_code(404);
    exit('TV item not found.');
}

$item = $library['items'][$index];
$seasonEpisodes = app_progress_season_episode_data($item, $season);
$existing = [];
foreach (($item['episodes'] ?? []) as $entry) {
    if (!is_array($entry)) { continue; }
    $entrySeason = (int)($entry['season'] ?? 0);
    $entryEpisode = (int)($entry['episode'] ?? 0);
    if ($entrySeason <= 0 || $entryEpisode <= 0) { continue; }
    $existing[$entrySeason . '-' . $entryEpisode] = $entry;
}

$activity = 'episode-progress-updated';
$activityData = ['uid' => $uid, 'season' => $season];

if ($operation === 'sw') {
    foreach ($seasonEpisodes as $episodeData) {
        if (!is_array($episodeData)) { continue; }
        $episodeNumber = (int)($episodeData['episode_number'] ?? 0);
        if ($episodeNumber <= 0) { continue; }
        $key = $season . '-' . $episodeNumber;
        if (!isset($existing[$key])) {
            $existing[$key] = app_progress_episode_entry($item, $season, $episodeNumber, $seasonEpisodes);
        }
    }
    $activity = 'season-watched';
    $activityData['episode_count'] = count($seasonEpisodes);
} elseif ($operation === 'sc') {
    foreach (array_keys($existing) as $key) {
        [$entrySeason] = array_map('intval', explode('-', $key, 2));
        if ($entrySeason === $season) { unset($existing[$key]); }
    }
    $activity = 'season-unwatched';
} else {
    if ($episode <= 0) {
        http_response_code(400);
        exit('Missing episode.');
    }
    $key = $season . '-' . $episode;
    if (isset($existing[$key])) {
        unset($existing[$key]);
        $activity = 'episode-unwatched';
    } else {
        $existing[$key] = app_progress_episode_entry($item, $season, $episode, $seasonEpisodes);
        $activity = 'episode-watched';
    }
    $activityData['episode'] = $episode;
}

uksort($existing, static function (string $left, string $right): int {
    [$leftSeason, $leftEpisode] = array_map('intval', explode('-', $left, 2));
    [$rightSeason, $rightEpisode] = array_map('intval', explode('-', $right, 2));
    return [$leftSeason, $leftEpisode] <=> [$rightSeason, $rightEpisode];
});

$library['items'][$index]['episodes'] = array_values($existing);
if ($library['items'][$index]['episodes'] === []) {
    unset($library['items'][$index]['last_episode']);
} else {
    $lastEpisode = end($library['items'][$index]['episodes']);
    if (is_array($lastEpisode)) { $library['items'][$index]['last_episode'] = $lastEpisode; }
    reset($library['items'][$index]['episodes']);
}
$library['items'][$index]['updated_at'] = date(DATE_ATOM);
app_save_library($targetUser, $library);
app_log_activity((string)$user['username'], $activity, $targetUser, $activityData);

header('Location: ' . $redirect);
exit;
