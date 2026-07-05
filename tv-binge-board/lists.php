<?php
/**
 * File: lists.php
 * Project: TV Binge Board
 * Description: Custom user lists and item tags for organizing tracked movies and TV shows.
 * Author: Jason Lamb / ChatGPT
 * Created: 2026-07-05
 * Modified: 2026-07-05
 * Revision: 1.0.0
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';
$user = app_require_login();
if (!app_can_track($user)) { http_response_code(403); exit('This account cannot track media.'); }
$username = (string)$user['username'];

function app_custom_lists_path(string $username): string { return app_user_file($username, 'custom-lists.json'); }
function app_custom_list_slug(string $name): string
{
    $slug = strtolower(trim($name));
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';
    $slug = trim($slug, '-');
    return $slug !== '' ? $slug : 'list';
}
function app_load_custom_lists(string $username): array
{
    app_seed_user_files($username);
    $data = app_load_json(app_custom_lists_path($username), ['_meta' => app_json_meta('Custom media lists.'), 'lists' => []]);
    if (!isset($data['lists']) || !is_array($data['lists'])) { $data['lists'] = []; }
    return $data;
}
function app_save_custom_lists(string $username, array $data): void
{
    $data['_meta']['updated_at'] = date(DATE_ATOM);
    $data['_meta']['version'] = APP_VERSION;
    app_save_json(app_custom_lists_path($username), $data);
}
function app_split_tags(string $tags): array
{
    $parts = preg_split('/[,#]+/', strtolower($tags)) ?: [];
    $out = [];
    foreach ($parts as $tag) {
        $tag = trim(preg_replace('/[^a-z0-9 _-]+/', '', $tag) ?? '');
        $tag = preg_replace('/\s+/', ' ', $tag) ?? '';
        if ($tag !== '') { $out[$tag] = true; }
    }
    return array_keys($out);
}
function app_find_custom_list_index(array $lists, string $id): ?int
{
    foreach ($lists as $index => $list) {
        if (is_array($list) && (string)($list['id'] ?? '') === $id) { return $index; }
    }
    return null;
}

$library = app_library($username);
$data = app_load_custom_lists($username);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    app_verify_csrf();
    $action = (string)($_POST['action'] ?? '');
    if ($action === 'create_list') {
        $name = trim((string)($_POST['name'] ?? ''));
        if ($name !== '') {
            $base = app_custom_list_slug($name);
            $id = $base;
            $n = 2;
            while (app_find_custom_list_index($data['lists'], $id) !== null) { $id = $base . '-' . $n++; }
            $data['lists'][] = ['id' => $id, 'name' => $name, 'description' => trim((string)($_POST['description'] ?? '')), 'items' => [], 'created_at' => date(DATE_ATOM), 'updated_at' => date(DATE_ATOM)];
            app_save_custom_lists($username, $data);
            app_log_activity($username, 'custom-list-created', $username, ['list_id' => $id]);
            app_flash('Custom list created.', 'success');
        }
    } elseif ($action === 'delete_list') {
        $id = (string)($_POST['list_id'] ?? '');
        $index = app_find_custom_list_index($data['lists'], $id);
        if ($index !== null) {
            array_splice($data['lists'], $index, 1);
            app_save_custom_lists($username, $data);
            app_flash('Custom list deleted.', 'success');
        }
    } elseif ($action === 'toggle_item') {
        $id = (string)($_POST['list_id'] ?? '');
        $uid = (string)($_POST['uid'] ?? '');
        $index = app_find_custom_list_index($data['lists'], $id);
        if ($index !== null && app_find_media_index($library, $uid) !== null) {
            $items = is_array($data['lists'][$index]['items'] ?? null) ? $data['lists'][$index]['items'] : [];
            if (in_array($uid, $items, true)) { $items = array_values(array_filter($items, static fn($v) => $v !== $uid)); }
            else { $items[] = $uid; }
            $data['lists'][$index]['items'] = array_values(array_unique($items));
            $data['lists'][$index]['updated_at'] = date(DATE_ATOM);
            app_save_custom_lists($username, $data);
        }
    } elseif ($action === 'save_tags') {
        $uid = (string)($_POST['uid'] ?? '');
        $index = app_find_media_index($library, $uid);
        if ($index !== null) {
            $library['items'][$index]['tags'] = app_split_tags((string)($_POST['tags'] ?? ''));
            $library['items'][$index]['updated_at'] = date(DATE_ATOM);
            app_save_library($username, $library);
            app_flash('Tags saved.', 'success');
        }
    }
    header('Location: lists.php' . (!empty($_POST['return_list']) ? '?list=' . rawurlencode((string)$_POST['return_list']) : ''));
    exit;
}

$listId = (string)($_GET['list'] ?? ($data['lists'][0]['id'] ?? ''));
$listIndex = app_find_custom_list_index($data['lists'], $listId);
$selectedList = $listIndex !== null ? $data['lists'][$listIndex] : null;
$itemsByUid = [];
$tagCounts = [];
foreach (($library['items'] ?? []) as $item) {
    if (!is_array($item)) { continue; }
    $uid = (string)($item['uid'] ?? '');
    if ($uid !== '') { $itemsByUid[$uid] = $item; }
    foreach (($item['tags'] ?? []) as $tag) { $tagCounts[(string)$tag] = ($tagCounts[(string)$tag] ?? 0) + 1; }
}
ksort($tagCounts);

app_page_header('Custom Lists');
?>
<section class="card"><h1>Custom lists and tags</h1><p>Group titles into custom lists and tag items with your own labels, such as family, rewatch, date night, sci-fi, or Matt recommended.</p></section>
<section class="card"><h2>Create list</h2><form method="post" class="stack"><input type="hidden" name="csrf_token" value="<?= e(app_csrf_token()) ?>"><input type="hidden" name="action" value="create_list"><label>List name <input name="name" placeholder="Weekend binge"></label><label>Description <input name="description" placeholder="Optional"></label><button type="submit">Create list</button></form></section>
<?php if ($data['lists']): ?>
<section class="card"><h2>Your lists</h2><div class="chip-row"><?php foreach ($data['lists'] as $list): ?><a class="chip <?= (string)($list['id'] ?? '') === $listId ? 'active' : '' ?>" href="lists.php?list=<?= e((string)($list['id'] ?? '')) ?>"><?= e((string)($list['name'] ?? 'List')) ?> · <?= e((string)count((array)($list['items'] ?? []))) ?></a><?php endforeach; ?></div></section>
<?php endif; ?>
<?php if ($selectedList): $selectedItems = array_values(array_filter((array)($selectedList['items'] ?? []), static fn($uid) => isset($GLOBALS['itemsByUid'][$uid]))); ?>
<section class="card"><h2><?= e((string)$selectedList['name']) ?></h2><?php if (!empty($selectedList['description'])): ?><p><?= e((string)$selectedList['description']) ?></p><?php endif; ?><div class="actions"><form method="post" onsubmit="return confirm('Delete this custom list?');"><input type="hidden" name="csrf_token" value="<?= e(app_csrf_token()) ?>"><input type="hidden" name="action" value="delete_list"><input type="hidden" name="list_id" value="<?= e((string)$selectedList['id']) ?>"><button class="danger" type="submit">Delete list</button></form></div></section>
<section class="card"><h2>Items in this list</h2><div class="media-list"><?php if (!$selectedItems): ?><p class="muted">No items in this list yet.</p><?php endif; ?><?php foreach ($selectedItems as $uid) { app_render_media_card($itemsByUid[$uid], true); } ?></div></section>
<section class="card"><h2>Add or remove items</h2><div class="user-list">
<?php foreach ($itemsByUid as $uid => $item): $inList = in_array($uid, $selectedItems, true); ?>
<article class="user-card"><div><strong><?= e((string)($item['title'] ?? 'Untitled')) ?></strong><p class="muted"><?= e(strtoupper((string)($item['type'] ?? 'movie'))) ?> · <?= e(app_statuses()[(string)($item['status'] ?? 'watchlist')] ?? 'Watchlist') ?></p></div><form method="post"><input type="hidden" name="csrf_token" value="<?= e(app_csrf_token()) ?>"><input type="hidden" name="action" value="toggle_item"><input type="hidden" name="list_id" value="<?= e((string)$selectedList['id']) ?>"><input type="hidden" name="return_list" value="<?= e((string)$selectedList['id']) ?>"><input type="hidden" name="uid" value="<?= e($uid) ?>"><button class="<?= $inList ? 'secondary' : '' ?>" type="submit"><?= $inList ? 'Remove' : 'Add' ?></button></form></article>
<?php endforeach; ?>
</div></section>
<?php endif; ?>
<section class="card"><h2>Tags</h2><?php if ($tagCounts): ?><p class="muted"><?php foreach ($tagCounts as $tag => $count): ?><span class="chip">#<?= e($tag) ?> · <?= e((string)$count) ?></span> <?php endforeach; ?></p><?php else: ?><p class="muted">No tags yet.</p><?php endif; ?><div class="user-list">
<?php foreach ($itemsByUid as $uid => $item): ?>
<article class="user-card stacked-card"><div><strong><?= e((string)($item['title'] ?? 'Untitled')) ?></strong><p class="muted">Separate tags with commas or # signs.</p></div><form method="post" class="stack"><input type="hidden" name="csrf_token" value="<?= e(app_csrf_token()) ?>"><input type="hidden" name="action" value="save_tags"><input type="hidden" name="uid" value="<?= e($uid) ?>"><input name="tags" value="<?= e(implode(', ', (array)($item['tags'] ?? []))) ?>"><button class="secondary" type="submit">Save tags</button></form></article>
<?php endforeach; ?>
</div></section>
<?php app_page_footer(); ?>
