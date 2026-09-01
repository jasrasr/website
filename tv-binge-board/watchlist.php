<?php
/**
 * File: watchlist.php
 * Project: TV Binge Board
 * Description: Full editable library list with automatic new-episode checks, partial title search, persistent per-user filters, compact mobile cards, next-up/caught-up indicators, default hidden completed items, and smart sorting.
 * Author: Jason Lamb / ChatGPT
 * Created: 2026-07-02
 * Modified: 2026-07-05
 * Revision: 1.5.23
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/auto-refresh.php';
$user = app_require_login();

function app_watchlist_status_rank(array $item): int
{
    $status = (string)($item['status'] ?? 'watchlist');
    $percent = app_episode_percent($item);
    $nextUp = app_next_up_summary($item);

    if (($item['type'] ?? '') === 'tv' && ($nextUp['state'] ?? '') === 'caught-up') {
        return 2;
    }
    if (($item['type'] ?? '') === 'tv' && $percent !== null && $percent >= 100) {
        return 3;
    }

    return match ($status) {
        'watching' => 0,
        'watchlist' => 1,
        'completed', 'watched' => 3,
        'dropped' => 4,
        default => 2,
    };
}

function app_watchlist_is_in_progress(array $item): bool
{
    $status = (string)($item['status'] ?? 'watchlist');
    if (in_array($status, ['completed', 'watched', 'dropped'], true)) { return false; }

    if (($item['type'] ?? '') === 'tv') {
        $nextUp = app_next_up_summary($item);
        if (($nextUp['state'] ?? '') === 'caught-up') { return false; }
        $percent = app_episode_percent($item);
        if ($percent !== null && $percent >= 100) { return false; }
    }

    return true;
}

function app_watchlist_default_filters(): array
{
    return ['q' => '', 'status' => '', 'type' => '', 'sort' => 'smart', 'in_progress' => true];
}

function app_watchlist_query_has_filters(array $query): bool
{
    foreach (['q', 'status', 'type', 'sort', 'in_progress'] as $key) {
        if (array_key_exists($key, $query)) { return true; }
    }
    return false;
}

function app_watchlist_normalize_filters(array $query): array
{
    $defaults = app_watchlist_default_filters();
    $filters = [
        'q' => trim((string)($query['q'] ?? $defaults['q'])),
        'status' => (string)($query['status'] ?? $defaults['status']),
        'type' => (string)($query['type'] ?? $defaults['type']),
        'sort' => (string)($query['sort'] ?? $defaults['sort']),
        'in_progress' => !empty($query['in_progress']),
    ];
    if (!array_key_exists($filters['status'], app_statuses())) { $filters['status'] = ''; }
    if (!in_array($filters['type'], ['movie', 'tv'], true)) { $filters['type'] = ''; }
    if (!in_array($filters['sort'], ['smart', 'title', 'updated', 'rating', 'year'], true)) { $filters['sort'] = 'smart'; }
    return $filters;
}

function app_watchlist_current_filters(string $username, array $query): array
{
    $profile = app_profile($username);
    if (isset($query['reset'])) {
        $profile['watchlist_filters'] = app_watchlist_default_filters();
        app_save_profile($username, $profile);
        header('Location: watchlist.php');
        exit;
    }

    if (app_watchlist_query_has_filters($query)) {
        $filters = app_watchlist_normalize_filters($query);
        $profile['watchlist_filters'] = $filters;
        app_save_profile($username, $profile);
        return $filters;
    }

    $saved = $profile['watchlist_filters'] ?? null;
    if (is_array($saved)) {
        return array_merge(app_watchlist_default_filters(), app_watchlist_normalize_filters($saved));
    }

    return app_watchlist_default_filters();
}

function app_watchlist_filter_sort_items(array $items, array $query): array
{
    $q = strtolower(trim((string)($query['q'] ?? '')));
    $status = (string)($query['status'] ?? '');
    $type = (string)($query['type'] ?? '');
    $sort = (string)($query['sort'] ?? 'smart');
    $inProgressOnly = !empty($query['in_progress']);

    if ($q !== '') {
        $items = array_values(array_filter($items, static function ($item) use ($q) {
            return str_contains(strtolower((string)($item['title'] ?? '')), $q);
        }));
    }

    if ($status !== '' && array_key_exists($status, app_statuses())) {
        $items = array_values(array_filter($items, static fn($i) => ($i['status'] ?? '') === $status));
    }

    if (in_array($type, ['movie', 'tv'], true)) {
        $items = array_values(array_filter($items, static fn($i) => ($i['type'] ?? '') === $type));
    }

    if ($inProgressOnly) {
        $items = array_values(array_filter($items, static fn($i) => app_watchlist_is_in_progress(is_array($i) ? $i : [])));
    }

    usort($items, static function ($a, $b) use ($sort) {
        $titleCompare = strcmp(strtolower((string)($a['title'] ?? '')), strtolower((string)($b['title'] ?? '')));
        return match ($sort) {
            'updated' => strcmp((string)($b['updated_at'] ?? ''), (string)($a['updated_at'] ?? '')) ?: $titleCompare,
            'rating' => (((int)($b['rating'] ?? 0)) <=> ((int)($a['rating'] ?? 0))) ?: $titleCompare,
            'year' => strcmp((string)($b['year'] ?? ''), (string)($a['year'] ?? '')) ?: $titleCompare,
            'title' => $titleCompare,
            default => (app_watchlist_status_rank(is_array($a) ? $a : []) <=> app_watchlist_status_rank(is_array($b) ? $b : []))
                ?: strcmp((string)($b['updated_at'] ?? ''), (string)($a['updated_at'] ?? ''))
                ?: $titleCompare,
        };
    });

    return $items;
}

function app_render_compact_media_card(array $item): void
{
    $poster = app_media_poster_url($item);
    $uid = (string)($item['uid'] ?? '');
    $detailQuery = 'item.php?uid=' . rawurlencode($uid);
    $percent = app_episode_percent($item);
    $genreText = app_media_genre_text($item);
    $lastEpisode = $item['last_episode'] ?? [];
    $nextUp = app_next_up_summary($item);
    ?>
    <article class="media-card compact-media-card">
        <img class="poster" src="<?= e($poster) ?>" alt="Poster for <?= e($item['title'] ?? 'media') ?>" loading="lazy">
        <div class="media-body">
            <div class="media-title-row">
                <h3><a href="<?= e(app_href($detailQuery)) ?>"><?= e($item['title'] ?? 'Untitled') ?></a></h3>
                <span class="pill"><?= e(strtoupper((string)($item['type'] ?? 'movie'))) ?></span>
            </div>
            <p class="muted compact-meta-line"><?= e((string)($item['year'] ?? '')) ?> · <?= e(app_statuses()[$item['status'] ?? 'watchlist'] ?? ($item['status'] ?? 'watchlist')) ?></p>
            <?php if ($genreText !== ''): ?><p class="muted compact-meta-line"><?= e($genreText) ?></p><?php endif; ?>
            <?php if ($percent !== null): ?>
                <div class="progress"><span style="width: <?= e((string)$percent) ?>%"></span></div>
                <p class="muted compact-meta-line"><?= e((string)$percent) ?>% complete<?php if (!empty($lastEpisode)): ?> · Last S<?= e((string)($lastEpisode['season'] ?? '?')) ?>E<?= e((string)($lastEpisode['episode'] ?? '?')) ?><?php endif; ?></p>
                <?php if (($nextUp['label'] ?? '') !== ''): ?><p class="compact-meta-line next-up-line <?= e((string)($nextUp['css'] ?? '')) ?>"><?= e((string)$nextUp['label']) ?></p><?php endif; ?>
            <?php elseif (!empty($item['rating'])): ?>
                <p class="muted compact-meta-line">Rating: <?= e((string)$item['rating']) ?>/10</p>
            <?php endif; ?>
            <p class="compact-actions"><a class="small-link" href="<?= e(app_href($detailQuery)) ?>">Details / Edit</a></p>
        </div>
    </article>
    <?php
}

app_page_header('My List');
if (!app_can_track($user)):
?>
<section class="card">
    <h1>No personal list for admin</h1>
    <p>Use <a href="admin/users.php">Manage users</a> to view or edit other accounts.</p>
</section>
<?php
else:
$autoRefresh = app_auto_refresh_user_library((string)$user['username']);
$autoRefreshNotice = app_auto_refresh_notice($autoRefresh);
$library = is_array($autoRefresh['library'] ?? null) ? $autoRefresh['library'] : app_library($user['username']);
$filters = app_watchlist_current_filters((string)$user['username'], $_GET);
$items = app_watchlist_filter_sort_items($library['items'], $filters);
$currentSort = (string)($filters['sort'] ?? 'smart');
$advancedOpen = ($filters['status'] ?? '') !== '' || ($filters['type'] ?? '') !== '' || $currentSort !== 'smart' || empty($filters['in_progress']);
?>
<style>
.compact-list-toolbar{padding:.85rem}.compact-list-toolbar h1{font-size:1.45rem;margin-bottom:.45rem}.compact-list-toolbar p{margin:.25rem 0}.compact-list-toolbar .filter-form{gap:.55rem}.compact-list-toolbar details{border:1px solid var(--border);border-radius:.85rem;padding:.65rem;background:rgba(15,23,42,.42)}.compact-list-toolbar summary{cursor:pointer;font-weight:700}.compact-list-toolbar .actions{gap:.45rem}.compact-list-toolbar button,.compact-list-toolbar .button{padding:.65rem .85rem}
</style>
<section class="card compact-list-toolbar">
    <h1>My List</h1>
    <?php if ($autoRefreshNotice !== ''): ?>
        <div class="alert success"><?= e($autoRefreshNotice) ?> Newly aired episodes and seasons are now available for next-up tracking.</div>
    <?php endif; ?>
    <form method="get" class="stack filter-form">
        <label>Title search
            <input name="q" value="<?= e((string)($filters['q'] ?? '')) ?>" placeholder="Type part of a title">
        </label>
        <details <?= $advancedOpen ? 'open' : '' ?>>
            <summary>Advanced filters</summary>
            <div class="grid-3">
                <label>Status
                    <select name="status"><option value="">All</option><?php foreach (app_statuses() as $status => $label): ?><option value="<?= e($status) ?>" <?= ($filters['status'] ?? '') === $status ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select>
                </label>
                <label>Type
                    <select name="type"><option value="">All</option><option value="tv" <?= ($filters['type'] ?? '') === 'tv' ? 'selected' : '' ?>>TV</option><option value="movie" <?= ($filters['type'] ?? '') === 'movie' ? 'selected' : '' ?>>Movie</option></select>
                </label>
                <label>Sort
                    <select name="sort">
                        <option value="smart" <?= $currentSort === 'smart' ? 'selected' : '' ?>>Smart</option>
                        <option value="title" <?= $currentSort === 'title' ? 'selected' : '' ?>>Title</option>
                        <option value="updated" <?= $currentSort === 'updated' ? 'selected' : '' ?>>Updated</option>
                        <option value="rating" <?= $currentSort === 'rating' ? 'selected' : '' ?>>Rating</option>
                        <option value="year" <?= $currentSort === 'year' ? 'selected' : '' ?>>Year</option>
                    </select>
                </label>
            </div>
            <label class="checkbox-row"><input type="checkbox" name="in_progress" value="1" <?= !empty($filters['in_progress']) ? 'checked' : '' ?>> Hide 100% / caught-up / finished items</label>
        </details>
        <div class="actions"><button type="submit">Apply</button><a class="button secondary" href="watchlist.php?reset=1">Reset</a></div>
    </form>
</section>
<p class="muted"><?= e((string)count($items)) ?> matching item(s)</p>
<div class="media-list compact-media-list">
    <?php if (!$items): ?><p class="muted">No matching items.</p><?php endif; ?>
    <?php foreach ($items as $item) app_render_compact_media_card($item); ?>
</div>
<?php endif; app_page_footer(); ?>
