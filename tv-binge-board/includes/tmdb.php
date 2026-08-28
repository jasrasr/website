<?php
/**
 * File: includes/tmdb.php
 * Project: TV Binge Board
 * Description: TMDB API wrapper with server-side authentication, caching, linking, normalized search, details, and season metadata helpers.
 * Author: Jason Lamb / ChatGPT
 * Created: 2026-07-02
 * Modified: 2026-07-02
 * Revision: 1.4.2
 */
declare(strict_types=1);

require_once __DIR__ . '/functions.php';

function app_tmdb_key(): string
{
    if (defined('TMDB_API_KEY_LOCAL') && TMDB_API_KEY_LOCAL !== '') { return TMDB_API_KEY_LOCAL; }
    if (defined('TMDB_API_KEY') && TMDB_API_KEY !== '') { return TMDB_API_KEY; }
    $env = getenv('TMDB_API_KEY');
    return is_string($env) ? $env : '';
}

function app_tmdb_token(): string
{
    if (defined('TMDB_API_READ_ACCESS_TOKEN_LOCAL') && TMDB_API_READ_ACCESS_TOKEN_LOCAL !== '') { return TMDB_API_READ_ACCESS_TOKEN_LOCAL; }
    if (defined('TMDB_API_READ_ACCESS_TOKEN') && TMDB_API_READ_ACCESS_TOKEN !== '') { return TMDB_API_READ_ACCESS_TOKEN; }
    $env = getenv('TMDB_API_READ_ACCESS_TOKEN');
    return is_string($env) ? $env : '';
}

function app_tmdb_configured(): bool
{
    return app_tmdb_token() !== '' || app_tmdb_key() !== '';
}

function app_tmdb_external_url(string $type, int $tmdbId): string
{
    if ($tmdbId <= 0) { return ''; }
    return 'https://www.themoviedb.org/' . ($type === 'tv' ? 'tv' : 'movie') . '/' . $tmdbId;
}

function app_tmdb_image_url(?string $imagePath, string $size = 'w342'): string
{
    $imagePath = trim((string)$imagePath);
    if ($imagePath === '') { return ''; }
    $base = match ($size) {
        'w185' => TMDB_IMAGE_BASE_SMALL,
        'w300' => TMDB_IMAGE_BASE_STILL,
        'w500' => TMDB_IMAGE_BASE_MEDIUM,
        'original' => TMDB_IMAGE_BASE_ORIGINAL,
        default => 'https://image.tmdb.org/t/p/' . preg_replace('/[^a-z0-9_]+/i', '', $size),
    };
    return $base . $imagePath;
}

function app_public_cache_subdir(string $subdir): string
{
    $subdir = trim($subdir, '/\\');
    $subdir = preg_replace('/[^a-z0-9_\/-]+/i', '-', $subdir) ?? 'misc';
    $dir = APP_PUBLIC_CACHE_DIR . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $subdir);
    app_ensure_dir($dir);
    return $dir;
}

function app_public_cache_rel_path(string $subdir, string $filename): string
{
    $subdir = trim($subdir, '/\\');
    $filename = preg_replace('/[^a-z0-9._-]+/i', '-', $filename) ?? 'image.jpg';
    return trim(APP_PUBLIC_CACHE_URL, '/') . '/' . $subdir . '/' . $filename;
}

function app_public_cache_abs_path(string $relativePath): string
{
    $relativePath = ltrim(str_replace('\\', '/', $relativePath), '/');
    $prefix = trim(APP_PUBLIC_CACHE_URL, '/') . '/';
    if (!str_starts_with($relativePath, $prefix)) {
        throw new InvalidArgumentException('Invalid public cache path.');
    }
    $inside = substr($relativePath, strlen($prefix));
    $inside = str_replace(['../', '..\\'], '', $inside);
    return APP_PUBLIC_CACHE_DIR . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $inside);
}

function app_public_cached_file_exists(?string $relativePath): bool
{
    $relativePath = trim((string)$relativePath);
    if ($relativePath === '') { return false; }
    try { return is_file(app_public_cache_abs_path($relativePath)); }
    catch (Throwable $ex) { return false; }
}

function app_tmdb_image_extension(string $imagePath, string $contentType = ''): string
{
    $ext = strtolower(pathinfo(parse_url($imagePath, PHP_URL_PATH) ?: '', PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'webp'];
    if (in_array($ext, $allowed, true)) { return $ext === 'jpeg' ? 'jpg' : $ext; }
    if (str_contains(strtolower($contentType), 'png')) { return 'png'; }
    if (str_contains(strtolower($contentType), 'webp')) { return 'webp'; }
    return 'jpg';
}

function app_tmdb_cached_image_filename(string $kind, string $type, int $tmdbId, string $imagePath, string $size, string $suffix = ''): string
{
    $hash = substr(sha1($imagePath), 0, 12);
    $safeKind = preg_replace('/[^a-z0-9-]+/i', '-', strtolower($kind)) ?: 'image';
    $safeType = $type === 'tv' ? 'tv' : 'movie';
    $safeSuffix = trim(preg_replace('/[^a-z0-9-]+/i', '-', strtolower($suffix)) ?: '', '-');
    $name = 'tmdb-' . $safeType . '-' . $tmdbId . '-' . $safeKind;
    if ($safeSuffix !== '') { $name .= '-' . $safeSuffix; }
    $name .= '-' . preg_replace('/[^a-z0-9]+/i', '', $size) . '-' . $hash;
    return $name . '.' . app_tmdb_image_extension($imagePath);
}

function app_tmdb_download_image(string $imagePath, string $size, string $subdir, string $filename, bool $force = false): array
{
    $imagePath = trim($imagePath);
    if ($imagePath === '') { return ['ok' => false, 'reason' => 'missing-image-path']; }
    $relativePath = app_public_cache_rel_path($subdir, $filename);
    $absolutePath = APP_ROOT . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
    app_ensure_dir(dirname($absolutePath));
    if (!$force && is_file($absolutePath) && filesize($absolutePath) > 0) {
        return ['ok' => true, 'downloaded' => false, 'path' => $relativePath, 'source_url' => app_tmdb_image_url($imagePath, $size)];
    }

    $sourceUrl = app_tmdb_image_url($imagePath, $size);
    $context = stream_context_create([
        'http' => [
            'timeout' => 20,
            'ignore_errors' => true,
            'header' => "Accept: image/*
User-Agent: TVBingeBoard/" . APP_VERSION . "
",
        ],
    ]);
    $bytes = @file_get_contents($sourceUrl, false, $context);
    if ($bytes === false || strlen($bytes) < 128) {
        return ['ok' => false, 'reason' => 'download-failed', 'source_url' => $sourceUrl];
    }
    if (@getimagesizefromstring($bytes) === false) {
        return ['ok' => false, 'reason' => 'invalid-image', 'source_url' => $sourceUrl];
    }
    $tmp = $absolutePath . '.tmp.' . bin2hex(random_bytes(4));
    if (file_put_contents($tmp, $bytes, LOCK_EX) === false) {
        return ['ok' => false, 'reason' => 'write-failed', 'source_url' => $sourceUrl];
    }
    chmod($tmp, 0644);
    if (!rename($tmp, $absolutePath)) {
        @unlink($tmp);
        return ['ok' => false, 'reason' => 'replace-failed', 'source_url' => $sourceUrl];
    }
    return ['ok' => true, 'downloaded' => true, 'path' => $relativePath, 'source_url' => $sourceUrl];
}

function app_tmdb_cache_item_poster(array $item, bool $force = false): array
{
    $tmdbId = (int)($item['tmdb_id'] ?? 0);
    $type = (string)($item['type'] ?? 'movie');
    $posterPath = trim((string)($item['poster_path'] ?? ''));
    if ($tmdbId <= 0 || $posterPath === '' || !in_array($type, ['movie', 'tv'], true)) {
        $item['poster_last_checked_at'] = date(DATE_ATOM);
        return $item;
    }
    if (!$force && ($item['poster_cached_source_path'] ?? '') === $posterPath && app_public_cached_file_exists($item['local_poster_path'] ?? '')) {
        $item['poster_last_checked_at'] = date(DATE_ATOM);
        return $item;
    }
    $filename = app_tmdb_cached_image_filename('poster', $type, $tmdbId, $posterPath, 'w500');
    $download = app_tmdb_download_image($posterPath, 'w500', 'posters', $filename, $force);
    $item['poster_source_url'] = app_tmdb_image_url($posterPath, 'w500');
    $item['poster_last_checked_at'] = date(DATE_ATOM);
    if (!empty($download['ok']) && !empty($download['path'])) {
        $item['local_poster_path'] = (string)$download['path'];
        $item['poster_cached_source_path'] = $posterPath;
        $item['poster_cached_at'] = date(DATE_ATOM);
    } else {
        $item['poster_cache_error'] = (string)($download['reason'] ?? 'unknown');
    }
    return $item;
}

function app_tmdb_cache_season_art(int $seriesId, int $seasonNumber, array $season, bool $force = false, bool $includeEpisodes = true): array
{
    $stats = ['season_posters' => 0, 'episode_stills' => 0, 'failed' => 0];
    $posterPath = trim((string)($season['poster_path'] ?? ''));
    if ($posterPath !== '') {
        $filename = app_tmdb_cached_image_filename('season-poster', 'tv', $seriesId, $posterPath, 'w500', 'season-' . $seasonNumber);
        $download = app_tmdb_download_image($posterPath, 'w500', 'posters', $filename, $force);
        $season['poster_source_url'] = app_tmdb_image_url($posterPath, 'w500');
        $season['poster_last_checked_at'] = date(DATE_ATOM);
        if (!empty($download['ok']) && !empty($download['path'])) {
            if (empty($season['local_poster_path']) || $force || ($season['poster_cached_source_path'] ?? '') !== $posterPath) { $stats['season_posters']++; }
            $season['local_poster_path'] = (string)$download['path'];
            $season['poster_cached_source_path'] = $posterPath;
            $season['poster_cached_at'] = date(DATE_ATOM);
        } else { $stats['failed']++; }
    }

    if ($includeEpisodes && isset($season['episodes']) && is_array($season['episodes'])) {
        foreach ($season['episodes'] as $index => $episode) {
            if (!is_array($episode)) { continue; }
            $episodeNumber = (int)($episode['episode_number'] ?? 0);
            $stillPath = trim((string)($episode['still_path'] ?? ''));
            if ($episodeNumber <= 0 || $stillPath === '') { continue; }
            if (!$force && ($episode['still_cached_source_path'] ?? '') === $stillPath && app_public_cached_file_exists($episode['local_still_path'] ?? '')) {
                $season['episodes'][$index]['still_last_checked_at'] = date(DATE_ATOM);
                continue;
            }
            $filename = app_tmdb_cached_image_filename('still', 'tv', $seriesId, $stillPath, 'w300', 's' . str_pad((string)$seasonNumber, 2, '0', STR_PAD_LEFT) . 'e' . str_pad((string)$episodeNumber, 2, '0', STR_PAD_LEFT));
            $download = app_tmdb_download_image($stillPath, 'w300', 'stills', $filename, $force);
            $season['episodes'][$index]['still_source_url'] = app_tmdb_image_url($stillPath, 'w300');
            $season['episodes'][$index]['still_last_checked_at'] = date(DATE_ATOM);
            if (!empty($download['ok']) && !empty($download['path'])) {
                $season['episodes'][$index]['local_still_path'] = (string)$download['path'];
                $season['episodes'][$index]['still_cached_source_path'] = $stillPath;
                $season['episodes'][$index]['still_cached_at'] = date(DATE_ATOM);
                $stats['episode_stills']++;
            } else { $stats['failed']++; }
        }
    }
    $season['_artwork_stats'] = $stats;
    return $season;
}

function app_tmdb_save_season_cache(int $seriesId, int $seasonNumber, array $season): void
{
    app_tmdb_save_cache('tv-' . $seriesId . '-season-' . $seasonNumber, ['season' => $season], 'TMDB cached season details and local artwork references.');
}

function app_tmdb_cache_item_artwork(array $item, bool $force = false, bool $includeSeasons = false, bool $includeEpisodes = false): array
{
    $stats = ['posters' => 0, 'season_posters' => 0, 'episode_stills' => 0, 'failed' => 0];
    $before = (string)($item['local_poster_path'] ?? '');
    $item = app_tmdb_cache_item_poster($item, $force);
    if ((string)($item['local_poster_path'] ?? '') !== '' && ($force || (string)($item['local_poster_path'] ?? '') !== $before)) { $stats['posters']++; }

    if ($includeSeasons && ($item['type'] ?? '') === 'tv' && (int)($item['tmdb_id'] ?? 0) > 0) {
        $seriesId = (int)$item['tmdb_id'];
        foreach (($item['seasons'] ?? []) as $seasonSummary) {
            if (!is_array($seasonSummary)) { continue; }
            $seasonNumber = (int)($seasonSummary['season_number'] ?? -1);
            if ($seasonNumber <= 0) { continue; }
            try {
                $season = app_tmdb_season_details($seriesId, $seasonNumber, false);
                $season = app_tmdb_cache_season_art($seriesId, $seasonNumber, $season, $force, $includeEpisodes);
                app_tmdb_save_season_cache($seriesId, $seasonNumber, $season);
                $seasonStats = $season['_artwork_stats'] ?? [];
                $stats['season_posters'] += (int)($seasonStats['season_posters'] ?? 0);
                $stats['episode_stills'] += (int)($seasonStats['episode_stills'] ?? 0);
                $stats['failed'] += (int)($seasonStats['failed'] ?? 0);
            } catch (Throwable $ex) { $stats['failed']++; }
        }
    }
    $item['artwork_last_checked_at'] = date(DATE_ATOM);
    $item['_artwork_stats'] = $stats;
    return $item;
}

function app_tmdb_cache_path(string $name): string
{
    app_ensure_dir(APP_CACHE_DIR . DIRECTORY_SEPARATOR . 'tmdb');
    return APP_CACHE_DIR . DIRECTORY_SEPARATOR . 'tmdb' . DIRECTORY_SEPARATOR . preg_replace('/[^a-z0-9._-]+/i', '-', $name) . '.json';
}

function app_tmdb_cached(string $name, int $ttlSeconds): ?array
{
    $cached = app_load_json(app_tmdb_cache_path($name), []);
    if (!empty($cached['cached_at']) && isset($cached['payload'])) {
        $cachedAt = strtotime((string)$cached['cached_at']) ?: 0;
        if ($cachedAt > 0 && (time() - $cachedAt) < $ttlSeconds && is_array($cached['payload'])) {
            return $cached['payload'];
        }
    }
    return null;
}

function app_tmdb_save_cache(string $name, array $payload, string $description): void
{
    app_save_json(app_tmdb_cache_path($name), [
        '_meta' => app_json_meta($description),
        'cached_at' => date(DATE_ATOM),
        'payload' => $payload,
    ]);
}

function app_tmdb_request(string $path, array $params = []): array
{
    if (!app_tmdb_configured()) {
        throw new RuntimeException('TMDB API credential is not configured. Use manual add or create includes/config.local.php.');
    }

    $params = array_merge(['language' => 'en-US'], $params);
    $headers = [
        'Accept: application/json',
        'User-Agent: TVBingeBoard/' . APP_VERSION,
    ];

    $token = app_tmdb_token();
    if ($token !== '') {
        $headers[] = 'Authorization: Bearer ' . $token;
    } else {
        $params['api_key'] = app_tmdb_key();
    }

    $url = 'https://api.themoviedb.org/3/' . ltrim($path, '/') . '?' . http_build_query($params);
    $context = stream_context_create([
        'http' => [
            'timeout' => 15,
            'ignore_errors' => true,
            'header' => implode("\r\n", $headers) . "\r\n",
        ],
    ]);
    $body = @file_get_contents($url, false, $context);
    $statusLine = $http_response_header[0] ?? '';
    if ($body === false) { throw new RuntimeException('Unable to contact TMDB.'); }
    $decoded = json_decode($body, true);
    if (!is_array($decoded)) { throw new RuntimeException('Invalid TMDB response.'); }
    if (preg_match('/\s(\d{3})\s/', $statusLine, $m) && (int)$m[1] >= 400) {
        $message = (string)($decoded['status_message'] ?? 'TMDB request failed.');
        throw new RuntimeException($message);
    }
    return $decoded;
}

function app_tmdb_normalize_search_item(array $item): ?array
{
    $type = (string)($item['media_type'] ?? '');
    if (!in_array($type, ['movie', 'tv'], true)) { return null; }
    $tmdbId = (int)($item['id'] ?? 0);
    if ($tmdbId <= 0) { return null; }
    $title = $type === 'movie' ? (string)($item['title'] ?? '') : (string)($item['name'] ?? '');
    $date = $type === 'movie' ? (string)($item['release_date'] ?? '') : (string)($item['first_air_date'] ?? '');
    return [
        'tmdb_id' => $tmdbId,
        'type' => $type,
        'title' => $title,
        'year' => $date !== '' ? substr($date, 0, 4) : '',
        'release_date' => $date,
        'poster_path' => (string)($item['poster_path'] ?? ''),
        'backdrop_path' => (string)($item['backdrop_path'] ?? ''),
        'overview' => (string)($item['overview'] ?? ''),
        'vote_average' => isset($item['vote_average']) ? round((float)$item['vote_average'], 1) : null,
        'vote_count' => isset($item['vote_count']) ? (int)$item['vote_count'] : null,
        'tmdb_url' => app_tmdb_external_url($type, $tmdbId),
    ];
}

function app_tmdb_search(string $query): array
{
    $query = trim($query);
    if ($query === '') { return []; }
    if (!app_tmdb_configured()) {
        throw new RuntimeException('TMDB API credential is not configured. Use manual add or create includes/config.local.php.');
    }

    $cacheName = 'search-' . sha1(strtolower($query));
    $cached = app_tmdb_cached($cacheName, 86400);
    if ($cached !== null) { return $cached['results'] ?? []; }

    $decoded = app_tmdb_request('search/multi', ['query' => $query, 'include_adult' => 'false', 'page' => '1']);
    $results = [];
    foreach (($decoded['results'] ?? []) as $item) {
        if (!is_array($item)) { continue; }
        $normalized = app_tmdb_normalize_search_item($item);
        if ($normalized !== null && $normalized['title'] !== '') { $results[] = $normalized; }
    }
    app_tmdb_save_cache($cacheName, ['query' => $query, 'results' => $results], 'TMDB cached search result.');
    return $results;
}

function app_tmdb_normalize_details(string $type, array $decoded): array
{
    $tmdbId = (int)($decoded['id'] ?? 0);
    $title = $type === 'movie' ? (string)($decoded['title'] ?? '') : (string)($decoded['name'] ?? '');
    $date = $type === 'movie' ? (string)($decoded['release_date'] ?? '') : (string)($decoded['first_air_date'] ?? '');
    $genres = [];
    foreach (($decoded['genres'] ?? []) as $genre) {
        if (is_array($genre) && !empty($genre['name'])) { $genres[] = (string)$genre['name']; }
    }
    $details = [
        'tmdb_id' => $tmdbId,
        'source' => 'tmdb',
        'tmdb_url' => app_tmdb_external_url($type, $tmdbId),
        'type' => $type,
        'title' => $title,
        'year' => $date !== '' ? substr($date, 0, 4) : '',
        'release_date' => $date,
        'poster_path' => (string)($decoded['poster_path'] ?? ''),
        'backdrop_path' => (string)($decoded['backdrop_path'] ?? ''),
        'overview' => (string)($decoded['overview'] ?? ''),
        'genres' => $genres,
        'homepage' => (string)($decoded['homepage'] ?? ''),
        'original_language' => (string)($decoded['original_language'] ?? ''),
        'vote_average' => isset($decoded['vote_average']) ? round((float)$decoded['vote_average'], 1) : null,
        'vote_count' => isset($decoded['vote_count']) ? (int)$decoded['vote_count'] : null,
        'metadata_refreshed_at' => date(DATE_ATOM),
    ];

    if ($type === 'movie') {
        $details['runtime_minutes'] = isset($decoded['runtime']) ? (int)$decoded['runtime'] : null;
        $details['imdb_id'] = (string)($decoded['imdb_id'] ?? '');
    } else {
        $seasons = [];
        foreach (($decoded['seasons'] ?? []) as $season) {
            if (!is_array($season)) { continue; }
            $seasonNumber = (int)($season['season_number'] ?? 0);
            if ($seasonNumber < 0) { continue; }
            $seasons[] = [
                'season_number' => $seasonNumber,
                'name' => (string)($season['name'] ?? ('Season ' . $seasonNumber)),
                'episode_count' => (int)($season['episode_count'] ?? 0),
                'air_date' => (string)($season['air_date'] ?? ''),
                'poster_path' => (string)($season['poster_path'] ?? ''),
                'overview' => (string)($season['overview'] ?? ''),
            ];
        }
        $details['total_seasons'] = (int)($decoded['number_of_seasons'] ?? 0);
        $details['total_episodes'] = (int)($decoded['number_of_episodes'] ?? 0);
        $details['seasons'] = $seasons;
        $details['status_tmdb'] = (string)($decoded['status'] ?? '');
        $details['next_episode_to_air'] = is_array($decoded['next_episode_to_air'] ?? null) ? $decoded['next_episode_to_air'] : null;
        $details['last_episode_to_air'] = is_array($decoded['last_episode_to_air'] ?? null) ? $decoded['last_episode_to_air'] : null;
    }

    return $details;
}

function app_tmdb_details(string $type, int $tmdbId, bool $force = false): array
{
    if (!in_array($type, ['movie', 'tv'], true) || $tmdbId <= 0) {
        throw new InvalidArgumentException('Invalid TMDB detail request.');
    }
    $cacheName = $type . '-' . $tmdbId;
    if (!$force) {
        $cached = app_tmdb_cached($cacheName, 604800);
        if ($cached !== null) { return $cached['details'] ?? $cached; }
    }
    $decoded = app_tmdb_request($type . '/' . $tmdbId, [
        'append_to_response' => $type === 'movie' ? 'external_ids' : 'external_ids',
    ]);
    $details = app_tmdb_normalize_details($type, $decoded);
    app_tmdb_save_cache($cacheName, ['details' => $details], 'TMDB cached details.');
    return $details;
}

function app_tmdb_season_details(int $seriesId, int $seasonNumber, bool $force = false): array
{
    if ($seriesId <= 0 || $seasonNumber < 0) {
        throw new InvalidArgumentException('Invalid TMDB season request.');
    }
    $cacheName = 'tv-' . $seriesId . '-season-' . $seasonNumber;
    if (!$force) {
        $cached = app_tmdb_cached($cacheName, 604800);
        if ($cached !== null) { return $cached['season'] ?? $cached; }
    }
    $decoded = app_tmdb_request('tv/' . $seriesId . '/season/' . $seasonNumber);
    $episodes = [];
    foreach (($decoded['episodes'] ?? []) as $episode) {
        if (!is_array($episode)) { continue; }
        $episodeNumber = (int)($episode['episode_number'] ?? 0);
        if ($episodeNumber <= 0) { continue; }
        $episodes[] = [
            'episode_number' => $episodeNumber,
            'season_number' => (int)($episode['season_number'] ?? $seasonNumber),
            'name' => (string)($episode['name'] ?? ('Episode ' . $episodeNumber)),
            'overview' => (string)($episode['overview'] ?? ''),
            'air_date' => (string)($episode['air_date'] ?? ''),
            'still_path' => (string)($episode['still_path'] ?? ''),
            'runtime_minutes' => isset($episode['runtime']) ? (int)$episode['runtime'] : null,
            'vote_average' => isset($episode['vote_average']) ? round((float)$episode['vote_average'], 1) : null,
        ];
    }
    $season = [
        'tmdb_id' => (int)($decoded['id'] ?? 0),
        'series_id' => $seriesId,
        'season_number' => (int)($decoded['season_number'] ?? $seasonNumber),
        'name' => (string)($decoded['name'] ?? ('Season ' . $seasonNumber)),
        'overview' => (string)($decoded['overview'] ?? ''),
        'air_date' => (string)($decoded['air_date'] ?? ''),
        'poster_path' => (string)($decoded['poster_path'] ?? ''),
        'episodes' => $episodes,
        'metadata_refreshed_at' => date(DATE_ATOM),
    ];
    app_tmdb_save_cache($cacheName, ['season' => $season], 'TMDB cached season details.');
    return $season;
}

function app_apply_tmdb_details_to_item(array $item, array $details, bool $replaceTitle = true): array
{
    $protected = [
        'uid' => $item['uid'] ?? '',
        'status' => $item['status'] ?? 'watchlist',
        'rating' => $item['rating'] ?? null,
        'notes' => $item['notes'] ?? '',
        'episodes' => $item['episodes'] ?? [],
        'created_at' => $item['created_at'] ?? date(DATE_ATOM),
    ];
    $merged = array_merge($item, array_filter($details, static fn($v) => $v !== null && $v !== ''));
    if (!$replaceTitle && !empty($item['title'])) { $merged['title'] = $item['title']; }
    foreach ($protected as $key => $value) { $merged[$key] = $value; }
    $merged['source'] = 'tmdb';
    $merged['linked_at'] = $merged['linked_at'] ?? date(DATE_ATOM);
    $merged['updated_at'] = date(DATE_ATOM);
    return $merged;
}
