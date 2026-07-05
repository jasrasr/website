<?php
/**
 * File: item.php
 * Project: TV Binge Board
 * Description: Media detail page with editable metadata, next-up/caught-up TV status, TMDB links, metadata refresh controls, local artwork refresh controls, clearer watch progress actions, spoiler-safe episode display modes, completion percentage, and TMDB-backed TV episode grid.
 * Author: Jason Lamb / ChatGPT
 * Created: 2026-07-02
 * Modified: 2026-07-04
 * Revision: 1.5.13
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/tmdb.php';
require_once __DIR__ . '/includes/next-up.php';
$user = app_require_login();
$targetUsername = app_is_admin($user) && isset($_GET['u']) ? app_sanitize_username((string)$_GET['u']) : (string)$user['username'];
if (!app_find_user($targetUsername) || (!app_is_admin($user) && $targetUsername !== $user['username'])) { http_response_code(403); exit('Forbidden.'); }
$uid = (string)($_GET['uid'] ?? '');
$library = app_library($targetUsername);
$index = app_find_media_index($library, $uid);
if ($index === null) { http_response_code(404); exit('Item not found.'); }
$item = $library['items'][$index];
$editable = app_is_admin($user) || $targetUsername === $user['username'];
$watched = app_watched_episode_keys($item);
$percent = app_episode_percent($item);
$nextUpSummary = app_next_up_summary($item, true);
$requestedSeason = max(0, (int)($_GET['season'] ?? 1));
$episodeView = (string)($_GET['episode_view'] ?? ($_COOKIE['tvbb_episode_view'] ?? 'image'));
if (!in_array($episodeView, ['image', 'text'], true)) { $episodeView = 'image'; }
if (isset($_GET['episode_view'])) {
    setcookie('tvbb_episode_view', $episodeView, ['expires' => time() + 31536000, 'path' => '/', 'secure' => !empty($_SERVER['HTTPS']), 'httponly' => false, 'samesite' => 'Lax']);
}
$baseItemQuery = 'item.php?uid=' . rawurlencode($uid) . (app_is_admin($user) ? '&u=' . rawurlencode($targetUsername) : '');
$tmdbUrl = app_tmdb_public_url_for_item($item);
$artworkQuery = 'artwork.php?uid=' . rawurlencode($uid) . (app_is_admin($user) ? '&u=' . rawurlencode($targetUsername) : '');

$seasonSummaries = [];
if (($item['type'] ?? '') === 'tv') {
    foreach (($item['seasons'] ?? []) as $season) {
        if (!is_array($season)) { continue; }
        $seasonNumber = (int)($season['season_number'] ?? -1);
        $episodeCount = (int)($season['episode_count'] ?? 0);
        if ($seasonNumber > 0 && $episodeCount > 0) { $seasonSummaries[] = $season; }
    }
    if (!$seasonSummaries) {
        $totalSeasons = max(1, (int)($item['total_seasons'] ?? ($item['last_episode']['season'] ?? 1)));
        $totalEpisodes = max((int)($item['total_episodes'] ?? 10), 10);
        $episodesPerSeason = max(1, (int)ceil($totalEpisodes / max($totalSeasons, 1)));
        for ($season = 1; $season <= min($totalSeasons, 25); $season++) {
            $seasonSummaries[] = ['season_number' => $season, 'name' => 'Season ' . $season, 'episode_count' => min($episodesPerSeason, 40), 'air_date' => ''];
        }
    }
}

app_page_header((string)($item['title'] ?? 'Item'));
?>
<section class="card">
    <h1><?= e((string)($item['title'] ?? 'Untitled')) ?></h1>
    <p class="muted"><?= e(strtoupper((string)($item['type'] ?? 'movie'))) ?> · <?= e((string)($item['year'] ?? '')) ?> · <?= e(app_statuses()[$item['status'] ?? 'watchlist'] ?? 'Watchlist') ?></p>
    <?php $genreText = app_media_genre_text($item); if ($genreText !== ''): ?><p class="muted"><?= e($genreText) ?></p><?php endif; ?>
    <?php if (!empty($item['vote_average'])): ?><p class="muted">TMDB score: <?= e((string)$item['vote_average']) ?>/10<?= !empty($item['vote_count']) ? ' · ' . e((string)$item['vote_count']) . ' votes' : '' ?></p><?php endif; ?>
    <?php if ($tmdbUrl !== ''): ?><p><a class="button secondary" href="<?= e($tmdbUrl) ?>" target="_blank" rel="noopener">Open on TMDB</a></p><?php endif; ?>
    <?php if ($percent !== null): ?><div class="progress"><span style="width: <?= e((string)$percent) ?>%"></span></div><p class="muted"><?= e((string)$percent) ?>% complete</p><?php endif; ?>
    <?php if (!empty($item['overview'])): ?><p><?= e((string)$item['overview']) ?></p><?php endif; ?>
    <?php if (!empty($item['metadata_refreshed_at'])): ?><p class="muted">TMDB metadata refreshed: <?= e((string)$item['metadata_refreshed_at']) ?></p><?php endif; ?>
    <?php if (!empty($item['metadata_checked_for_new_episodes_at'])): ?><p class="muted">Checked for new episodes: <?= e((string)$item['metadata_checked_for_new_episodes_at']) ?></p><?php endif; ?>
    <?php if (!empty($item['local_poster_path'])): ?><p class="muted">Local poster cached: <?= e((string)($item['poster_cached_at'] ?? 'cached')) ?></p><?php endif; ?>
    <?php if (!empty($item['local_backdrop_path'])): ?><p class="muted">Local backdrop cached: <?= e((string)($item['backdrop_cached_at'] ?? 'cached')) ?></p><?php endif; ?>
    <?php if ($editable && !empty($item['tmdb_id'])): ?>
        <div class="actions wrap-actions">
            <form method="post" action="<?= e(app_href('api/refresh-metadata.php')) ?>">
                <input type="hidden" name="csrf_token" value="<?= e(app_csrf_token()) ?>">
                <input type="hidden" name="uid" value="<?= e($uid) ?>">
                <?php if (app_is_admin($user)): ?><input type="hidden" name="target_user" value="<?= e($targetUsername) ?>"><?php endif; ?>
                <input type="hidden" name="redirect" value="<?= e($_SERVER['REQUEST_URI'] ?? $baseItemQuery) ?>">
                <button class="secondary" type="submit"><?= ($item['type'] ?? '') === 'tv' ? 'Check for new episodes' : 'Refresh TMDB metadata' ?></button>
            </form>
            <a class="button secondary" href="<?= e(app_href($artworkQuery)) ?>">Choose poster/backdrop</a>
            <form method="post" action="<?= e(app_href('api/refresh-artwork.php')) ?>">
                <input type="hidden" name="csrf_token" value="<?= e(app_csrf_token()) ?>">
                <input type="hidden" name="uid" value="<?= e($uid) ?>">
                <?php if (app_is_admin($user)): ?><input type="hidden" name="target_user" value="<?= e($targetUsername) ?>"><?php endif; ?>
                <input type="hidden" name="scope" value="item">
                <input type="hidden" name="redirect" value="<?= e($_SERVER['REQUEST_URI'] ?? $baseItemQuery) ?>">
                <button class="secondary" type="submit">Cache local artwork</button>
            </form>
            <form method="post" action="<?= e(app_href('api/refresh-artwork.php')) ?>">
                <input type="hidden" name="csrf_token" value="<?= e(app_csrf_token()) ?>">
                <input type="hidden" name="uid" value="<?= e($uid) ?>">
                <?php if (app_is_admin($user)): ?><input type="hidden" name="target_user" value="<?= e($targetUsername) ?>"><?php endif; ?>
                <input type="hidden" name="scope" value="item">
                <input type="hidden" name="force" value="1">
                <input type="hidden" name="redirect" value="<?= e($_SERVER['REQUEST_URI'] ?? $baseItemQuery) ?>">
                <button class="secondary" type="submit">Force refresh artwork</button>
            </form>
        </div>
    <?php endif; ?>
</section>
<?php if (($item['type'] ?? '') === 'tv' && ($nextUpSummary['label'] ?? '') !== ''): ?>
<section class="card next-up-card <?= e((string)($nextUpSummary['css'] ?? '')) ?>">
    <h2><?= e((string)$nextUpSummary['label']) ?></h2>
    <?php if (($nextUpSummary['detail'] ?? '') !== ''): ?><p><?= e((string)$nextUpSummary['detail']) ?></p><?php endif; ?>
    <p class="muted">This is calculated from your watched episode records and the currently saved TMDB season/episode metadata.</p>
</section>
<?php endif; ?>
<div class="media-list"><?php app_render_media_card($item, $editable, app_is_admin($user) ? $targetUsername : ''); ?></div>
<?php if (($item['type'] ?? '') === 'tv'): ?>
<section class="card">
    <h2>Episode grid</h2>
    <?php if (!empty($item['tmdb_id']) && app_tmdb_configured()): ?>
        <p class="muted">Using TMDB season metadata when available. Cached season data refreshes weekly on view; use Check for new episodes to force a refresh immediately.</p>
    <?php else: ?>
        <p class="muted">No TMDB episode metadata available. Link this show to TMDB or set total seasons/episodes manually.</p>
    <?php endif; ?>
    <div class="episode-view-toolbar">
        <span class="muted">Episode display:</span>
        <a class="chip <?= $episodeView === 'image' ? 'active' : '' ?>" href="<?= e(app_href($baseItemQuery . '&episode_view=image#episodes')) ?>">Picture cards</a>
        <a class="chip <?= $episodeView === 'text' ? 'active' : '' ?>" href="<?= e(app_href($baseItemQuery . '&episode_view=text#episodes')) ?>">Text-only</a>
    </div>
    <p class="muted">Text-only mode is more compact and avoids episode stills that may reveal spoilers.</p>
    <p class="muted"><span class="pill success">Green = watched</span> <span class="pill">Gray = unwatched</span></p>
    <p class="muted">Selecting an unwatched later episode now marks all earlier episodes and prior seasons as watched. Selecting a watched episode clears only that episode.</p>
    <div id="episodes"></div>
    <?php foreach (array_slice($seasonSummaries, 0, 30) as $summary): ?>
        <?php
            $seasonNumber = (int)($summary['season_number'] ?? 0);
            $seasonName = (string)($summary['name'] ?? ('Season ' . $seasonNumber));
            $seasonDetails = null;
            $episodes = [];
            if (!empty($item['tmdb_id']) && app_tmdb_configured()) {
                try { $seasonDetails = app_tmdb_season_details((int)$item['tmdb_id'], $seasonNumber); }
                catch (Throwable $ex) { $seasonDetails = null; }
            }
            if (is_array($seasonDetails) && !empty($seasonDetails['episodes']) && is_array($seasonDetails['episodes'])) {
                $episodes = $seasonDetails['episodes'];
                $seasonName = (string)($seasonDetails['name'] ?? $seasonName);
            } else {
                $episodeCount = max(1, (int)($summary['episode_count'] ?? 1));
                for ($episode = 1; $episode <= min($episodeCount, 60); $episode++) {
                    $episodes[] = ['season_number' => $seasonNumber, 'episode_number' => $episode, 'name' => 'Episode ' . $episode, 'air_date' => '', 'overview' => ''];
                }
            }
        ?>
        <details class="season-block" id="season-<?= e((string)$seasonNumber) ?>" <?= $seasonNumber === $requestedSeason ? 'open' : '' ?>>
            <summary><?= e($seasonName) ?> <span class="muted">(<?= e((string)count($episodes)) ?> episodes)</span></summary>
            <div class="season-actions">
                <form method="post" action="<?= e(app_href('progress.php')) ?>">
                    <input type="hidden" name="csrf_token" value="<?= e(app_csrf_token()) ?>">
                    <input type="hidden" name="uid" value="<?= e($uid) ?>">
                    <?php if (app_is_admin($user)): ?><input type="hidden" name="tu" value="<?= e($targetUsername) ?>"><?php endif; ?>
                    <input type="hidden" name="s" value="<?= e((string)$seasonNumber) ?>">
                    <input type="hidden" name="op" value="sw">
                    <input type="hidden" name="v" value="<?= e($episodeView) ?>">
                    <button class="secondary" type="submit">Mark season watched</button>
                </form>
                <form method="post" action="<?= e(app_href('progress.php')) ?>">
                    <input type="hidden" name="csrf_token" value="<?= e(app_csrf_token()) ?>">
                    <input type="hidden" name="uid" value="<?= e($uid) ?>">
                    <?php if (app_is_admin($user)): ?><input type="hidden" name="tu" value="<?= e($targetUsername) ?>"><?php endif; ?>
                    <input type="hidden" name="s" value="<?= e((string)$seasonNumber) ?>">
                    <input type="hidden" name="op" value="sc">
                    <input type="hidden" name="v" value="<?= e($episodeView) ?>">
                    <button class="secondary" type="submit">Clear season watched</button>
                </form>
            </div>
            <div class="episode-grid rich <?= $episodeView === 'text' ? 'text-episode-grid' : 'image-episode-grid' ?>">
                <?php foreach ($episodes as $episodeData): ?>
                    <?php
                        $episodeNumber = (int)($episodeData['episode_number'] ?? 0);
                        if ($episodeNumber <= 0) { continue; }
                        $key = $seasonNumber . '-' . $episodeNumber;
                        $isWatched = !empty($watched[$key]);
                        $episodeTitle = (string)($episodeData['name'] ?? ('Episode ' . $episodeNumber));
                        $airDate = (string)($episodeData['air_date'] ?? '');
                        $episodeArt = $episodeView === 'image' ? app_episode_art_url($episodeData, is_array($seasonDetails) ? $seasonDetails : $summary, $item) : '';
                    ?>
                    <form method="post" action="<?= e(app_href('progress.php')) ?>" class="episode-card-form">
                        <input type="hidden" name="csrf_token" value="<?= e(app_csrf_token()) ?>">
                        <input type="hidden" name="uid" value="<?= e($uid) ?>">
                        <?php if (app_is_admin($user)): ?><input type="hidden" name="tu" value="<?= e($targetUsername) ?>"><?php endif; ?>
                        <input type="hidden" name="s" value="<?= e((string)$seasonNumber) ?>">
                        <input type="hidden" name="e" value="<?= e((string)$episodeNumber) ?>">
                        <input type="hidden" name="op" value="et">
                        <input type="hidden" name="v" value="<?= e($episodeView) ?>">
                        <button class="episode-button <?= $isWatched ? 'watched' : '' ?> <?= $episodeView === 'text' ? 'episode-text-only' : '' ?>" type="submit" title="<?= e($episodeTitle) ?>">
                            <?php if ($episodeView === 'image'): ?><img class="episode-still" src="<?= e($episodeArt) ?>" alt="Image for <?= e($episodeTitle) ?>" loading="lazy"><?php endif; ?>
                            <span>S<?= e((string)$seasonNumber) ?>E<?= e((string)$episodeNumber) ?></span>
                            <small><?= e(app_excerpt($episodeTitle, $episodeView === 'text' ? 58 : 34)) ?></small>
                            <?php if ($airDate !== ''): ?><small><?= e($airDate) ?></small><?php endif; ?>
                            <small><?= $isWatched ? '✓ Watched' : '○ Unwatched' ?></small>
                        </button>
                    </form>
                <?php endforeach; ?>
            </div>
        </details>
    <?php endforeach; ?>
</section>
<?php endif; app_page_footer(); ?>
