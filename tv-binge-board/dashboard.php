<?php
/**
 * File: dashboard.php
 * Project: TV Binge Board
 * Description: User landing page with watch progress and admin account routing.
 * Author: Jason Lamb / ChatGPT
 * Created: 2026-07-02
 * Modified: 2026-07-02
 * Revision: 1.4.2
 */
declare(strict_types=1);


require_once __DIR__ . '/includes/functions.php';
$user = app_require_login();

app_page_header('Dashboard');
if (app_is_admin($user)):
$settings = app_get_settings();
$activity = app_activity_events(6);
?>
<section class="card">
    <h1>Admin dashboard</h1>
    <p>This account is intentionally not a watch-tracking account. Use it to manage users, site settings, and audits.</p>
    <div class="actions">
        <a class="button" href="admin/users.php">Manage users</a>
        <a class="button secondary" href="admin/site-settings.php">Site settings</a>
        <a class="button secondary" href="changelog.php">View changelog</a>
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
$library = app_library($user['username']);
$items = $library['items'];
$stats = app_library_stats($items);
$watching = array_values(array_filter($items, fn($i) => ($i['status'] ?? '') === 'watching'));
$watchlist = array_values(array_filter($items, fn($i) => ($i['status'] ?? '') === 'watchlist'));
$recent = $items;
usort($recent, fn($a, $b) => strcmp((string)($b['updated_at'] ?? ''), (string)($a['updated_at'] ?? '')));
?>
<section class="hero-card">
    <h1>What’s next?</h1>
    <p>Track movies, shows, ratings, notes, exports, imports, and episode progress from a mobile-friendly page.</p>
    <div class="actions">
        <a class="button" href="search.php">Add something</a>
        <a class="button secondary" href="watchlist.php">Open full list</a>
    </div>
</section>
<section class="stats-grid">
    <div class="stat-card"><strong><?= e((string)$stats['total']) ?></strong><span>Total</span></div>
    <div class="stat-card"><strong><?= e((string)$stats['watching']) ?></strong><span>Watching</span></div>
    <div class="stat-card"><strong><?= e((string)$stats['watchlist']) ?></strong><span>Watchlist</span></div>
    <div class="stat-card"><strong><?= e((string)$stats['completed']) ?></strong><span>Completed</span></div>
</section>
<section>
    <h2>Continue Watching</h2>
    <?php if (!$watching): ?><p class="muted">Nothing currently marked as watching.</p><?php endif; ?>
    <div class="media-list">
        <?php foreach (array_slice($watching, 0, 6) as $item) app_render_media_card($item, true); ?>
    </div>
</section>
<section>
    <h2>Watchlist</h2>
    <?php if (!$watchlist): ?><p class="muted">Your watchlist is empty. Glorious freedom, or terrible data entry discipline.</p><?php endif; ?>
    <div class="media-list">
        <?php foreach (array_slice($watchlist, 0, 6) as $item) app_render_media_card($item, true); ?>
    </div>
</section>
<section>
    <h2>Recently Updated</h2>
    <div class="media-list">
        <?php foreach (array_slice($recent, 0, 6) as $item) app_render_media_card($item, true); ?>
    </div>
</section>
<?php endif; app_page_footer(); ?>
