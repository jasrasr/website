<?php
/**
 * File: watch-progress.php
 * Project: TV Binge Board
 * Description: Host-friendly watch progress endpoint for episode and season watched state, including optional prior-episode and prior-season progress fill.
 * Author: Jason Lamb / ChatGPT
 * Created: 2026-07-04
 * Modified: 2026-07-05
 * Revision: 1.2.0
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
    if (!empty($item['tmdb_id']) && app_tmdb_configured()) {
        try {
            $seasonDetails = app_tmdb_season_details((int)$item['tmdb_id'], $season);
            if (is_array($seasonDetails['episodes'] ?? null)) { return $seasonDetails['episodes']; }
        } catch (Throwable $ex) {}
    }

    $episodeCount = 0;
    foreach (($item['seasons'] ?? []) as $seasonEntry) {
        if (is_array($seasonEntry) && (int)($seasonEntry['season_number'] ?? 0) === $season) {
            $episodeCount = max(0, (int)($seasonEntry['episode_count'] ?? 0));
            break;
        }
    }
    foreach (($item['episodes'] ?? []) as $entry) {
        if (is_array($entry) && (int)($entry['season'] ?? 0) === $season) {
            $episodeCount = max($episodeCount, (int)($entry['episode'] ?? 0));
        }
    }
    $episodes = [];
    for ($n = 1; $n <= min($episodeCount, 80); $n++) {
        $episodes[] = ['episode_number' => $n, 'name' => 'Episode ' . $n, 'air_date' => '', 'still_path' => '', 'local_still_path' => ''];
    }
    return $episodes;
}

function app_progress_episode_entry(int $season, int $episode, array $seasonEpisodes): array
{
    $episodeData = [];
    foreach ($seasonEpisodes as $candidate) {
        if (is_array($candidate) && (int)($candidate['episode_number'] ?? 0) === $episode) { $episodeData = $candidate; break; }
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

function app_progress_season_numbers(array $item, int $throughSeason): array
{
    $numbers = [];
    foreach (($item['seasons'] ?? []) as $seasonEntry) {
        if (!is_array($seasonEntry)) { continue; }
        $number = (int)($seasonEntry['season_number'] ?? 0);
        if ($number > 0 && $number <= $throughSeason) { $numbers[$number] = true; }
    }
    for ($number = 1; $number <= $throughSeason; $number++) { $numbers[$number] = true; }
    $result = array_keys($numbers);
    sort($result, SORT_NUMERIC);
    return $result;
}

$redirect = app_progress_redirect($uid, $targetUser, $user, $season, $episodeView);
if ($uid === '' || $season <= 0) { http_response_code(400); exit('Missing item or season.'); }

$library = app_library($targetUser);
$index = app_find_media_index($library, $uid);
if ($index === null || ($library['items'][$index]['type'] ?? '') !== 'tv') { http_response_code(404); exit('TV item not found.'); }

$item = $library['items'][$index];
$existing = [];
foreach (($item['episodes'] ?? []) as $entry) {
    if (!is_array($entry)) { continue; }
    $entrySeason = (int)($entry['season'] ?? 0);
    $entryEpisode = (int)($entry['episode'] ?? 0);
    if ($entrySeason > 0 && $entryEpisode > 0) { $existing[$entrySeason . '-' . $entryEpisode] = $entry; }
}

$seasonEpisodes = app_progress_season_episode_data($item, $season);
$activity = 'episode-progress-updated';
$activityData = ['uid' => $uid, 'season' => $season];

if ($operation === 'sw' || $operation === 'swp') {
    $seasonsToMark = $operation === 'swp' ? app_progress_season_numbers($item, $season) : [$season];
    foreach ($seasonsToMark as $seasonToMark) {
        $episodesToMark = app_progress_season_episode_data($item, (int)$seasonToMark);
        foreach ($episodesToMark as $episodeData) {
            $episodeNumber = (int)($episodeData['episode_number'] ?? 0);
            if ($episodeNumber > 0 && !isset($existing[$seasonToMark . '-' . $episodeNumber])) {
                $existing[$seasonToMark . '-' . $episodeNumber] = app_progress_episode_entry((int)$seasonToMark, $episodeNumber, $episodesToMark);
            }
        }
    }
    $activity = $operation === 'swp' ? 'season-watched-through' : 'season-watched';
    $activityData['episode_count'] = count($existing);
    $activityData['through_season'] = $operation === 'swp' ? $season : null;
} elseif ($operation === 'sc') {
    foreach (array_keys($existing) as $key) {
        [$entrySeason] = array_map('intval', explode('-', $key, 2));
        if ($entrySeason === $season) { unset($existing[$key]); }
    }
    $activity = 'season-unwatched';
} else {
    if ($episode <= 0) { http_response_code(400); exit('Missing episode.'); }
    $key = $season . '-' . $episode;
    if ($operation === 'ec' || isset($existing[$key])) {
        unset($existing[$key]);
        $activity = 'episode-unwatched';
    } elseif ($operation === 'eo') {
        $existing[$key] = app_progress_episode_entry($season, $episode, $seasonEpisodes);
        $activity = 'episode-watched';
    } else {
        foreach (app_progress_season_numbers($item, $season) as $seasonNumber) {
            $episodesToFill = app_progress_season_episode_data($item, (int)$seasonNumber);
            foreach ($episodesToFill as $episodeData) {
                $episodeNumber = (int)($episodeData['episode_number'] ?? 0);
                if ($episodeNumber <= 0 || ($seasonNumber === $season && $episodeNumber > $episode)) { continue; }
                if (!isset($existing[$seasonNumber . '-' . $episodeNumber])) {
                    $existing[$seasonNumber . '-' . $episodeNumber] = app_progress_episode_entry((int)$seasonNumber, $episodeNumber, $episodesToFill);
                }
            }
        }
        $activity = 'episode-watched-through';
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
