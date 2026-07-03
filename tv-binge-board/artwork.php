<?php
/**
 * File: artwork.php
 * Project: TV Binge Board
 * Description: TMDB artwork picker for choosing preferred poster and backdrop images for linked media items.
 * Author: Jason Lamb / ChatGPT
 * Created: 2026-07-02
 * Modified: 2026-07-02
 * Revision: 1.4.5
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
if (empty($item['tmdb_id'])) { http_response_code(400); exit('This item is not linked to TMDB.'); }
$editable = app_is_admin($user) || $targetUsername === $user['username'];
$type = (string)($item['type'] ?? 'movie');
$tmdbId = (int)($item['tmdb_id'] ?? 0);
$error = '';
$images = ['posters' => [], 'backdrops' => []];

function app_artwork_sort_candidates(array $items): array
{
    usort($items, static function ($left, $right): int {
        $vote = ((float)($right['vote_average'] ?? 0)) <=> ((float)($left['vote_average'] ?? 0));
        if ($vote !== 0) { return $vote; }
        return ((int)($right['vote_count'] ?? 0)) <=> ((int)($left['vote_count'] ?? 0));
    });
    return $items;
}

if (!app_tmdb_configured()) {
    $error = 'TMDB API credential is not configured.';
} else {
    try {
        $images = app_tmdb_request($type . '/' . $tmdbId . '/images', ['include_image_language' => 'en,null']);
        $images['posters'] = app_artwork_sort_candidates(is_array($images['posters'] ?? null) ? $images['posters'] : []);
        $images['backdrops'] = app_artwork_sort_candidates(is_array($images['backdrops'] ?? null) ? $images['backdrops'] : []);
    } catch (Throwable $ex) {
        $error = $ex->getMessage();
    }
}

function app_artwork_current_url(array $item, string $kind): string
{
    if ($kind === 'poster') {
        if (!empty($item['local_poster_path'])) { return app_href((string)$item['local_poster_path']); }
        return app_tmdb_image_url((string)($item['poster_path'] ?? ''), 'w342');
    }
    if (!empty($item['local_backdrop_path'])) { return app_href((string)$item['local_backdrop_path']); }
    return app_tmdb_image_url((string)($item['backdrop_path'] ?? ''), 'w500');
}

function app_render_artwork_options(array $items, array $item, string $kind, string $uid, string $targetUsername, bool $editable): void
{
    $currentPath = (string)($kind === 'poster' ? ($item['poster_path'] ?? '') : ($item['backdrop_path'] ?? ''));
    $size = $kind === 'poster' ? 'w342' : 'w500';
    $label = $kind === 'poster' ? 'Poster' : 'Backdrop';
    ?>
    <section class="card">
        <h2><?= e($label) ?> options</h2>
        <?php if (!$items): ?>
            <p class="muted">No <?= e(strtolower($label)) ?> options returned by TMDB.</p>
        <?php else: ?>
            <div class="artwork-grid <?= $kind === 'backdrop' ? 'backdrop-grid' : '' ?>">
                <?php foreach (array_slice($items, 0, 60) as $candidate): ?>
                    <?php
                        if (!is_array($candidate)) { continue; }
                        $path = (string)($candidate['file_path'] ?? '');
                        if ($path === '') { continue; }
                        $selected = $path === $currentPath;
                    ?>
                    <article class="artwork-option <?= $selected ? 'selected' : '' ?>">
                        <img src="<?= e(app_tmdb_image_url($path, $size)) ?>" alt="<?= e($label) ?> option" loading="lazy">
                        <p class="muted">Score <?= e((string)($candidate['vote_average'] ?? '0')) ?> · <?= e((string)($candidate['vote_count'] ?? '0')) ?> votes</p>
                        <p class="muted"><?= e((string)($candidate['width'] ?? '?')) ?>×<?= e((string)($candidate['height'] ?? '?')) ?><?= !empty($candidate['iso_639_1']) ? ' · ' . e((string)$candidate['iso_639_1']) : '' ?></p>
                        <?php if ($selected): ?>
                            <span class="pill success">Selected</span>
                        <?php elseif ($editable): ?>
                            <form method="post" action="<?= e(app_href('api/select-artwork.php')) ?>">
                                <input type="hidden" name="csrf_token" value="<?= e(app_csrf_token()) ?>">
                                <input type="hidden" name="uid" value="<?= e($uid) ?>">
                                <?php if (app_is_admin(app_current_user() ?? [])): ?><input type="hidden" name="target_user" value="<?= e($targetUsername) ?>"><?php endif; ?>
                                <input type="hidden" name="kind" value="<?= e($kind) ?>">
                                <input type="hidden" name="image_path" value="<?= e($path) ?>">
                                <button class="secondary" type="submit">Use this <?= e(strtolower($label)) ?></button>
                            </form>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
    <?php
}

app_page_header('Choose Artwork');
?>
<section class="card">
    <p><a class="small-link" href="<?= e(app_href('item.php?uid=' . rawurlencode($uid) . (app_is_admin($user) ? '&u=' . rawurlencode($targetUsername) : ''))) ?>">← Back to item</a></p>
    <h1>Choose artwork</h1>
    <p class="muted"><?= e((string)($item['title'] ?? 'Untitled')) ?> · TMDB <?= e((string)$tmdbId) ?></p>
    <?php if ($error !== ''): ?><p class="alert danger"><?= e($error) ?></p><?php endif; ?>
    <div class="current-artwork-grid">
        <div>
            <h3>Current poster</h3>
            <?php $posterUrl = app_artwork_current_url($item, 'poster'); ?>
            <?php if ($posterUrl !== ''): ?><img class="current-poster-preview" src="<?= e($posterUrl) ?>" alt="Current poster"><?php else: ?><p class="muted">No poster selected.</p><?php endif; ?>
        </div>
        <div>
            <h3>Current backdrop</h3>
            <?php $backdropUrl = app_artwork_current_url($item, 'backdrop'); ?>
            <?php if ($backdropUrl !== ''): ?><img class="current-backdrop-preview" src="<?= e($backdropUrl) ?>" alt="Current backdrop"><?php else: ?><p class="muted">No backdrop selected.</p><?php endif; ?>
        </div>
    </div>
</section>
<?php
app_render_artwork_options($images['posters'] ?? [], $item, 'poster', $uid, $targetUsername, $editable);
app_render_artwork_options($images['backdrops'] ?? [], $item, 'backdrop', $uid, $targetUsername, $editable);
app_page_footer();
