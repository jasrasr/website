<?php
/**
 * File: watchlist.php
 * Project: TV Binge Board
 * Description: Full editable library list with search, status/type filters, compact mobile cards, next-up/caught-up indicators, in-progress filtering, and smart sorting.
 * Author: Jason Lamb / ChatGPT
 * Created: 2026-07-02
 * Modified: 2026-07-03
 * Revision: 1.4.5
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/next-up.php';
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
    if (($item['type'] ?? '') === 'tv') {
        $nextUp = app_next_up_summary($item);
        if (($nextUp['state'] ?? '') === 'caught-up') { return false; }
        $percent = app_episode_percent($item);
        return $percent === null || $percent < 100;
    }

    return !in_array((string)($item['status'] ?? ''), ['completed', 'watched', 'dropped'], true);
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
$library = app_library($user['username']);
$items = app_watchlist_filter_sort_items($library['items'], $_GET);
$currentSort = (string)($_GET['sort'] ?? 'smart');
?>
<section class="card">
    <h1>My List</h1>
    <form method="get" class="stack filter-form">
        <label>Search
            <input name="q" value="<?= e((string)($_GET['q'] ?? '')) ?>" placeholder="Title, notes, overview">
        </label>
        <div class="grid-3">
            <label>Status
                <select name="status"><option value="">All</option><?php foreach (app_statuses() as $status => $label): ?><option value="<?= e($status) ?>" <?= ($_GET['status'] ?? '') === $status ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select>
            </label>
            <label>Type
                <select name="type"><option value="">All</option><option value="tv" <?= ($_GET['type'] ?? '') === 'tv' ? 'selected' : '' ?>>TV</option><option value="movie" <?= ($_GET['type'] ?? '') === 'movie' ? 'selected' : '' ?>>Movie</option></select>
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
        <label class="checkbox-row"><input type="checkbox" name="in_progress" value="1" <?= !empty($_GET['in_progress']) ? 'checked' : '' ?>> Hide 100% / caught-up / finished items</label>
        <div class="actions"><button type="submit">Apply</button><a class="button secondary" href="watchlist.php">Reset</a></div>
    </form>
</section>
<p class="muted"><?= e((string)count($items)) ?> matching item(s)</p>
<div class="media-list compact-media-list">
    <?php if (!$items): ?><p class="muted">No matching items.</p><?php endif; ?>
    <?php foreach ($items as $item) app_render_compact_media_card($item); ?>
</div>
<?php endif; app_page_footer(); ?>
