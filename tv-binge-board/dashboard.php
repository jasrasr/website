<?php
/**
 * File: dashboard.php
 * Project: TV Binge Board
 * Description: User landing page with automatic new-episode checks, watch progress, hidden finished/caught-up home cards, dismissible PWA install help, compact dashboard notices, advanced feature links, suggestion-board link, and admin account routing.
 * Author: Jason Lamb / ChatGPT
 * Created: 2026-07-02
 * Modified: 2026-07-05
 * Revision: 1.5.23
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/auto-refresh.php';
$user = app_require_login();

function app_dashboard_should_show_item(array $item): bool
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

app_page_header('Dashboard');
?>
<style>
.update-notice{gap:.4rem;margin-bottom:.55rem;padding:.55rem .7rem;border-radius:.8rem;font-size:.88rem}.update-notice p{margin:.15rem 0 0;font-size:.82rem}.update-notice-actions{gap:.4rem}.update-notice-actions .button,.update-notice-actions button{padding:.55rem .75rem}.compact-dashboard-note{padding:.85rem;margin-bottom:.85rem}.compact-dashboard-note h2{font-size:1.08rem;margin-bottom:.35rem}.compact-dashboard-note p{margin:.25rem 0}.compact-dashboard-note .actions{margin-top:.55rem;gap:.5rem}.compact-dashboard-note form{margin:0}.compact-dashboard-note .button,.compact-dashboard-note button{padding:.65rem .85rem}@media (min-width:720px){.compact-dashboard-note{display:grid;grid-template-columns:1fr auto;align-items:center;gap:.8rem}}
</style>
<?php
if (app_is_admin($user)):
$settings = app_get_settings();
$activity = app_activity_events(6);
?>
<section class="card">
    <h1>Admin dashboard</h1>
    <p>This account is intentionally not a watch-tracking account. Use it to manage users, site settings, audits, and suggestions.</p>
    <div class="actions">
        <a class="button" href="admin/users.php">Manage users</a>
        <a class="button secondary" href="admin/site-settings.php">Site settings</a>
        <a class="button secondary" href="suggestions.php">Suggestions / bugs</a>
        <a class="button secondary" href="changelog.php">View changelog</a>
        <a class="button secondary" href="install.php">Install app</a>
    </div>
    <p class="muted">Public registration: <?= !empty($settings['public_registration_enabled']) ? 'enabled' : 'disabled' ?></p>
</section>
<section class="card">
    <h2>Recent admin activity</h2>
    <?php if (!$activity): ?><p class="muted">No activity recorded yet.</p><?php endif; ?>
    <ul class="compact-list">
        <?php foreach ($activity as $event): ?>
            <li><strong><?= e((string)($event['action'] ?? 'event')) ?></strong> · <?= e((string)($event['target'] ?? '')) ?> <span class="muted"><?= e((string)($event['at'] ?? '')) ?></span></li>
        <?php endforeach; ?>
    </ul>
</section>
<?php
else:
$profile = app_profile((string)$user['username']);
$autoRefresh = app_auto_refresh_user_library((string)$user['username']);
$autoRefreshNotice = app_auto_refresh_notice($autoRefresh);
$library = is_array($autoRefresh['library'] ?? null) ? $autoRefresh['library'] : app_library($user['username']);
$items = $library['items'];
$homeItems = array_values(array_filter($items, static fn($i) => app_dashboard_should_show_item(is_array($i) ? $i : [])));
$stats = app_library_stats($items);
$watching = array_values(array_filter($homeItems, fn($i) => ($i['status'] ?? '') === 'watching'));
$watchlist = array_values(array_filter($homeItems, fn($i) => ($i['status'] ?? '') === 'watchlist'));
$recent = $homeItems;
usort($recent, fn($a, $b) => strcmp((string)($b['updated_at'] ?? ''), (string)($a['updated_at'] ?? '')));
$showInstallPrompt = empty($profile['hide_install_prompt']);
?>
<section class="hero-card">
    <h1>What’s next?</h1>
    <p>Track movies, shows, ratings, notes, exports, imports, episode progress, tags, custom lists, friend recommendations, and feature suggestions.</p>
    <div class="actions">
        <a class="button" href="search.php">Add something</a>
        <a class="button secondary" href="watchlist.php">Open full list</a>
        <a class="button secondary" href="suggestions.php">Suggest / report</a>
        <a class="button secondary" href="smart-import.php">Smart import</a>
        <a class="button secondary" href="lists.php">Lists / tags</a>
        <a class="button secondary" href="recommendations.php">Recommendations</a>
    </div>
</section>
<?php if ($autoRefreshNotice !== ''): ?>
<section class="card compact-dashboard-note">
    <h2>Tracked show check</h2>
    <p><?= e($autoRefreshNotice) ?></p>
    <p class="muted">Newly aired episodes and seasons are added to the saved TMDB metadata automatically; watched episodes are not marked for you.</p>
</section>
<?php endif; ?>
<?php if ($showInstallPrompt): ?>
<section class="card install-prompt-card compact-dashboard-note">
    <div>
        <h2>Use it like an app</h2>
        <p>Add TV Binge Board to your Home Screen for a cleaner app-style launch.</p>
    </div>
    <div class="actions">
        <a class="button secondary" href="install.php">Install / Add to Home Screen</a>
        <form method="post" action="api/dismiss-dashboard-card.php">
            <input type="hidden" name="csrf_token" value="<?= e(app_csrf_token()) ?>">
            <input type="hidden" name="card" value="install_prompt">
            <button class="secondary" type="submit">Dismiss</button>
        </form>
    </div>
</section>
<?php endif; ?>
<section class="stats-grid">
    <div class="stat-card"><strong><?= e((string)$stats['total']) ?></strong><span>Total</span></div>
    <div class="stat-card"><strong><?= e((string)$stats['watching']) ?></strong><span>Watching</span></div>
    <div class="stat-card"><strong><?= e((string)$stats['watchlist']) ?></strong><span>Watchlist</span></div>
    <div class="stat-card"><strong><?= e((string)$stats['completed']) ?></strong><span>Completed</span></div>
</section>
<section>
    <h2>Continue Watching</h2>
    <?php if (!$watching): ?><p class="muted">Nothing currently active in Watching.</p><?php endif; ?>
    <div class="media-list">
        <?php foreach (array_slice($watching, 0, 6) as $item) app_render_media_card($item, true); ?>
    </div>
</section>
<section>
    <h2>Watchlist</h2>
    <?php if (!$watchlist): ?><p class="muted">Your active watchlist is empty.</p><?php endif; ?>
    <div class="media-list">
        <?php foreach (array_slice($watchlist, 0, 6) as $item) app_render_media_card($item, true); ?>
    </div>
</section>
<section>
    <h2>Recently Updated</h2>
    <?php if (!$recent): ?><p class="muted">No active unfinished items to show.</p><?php endif; ?>
    <div class="media-list">
        <?php foreach (array_slice($recent, 0, 6) as $item) app_render_media_card($item, true); ?>
    </div>
</section>
<?php endif; app_page_footer(); ?>
