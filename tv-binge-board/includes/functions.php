<?php
/**
 * File: includes/functions.php
 * Project: TV Binge Board
 * Description: Shared helpers for escaping, redirects, auth/session access, page layout, navigation, media rendering, library filters, avatars, and utility formatting.
 * Author: Jason Lamb / ChatGPT
 * Created: 2026-07-02
 * Modified: 2026-07-03
 * Revision: 1.5.4
 */
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/json-store.php';
require_once __DIR__ . '/auth.php';

function e(string $value): string { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }
function app_base_path(): string
{
    $script = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
    $script = rtrim($script, '/');
    if (str_ends_with($script, '/api') || str_ends_with($script, '/admin')) { $script = dirname($script); }
    return $script === '/' ? '' : $script;
}
function app_href(string $path): string { return app_base_path() . '/' . ltrim($path, '/'); }
function app_redirect(string $path): void { header('Location: ' . app_href($path)); exit; }
function app_flash(?string $message = null, string $type = 'success'): ?array
{
    if ($message !== null) { $_SESSION['flash'] = ['message' => $message, 'type' => $type]; return null; }
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return is_array($flash) ? $flash : null;
}
function app_statuses(): array { return ['watchlist' => 'Want to Watch', 'watching' => 'Watching', 'completed' => 'Completed', 'dropped' => 'Dropped']; }
function app_types(): array { return ['movie' => 'Movie', 'tv' => 'TV Show']; }
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
    <link rel="icon" href="<?= e(app_href('app-icon.php?size=32')) ?>" sizes="32x32" type="image/png">
    <link rel="icon" href="<?= e(app_href('app-icon.php?size=192')) ?>" sizes="192x192" type="image/png">
    <link rel="apple-touch-icon" sizes="180x180" href="<?= e(app_href('app-icon.php?size=180')) ?>">
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
<footer class="footer">
    <p><?= e(APP_PUBLIC_SITE_NOTE) ?></p>
    <p>Metadata may use TMDB. This product uses the TMDB API but is not endorsed or certified by TMDB.</p>
    <p><a href="<?= e(app_href('changelog.php')) ?>">CHANGELOG</a> · <a href="<?= e(app_href('README.md')) ?>">README</a> · <a href="<?= e(app_href('TASKS.md')) ?>">TASKS</a></p>
</footer>
<?php if ($user): ?>
<nav class="bottom-nav">
    <a href="<?= e(app_href('dashboard.php')) ?>">Home</a>
    <?php if (!app_is_admin($user)): ?>
        <a href="<?= e(app_href('search.php')) ?>">Search</a>
        <a href="<?= e(app_href('watchlist.php')) ?>">List</a>
        <a href="<?= e(app_href('people.php')) ?>">People</a>
        <a href="<?= e(app_href('import.php')) ?>">Import</a>
        <a href="<?= e(app_href('settings.php')) ?>">Settings</a>
    <?php else: ?>
        <a href="<?= e(app_href('admin/users.php')) ?>">Users</a>
        <a href="<?= e(app_href('admin/site-settings.php')) ?>">Site</a>
    <?php endif; ?>
    <a href="<?= e(app_href('logout.php')) ?>">Logout</a>
</nav>
<?php endif; ?>
<script>window.WATCHLEDGER_CSRF = <?= json_encode(app_csrf_token()) ?>;</script>
<script src="<?= e(app_href('assets/js/app.js?v=' . rawurlencode(APP_VERSION))) ?>"></script>
</body>
</html><?php
}

function app_excerpt(string $text, int $length = 180): string
{
    $text = trim($text);
    if (mb_strlen($text) <= $length) { return $text; }
    return rtrim(mb_substr($text, 0, $length - 1)) . '…';
}

function app_episode_percent(array $item): ?int
{
    if (($item['type'] ?? '') !== 'tv') { return null; }
    $total = max(0, (int)($item['total_episodes'] ?? 0));
    if ($total <= 0 && !empty($item['seasons']) && is_array($item['seasons'])) {
        foreach ($item['seasons'] as $season) { $total += max(0, (int)($season['episode_count'] ?? 0)); }
    }
    if ($total <= 0) { return null; }
    $watched = count($item['episodes'] ?? []);
    return min(100, (int)round(($watched / $total) * 100));
}

function app_media_poster_url(array $item): string
{
    if (!empty($item['local_poster_path'])) { return app_href((string)$item['local_poster_path']); }
    if (!empty($item['poster_path'])) { return TMDB_IMAGE_BASE . $item['poster_path']; }
    return app_href(APP_DEFAULT_POSTER);
}

function app_media_genre_text(array $item): string
{
    $genres = $item['genres'] ?? [];
    if (!is_array($genres) || $genres === []) { return ''; }
    $names = [];
    foreach ($genres as $genre) {
        if (is_array($genre) && !empty($genre['name'])) { $names[] = (string)$genre['name']; }
        elseif (is_string($genre)) { $names[] = $genre; }
    }
    return implode(', ', array_slice($names, 0, 4));
}

function app_tmdb_public_url_for_item(array $item): string
{
    $tmdbId = (int)($item['tmdb_id'] ?? 0);
    if ($tmdbId <= 0) { return ''; }
    $type = ($item['type'] ?? '') === 'tv' ? 'tv' : 'movie';
    return 'https://www.themoviedb.org/' . $type . '/' . $tmdbId;
}

function app_render_avatar(array $profile, string $username, int $size = 40): string
{
    $avatar = trim((string)($profile['avatar_url'] ?? ''));
    $display = trim((string)($profile['display_name'] ?? $username));
    $initial = strtoupper(mb_substr($display !== '' ? $display : $username, 0, 1));
    if ($avatar !== '') {
        return '<img class="avatar" src="' . e($avatar) . '" alt="Avatar for ' . e($display) . '" width="' . $size . '" height="' . $size . '">';
    }
    return '<span class="avatar avatar-fallback" style="width:' . $size . 'px;height:' . $size . 'px" aria-hidden="true">' . e($initial) . '</span>';
}

function app_watched_episode_keys(array $item): array
{
    $keys = [];
    foreach (($item['episodes'] ?? []) as $episode) {
        $season = (int)($episode['season'] ?? 0);
        $ep = (int)($episode['episode'] ?? 0);
        if ($season > 0 && $ep > 0) { $keys[$season . '-' . $ep] = true; }
    }
    return $keys;
}

function app_tv_season_options(array $item): array
{
    $options = [];
    foreach (($item['seasons'] ?? []) as $season) {
        $seasonNumber = (int)($season['season_number'] ?? 0);
        $episodeCount = (int)($season['episode_count'] ?? 0);
        if ($seasonNumber > 0 && $episodeCount > 0) { $options[$seasonNumber] = $episodeCount; }
    }
    if ($options) { ksort($options); return $options; }
    $totalSeasons = max(1, (int)($item['total_seasons'] ?? 1));
    $totalEpisodes = max(1, (int)($item['total_episodes'] ?? 10));
    $per = max(1, (int)ceil($totalEpisodes / $totalSeasons));
    for ($s = 1; $s <= min($totalSeasons, 25); $s++) { $options[$s] = min($per, 60); }
    return $options;
}

function app_episode_art_url(array $episode, array $season, array $item): string
{
    if (!empty($episode['local_still_path'])) { return app_href((string)$episode['local_still_path']); }
    if (!empty($episode['still_path'])) { return TMDB_IMAGE_BASE_STILL . $episode['still_path']; }
    if (!empty($season['local_poster_path'])) { return app_href((string)$season['local_poster_path']); }
    if (!empty($season['poster_path'])) { return TMDB_IMAGE_BASE_MEDIUM . $season['poster_path']; }
    return app_media_poster_url($item);
}

function app_render_media_card(array $item, bool $editable = false, string $targetUsername = ''): void
{
    $poster = app_media_poster_url($item);
    $uid = (string)($item['uid'] ?? '');
    $detailQuery = 'item.php?uid=' . rawurlencode($uid) . ($targetUsername !== '' ? '&u=' . rawurlencode($targetUsername) : '');
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
            <?php if (!empty($item['vote_average'])): ?><p class="muted">TMDB <?= e((string)$item['vote_average']) ?>/10<?= !empty($item['vote_count']) ? ' · ' . e((string)$item['vote_count']) . ' votes' : '' ?></p><?php endif; ?>
            <?php $tmdbUrl = app_tmdb_public_url_for_item($item); if ($tmdbUrl !== ''): ?><p><a class="small-link" href="<?= e($tmdbUrl) ?>" target="_blank" rel="noopener">Open on TMDB</a></p><?php endif; ?>
            <?php $percent = app_episode_percent($item); if ($percent !== null): ?><div class="progress"><span style="width: <?= e((string)$percent) ?>%"></span></div><p class="muted"><?= e((string)$percent) ?>% complete</p><?php endif; ?>
            <?php if (!empty($item['overview'])): ?><p><?= e(app_excerpt((string)$item['overview'], 160)) ?></p><?php endif; ?>
            <?php if (!empty($item['rating'])): ?><p>Rating: <?= e((string)$item['rating']) ?>/10</p><?php endif; ?>
            <?php if (!empty($item['notes'])): ?><p><strong>Notes:</strong> <?= e(app_excerpt((string)$item['notes'], 140)) ?></p><?php endif; ?>
            <?php if (!empty($item['last_episode'])): ?><p class="muted">Last watched: S<?= e((string)($item['last_episode']['season'] ?? '?')) ?>E<?= e((string)($item['last_episode']['episode'] ?? '?')) ?></p><?php endif; ?>
            <p><a class="small-link" href="<?= e(app_href($detailQuery)) ?>">Details / Edit</a></p>
            <?php if ($editable): ?>
            <details class="edit-panel">
                <summary>Quick edit</summary>
                <form method="post" action="<?= e(app_href('api/update-status.php')) ?>" class="stack">
                    <input type="hidden" name="csrf_token" value="<?= e(app_csrf_token()) ?>">
                    <input type="hidden" name="uid" value="<?= e($uid) ?>">
                    <?php if ($targetUsername !== ''): ?><input type="hidden" name="target_user" value="<?= e($targetUsername) ?>"><?php endif; ?>
                    <label>Status <select name="status"><?php foreach (app_statuses() as $status => $label): ?><option value="<?= e($status) ?>" <?= ($item['status'] ?? '') === $status ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></label>
                    <label>Rating <input type="number" min="0" max="10" name="rating" value="<?= e((string)($item['rating'] ?? '')) ?>"></label>
                    <label>Notes <textarea name="notes" rows="3"><?= e((string)($item['notes'] ?? '')) ?></textarea></label>
                    <?php if (($item['type'] ?? '') === 'tv'): ?>
                        <?php $seasonOptions = app_tv_season_options($item); ?>
                        <div class="grid-2">
                            <label>Season <select name="season" data-episode-options='<?= e(json_encode($seasonOptions)) ?>'><?php foreach ($seasonOptions as $season => $count): ?><option value="<?= e((string)$season) ?>" <?= (int)($item['last_episode']['season'] ?? 1) === (int)$season ? 'selected' : '' ?>><?= e((string)$season) ?></option><?php endforeach; ?></select></label>
                            <label>Episode <select name="episode"><option value="<?= e((string)($item['last_episode']['episode'] ?? 1)) ?>"><?= e((string)($item['last_episode']['episode'] ?? 1)) ?></option></select></label>
                        </div>
                    <?php endif; ?>
                    <button type="submit">Save</button>
                </form>
                <form method="post" action="<?= e(app_href('api/delete-media.php')) ?>" onsubmit="return confirm('Delete this item?');">
                    <input type="hidden" name="csrf_token" value="<?= e(app_csrf_token()) ?>">
                    <input type="hidden" name="uid" value="<?= e($uid) ?>">
                    <?php if ($targetUsername !== ''): ?><input type="hidden" name="target_user" value="<?= e($targetUsername) ?>"><?php endif; ?>
                    <button class="danger" type="submit">Delete</button>
                </form>
            </details>
            <?php endif; ?>
        </div>
    </article>
    <?php
}

function app_filter_sort_items(array $items, array $query): array
{
    $q = strtolower(trim((string)($query['q'] ?? '')));
    $status = (string)($query['status'] ?? '');
    $type = (string)($query['type'] ?? '');
    $sort = (string)($query['sort'] ?? 'title');
    if ($q !== '') {
        $items = array_values(array_filter($items, fn($i) => str_contains(strtolower((string)($i['title'] ?? '')), $q) || str_contains(strtolower((string)($i['notes'] ?? '')), $q)));
    }
    if ($status !== '' && array_key_exists($status, app_statuses())) { $items = array_values(array_filter($items, fn($i) => ($i['status'] ?? '') === $status)); }
    if (in_array($type, ['movie', 'tv'], true)) { $items = array_values(array_filter($items, fn($i) => ($i['type'] ?? '') === $type)); }
    usort($items, function ($a, $b) use ($sort) {
        return match ($sort) {
            'updated' => strcmp((string)($b['updated_at'] ?? ''), (string)($a['updated_at'] ?? '')),
            'rating' => ((int)($b['rating'] ?? 0)) <=> ((int)($a['rating'] ?? 0)),
            'year' => strcmp((string)($b['year'] ?? ''), (string)($a['year'] ?? '')),
            default => strcmp(strtolower((string)($a['title'] ?? '')), strtolower((string)($b['title'] ?? ''))),
        };
    });
    return $items;
}
