<?php
/**
 * File: smart-import.php
 * Project: TV Binge Board
 * Description: Paste-based structured parser with fuzzy existing-library and TMDB matching for quick watch-list imports.
 * Author: Jason Lamb / ChatGPT
 * Created: 2026-07-05
 * Modified: 2026-07-05
 * Revision: 1.0.0
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/tmdb.php';
$user = app_require_login();
if (!app_can_track($user)) { http_response_code(403); exit('This account cannot track media.'); }

function app_smart_lines(string $text): array
{
    $lines = preg_split('/\R+/', trim($text)) ?: [];
    $out = [];
    foreach ($lines as $line) {
        $line = trim(preg_replace('/\s+/', ' ', $line) ?? '');
        $line = trim($line, " \t\n\r\0\x0B-•*#");
        if ($line !== '') { $out[] = $line; }
    }
    return array_values(array_unique($out));
}

function app_smart_parse_line(string $line): array
{
    $original = $line;
    $status = 'watchlist';
    if (preg_match('/\b(watching|watching now|in progress)\b/i', $line)) { $status = 'watching'; }
    if (preg_match('/\b(done|completed|watched|finished)\b/i', $line)) { $status = 'completed'; }
    if (preg_match('/\b(dropped|quit|abandoned)\b/i', $line)) { $status = 'dropped'; }

    $type = preg_match('/\b(tv|show|series|season|s\d+)\b/i', $line) ? 'tv' : 'movie';
    $season = null;
    $episode = null;
    if (preg_match('/\bS(\d{1,3})\s*E(\d{1,3})\b/i', $line, $m)) {
        $type = 'tv';
        $season = (int)$m[1];
        $episode = (int)$m[2];
    } elseif (preg_match('/\bseason\s*(\d{1,3})(?:\s*(?:episode|ep)\s*(\d{1,3}))?/i', $line, $m)) {
        $type = 'tv';
        $season = (int)$m[1];
        $episode = isset($m[2]) && $m[2] !== '' ? (int)$m[2] : null;
    }

    $year = '';
    if (preg_match('/\b(19\d{2}|20\d{2})\b/', $line, $m)) { $year = $m[1]; }

    $title = $line;
    $title = preg_replace('/\bS\d{1,3}\s*E\d{1,3}\b/i', '', $title) ?? $title;
    $title = preg_replace('/\bseason\s*\d{1,3}(?:\s*(?:episode|ep)\s*\d{1,3})?/i', '', $title) ?? $title;
    $title = preg_replace('/\b(watching now|in progress|watching|done|completed|watched|finished|dropped|quit|abandoned|movie|tv|show|series)\b/i', '', $title) ?? $title;
    $title = preg_replace('/\b(19\d{2}|20\d{2})\b/', '', $title) ?? $title;
    $title = trim(preg_replace('/\s*[-–—:|]\s*$/', '', $title) ?? $title);
    $title = trim(preg_replace('/\s+/', ' ', $title) ?? $title, " \t\n\r\0\x0B-–—:|()");
    if ($title === '') { $title = $original; }

    return ['raw' => $original, 'title' => $title, 'type' => $type, 'year' => $year, 'status' => $status, 'season' => $season, 'episode' => $episode];
}

function app_smart_existing_match(array $candidate, array $items): ?array
{
    $needle = strtolower((string)$candidate['title']);
    $best = null;
    $bestScore = 0;
    foreach ($items as $item) {
        if (!is_array($item)) { continue; }
        similar_text($needle, strtolower((string)($item['title'] ?? '')), $pct);
        if ((string)($item['type'] ?? '') === (string)$candidate['type']) { $pct += 8; }
        if ($pct > $bestScore) { $bestScore = $pct; $best = $item; }
    }
    return $bestScore >= 82 ? ['item' => $best, 'score' => min(100, (int)round($bestScore))] : null;
}

function app_smart_tmdb_matches(array $candidate): array
{
    if (!app_tmdb_configured()) { return []; }
    try {
        $results = app_tmdb_search(trim((string)$candidate['title'] . ' ' . (string)$candidate['year']));
    } catch (Throwable $ex) {
        return [];
    }
    $filtered = [];
    foreach ($results as $result) {
        if (!is_array($result)) { continue; }
        if (($result['type'] ?? '') !== $candidate['type']) { continue; }
        $filtered[] = $result;
        if (count($filtered) >= 3) { break; }
    }
    return $filtered;
}

$rawText = (string)($_POST['bulk_text'] ?? '');
$library = app_library((string)$user['username']);
$candidates = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    app_verify_csrf();
    foreach (app_smart_lines($rawText) as $line) {
        $candidate = app_smart_parse_line($line);
        $candidate['existing'] = app_smart_existing_match($candidate, $library['items'] ?? []);
        $candidate['tmdb'] = app_smart_tmdb_matches($candidate);
        $candidates[] = $candidate;
    }
}

app_page_header('Smart Import');
?>
<section class="card">
    <h1>Smart import</h1>
    <p>Paste a rough list from a text, note, spreadsheet, or another tracking app. The parser extracts title, type, status, year, and TV progress when it can, then checks for existing and TMDB matches.</p>
    <form method="post" class="stack">
        <input type="hidden" name="csrf_token" value="<?= e(app_csrf_token()) ?>">
        <label>Paste list text
            <textarea name="bulk_text" rows="8" placeholder="The Bear S2E4 watching&#10;Inception 2010 watched&#10;Silo season 2 episode 1"><?= e($rawText) ?></textarea>
        </label>
        <button type="submit">Parse and match</button>
    </form>
</section>
<?php if ($candidates): ?>
<section class="card"><h2>Parsed results</h2><div class="user-list">
<?php foreach ($candidates as $candidate): ?>
<article class="user-card stacked-card">
    <div><strong><?= e((string)$candidate['title']) ?></strong><p class="muted"><?= e(strtoupper((string)$candidate['type'])) ?> · <?= e(app_statuses()[(string)$candidate['status']] ?? 'Watchlist') ?><?= $candidate['year'] !== '' ? ' · ' . e((string)$candidate['year']) : '' ?><?= $candidate['season'] ? ' · S' . e((string)$candidate['season']) . ($candidate['episode'] ? 'E' . e((string)$candidate['episode']) : '') : '' ?></p><p class="muted">Source: <?= e((string)$candidate['raw']) ?></p></div>
    <?php if ($candidate['existing']): ?><p class="muted">Existing match: <?= e((string)($candidate['existing']['item']['title'] ?? '')) ?> · <?= e((string)$candidate['existing']['score']) ?>%</p><?php endif; ?>
    <div class="actions small">
        <form method="post" action="api/add-media.php"><input type="hidden" name="csrf_token" value="<?= e(app_csrf_token()) ?>"><input type="hidden" name="redirect" value="../smart-import.php"><input type="hidden" name="title" value="<?= e((string)$candidate['title']) ?>"><input type="hidden" name="type" value="<?= e((string)$candidate['type']) ?>"><input type="hidden" name="year" value="<?= e((string)$candidate['year']) ?>"><input type="hidden" name="status" value="<?= e((string)$candidate['status']) ?>"><button class="secondary" type="submit">Add basic</button></form>
        <?php foreach ($candidate['tmdb'] as $match): ?>
        <form method="post" action="api/add-media.php"><input type="hidden" name="csrf_token" value="<?= e(app_csrf_token()) ?>"><input type="hidden" name="redirect" value="../smart-import.php"><input type="hidden" name="tmdb_id" value="<?= e((string)($match['tmdb_id'] ?? '')) ?>"><input type="hidden" name="type" value="<?= e((string)($match['type'] ?? $candidate['type'])) ?>"><input type="hidden" name="title" value="<?= e((string)($match['title'] ?? $candidate['title'])) ?>"><input type="hidden" name="year" value="<?= e((string)($match['year'] ?? $candidate['year'])) ?>"><input type="hidden" name="poster_path" value="<?= e((string)($match['poster_path'] ?? '')) ?>"><input type="hidden" name="overview" value="<?= e((string)($match['overview'] ?? '')) ?>"><input type="hidden" name="status" value="<?= e((string)$candidate['status']) ?>"><button type="submit">Add <?= e((string)($match['title'] ?? 'TMDB match')) ?></button></form>
        <?php endforeach; ?>
    </div>
</article>
<?php endforeach; ?>
</div></section>
<?php endif; app_page_footer(); ?>
