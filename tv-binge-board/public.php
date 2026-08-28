<?php
/**
 * File: public.php
 * Project: TV Binge Board
 * Description: Public or connection-authorized list view for a selected user with avatar support.
 * Author: Jason Lamb / ChatGPT
 * Created: 2026-07-02
 * Modified: 2026-07-02
 * Revision: 1.4.2
 */
declare(strict_types=1);


require_once __DIR__ . '/includes/functions.php';
$viewer = app_current_user();
$targetUsername = app_sanitize_username((string)($_GET['u'] ?? ''));
$target = $targetUsername !== '' ? app_find_user($targetUsername) : null;
if (!$target || ($target['role'] ?? '') === 'admin' || !empty($target['disabled'])) {
    http_response_code(404);
    app_page_header('List not found');
    echo '<section class="card"><h1>List not found</h1></section>';
    app_page_footer();
    exit;
}
$profile = app_profile($targetUsername);
$public = !empty($profile['public_share_enabled']);
$allowed = $public || ($viewer && app_can_view_library($viewer, $targetUsername));
app_page_header('Shared List');
if (!$allowed): ?>
<section class="card"><h1>This list is private</h1><p>Sign in and connect with this user, or ask them to enable public sharing.</p></section>
<?php else:
$library = app_library($targetUsername);
$items = app_filter_sort_items($library['items'], $_GET);
$stats = app_library_stats($library['items']);
?>
<section class="card">
    <div class="profile-heading"><?= app_render_avatar($profile, $targetUsername, 64) ?><div><h1><?= e((string)($profile['display_name'] ?? $target['display_name'] ?? $targetUsername)) ?>’s List</h1><p class="muted"><?= $public ? 'Public list' : 'Shared through connection' ?> · <?= e((string)$stats['total']) ?> item(s)</p></div></div>
    <?php if (!empty($profile['bio'])): ?><p><?= e((string)$profile['bio']) ?></p><?php endif; ?>
</section>
<section class="card"><form method="get" class="stack"><input type="hidden" name="u" value="<?= e($targetUsername) ?>"><label>Search <input name="q" value="<?= e((string)($_GET['q'] ?? '')) ?>"></label><button type="submit">Filter</button></form></section>
<div class="media-list"><?php if (!$items): ?><p class="muted">No tracked items yet.</p><?php endif; ?><?php foreach ($items as $item) app_render_media_card($item, false); ?></div>
<?php endif; app_page_footer(); ?>
