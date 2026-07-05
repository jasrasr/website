<?php
/**
 * File: recommendations.php
 * Project: TV Binge Board
 * Description: Friend and public-list recommendation engine based on visible libraries, overlap, ratings, and completion signals.
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

function app_reco_key(array $item): string
{
    $type = (string)($item['type'] ?? 'movie');
    $tmdbId = (int)($item['tmdb_id'] ?? 0);
    if ($tmdbId > 0) { return $type . ':tmdb:' . $tmdbId; }
    return $type . ':title:' . strtolower(trim((string)($item['title'] ?? '')));
}
function app_reco_map(array $items): array
{
    $map = [];
    foreach ($items as $item) {
        if (!is_array($item)) { continue; }
        $title = trim((string)($item['title'] ?? ''));
        if ($title === '') { continue; }
        $map[app_reco_key($item)] = $item;
    }
    return $map;
}
function app_reco_visible_users(array $viewer): array
{
    $out = [];
    foreach (app_get_accounts()['users'] as $account) {
        if (!is_array($account)) { continue; }
        $candidate = app_sanitize_username((string)($account['username'] ?? ''));
        if ($candidate === '' || $candidate === ($viewer['username'] ?? '') || !empty($account['disabled']) || ($account['role'] ?? '') === 'admin') { continue; }
        if (app_can_view_library($viewer, $candidate)) { $out[$candidate] = $account; }
    }
    return $out;
}
function app_reco_status_score(string $status): int
{
    return match ($status) {
        'completed', 'watched' => 34,
        'watching' => 18,
        'watchlist' => 8,
        default => 0,
    };
}
function app_reco_title_score(array $item, int $support, int $score): string
{
    $rating = (int)($item['rating'] ?? 0);
    $parts = [];
    if ($support > 1) { $parts[] = $support . ' people'; }
    if ($rating > 0) { $parts[] = $rating . '/10'; }
    $parts[] = 'score ' . $score;
    return implode(' · ', $parts);
}

$mine = app_reco_map(app_library($username)['items'] ?? []);
$visibleUsers = app_reco_visible_users($user);
$recommendations = [];
foreach ($visibleUsers as $otherUsername => $account) {
    $otherProfile = app_profile($otherUsername);
    $otherItems = app_reco_map(app_library($otherUsername)['items'] ?? []);
    $overlap = count(array_intersect(array_keys($mine), array_keys($otherItems)));
    $similarityBonus = min(20, $overlap * 2);
    foreach ($otherItems as $key => $item) {
        if (isset($mine[$key])) { continue; }
        $title = trim((string)($item['title'] ?? ''));
        if ($title === '') { continue; }
        $status = (string)($item['status'] ?? 'watchlist');
        $rating = (int)($item['rating'] ?? 0);
        $score = app_reco_status_score($status) + ($rating * 5) + $similarityBonus;
        if ($score < 18) { continue; }
        if (!isset($recommendations[$key])) {
            $recommendations[$key] = ['item' => $item, 'score' => 0, 'support' => 0, 'people' => [], 'reasons' => []];
        }
        $recommendations[$key]['score'] += $score;
        $recommendations[$key]['support']++;
        $display = trim((string)($otherProfile['display_name'] ?? $account['display_name'] ?? $otherUsername));
        $recommendations[$key]['people'][$otherUsername] = $display !== '' ? $display : $otherUsername;
        $reason = [];
        $reason[] = app_statuses()[$status] ?? $status;
        if ($rating > 0) { $reason[] = $rating . '/10'; }
        if ($overlap > 0) { $reason[] = $overlap . ' shared'; }
        $recommendations[$key]['reasons'][$otherUsername] = implode(' · ', $reason);
    }
}

usort($recommendations, static function ($a, $b) {
    $score = ((int)$b['score']) <=> ((int)$a['score']);
    if ($score !== 0) { return $score; }
    return strcmp(strtolower((string)($a['item']['title'] ?? '')), strtolower((string)($b['item']['title'] ?? '')));
});

app_page_header('Recommendations');
?>
<section class="card"><h1>Recommendations</h1><p>Suggestions are built from visible connected and public lists. Higher scores come from completed/watched items, high ratings, repeated support, and people with more overlap with your list.</p><div class="actions"><a class="button secondary" href="connections.php">People</a><a class="button secondary" href="compare.php?u=<?= e((string)array_key_first($visibleUsers)) ?>">Compare lists</a></div></section>
<?php if (!$visibleUsers): ?>
<section class="card"><h2>No visible people yet</h2><p>Connect with another user or view a public list to generate recommendations.</p></section>
<?php elseif (!$recommendations): ?>
<section class="card"><h2>No recommendations yet</h2><p>Visible users do not have enough completed, watched, rated, or overlapping titles outside your list yet.</p></section>
<?php else: ?>
<section class="card"><h2>Best next picks</h2><div class="media-list">
<?php foreach (array_slice($recommendations, 0, 30) as $row): $item = $row['item']; ?>
<article class="media-card">
    <?php $poster = app_media_poster_url($item); ?><img class="poster" src="<?= e($poster !== '' ? $poster : app_href(APP_DEFAULT_POSTER)) ?>" alt="Poster for <?= e((string)($item['title'] ?? 'Untitled')) ?>" loading="lazy">
    <div class="media-body"><div class="media-title-row"><h3><?= e((string)($item['title'] ?? 'Untitled')) ?></h3><span class="pill"><?= e(strtoupper((string)($item['type'] ?? 'movie'))) ?></span></div><p class="muted"><?= e(app_reco_title_score($item, (int)$row['support'], (int)$row['score'])) ?></p><p class="muted">From: <?= e(implode(', ', array_values($row['people']))) ?></p><ul class="compact-list"><?php foreach ($row['reasons'] as $person => $reason): ?><li><?= e((string)($row['people'][$person] ?? $person)) ?>: <?= e((string)$reason) ?></li><?php endforeach; ?></ul><form method="post" action="api/add-media.php"><input type="hidden" name="csrf_token" value="<?= e(app_csrf_token()) ?>"><input type="hidden" name="redirect" value="../recommendations.php"><input type="hidden" name="tmdb_id" value="<?= e((string)($item['tmdb_id'] ?? '')) ?>"><input type="hidden" name="type" value="<?= e((string)($item['type'] ?? 'movie')) ?>"><input type="hidden" name="title" value="<?= e((string)($item['title'] ?? '')) ?>"><input type="hidden" name="year" value="<?= e((string)($item['year'] ?? '')) ?>"><input type="hidden" name="poster_path" value="<?= e((string)($item['poster_path'] ?? '')) ?>"><input type="hidden" name="poster_url" value="<?= e((string)($item['poster_url'] ?? '')) ?>"><input type="hidden" name="overview" value="<?= e((string)($item['overview'] ?? '')) ?>"><input type="hidden" name="status" value="watchlist"><button type="submit">Add to my watchlist</button></form></div>
</article>
<?php endforeach; ?>
</div></section>
<?php endif; app_page_footer(); ?>
