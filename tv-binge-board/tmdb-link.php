<?php
/**
 * File: tmdb-link.php
 * Project: TV Binge Board
 * Description: Search-and-link workflow for connecting an existing manual item to a TMDB movie or TV record.
 * Author: Jason Lamb / ChatGPT
 * Created: 2026-07-02
 * Modified: 2026-07-02
 * Revision: 1.4.2
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/tmdb.php';
$user = app_require_login();
$targetUsername = app_is_admin($user) && isset($_GET['u']) ? app_sanitize_username((string)$_GET['u']) : (string)$user['username'];
if (!app_find_user($targetUsername) || (!app_is_admin($user) && $targetUsername !== $user['username'])) { http_response_code(403); exit('Forbidden.'); }
$uid = (string)($_GET['uid'] ?? '');
$library = app_library($targetUsername);
$index = app_find_media_index($library, $uid);
if ($index === null) { http_response_code(404); exit('Item not found.'); }
$item = $library['items'][$index];
$q = trim((string)($_GET['q'] ?? ($item['title'] ?? '')));
$results = [];
$error = '';
if ($q !== '') {
    try { $results = app_tmdb_search($q); }
    catch (Throwable $ex) { $error = $ex->getMessage(); }
}
app_page_header('Link to TMDB');
?>
<section class="card">
    <h1>Link to TMDB</h1>
    <p>Current item: <strong><?= e((string)($item['title'] ?? 'Untitled')) ?></strong> <?= !empty($item['year']) ? '(' . e((string)$item['year']) . ')' : '' ?></p>
    <form method="get" class="stack">
        <input type="hidden" name="uid" value="<?= e($uid) ?>">
        <?php if (app_is_admin($user)): ?><input type="hidden" name="u" value="<?= e($targetUsername) ?>"><?php endif; ?>
        <label>Search TMDB
            <input name="q" value="<?= e($q) ?>" required>
        </label>
        <button type="submit">Search</button>
    </form>
    <?php if ($error !== ''): ?><div class="alert danger"><?= e($error) ?></div><?php endif; ?>
</section>
<?php if ($results): ?>
<section class="media-list">
    <?php foreach ($results as $result): ?>
        <?php $poster = !empty($result['poster_path']) ? app_tmdb_image_url((string)$result['poster_path']) : app_href(APP_DEFAULT_POSTER); ?>
        <article class="media-card">
            <img class="poster" src="<?= e($poster) ?>" alt="Poster for <?= e((string)$result['title']) ?>" loading="lazy">
            <div class="media-body">
                <div class="media-title-row"><h3><?= e((string)$result['title']) ?></h3><span class="pill"><?= e(strtoupper((string)$result['type'])) ?></span></div>
                <p class="muted"><?= e((string)($result['year'] ?? '')) ?><?= !empty($result['vote_average']) ? ' · TMDB ' . e((string)$result['vote_average']) . '/10' : '' ?></p>
                <?php if (!empty($result['overview'])): ?><p><?= e(app_excerpt((string)$result['overview'], 220)) ?></p><?php endif; ?>
                <p><a class="small-link" href="<?= e((string)$result['tmdb_url']) ?>" target="_blank" rel="noopener">Open on TMDB</a></p>
                <form method="post" action="api/link-tmdb.php">
                    <input type="hidden" name="csrf_token" value="<?= e(app_csrf_token()) ?>">
                    <input type="hidden" name="uid" value="<?= e($uid) ?>">
                    <?php if (app_is_admin($user)): ?><input type="hidden" name="target_user" value="<?= e($targetUsername) ?>"><?php endif; ?>
                    <input type="hidden" name="tmdb_id" value="<?= e((string)$result['tmdb_id']) ?>">
                    <input type="hidden" name="type" value="<?= e((string)$result['type']) ?>">
                    <button type="submit">Link this TMDB item</button>
                </form>
            </div>
        </article>
    <?php endforeach; ?>
</section>
<?php endif; app_page_footer(); ?>
