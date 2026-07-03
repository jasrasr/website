<?php
/**
 * File: includes/next-up.php
 * Project: TV Binge Board
 * Description: Helper functions that calculate the next available unwatched TV episode and caught-up state from watched episode records and TMDB metadata.
 * Author: Jason Lamb / ChatGPT
 * Created: 2026-07-03
 * Modified: 2026-07-03
 * Revision: 1.0.0
 */
declare(strict_types=1);

require_once __DIR__ . '/functions.php';

function app_next_up_episode_code(int $season, int $episode): string
{
    return 'S' . $season . 'E' . $episode;
}

function app_next_up_last_aired_limit(array $item): ?array
{
    $lastAired = $item['last_episode_to_air'] ?? null;
    if (is_array($lastAired)) {
        $season = (int)($lastAired['season_number'] ?? $lastAired['season'] ?? 0);
        $episode = (int)($lastAired['episode_number'] ?? $lastAired['episode'] ?? 0);
        if ($season > 0 && $episode > 0) {
            return ['season' => $season, 'episode' => $episode, 'air_date' => (string)($lastAired['air_date'] ?? '')];
        }
    }
    return null;
}

function app_next_up_next_announced(array $item): ?array
{
    $next = $item['next_episode_to_air'] ?? null;
    if (is_array($next)) {
        $season = (int)($next['season_number'] ?? $next['season'] ?? 0);
        $episode = (int)($next['episode_number'] ?? $next['episode'] ?? 0);
        if ($season > 0 && $episode > 0) {
            return [
                'season' => $season,
                'episode' => $episode,
                'air_date' => (string)($next['air_date'] ?? ''),
                'name' => (string)($next['name'] ?? ''),
            ];
        }
    }
    return null;
}

function app_next_up_available_episodes(array $item): array
{
    if (($item['type'] ?? '') !== 'tv') { return []; }
    $seasonOptions = app_tv_season_options($item);
    $limit = app_next_up_last_aired_limit($item);
    $episodes = [];
    foreach ($seasonOptions as $season => $episodeCount) {
        $season = (int)$season;
        $episodeCount = max(0, (int)$episodeCount);
        if ($season <= 0 || $episodeCount <= 0) { continue; }
        $maxEpisode = $episodeCount;
        if ($limit !== null) {
            if ($season > (int)$limit['season']) { continue; }
            if ($season === (int)$limit['season']) { $maxEpisode = min($maxEpisode, (int)$limit['episode']); }
        }
        for ($episode = 1; $episode <= $maxEpisode; $episode++) {
            $episodes[] = ['season' => $season, 'episode' => $episode];
        }
    }
    usort($episodes, static fn(array $a, array $b): int => ((int)$a['season'] <=> (int)$b['season']) ?: ((int)$a['episode'] <=> (int)$b['episode']));
    return $episodes;
}

function app_next_up_episode_title(array $item, int $season, int $episode): string
{
    $tmdbId = (int)($item['tmdb_id'] ?? 0);
    if ($tmdbId <= 0 || !function_exists('app_tmdb_season_details')) { return ''; }
    try {
        $seasonDetails = app_tmdb_season_details($tmdbId, $season);
        foreach (($seasonDetails['episodes'] ?? []) as $episodeData) {
            if (!is_array($episodeData)) { continue; }
            if ((int)($episodeData['episode_number'] ?? 0) === $episode) {
                return trim((string)($episodeData['name'] ?? ''));
            }
        }
    } catch (Throwable $ex) {
        return '';
    }
    return '';
}

function app_next_up_summary(array $item, bool $includeTitle = false): array
{
    if (($item['type'] ?? '') !== 'tv') {
        return ['state' => 'not-tv', 'label' => '', 'detail' => '', 'season' => null, 'episode' => null, 'css' => ''];
    }

    $watchedKeys = app_watched_episode_keys($item);
    $available = app_next_up_available_episodes($item);
    $watchedAvailable = 0;
    $next = null;
    foreach ($available as $episodeRef) {
        $key = (int)$episodeRef['season'] . '-' . (int)$episodeRef['episode'];
        if (!empty($watchedKeys[$key])) {
            $watchedAvailable++;
            continue;
        }
        $next = $episodeRef;
        break;
    }

    if ($next !== null) {
        $season = (int)$next['season'];
        $episode = (int)$next['episode'];
        $code = app_next_up_episode_code($season, $episode);
        $title = $includeTitle ? app_next_up_episode_title($item, $season, $episode) : '';
        $isStart = $watchedAvailable === 0 && $season === 1 && $episode === 1;
        return [
            'state' => $isStart ? 'start' : 'next',
            'label' => ($isStart ? 'Start: ' : 'Next up: ') . $code,
            'detail' => $title !== '' ? $title : 'Watch this next.',
            'season' => $season,
            'episode' => $episode,
            'css' => $isStart ? 'next-up-start' : 'next-up-next',
        ];
    }

    if ($available !== []) {
        $announced = app_next_up_next_announced($item);
        $detail = 'No currently available unwatched episodes in the saved metadata.';
        if ($announced !== null) {
            $detail = 'Next announced: ' . app_next_up_episode_code((int)$announced['season'], (int)$announced['episode']);
            if ((string)$announced['air_date'] !== '') { $detail .= ' on ' . (string)$announced['air_date']; }
            if ((string)$announced['name'] !== '') { $detail .= ' · ' . (string)$announced['name']; }
        }
        return [
            'state' => 'caught-up',
            'label' => 'Caught up',
            'detail' => $detail,
            'season' => null,
            'episode' => null,
            'css' => 'next-up-caught-up',
        ];
    }

    $last = $item['last_episode'] ?? null;
    if (is_array($last)) {
        $season = max(1, (int)($last['season'] ?? 1));
        $episode = max(1, (int)($last['episode'] ?? 0)) + 1;
        return [
            'state' => 'likely-next',
            'label' => 'Likely next: ' . app_next_up_episode_code($season, $episode),
            'detail' => 'Episode metadata is incomplete; refresh or link to TMDB to confirm.',
            'season' => $season,
            'episode' => $episode,
            'css' => 'next-up-likely',
        ];
    }

    return [
        'state' => 'unknown',
        'label' => 'Episode data needed',
        'detail' => 'Link to TMDB, refresh metadata, or set total seasons/episodes to calculate the next episode.',
        'season' => null,
        'episode' => null,
        'css' => 'next-up-unknown',
    ];
}
