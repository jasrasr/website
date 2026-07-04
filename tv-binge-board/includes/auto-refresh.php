<?php
/**
 * File: includes/auto-refresh.php
 * Project: TV Binge Board
 * Description: Lazy automatic TMDB refresh helpers that keep tracked TV shows current with newly aired episodes and seasons.
 * Author: Jason Lamb / ChatGPT
 * Created: 2026-07-03
 * Modified: 2026-07-03
 * Revision: 1.0.0
 */
declare(strict_types=1);

require_once __DIR__ . '/tmdb.php';
require_once __DIR__ . '/next-up.php';

const APP_AUTO_REFRESH_TTL_SECONDS = 21600;
const APP_AUTO_REFRESH_MAX_ITEMS = 3;

function app_auto_refresh_is_trackable_tv(array $item): bool
{
    if (($item['type'] ?? '') !== 'tv') { return false; }
    if ((int)($item['tmdb_id'] ?? 0) <= 0) { return false; }
    if (!app_tmdb_configured()) { return false; }
    return !in_array((string)($item['status'] ?? ''), ['dropped'], true);
}

function app_auto_refresh_last_check_time(array $item): int
{
    foreach (['auto_new_episode_checked_at', 'metadata_checked_for_new_episodes_at', 'metadata_refreshed_at'] as $key) {
        $time = strtotime((string)($item[$key] ?? '')) ?: 0;
        if ($time > 0) { return $time; }
    }
    return 0;
}

function app_auto_refresh_is_stale(array $item, int $ttlSeconds = APP_AUTO_REFRESH_TTL_SECONDS): bool
{
    if (!app_auto_refresh_is_trackable_tv($item)) { return false; }
    $last = app_auto_refresh_last_check_time($item);
    return $last <= 0 || (time() - $last) >= $ttlSeconds;
}

function app_auto_refresh_available_episode_count(array $item): int
{
    if (($item['type'] ?? '') !== 'tv') { return 0; }
    return count(app_next_up_available_episodes($item));
}

function app_auto_refresh_episode_marker(array $episode): string
{
    $season = (int)($episode['season_number'] ?? $episode['season'] ?? 0);
    $episodeNumber = (int)($episode['episode_number'] ?? $episode['episode'] ?? 0);
    $airDate = (string)($episode['air_date'] ?? '');
    return $season . '-' . $episodeNumber . '-' . $airDate;
}

function app_auto_refresh_signature(array $item): array
{
    $seasons = [];
    foreach (($item['seasons'] ?? []) as $season) {
        if (!is_array($season)) { continue; }
        $number = (int)($season['season_number'] ?? -1);
        if ($number < 0) { continue; }
        $seasons[$number] = [
            'episode_count' => (int)($season['episode_count'] ?? 0),
            'air_date' => (string)($season['air_date'] ?? ''),
            'name' => (string)($season['name'] ?? ''),
        ];
    }
    ksort($seasons);
    return [
        'total_seasons' => (int)($item['total_seasons'] ?? 0),
        'total_episodes' => (int)($item['total_episodes'] ?? 0),
        'status_tmdb' => (string)($item['status_tmdb'] ?? ''),
        'last_episode_to_air' => is_array($item['last_episode_to_air'] ?? null) ? app_auto_refresh_episode_marker($item['last_episode_to_air']) : '',
        'next_episode_to_air' => is_array($item['next_episode_to_air'] ?? null) ? app_auto_refresh_episode_marker($item['next_episode_to_air']) : '',
        'seasons' => $seasons,
    ];
}

function app_auto_refresh_season_numbers_to_refresh(array $before, array $after): array
{
    $numbers = [];
    $beforeSeasons = [];
    foreach (($before['seasons'] ?? []) as $season) {
        if (is_array($season)) { $beforeSeasons[(int)($season['season_number'] ?? -1)] = (int)($season['episode_count'] ?? 0); }
    }
    foreach (($after['seasons'] ?? []) as $season) {
        if (!is_array($season)) { continue; }
        $seasonNumber = (int)($season['season_number'] ?? 0);
        if ($seasonNumber <= 0) { continue; }
        $episodeCount = (int)($season['episode_count'] ?? 0);
        if (!array_key_exists($seasonNumber, $beforeSeasons) || $beforeSeasons[$seasonNumber] !== $episodeCount) {
            $numbers[$seasonNumber] = true;
        }
    }
    foreach (['last_episode_to_air', 'next_episode_to_air'] as $key) {
        if (is_array($after[$key] ?? null)) {
            $seasonNumber = (int)($after[$key]['season_number'] ?? $after[$key]['season'] ?? 0);
            if ($seasonNumber > 0) { $numbers[$seasonNumber] = true; }
        }
    }
    $result = array_keys($numbers);
    sort($result, SORT_NUMERIC);
    return array_slice($result, 0, 6);
}

function app_auto_refresh_tv_item(array $item, bool $force = false): array
{
    if (!app_auto_refresh_is_trackable_tv($item)) {
        return ['item' => $item, 'checked' => false, 'changed' => false, 'new_available' => 0, 'reopened' => false, 'season_refreshes' => 0, 'error' => 'not-trackable-tv'];
    }

    $oldUpdatedAt = (string)($item['updated_at'] ?? '');
    $beforeSignature = app_auto_refresh_signature($item);
    $beforeAvailableCount = app_auto_refresh_available_episode_count($item);
    $beforeNextUp = app_next_up_summary($item);
    $tmdbId = (int)$item['tmdb_id'];

    $details = app_tmdb_details('tv', $tmdbId, true);
    $updated = app_apply_tmdb_details_to_item($item, $details, false);
    $seasonRefreshes = 0;
    foreach (app_auto_refresh_season_numbers_to_refresh($item, $updated) as $seasonNumber) {
        try { app_tmdb_season_details($tmdbId, (int)$seasonNumber, true); $seasonRefreshes++; }
        catch (Throwable $seasonEx) {}
    }

    $afterSignature = app_auto_refresh_signature($updated);
    $afterAvailableCount = app_auto_refresh_available_episode_count($updated);
    $afterNextUp = app_next_up_summary($updated);
    $newAvailable = max(0, $afterAvailableCount - $beforeAvailableCount);
    $metadataChanged = $beforeSignature !== $afterSignature;
    $reopened = false;

    if (in_array((string)($item['status'] ?? ''), ['completed', 'watched'], true) && in_array((string)($afterNextUp['state'] ?? ''), ['next', 'start'], true)) {
        $updated['status'] = 'watching';
        $updated['auto_reopened_from_completed_at'] = date(DATE_ATOM);
        $reopened = true;
    }

    $updated['auto_new_episode_checked_at'] = date(DATE_ATOM);
    $updated['auto_new_episode_last_result'] = [
        'checked_at' => $updated['auto_new_episode_checked_at'],
        'metadata_changed' => $metadataChanged,
        'new_available_count' => $newAvailable,
        'before_state' => (string)($beforeNextUp['state'] ?? ''),
        'after_state' => (string)($afterNextUp['state'] ?? ''),
        'after_label' => (string)($afterNextUp['label'] ?? ''),
        'season_refreshes' => $seasonRefreshes,
        'reopened' => $reopened,
    ];
    if ($newAvailable > 0 || $reopened) {
        $updated['auto_new_episode_found_at'] = date(DATE_ATOM);
        $updated['auto_new_episode_count_since_last_check'] = $newAvailable;
    }

    if (!$metadataChanged && !$reopened && $newAvailable <= 0 && $oldUpdatedAt !== '') {
        $updated['updated_at'] = $oldUpdatedAt;
    }

    return [
        'item' => $updated,
        'checked' => true,
        'changed' => $metadataChanged || $reopened || $newAvailable > 0,
        'new_available' => $newAvailable,
        'reopened' => $reopened,
        'season_refreshes' => $seasonRefreshes,
        'error' => '',
    ];
}

function app_auto_refresh_user_library(string $username, int $limit = APP_AUTO_REFRESH_MAX_ITEMS, int $ttlSeconds = APP_AUTO_REFRESH_TTL_SECONDS): array
{
    $summary = ['configured' => app_tmdb_configured(), 'checked' => 0, 'changed' => 0, 'new_available' => 0, 'reopened' => 0, 'season_refreshes' => 0, 'errors' => [], 'library' => null];
    $library = app_library($username);
    if (!$summary['configured']) { $summary['library'] = $library; return $summary; }

    $saved = false;
    foreach (($library['items'] ?? []) as $index => $item) {
        if ($summary['checked'] >= $limit) { break; }
        if (!is_array($item) || !app_auto_refresh_is_stale($item, $ttlSeconds)) { continue; }
        try {
            $result = app_auto_refresh_tv_item($item);
            if (!empty($result['checked'])) {
                $summary['checked']++;
                $summary['new_available'] += (int)($result['new_available'] ?? 0);
                $summary['season_refreshes'] += (int)($result['season_refreshes'] ?? 0);
                if (!empty($result['changed'])) { $summary['changed']++; }
                if (!empty($result['reopened'])) { $summary['reopened']++; }
                $library['items'][$index] = $result['item'];
                $saved = true;
            }
        } catch (Throwable $ex) {
            $summary['checked']++;
            $summary['errors'][] = (string)($item['title'] ?? 'TV item') . ': ' . $ex->getMessage();
            $library['items'][$index]['auto_new_episode_checked_at'] = date(DATE_ATOM);
            $library['items'][$index]['auto_new_episode_error'] = $ex->getMessage();
            $saved = true;
        }
    }

    if ($saved) {
        app_save_library($username, $library);
        app_log_activity('system', 'auto-new-episode-refresh', $username, [
            'checked' => $summary['checked'],
            'changed' => $summary['changed'],
            'new_available' => $summary['new_available'],
            'reopened' => $summary['reopened'],
            'season_refreshes' => $summary['season_refreshes'],
            'errors' => count($summary['errors']),
        ]);
    }
    $summary['library'] = $library;
    return $summary;
}

function app_auto_refresh_notice(array $summary): string
{
    if (empty($summary['configured'])) { return ''; }
    $checked = (int)($summary['checked'] ?? 0);
    if ($checked <= 0) { return ''; }
    $parts = ['Auto-checked ' . $checked . ' tracked TV show(s) for new TMDB episodes/seasons.'];
    if ((int)($summary['new_available'] ?? 0) > 0) { $parts[] = (int)$summary['new_available'] . ' newly available episode(s) detected.'; }
    if ((int)($summary['reopened'] ?? 0) > 0) { $parts[] = (int)$summary['reopened'] . ' completed show(s) moved back to Watching.'; }
    if ((int)($summary['season_refreshes'] ?? 0) > 0) { $parts[] = 'Season cache refreshes: ' . (int)$summary['season_refreshes'] . '.'; }
    if (!empty($summary['errors'])) { $parts[] = count((array)$summary['errors']) . ' refresh error(s).'; }
    return implode(' ', $parts);
}
