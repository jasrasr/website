<?php
/**
 * File: compare.php
 * Project: TV Binge Board
 * Description: Compares the signed-in user's library with a visible public or connected user's library.
 * Author: Jason Lamb / ChatGPT
 * Created: 2026-07-05
 * Modified: 2026-07-05
 * Revision: 1.0.0
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';
$viewer = app_require_login();
$targetUsername = app_sanitize_username((string)($_GET['u'] ?? ''));
$target = $targetUsername !== '' ? app_find_user($targetUsername) : null;

function app_compare_key(array $item): string
{
    $type = (string)($item['type'] ?? 'movie');
    $tmdbId = (int)($item['tmdb_id'] ?? 0);
    if ($tmdbId > 0) {
        return $type . ':tmdb:' . $tmdbId;
    }
    return $type . ':title:' . strtolower(trim((string)($item['title'] ?? '')));
}

function app_compare_item_map(array $items): array
{
    $map = [];
    foreach ($items as $item) {
        if (!is_array($item)) { continue; }
        $title = trim((string)($item['title'] ?? ''));
        if ($title === '') { continue; }
        $key = app_compare_key($item);
        if ($key !== 'movie:title:' && $key !== 'tv:title:') {
            $map[$key] = $item;
        }
    }
    return $map;
}

function app_compare_progress(array $item): string
{
    $status = app_statuses()[(string)($item['status'] ?? 'watchlist')] ?? 'Unknown';
    $rating = (int)($item['rating'] ?? 0);
    $parts = [$status];
    if (($item['type'] ?? '') === 'tv' && !empty($item['last_episode']) && is_array($item['last_episode'])) {
        $season = (int)($item['last_episode']['season'] ?? 0);
        $episode = (int)($item['last_episode']['episode'] ?? 0);
        if ($season > 0 && $episode > 0) { $parts[] = 'S' . $season . 'E' . $episode; }
    }
    if ($rating > 0) { $parts[] = $rating . '/10'; }
    return implode(' · ', $parts);
}

function app_compare_sort_items(array $items): array
{
    usort($items, static fn($a, $b) => strcmp(strtolower((string)($a['title'] ?? '')), strtolower((string)($b['title'] ?? ''))));
    return $items;
}

function app_compare_render_simple_items(array $items, string $emptyText): void
{
    if (!$items) {
        echo '<p class="muted">' . e($emptyText) . '</p>';
        return;
    }
    echo '<div class="user-list">';
    foreach (app_compare_sort_items($items) as $item) {
        echo '<article class="user-card"><div><strong>' . e((string)($item['title'] ?? 'Untitled')) . '</strong><p class="muted">' . e(strtoupper((string)($item['type'] ?? 'movie'))) . ' · ' . e(app_compare_progress($item)) . '</p></div></article>';
    }
    echo '</div>';
}

if (!$target || ($target['role'] ?? '') === 'admin' || !empty($target['disabled']) || $targetUsername === ($viewer['username'] ?? '')) {
    http_response_code(404);
    app_page_header('Compare not found');
    echo '<section class="card"><h1>Compare not found</h1><p>The selected user is not available for comparison.</p></section>';
    app_page_footer();
    exit;
}

if (!app_can_view_library($viewer, $targetUsername)) {
    http_response_code(403);
    app_page_header('Private list');
    echo '<section class="card"><h1>This list is private</h1><p>Connect with this user, or ask them to enable public sharing, before comparing lists.</p></section>';
    app_page_footer();
    exit;
}

$viewerProfile = app_profile((string)$viewer['username']);
$targetProfile = app_profile($targetUsername);
$viewerLibrary = app_library((string)$viewer['username']);
$targetLibrary = app_library($targetUsername);
$viewerItems = app_compare_item_map($viewerLibrary['items'] ?? []);
$targetItems = app_compare_item_map($targetLibrary['items'] ?? []);

$shared = [];
$onlyViewer = [];
$onlyTarget = [];
$targetRecommendations = [];

foreach ($viewerItems as $key => $item) {
    if (isset($targetItems[$key])) {
        $shared[$key] = ['mine' => $item, 'theirs' => $targetItems[$key]];
    } else {
        $onlyViewer[] = $item;
    }
}
foreach ($targetItems as $key => $item) {
    if (!isset($viewerItems[$key])) {
        $onlyTarget[] = $item;
        $status = (string)($item['status'] ?? '');
        $rating = (int)($item['rating'] ?? 0);
        if (in_array($status, ['watched', 'completed'], true) || $rating >= 8) {
            $targetRecommendations[] = $item;
        }
    }
}

uasort($shared, static fn($a, $b) => strcmp(strtolower((string)($a['mine']['title'] ?? '')), strtolower((string)($b['mine']['title'] ?? ''))));
$targetDisplay = (string)($targetProfile['display_name'] ?? $target['display_name'] ?? $targetUsername);
$viewerDisplay = (string)($viewerProfile['display_name'] ?? $viewer['display_name'] ?? $viewer['username']);

app_page_header('Compare Lists');
?>
<section class="card">
    <div class="profile-heading">
        <?= app_render_avatar($targetProfile, $targetUsername, 64) ?>
        <div><h1>Compare with <?= e($targetDisplay) ?></h1><p class="muted"><?= e($viewerDisplay) ?> vs <?= e($targetDisplay) ?></p></div>
    </div>
    <div class="actions">
        <a class="button secondary" href="connections.php">Back to People</a>
        <a class="button secondary" href="public.php?u=<?= e($targetUsername) ?>">View <?= e($targetDisplay) ?>’s list</a>
    </div>
</section>
<section class="stats-grid">
    <div class="stat-card"><strong><?= e((string)count($shared)) ?></strong><span>Both lists</span></div>
    <div class="stat-card"><strong><?= e((string)count($onlyViewer)) ?></strong><span>Only yours</span></div>
    <div class="stat-card"><strong><?= e((string)count($onlyTarget)) ?></strong><span>Only theirs</span></div>
    <div class="stat-card"><strong><?= e((string)count($targetRecommendations)) ?></strong><span>Try from them</span></div>
</section>
<section class="card">
    <h2>Both of you track</h2>
    <?php if (!$shared): ?><p class="muted">No overlap yet.</p><?php else: ?>
    <div class="user-list">
        <?php foreach ($shared as $row): ?>
        <article class="user-card stacked-card">
            <div><strong><?= e((string)($row['mine']['title'] ?? $row['theirs']['title'] ?? 'Untitled')) ?></strong><p class="muted"><?= e(strtoupper((string)($row['mine']['type'] ?? $row['theirs']['type'] ?? 'movie'))) ?></p></div>
            <div class="grid-2"><p><strong>You</strong><br><span class="muted"><?= e(app_compare_progress($row['mine'])) ?></span></p><p><strong><?= e($targetDisplay) ?></strong><br><span class="muted"><?= e(app_compare_progress($row['theirs'])) ?></span></p></div>
        </article>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</section>
<section class="card">
    <h2>Worth checking from <?= e($targetDisplay) ?></h2>
    <p class="muted">Titles they completed, watched, or rated highly that are not on your list.</p>
    <?php app_compare_render_simple_items($targetRecommendations, 'No recommendations found yet.'); ?>
</section>
<section class="grid-2">
    <div class="card"><h2>Only yours</h2><?php app_compare_render_simple_items($onlyViewer, 'No unique titles on your side.'); ?></div>
    <div class="card"><h2>Only <?= e($targetDisplay) ?>’s</h2><?php app_compare_render_simple_items($onlyTarget, 'No unique titles on their side.'); ?></div>
</section>
<?php app_page_footer(); ?>
