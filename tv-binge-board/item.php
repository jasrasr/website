<?php
/**
 * File: item.php
 * Project: TV Binge Board
 * Description: Media detail page with editable metadata, TMDB links, local artwork refresh controls, completion percentage, and TMDB-backed TV episode grid.
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
$editable = app_is_admin($user) || $targetUsername === $user['username'];
$watched = app_watched_episode_keys($item);
$percent = app_episode_percent($item);
$tmdbUrl = app_tmdb_public_url_for_item($item);

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
    <?php if (!empty($item['local_poster_path'])): ?><p class="muted">Local poster cached: <?= e((string)($item['poster_cached_at'] ?? 'cached')) ?></p><?php endif; ?>
    <?php if ($editable && !empty($item['tmdb_id'])): ?>
        <div class="actions wrap-actions">
            <form method="post" action="<?= e(app_href('api/refresh-artwork.php')) ?>">
                <input type="hidden" name="csrf_token" value="<?= e(app_csrf_token()) ?>">
                <input type="hidden" name="uid" value="<?= e($uid) ?>">
                <?php if (app_is_admin($user)): ?><input type="hidden" name="target_user" value="<?= e($targetUsername) ?>"><?php endif; ?>
                <input type="hidden" name="scope" value="item">
                <input type="hidden" name="redirect" value="<?= e($_SERVER['REQUEST_URI'] ?? 'item.php?uid=' . $uid) ?>">
                <button class="secondary" type="submit">Cache local artwork</button>
            </form>
            <form method="post" action="<?= e(app_href('api/refresh-artwork.php')) ?>">
                <input type="hidden" name="csrf_token" value="<?= e(app_csrf_token()) ?>">
                <input type="hidden" name="uid" value="<?= e($uid) ?>">
                <?php if (app_is_admin($user)): ?><input type="hidden" name="target_user" value="<?= e($targetUsername) ?>"><?php endif; ?>
                <input type="hidden" name="scope" value="item">
                <input type="hidden" name="force" value="1">
                <input type="hidden" name="redirect" value="<?= e($_SERVER['REQUEST_URI'] ?? 'item.php?uid=' . $uid) ?>">
                <button class="secondary" type="submit">Force refresh artwork</button>
            </form>
        </div>
    <?php endif; ?>
</section>
<div class="media-list"><?php app_render_media_card($item, $editable, app_is_admin($user) ? $targetUsername : ''); ?></div>
<?php if (($item['type'] ?? '') === 'tv'): ?>
<section class="card">
    <h2>Episode grid</h2>
    <?php if (!empty($item['tmdb_id']) && app_tmdb_configured()): ?>
        <p class="muted">Using TMDB season metadata when available. Cached season data is refreshed weekly.</p>
    <?php else: ?>
        <p class="muted">No TMDB episode metadata available. Link this show to TMDB or set total seasons/episodes manually.</p>
    <?php endif; ?>
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
        <details class="season-block" <?= $seasonNumber === 1 ? 'open' : '' ?>>
            <summary><?= e($seasonName) ?> <span class="muted">(<?= e((string)count($episodes)) ?> episodes)</span></summary>
            <div class="episode-grid rich">
                <?php foreach ($episodes as $episodeData): ?>
                    <?php
                        $episodeNumber = (int)($episodeData['episode_number'] ?? 0);
                        if ($episodeNumber <= 0) { continue; }
                        $key = $seasonNumber . '-' . $episodeNumber;
                        $isWatched = !empty($watched[$key]);
                        $episodeTitle = (string)($episodeData['name'] ?? ('Episode ' . $episodeNumber));
                        $airDate = (string)($episodeData['air_date'] ?? '');
                        $episodeArt = app_episode_art_url($episodeData, is_array($seasonDetails) ? $seasonDetails : $summary, $item);
                    ?>
                    <form method="post" action="<?= e(app_href('api/toggle-episode.php')) ?>" class="episode-card-form">
                        <input type="hidden" name="csrf_token" value="<?= e(app_csrf_token()) ?>">
                        <input type="hidden" name="uid" value="<?= e($uid) ?>">
                        <?php if (app_is_admin($user)): ?><input type="hidden" name="target_user" value="<?= e($targetUsername) ?>"><?php endif; ?>
                        <input type="hidden" name="season" value="<?= e((string)$seasonNumber) ?>">
                        <input type="hidden" name="episode" value="<?= e((string)$episodeNumber) ?>">
                        <input type="hidden" name="episode_title" value="<?= e($episodeTitle) ?>">
                        <input type="hidden" name="air_date" value="<?= e($airDate) ?>">
                        <input type="hidden" name="still_path" value="<?= e((string)($episodeData['still_path'] ?? '')) ?>">
                        <input type="hidden" name="local_still_path" value="<?= e((string)($episodeData['local_still_path'] ?? '')) ?>">
                        <input type="hidden" name="redirect" value="<?= e($_SERVER['REQUEST_URI'] ?? 'item.php?uid=' . $uid) ?>">
                        <button class="episode-button <?= $isWatched ? 'watched' : '' ?>" type="submit" title="<?= e($episodeTitle) ?>">
                            <img class="episode-still" src="<?= e($episodeArt) ?>" alt="Image for <?= e($episodeTitle) ?>" loading="lazy">
                            <span>S<?= e((string)$seasonNumber) ?>E<?= e((string)$episodeNumber) ?></span>
                            <small><?= e(app_excerpt($episodeTitle, 34)) ?></small>
                            <?php if ($airDate !== ''): ?><small><?= e($airDate) ?></small><?php endif; ?>
                        </button>
                    </form>
                <?php endforeach; ?>
            </div>
        </details>
    <?php endforeach; ?>
</section>
<?php endif; app_page_footer(); ?>
