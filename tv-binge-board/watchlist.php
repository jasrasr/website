<?php
/**
 * File: watchlist.php
 * Project: TV Binge Board
 * Description: Full editable library list with search, status/type filters, and sorting.
 * Author: Jason Lamb / ChatGPT
 * Created: 2026-07-02
 * Modified: 2026-07-02
 * Revision: 1.4.2
 */
declare(strict_types=1);


require_once __DIR__ . '/includes/functions.php';
$user = app_require_login();

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
$items = app_filter_sort_items($library['items'], $_GET);
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
                <select name="sort"><option value="title">Title</option><option value="updated" <?= ($_GET['sort'] ?? '') === 'updated' ? 'selected' : '' ?>>Updated</option><option value="rating" <?= ($_GET['sort'] ?? '') === 'rating' ? 'selected' : '' ?>>Rating</option><option value="year" <?= ($_GET['sort'] ?? '') === 'year' ? 'selected' : '' ?>>Year</option></select>
            </label>
        </div>
        <div class="actions"><button type="submit">Apply</button><a class="button secondary" href="watchlist.php">Reset</a></div>
    </form>
</section>
<p class="muted"><?= e((string)count($items)) ?> matching item(s)</p>
<div class="media-list">
    <?php if (!$items): ?><p class="muted">No matching items.</p><?php endif; ?>
    <?php foreach ($items as $item) app_render_media_card($item, true); ?>
</div>
<?php endif; app_page_footer(); ?>
