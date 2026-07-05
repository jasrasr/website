<?php
/**
 * File: progress.php
 * Project: TV Binge Board
 * Description: Root-level watch progress endpoint. Marks episode/season progress and fills prior episodes when a later episode is selected.
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
$op = (string)($_POST['op'] ?? 'et');
$view = (string)($_POST['v'] ?? 'image');
if (!in_array($view, ['image', 'text'], true)) { $view = 'image'; }
$targetUser = app_is_admin($user) ? app_sanitize_username((string)($_POST['tu'] ?? '')) : (string)$user['username'];
if ($uid === '' || $season <= 0 || $targetUser === '' || !app_find_user($targetUser)) {
    http_response_code(400);
    exit('Invalid progress request.');
}
if (!app_is_admin($user) && $targetUser !== (string)$user['username']) {
    http_response_code(403);
    exit('Forbidden.');
}

function app_progress_back_url(string $uid, string $targetUser, array $user, int $season, string $view): string
{
    $url = 'item.php?uid=' . rawurlencode($uid);
    if (app_is_admin($user)) { $url .= '&u=' . rawurlencode($targetUser); }
    return $url . '&episode_view=' . rawurlencode($view) . '&season=' . max(1, $season) . '#season-' . max(1, $season);
}

function app_progress_season_data(array $item, int $season): array
{
    if (!empty($item['tmdb_id']) && app_tmdb_configured()) {
        try {
            $details = app_tmdb_season_details((int)$item['tmdb_id'], $season);
            if (is_array($details['episodes'] ?? null)) { return $details['episodes']; }
        } catch (Throwable $ex) {}
    }
    $count = 0;
    foreach (($item['seasons'] ?? []) as $entry) {
        if (is_array($entry) && (int)($entry['season_number'] ?? 0) === $season) {
            $count = max(0, (int)($entry['episode_count'] ?? 0));
            break;
        }
    }
    foreach (($item['episodes'] ?? []) as $entry) {
        if (is_array($entry) && (int)($entry['season'] ?? 0) === $season) {
            $count = max($count, (int)($entry['episode'] ?? 0));
        }
    }
    $episodes = [];
    for ($n = 1; $n <= min($count, 80); $n++) {
        $episodes[] = ['episode_number' => $n, 'name' => 'Episode ' . $n, 'air_date' => '', 'still_path' => '', 'local_still_path' => ''];
    }
    return $episodes;
}

function app_progress_make_entry(int $season, int $episode, array $seasonData): array
{
    $found = [];
    foreach ($seasonData as $entry) {
        if (is_array($entry) && (int)($entry['episode_number'] ?? 0) === $episode) { $found = $entry; break; }
    }
    return [
        'season' => $season,
        'episode' => $episode,
        'title' => (string)($found['name'] ?? ('Episode ' . $episode)),
        'air_date' => (string)($found['air_date'] ?? ''),
        'still_path' => (string)($found['still_path'] ?? ''),
        'local_still_path' => (string)($found['local_still_path'] ?? ''),
        'watched_at' => date(DATE_ATOM),
    ];
}

function app_progress_season_numbers(array $item, int $throughSeason): array
{
    $numbers = [];
    foreach (($item['seasons'] ?? []) as $entry) {
        if (!is_array($entry)) { continue; }
        $number = (int)($entry['season_number'] ?? 0);
        if ($number > 0 && $number <= $throughSeason) { $numbers[$number] = true; }
    }
    for ($n = 1; $n <= $throughSeason; $n++) { $numbers[$n] = true; }
    $result = array_keys($numbers);
    sort($result, SORT_NUMERIC);
    return $result;
}

$library = app_library($targetUser);
$index = app_find_media_index($library, $uid);
if ($index === null || ($library['items'][$index]['type'] ?? '') !== 'tv') {
    http_response_code(404);
    exit('TV item not found.');
}
$item = $library['items'][$index];
$existing = [];
foreach (($item['episodes'] ?? []) as $entry) {
    if (!is_array($entry)) { continue; }
    $s = (int)($entry['season'] ?? 0);
    $e = (int)($entry['episode'] ?? 0);
    if ($s > 0 && $e > 0) { $existing[$s . '-' . $e] = $entry; }
}

$seasonData = app_progress_season_data($item, $season);
$activity = 'episode-progress-updated';
$activityData = ['uid' => $uid, 'season' => $season];

if ($op === 'sw') {
    foreach ($seasonData as $entry) {
        $n = (int)($entry['episode_number'] ?? 0);
        if ($n > 0 && !isset($existing[$season . '-' . $n])) { $existing[$season . '-' . $n] = app_progress_make_entry($season, $n, $seasonData); }
    }
    $activity = 'season-watched';
    $activityData['episode_count'] = count($seasonData);
} elseif ($op === 'sc') {
    foreach (array_keys($existing) as $key) {
        [$s] = array_map('intval', explode('-', $key, 2));
        if ($s === $season) { unset($existing[$key]); }
    }
    $activity = 'season-unwatched';
} else {
    if ($episode <= 0) { http_response_code(400); exit('Missing episode.'); }
    $key = $season . '-' . $episode;
    if (isset($existing[$key])) {
        unset($existing[$key]);
        $activity = 'episode-unwatched';
    } else {
        foreach (app_progress_season_numbers($item, $season) as $s) {
            $data = app_progress_season_data($item, (int)$s);
            foreach ($data as $entry) {
                $n = (int)($entry['episode_number'] ?? 0);
                if ($n <= 0 || ($s === $season && $n > $episode)) { continue; }
                if (!isset($existing[$s . '-' . $n])) { $existing[$s . '-' . $n] = app_progress_make_entry((int)$s, $n, $data); }
            }
        }
        $activity = 'episode-watched-through';
    }
    $activityData['episode'] = $episode;
}

uksort($existing, static function (string $a, string $b): int {
    [$as, $ae] = array_map('intval', explode('-', $a, 2));
    [$bs, $be] = array_map('intval', explode('-', $b, 2));
    return [$as, $ae] <=> [$bs, $be];
});
$library['items'][$index]['episodes'] = array_values($existing);
if ($library['items'][$index]['episodes'] === []) {
    unset($library['items'][$index]['last_episode']);
} else {
    $last = end($library['items'][$index]['episodes']);
    if (is_array($last)) { $library['items'][$index]['last_episode'] = $last; }
    reset($library['items'][$index]['episodes']);
}
$library['items'][$index]['updated_at'] = date(DATE_ATOM);
app_save_library($targetUser, $library);
app_log_activity((string)$user['username'], $activity, $targetUser, $activityData);
header('Location: ' . app_progress_back_url($uid, $targetUser, $user, $season, $view));
exit;
