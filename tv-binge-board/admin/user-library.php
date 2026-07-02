<?php
/**
 * File: admin/user-library.php
 * Project: TV Binge Board
 * Description: Admin-only page for viewing, adding, editing, exporting, and deleting another user tracked media.
 * Author: Jason Lamb / ChatGPT
 * Created: 2026-07-02
 * Modified: 2026-07-02
 * Revision: 1.4.2
 */
declare(strict_types=1);


require_once __DIR__ . '/../includes/functions.php';
app_require_admin();
$targetUsername = app_sanitize_username((string)($_GET['u'] ?? ''));
$target = $targetUsername !== '' ? app_find_user($targetUsername) : null;
if (!$target || ($target['role'] ?? '') === 'admin') { http_response_code(404); exit('User not found.'); }
$library = app_library($targetUsername);
$items = app_filter_sort_items($library['items'], $_GET);
app_page_header('Manage User Library');
?>
<section class="card">
    <h1>Manage <?= e((string)($target['display_name'] ?? $targetUsername)) ?>’s list</h1>
    <p class="muted">@<?= e($targetUsername) ?> <?= !empty($target['disabled']) ? '· disabled account' : '' ?></p>
    <div class="actions"><a class="button secondary" href="../public.php?u=<?= e($targetUsername) ?>">View shared page</a><a class="button secondary" href="../export.php?format=json&u=<?= e($targetUsername) ?>">Export JSON</a><a class="button secondary" href="../export.php?format=csv&u=<?= e($targetUsername) ?>">Export CSV</a></div>
</section>
<section class="card">
    <h2>Filter user list</h2>
    <form method="get" class="stack"><input type="hidden" name="u" value="<?= e($targetUsername) ?>"><label>Search <input name="q" value="<?= e((string)($_GET['q'] ?? '')) ?>"></label><div class="grid-3"><label>Status <select name="status"><option value="">All</option><?php foreach (app_statuses() as $status => $label): ?><option value="<?= e($status) ?>" <?= ($_GET['status'] ?? '') === $status ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></label><label>Type <select name="type"><option value="">All</option><option value="tv" <?= ($_GET['type'] ?? '') === 'tv' ? 'selected' : '' ?>>TV</option><option value="movie" <?= ($_GET['type'] ?? '') === 'movie' ? 'selected' : '' ?>>Movie</option></select></label><label>Sort <select name="sort"><option value="title">Title</option><option value="updated" <?= ($_GET['sort'] ?? '') === 'updated' ? 'selected' : '' ?>>Updated</option><option value="rating" <?= ($_GET['sort'] ?? '') === 'rating' ? 'selected' : '' ?>>Rating</option><option value="year" <?= ($_GET['sort'] ?? '') === 'year' ? 'selected' : '' ?>>Year</option></select></label></div><button type="submit">Apply</button></form>
</section>
<section class="card">
    <h2>Add manual item</h2>
    <form method="post" action="../api/add-media.php" class="stack">
        <input type="hidden" name="csrf_token" value="<?= e(app_csrf_token()) ?>">
        <input type="hidden" name="target_user" value="<?= e($targetUsername) ?>">
        <input type="hidden" name="redirect" value="../admin/user-library.php?u=<?= e($targetUsername) ?>">
        <label>Type <select name="type"><option value="tv">TV show</option><option value="movie">Movie</option></select></label>
        <label>Title <input name="title" required></label>
        <label>Year <input name="year" inputmode="numeric" pattern="[0-9]{4}"></label>
        <label>Status <select name="status"><?php foreach (app_statuses() as $status => $label): ?><option value="<?= e($status) ?>"><?= e($label) ?></option><?php endforeach; ?></select></label>
        <div class="grid-2"><label>Total seasons <input name="total_seasons" type="number" min="0"></label><label>Total episodes <input name="total_episodes" type="number" min="0"></label></div>
        <button type="submit">Add to user list</button>
    </form>
</section>
<div class="media-list">
    <?php if (!$items): ?><p class="muted">No items yet.</p><?php endif; ?>
    <?php foreach ($items as $item) app_render_media_card($item, true, $targetUsername); ?>
</div>
<?php app_page_footer(); ?>
