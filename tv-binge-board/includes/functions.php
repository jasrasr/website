<?php
/**
 * File: includes/functions.php
 * Project: TV Binge Board
 * Description: Shared UI, library, profile, import/export, stats, local artwork display, TMDB-link display, and connection helper functions.
 * Author: Jason Lamb / ChatGPT
 * Created: 2026-07-02
 * Modified: 2026-07-02
 * Revision: 1.4.3
 */
declare(strict_types=1);


require_once __DIR__ . '/auth.php';

function e(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function app_href(string $path): string
{
    return app_base_prefix() . ltrim($path, '/');
}

function app_flash(?string $message = null, string $type = 'info'): ?array
{
    if ($message !== null) {
        $_SESSION['flash'] = ['message' => $message, 'type' => $type];
        return null;
    }
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return is_array($flash) ? $flash : null;
}

function app_render_avatar(array $profile, string $username, int $size = 44): string
{
    $url = trim((string)($profile['avatar_url'] ?? ''));
    $name = trim((string)($profile['display_name'] ?? $username));
    if ($url !== '' && preg_match('#^https?://#i', $url)) {
        return '<img class="avatar" width="' . $size . '" height="' . $size . '" src="' . e($url) . '" alt="Avatar for ' . e($name) . '">';
    }
    $initials = strtoupper(substr($name !== '' ? $name : $username, 0, 1));
    return '<span class="avatar avatar-fallback" style="width:' . $size . 'px;height:' . $size . 'px">' . e($initials) . '</span>';
}

function app_page_header(string $title): void
{
    $user = app_current_user();
    $flash = app_flash();
    ?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#111827">
    <title><?= e($title) ?> - <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="<?= e(app_href('assets/css/app.css?v=' . rawurlencode(APP_VERSION))) ?>">
    <link rel="manifest" href="<?= e(app_href('manifest.webmanifest')) ?>">
    <link rel="apple-touch-icon" href="<?= e(app_href('assets/icons/icon-192.png')) ?>">
</head>
<body>
<header class="topbar">
    <a class="brand" href="<?= e(app_href('dashboard.php')) ?>"><?= e(APP_NAME) ?></a>
    <span class="version">rev <?= e(APP_VERSION) ?></span>
</header>
<main class="container">
<?php if ($flash): ?>
    <div class="alert <?= e($flash['type'] ?? 'info') ?>"><?= e($flash['message'] ?? '') ?></div>
<?php endif; ?>
<?php if ($user): $profile = app_profile((string)$user['username']); ?>
    <section class="user-strip">
        <span class="user-strip-name"><?= app_render_avatar($profile, (string)$user['username'], 32) ?> Signed in as <strong><?= e($user['display_name'] ?? $user['username']) ?></strong></span>
        <?php if (app_is_admin($user)): ?><span class="pill admin">Admin</span><?php endif; ?>
    </section>
<?php endif; ?>
<?php
}

function app_page_footer(): void
{
    $user = app_current_user();
    ?>
</main>
<?php if ($user): ?>
<nav class="bottom-nav" aria-label="Main navigation">
    <a href="<?= e(app_href('dashboard.php')) ?>">Home</a>
    <?php if (app_can_track($user)): ?>
        <a href="<?= e(app_href('search.php')) ?>">Search</a>
        <a href="<?= e(app_href('watchlist.php')) ?>">List</a>
        <a href="<?= e(app_href('connections.php')) ?>">People</a>
        <a href="<?= e(app_href('import.php')) ?>">Import</a>
    <?php else: ?>
        <a href="<?= e(app_href('admin/users.php')) ?>">Users</a>
        <a href="<?= e(app_href('admin/site-settings.php')) ?>">Site</a>
    <?php endif; ?>
    <a href="<?= e(app_href('settings.php')) ?>">Settings</a>
    <a href="<?= e(app_href('logout.php')) ?>">Logout</a>
</nav>
<?php endif; ?>
<footer class="footer">
    <p><?= e(APP_PUBLIC_SITE_NOTE) ?></p>
    <p>Metadata may use TMDB. This product uses the TMDB API but is not endorsed or certified by TMDB.</p>
    <p><a href="<?= e(app_href('changelog.php')) ?>">Changelog</a> · <a href="<?= e(app_href('README.md')) ?>">README</a> · <a href="<?= e(app_href('TASKS.md')) ?>">Task list</a></p>
</footer>
<script src="<?= e(app_href('assets/js/app.js?v=' . rawurlencode(APP_VERSION))) ?>"></script>
</body>
</html><?php
}

function app_library(string $username): array
{
    app_seed_user_files($username);
    $library = app_load_json(app_user_file($username, 'library.json'), [
        '_meta' => app_json_meta('Tracked shows and movies.'),
        'items' => [],
    ]);
    if (!isset($library['items']) || !is_array($library['items'])) {
        $library['items'] = [];
    }
    return $library;
}

function app_save_library(string $username, array $library): void
{
    $library['_meta']['updated_at'] = date(DATE_ATOM);
    $library['_meta']['version'] = APP_VERSION;
    app_save_json(app_user_file($username, 'library.json'), $library);
}

function app_make_media_uid(string $type, ?int $tmdbId, string $title): string
{
    $type = in_array($type, ['movie', 'tv'], true) ? $type : 'movie';
    if ($tmdbId !== null && $tmdbId > 0) {
        return $type . '-' . $tmdbId;
    }
    return 'manual-' . substr(sha1($type . '|' . strtolower(trim($title))), 0, 16);
}

function app_find_media_index(array $library, string $uid): ?int
{
    foreach ($library['items'] as $index => $item) {
        if (($item['uid'] ?? '') === $uid) {
            return $index;
        }
    }
    return null;
}

function app_statuses(): array
{
    return [
        'watchlist' => 'Want to Watch',
        'watching' => 'Watching',
        'watched' => 'Watched',
        'completed' => 'Completed',
        'dropped' => 'Dropped',
    ];
}

function app_excerpt(string $value, int $width = 180): string
{
    if (function_exists('mb_strimwidth')) {
        return mb_strimwidth($value, 0, $width, '…');
    }
    return strlen($value) > $width ? substr($value, 0, $width - 3) . '...' : $value;
}

function app_watched_episode_keys(array $item): array
{
    $keys = [];
    foreach (($item['episodes'] ?? []) as $entry) {
        $season = (int)($entry['season'] ?? -1);
        $episode = (int)($entry['episode'] ?? -1);
        if ($season >= 0 && $episode >= 0) {
            $keys[$season . '-' . $episode] = true;
        }
    }
    return $keys;
}


function app_total_episodes_from_item(array $item): int
{
    $total = (int)($item['total_episodes'] ?? 0);
    if ($total > 0) { return $total; }
    $sum = 0;
    foreach (($item['seasons'] ?? []) as $season) {
        if (!is_array($season)) { continue; }
        $number = (int)($season['season_number'] ?? -1);
        if ($number <= 0) { continue; }
        $sum += max(0, (int)($season['episode_count'] ?? 0));
    }
    return $sum;
}

function app_tmdb_public_url_for_item(array $item): string
{
    $tmdbId = (int)($item['tmdb_id'] ?? 0);
    if ($tmdbId <= 0) { return ''; }
    $type = (string)($item['type'] ?? 'movie');
    return 'https://www.themoviedb.org/' . ($type === 'tv' ? 'tv' : 'movie') . '/' . $tmdbId;
}

function app_media_genre_text(array $item): string
{
    $genres = $item['genres'] ?? [];
    if (!is_array($genres) || count($genres) === 0) { return ''; }
    return implode(', ', array_map('strval', array_slice($genres, 0, 4)));
}

function app_public_image_url(?string $relativePath): string
{
    $relativePath = trim((string)$relativePath);
    if ($relativePath === '') { return ''; }
    if (preg_match('#^https?://#i', $relativePath)) { return $relativePath; }
    $relativePath = ltrim(str_replace('\\', '/', $relativePath), '/');
    if (defined('APP_PUBLIC_CACHE_URL') && !str_starts_with($relativePath, trim(APP_PUBLIC_CACHE_URL, '/') . '/')) { return ''; }
    $absolute = APP_ROOT . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
    return is_file($absolute) ? app_href($relativePath) : '';
}

function app_external_tmdb_image_url(?string $imagePath, string $size = 'w342'): string
{
    $imagePath = trim((string)$imagePath);
    if ($imagePath === '') { return ''; }
    $base = match ($size) {
        'w185' => TMDB_IMAGE_BASE_SMALL,
        'w300' => defined('TMDB_IMAGE_BASE_STILL') ? TMDB_IMAGE_BASE_STILL : 'https://image.tmdb.org/t/p/w300',
        'w500' => defined('TMDB_IMAGE_BASE_MEDIUM') ? TMDB_IMAGE_BASE_MEDIUM : 'https://image.tmdb.org/t/p/w500',
        'original' => TMDB_IMAGE_BASE_ORIGINAL,
        default => TMDB_IMAGE_BASE,
    };
    return $base . $imagePath;
}

function app_media_poster_url(array $item): string
{
    $local = app_public_image_url($item['local_poster_path'] ?? '');
    if ($local !== '') { return $local; }
    $poster = trim((string)($item['poster_url'] ?? ''));
    if ($poster !== '' && preg_match('#^https?://#i', $poster)) { return $poster; }
    if (!empty($item['poster_path'])) { return app_external_tmdb_image_url((string)$item['poster_path'], 'w342'); }
    return app_href(APP_DEFAULT_POSTER);
}

function app_season_poster_url(array $season, array $item): string
{
    $local = app_public_image_url($season['local_poster_path'] ?? '');
    if ($local !== '') { return $local; }
    if (!empty($season['poster_path'])) { return app_external_tmdb_image_url((string)$season['poster_path'], 'w342'); }
    return app_media_poster_url($item);
}

function app_episode_art_url(array $episode, array $season, array $item): string
{
    $localStill = app_public_image_url($episode['local_still_path'] ?? '');
    if ($localStill !== '') { return $localStill; }
    if (!empty($episode['still_path'])) { return app_external_tmdb_image_url((string)$episode['still_path'], 'w300'); }
    return app_season_poster_url($season, $item);
}

function app_episode_percent(array $item): ?int
{
    if (($item['type'] ?? '') !== 'tv') {
        return null;
    }
    $total = app_total_episodes_from_item($item);
    if ($total <= 0) {
        return null;
    }
    $watched = count(app_watched_episode_keys($item));
    return (int)round(min(100, ($watched / $total) * 100));
}

function app_render_media_card(array $item, bool $editable = false, string $targetUser = ''): void
{
    $poster = app_media_poster_url($item);
    $uid = (string)($item['uid'] ?? '');
    $detailQuery = 'item.php?uid=' . rawurlencode($uid) . ($targetUser !== '' ? '&u=' . rawurlencode($targetUser) : '');
    $percent = app_episode_percent($item);
    ?>
    <article class="media-card">
        <img class="poster" src="<?= e($poster) ?>" alt="Poster for <?= e($item['title'] ?? 'media') ?>" loading="lazy">
        <div class="media-body">
            <div class="media-title-row">
                <h3><a href="<?= e(app_href($detailQuery)) ?>"><?= e($item['title'] ?? 'Untitled') ?></a></h3>
                <span class="pill"><?= e(strtoupper((string)($item['type'] ?? 'movie'))) ?></span>
            </div>
            <p class="muted"><?= e((string)($item['year'] ?? '')) ?> · <?= e(app_statuses()[$item['status'] ?? 'watchlist'] ?? ($item['status'] ?? 'watchlist')) ?></p>
            <?php $genreText = app_media_genre_text($item); if ($genreText !== ''): ?><p class="muted"><?= e($genreText) ?></p><?php endif; ?>
            <?php if (!empty($item['vote_average'])): ?><p class="muted">TMDB: <?= e((string)$item['vote_average']) ?>/10<?= !empty($item['vote_count']) ? ' · ' . e((string)$item['vote_count']) . ' votes' : '' ?></p><?php endif; ?>
            <?php $tmdbPublicUrl = app_tmdb_public_url_for_item($item); if ($tmdbPublicUrl !== ''): ?><p><a class="small-link" href="<?= e($tmdbPublicUrl) ?>" target="_blank" rel="noopener">Open on TMDB</a></p><?php endif; ?>
            <?php if ($percent !== null): ?>
                <div class="progress"><span style="width: <?= e((string)$percent) ?>%"></span></div>
                <p class="muted"><?= e((string)$percent) ?>% complete</p>
            <?php endif; ?>
            <?php if (!empty($item['overview'])): ?><p><?= e(app_excerpt((string)$item['overview'], 180)) ?></p><?php endif; ?>
            <?php if (!empty($item['rating'])): ?><p class="rating">Rating: <?= e((string)$item['rating']) ?>/10</p><?php endif; ?>
            <?php if (!empty($item['notes'])): ?><p class="notes"><?= e((string)$item['notes']) ?></p><?php endif; ?>
            <?php if (($item['type'] ?? '') === 'tv' && !empty($item['last_episode'])): ?>
                <p class="muted">Last episode: S<?= e((string)($item['last_episode']['season'] ?? '?')) ?>E<?= e((string)($item['last_episode']['episode'] ?? '?')) ?></p>
            <?php endif; ?>
            <?php if ($editable): ?>
                <details class="edit-panel">
                    <summary>Edit</summary>
                    <form method="post" action="<?= e(app_href('api/update-status.php')) ?>" class="stack">
                        <input type="hidden" name="csrf_token" value="<?= e(app_csrf_token()) ?>">
                        <input type="hidden" name="uid" value="<?= e($uid) ?>">
                        <?php if ($targetUser !== ''): ?><input type="hidden" name="target_user" value="<?= e($targetUser) ?>"><?php endif; ?>
                        <input type="hidden" name="redirect" value="<?= e($_SERVER['REQUEST_URI'] ?? '../watchlist.php') ?>">
                        <label>Status
                            <select name="status">
                                <?php foreach (app_statuses() as $status => $label): ?>
                                    <option value="<?= e($status) ?>" <?= ($item['status'] ?? '') === $status ? 'selected' : '' ?>><?= e($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label>Rating
                            <input type="number" name="rating" min="1" max="10" value="<?= e((string)($item['rating'] ?? '')) ?>" placeholder="1-10">
                        </label>
                        <label>Notes
                            <textarea name="notes" rows="3"><?= e((string)($item['notes'] ?? '')) ?></textarea>
                        </label>
                        <?php if (($item['type'] ?? '') === 'tv'): ?>
                            <div class="grid-2">
                                <label>Season <input type="number" name="season" min="0" value="<?= e((string)($item['last_episode']['season'] ?? '')) ?>"></label>
                                <label>Episode <input type="number" name="episode" min="0" value="<?= e((string)($item['last_episode']['episode'] ?? '')) ?>"></label>
                            </div>
                            <div class="grid-2">
                                <label>Total seasons <input type="number" name="total_seasons" min="0" value="<?= e((string)($item['total_seasons'] ?? '')) ?>"></label>
                                <label>Total episodes <input type="number" name="total_episodes" min="0" value="<?= e((string)($item['total_episodes'] ?? '')) ?>"></label>
                            </div>
                        <?php endif; ?>
                        <button type="submit">Save</button>
                    </form>
                    <?php if (empty($item['tmdb_id'])): ?>
                    <p><a class="button secondary" href="<?= e(app_href('tmdb-link.php?uid=' . rawurlencode($uid) . ($targetUser !== '' ? '&u=' . rawurlencode($targetUser) : ''))) ?>">Link to TMDB</a></p>
                    <?php endif; ?>
                    <?php if (!empty($item['tmdb_id'])): ?>
                    <form method="post" action="<?= e(app_href('api/refresh-poster.php')) ?>">
                        <input type="hidden" name="csrf_token" value="<?= e(app_csrf_token()) ?>">
                        <input type="hidden" name="uid" value="<?= e($uid) ?>">
                        <?php if ($targetUser !== ''): ?><input type="hidden" name="target_user" value="<?= e($targetUser) ?>"><?php endif; ?>
                        <input type="hidden" name="redirect" value="<?= e($_SERVER['REQUEST_URI'] ?? '../watchlist.php') ?>">
                        <button class="secondary" type="submit">Refresh TMDB details/poster</button>
                    </form>
                    <form method="post" action="<?= e(app_href('api/refresh-artwork.php')) ?>">
                        <input type="hidden" name="csrf_token" value="<?= e(app_csrf_token()) ?>">
                        <input type="hidden" name="uid" value="<?= e($uid) ?>">
                        <?php if ($targetUser !== ''): ?><input type="hidden" name="target_user" value="<?= e($targetUser) ?>"><?php endif; ?>
                        <input type="hidden" name="scope" value="item">
                        <input type="hidden" name="redirect" value="<?= e($_SERVER['REQUEST_URI'] ?? '../watchlist.php') ?>">
                        <button class="secondary" type="submit">Cache/refresh local artwork</button>
                    </form>
                    <?php endif; ?>
                    <form method="post" action="<?= e(app_href('api/delete-media.php')) ?>" onsubmit="return confirm('Delete this item from the list?');">
                        <input type="hidden" name="csrf_token" value="<?= e(app_csrf_token()) ?>">
                        <input type="hidden" name="uid" value="<?= e($uid) ?>">
                        <?php if ($targetUser !== ''): ?><input type="hidden" name="target_user" value="<?= e($targetUser) ?>"><?php endif; ?>
                        <input type="hidden" name="redirect" value="<?= e($_SERVER['REQUEST_URI'] ?? '../watchlist.php') ?>">
                        <button class="danger" type="submit">Delete</button>
                    </form>
                </details>
            <?php endif; ?>
        </div>
    </article>
    <?php
}

function app_profile(string $username): array
{
    app_seed_user_files($username);
    $profile = app_load_json(app_user_file($username, 'profile.json'), []);
    $profile['avatar_url'] = (string)($profile['avatar_url'] ?? '');
    return $profile;
}

function app_save_profile(string $username, array $profile): void
{
    $profile['_meta']['updated_at'] = date(DATE_ATOM);
    $profile['_meta']['version'] = APP_VERSION;
    app_save_json(app_user_file($username, 'profile.json'), $profile);
}

function app_connections(string $username): array
{
    app_seed_user_files($username);
    $data = app_load_json(app_user_file($username, 'connections.json'), []);
    foreach (['connections', 'incoming_requests', 'outgoing_requests'] as $key) {
        if (!isset($data[$key]) || !is_array($data[$key])) {
            $data[$key] = [];
        }
    }
    return $data;
}

function app_save_connections(string $username, array $data): void
{
    $data['_meta']['updated_at'] = date(DATE_ATOM);
    $data['_meta']['version'] = APP_VERSION;
    app_save_json(app_user_file($username, 'connections.json'), $data);
}

function app_can_view_library(array $viewer, string $targetUsername): bool
{
    if (($viewer['username'] ?? '') === $targetUsername || app_is_admin($viewer)) {
        return true;
    }
    $target = app_find_user($targetUsername);
    if (!$target || !empty($target['disabled'])) {
        return false;
    }
    $targetProfile = app_profile($targetUsername);
    if (!empty($targetProfile['public_share_enabled'])) {
        return true;
    }
    $viewerConnections = app_connections((string)$viewer['username']);
    return in_array($targetUsername, $viewerConnections['connections'], true);
}

function app_library_stats(array $items): array
{
    $stats = ['total' => count($items), 'movie' => 0, 'tv' => 0, 'completed' => 0, 'watching' => 0, 'watchlist' => 0];
    foreach ($items as $item) {
        $type = (string)($item['type'] ?? 'movie');
        if (isset($stats[$type])) { $stats[$type]++; }
        $status = (string)($item['status'] ?? 'watchlist');
        if (isset($stats[$status])) { $stats[$status]++; }
    }
    return $stats;
}

function app_filter_sort_items(array $items, array $query): array
{
    $q = strtolower(trim((string)($query['q'] ?? '')));
    $status = (string)($query['status'] ?? '');
    $type = (string)($query['type'] ?? '');
    $sort = (string)($query['sort'] ?? 'title');
    if ($q !== '') {
        $items = array_values(array_filter($items, static function ($item) use ($q) {
            return str_contains(strtolower((string)($item['title'] ?? '')), $q)
                || str_contains(strtolower((string)($item['notes'] ?? '')), $q)
                || str_contains(strtolower((string)($item['overview'] ?? '')), $q);
        }));
    }
    if ($status !== '' && array_key_exists($status, app_statuses())) {
        $items = array_values(array_filter($items, static fn($i) => ($i['status'] ?? '') === $status));
    }
    if (in_array($type, ['movie', 'tv'], true)) {
        $items = array_values(array_filter($items, static fn($i) => ($i['type'] ?? '') === $type));
    }
    usort($items, static function ($a, $b) use ($sort) {
        return match ($sort) {
            'updated' => strcmp((string)($b['updated_at'] ?? ''), (string)($a['updated_at'] ?? '')),
            'rating' => ((int)($b['rating'] ?? 0)) <=> ((int)($a['rating'] ?? 0)),
            'year' => strcmp((string)($b['year'] ?? ''), (string)($a['year'] ?? '')),
            default => strcmp(strtolower((string)($a['title'] ?? '')), strtolower((string)($b['title'] ?? ''))),
        };
    });
    return $items;
}

function app_normalize_import_item(array $row): array
{
    $type = strtolower(trim((string)($row['type'] ?? $row['media_type'] ?? 'movie')));
    $type = in_array($type, ['movie', 'tv'], true) ? $type : (str_contains($type, 'show') ? 'tv' : 'movie');
    $title = trim((string)($row['title'] ?? $row['name'] ?? ''));
    $tmdbIdRaw = trim((string)($row['tmdb_id'] ?? ''));
    $tmdbId = ctype_digit($tmdbIdRaw) ? (int)$tmdbIdRaw : null;
    $uid = app_make_media_uid($type, $tmdbId, $title);
    $status = strtolower(trim((string)($row['status'] ?? 'watchlist')));
    if (!array_key_exists($status, app_statuses())) {
        $status = 'watchlist';
    }
    $ratingRaw = trim((string)($row['rating'] ?? ''));
    $item = [
        'uid' => $uid,
        'source' => $tmdbId ? 'tmdb' : 'import',
        'tmdb_id' => $tmdbId,
        'type' => $type,
        'title' => $title,
        'year' => trim((string)($row['year'] ?? '')),
        'poster_path' => trim((string)($row['poster_path'] ?? '')),
        'poster_url' => trim((string)($row['poster_url'] ?? '')),
        'overview' => trim((string)($row['overview'] ?? '')),
        'tmdb_url' => trim((string)($row['tmdb_url'] ?? '')),
        'release_date' => trim((string)($row['release_date'] ?? '')),
        'status' => $status,
        'rating' => $ratingRaw === '' ? null : max(1, min(10, (int)$ratingRaw)),
        'notes' => trim((string)($row['notes'] ?? '')),
        'episodes' => [],
        'created_at' => date(DATE_ATOM),
        'updated_at' => date(DATE_ATOM),
    ];
    $season = trim((string)($row['season'] ?? ''));
    $episode = trim((string)($row['episode'] ?? ''));
    if ($type === 'tv' && $season !== '' && $episode !== '') {
        $entry = ['season' => max(0, (int)$season), 'episode' => max(0, (int)$episode), 'watched_at' => date(DATE_ATOM)];
        $item['episodes'][] = $entry;
        $item['last_episode'] = $entry;
    }
    return $item;
}

function app_export_csv(array $items): string
{
    $fp = fopen('php://temp', 'r+');
    fputcsv($fp, ['uid','type','tmdb_id','tmdb_url','title','year','release_date','status','rating','season','episode','total_seasons','total_episodes','genres','notes','overview']);
    foreach ($items as $item) {
        $last = $item['last_episode'] ?? [];
        fputcsv($fp, [
            $item['uid'] ?? '', $item['type'] ?? '', $item['tmdb_id'] ?? '', app_tmdb_public_url_for_item($item), $item['title'] ?? '', $item['year'] ?? '', $item['release_date'] ?? '',
            $item['status'] ?? '', $item['rating'] ?? '', $last['season'] ?? '', $last['episode'] ?? '',
            $item['total_seasons'] ?? '', $item['total_episodes'] ?? '', app_media_genre_text($item), $item['notes'] ?? '', $item['overview'] ?? '',
        ]);
    }
    rewind($fp);
    $csv = stream_get_contents($fp);
    fclose($fp);
    return is_string($csv) ? $csv : '';
}


function app_collect_public_cache_paths_from_value(mixed $value): array
{
    $paths = [];
    $prefix = trim(APP_PUBLIC_CACHE_URL, '/') . '/';
    $allowedSubdirs = ['posters/', 'stills/'];
    if (is_string($value)) {
        $normalized = ltrim(str_replace('\\', '/', trim($value)), '/');
        if (str_starts_with($normalized, $prefix)) {
            $inside = substr($normalized, strlen($prefix));
            foreach ($allowedSubdirs as $subdir) {
                if (str_starts_with($inside, $subdir)) {
                    $basename = basename($inside);
                    if ($basename !== '' && $basename !== '.placeholder' && preg_match('/\.(jpe?g|png|webp)$/i', $basename)) {
                        $paths[$prefix . $inside] = true;
                    }
                    break;
                }
            }
        }
    } elseif (is_array($value)) {
        foreach ($value as $child) {
            foreach (app_collect_public_cache_paths_from_value($child) as $path) {
                $paths[$path] = true;
            }
        }
    }
    return array_keys($paths);
}

function app_tmdb_named_cache_path(string $name): string
{
    $safe = preg_replace('/[^a-z0-9._-]+/i', '-', $name) . '.json';
    return APP_CACHE_DIR . DIRECTORY_SEPARATOR . 'tmdb' . DIRECTORY_SEPARATOR . $safe;
}

function app_referenced_artwork_cache_paths(): array
{
    $paths = [];
    $trackedTvSeasons = [];
    foreach (app_get_accounts()['users'] as $account) {
        $username = (string)($account['username'] ?? '');
        if ($username === '') { continue; }
        $library = app_library($username);
        foreach (($library['items'] ?? []) as $item) {
            if (!is_array($item)) { continue; }
            foreach (app_collect_public_cache_paths_from_value($item) as $path) {
                $paths[$path] = true;
            }
            if (($item['type'] ?? '') === 'tv' && (int)($item['tmdb_id'] ?? 0) > 0) {
                $seriesId = (int)$item['tmdb_id'];
                foreach (($item['seasons'] ?? []) as $season) {
                    if (!is_array($season)) { continue; }
                    $seasonNumber = (int)($season['season_number'] ?? -1);
                    if ($seasonNumber > 0) { $trackedTvSeasons[$seriesId . '-' . $seasonNumber] = [$seriesId, $seasonNumber]; }
                }
            }
        }
    }

    foreach ($trackedTvSeasons as $entry) {
        [$seriesId, $seasonNumber] = $entry;
        $cacheFile = app_tmdb_named_cache_path('tv-' . $seriesId . '-season-' . $seasonNumber);
        if (!is_file($cacheFile)) { continue; }
        $cache = app_load_json($cacheFile, []);
        foreach (app_collect_public_cache_paths_from_value($cache) as $path) {
            $paths[$path] = true;
        }
    }

    return array_keys($paths);
}

function app_all_artwork_cache_files(): array
{
    $files = [];
    foreach (['posters', 'stills'] as $subdir) {
        $dir = APP_PUBLIC_CACHE_DIR . DIRECTORY_SEPARATOR . $subdir;
        if (!is_dir($dir)) { continue; }
        foreach (new DirectoryIterator($dir) as $entry) {
            if ($entry->isDot() || !$entry->isFile()) { continue; }
            $name = $entry->getFilename();
            if ($name === '.placeholder' || !preg_match('/\.(jpe?g|png|webp)$/i', $name)) { continue; }
            $relative = trim(APP_PUBLIC_CACHE_URL, '/') . '/' . $subdir . '/' . $name;
            $files[$relative] = $entry->getPathname();
        }
    }
    return $files;
}

function app_remove_unused_artwork_cache(): array
{
    $referenced = array_fill_keys(app_referenced_artwork_cache_paths(), true);
    $files = app_all_artwork_cache_files();
    $stats = [
        'checked' => count($files),
        'kept' => 0,
        'removed' => 0,
        'errors' => 0,
        'bytes_removed' => 0,
    ];

    foreach ($files as $relative => $absolute) {
        if (isset($referenced[$relative])) {
            $stats['kept']++;
            continue;
        }
        $bytes = is_file($absolute) ? (int)filesize($absolute) : 0;
        if (@unlink($absolute)) {
            $stats['removed']++;
            $stats['bytes_removed'] += $bytes;
        } else {
            $stats['errors']++;
        }
    }
    return $stats;
}

function app_simple_markdown(string $markdown): string
{
    $lines = preg_split('/\R/', $markdown) ?: [];
    $html = '';
    $inList = false;
    $inHtmlComment = false;
    foreach ($lines as $line) {
        $trimmed = trim($line);
        if ($inHtmlComment) {
            if (str_contains($trimmed, '-->')) {
                $inHtmlComment = false;
            }
            continue;
        }
        if (str_starts_with($trimmed, '<!--')) {
            if (!str_contains($trimmed, '-->')) {
                $inHtmlComment = true;
            }
            continue;
        }
        $escaped = e($line);
        if (preg_match('/^###\s+(.*)$/', $line, $m)) {
            if ($inList) { $html .= '</ul>'; $inList = false; }
            $html .= '<h3>' . e($m[1]) . '</h3>';
        } elseif (preg_match('/^##\s+(.*)$/', $line, $m)) {
            if ($inList) { $html .= '</ul>'; $inList = false; }
            $html .= '<h2>' . e($m[1]) . '</h2>';
        } elseif (preg_match('/^#\s+(.*)$/', $line, $m)) {
            if ($inList) { $html .= '</ul>'; $inList = false; }
            $html .= '<h1>' . e($m[1]) . '</h1>';
        } elseif (preg_match('/^- \[([ xX])\]\s+(.*)$/', $line, $m)) {
            if (!$inList) { $html .= '<ul class="task-list">'; $inList = true; }
            $checked = strtolower($m[1]) === 'x' ? ' checked' : '';
            $html .= '<li><input type="checkbox" disabled' . $checked . '> ' . e($m[2]) . '</li>';
        } elseif (preg_match('/^-\s+(.*)$/', $line, $m)) {
            if (!$inList) { $html .= '<ul>'; $inList = true; }
            $html .= '<li>' . e($m[1]) . '</li>';
        } elseif (trim($line) === '') {
            if ($inList) { $html .= '</ul>'; $inList = false; }
        } else {
            if ($inList) { $html .= '</ul>'; $inList = false; }
            $html .= '<p>' . $escaped . '</p>';
        }
    }
    if ($inList) { $html .= '</ul>'; }
    return $html;
}
