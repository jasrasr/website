<?php
/**
 * File: search.php
 * Project: TV Binge Board
 * Description: Mobile-first add/import hub with TMDB lookup, manual add, CSV/JSON import upload, and screenshot upload entry points.
 * Author: Jason Lamb / ChatGPT
 * Created: 2026-07-02
 * Modified: 2026-07-03
 * Revision: 1.5.0
 */
declare(strict_types=1);


require_once __DIR__ . '/includes/functions.php';
$user = app_require_login();
app_page_header('Search');
?>
<?php if (!app_can_track($user)): ?>
<section class="card">
    <h1>Search disabled for admin</h1>
    <p>The admin account manages other users. It does not track its own shows or movies.</p>
</section>
<?php else: ?>
<section class="hero-card">
    <h1>Add, search, import, or upload</h1>
    <p>Use this page as the main intake hub for new shows and movies. Search TMDB, add manually, upload a CSV/JSON import, or upload screenshots from another tracking app.</p>
    <div class="chip-row">
        <a class="chip" href="#tmdb-search">TMDB search</a>
        <a class="chip" href="#manual-add">Manual add</a>
        <a class="chip" href="#file-import">CSV/JSON import</a>
        <a class="chip" href="#screenshot-upload">Screenshot upload</a>
    </div>
</section>
<section class="card" id="tmdb-search">
    <h2>Search TMDB</h2>
    <p class="muted">Best for adding one known show or movie with poster, overview, season data, and TMDB metadata.</p>
    <form id="searchForm" class="stack">
        <label>Search title
            <input id="searchQuery" name="q" placeholder="Movie or TV title" autocomplete="off" required>
        </label>
        <button type="submit">Search TMDB</button>
    </form>
    <p id="searchStatus" class="muted" aria-live="polite"></p>
    <p class="muted">TMDB search/add uses the server-side API credential. Your API key/token is never exposed to browser JavaScript.</p>
</section>
<section id="searchResults" class="media-list" aria-live="polite"></section>
<section class="card" id="manual-add">
    <h2>Manual add</h2>
    <p class="muted">Use this when TMDB search cannot find the title or when you want to add a quick placeholder first.</p>
    <form method="post" action="api/add-media.php" class="stack">
        <input type="hidden" name="csrf_token" value="<?= e(app_csrf_token()) ?>">
        <input type="hidden" name="redirect" value="../watchlist.php">
        <label>Type
            <select name="type"><option value="tv">TV show</option><option value="movie">Movie</option></select>
        </label>
        <label>Title <input name="title" required></label>
        <label>Year <input name="year" inputmode="numeric" pattern="[0-9]{4}"></label>
        <label>Status
            <select name="status"><?php foreach (app_statuses() as $status => $label): ?><option value="<?= e($status) ?>"><?= e($label) ?></option><?php endforeach; ?></select>
        </label>
        <div class="grid-2"><label>Total seasons <input name="total_seasons" type="number" min="0"></label><label>Total episodes <input name="total_episodes" type="number" min="0"></label></div>
        <label>TMDB ID <input name="tmdb_id" inputmode="numeric" placeholder="Optional: link directly to TMDB ID"></label>
        <label>Overview <textarea name="overview" rows="3"></textarea></label>
        <button type="submit">Add manually</button>
    </form>
</section>
<section class="card" id="file-import">
    <h2>Import CSV or JSON</h2>
    <p class="muted">Use this to bring in exported data from this app or another tracker. CSV files go to column mapping first; JSON files go straight to import review.</p>
    <div class="actions wrap-actions"><a class="button secondary" href="<?= e(app_href('import.php?sample=1')) ?>">Download sample CSV</a><a class="button secondary" href="<?= e(app_href('import.php')) ?>">Open full import page</a></div>
    <form method="post" action="<?= e(app_href('import.php')) ?>" enctype="multipart/form-data" class="stack">
        <input type="hidden" name="csrf_token" value="<?= e(app_csrf_token()) ?>">
        <input type="hidden" name="action" value="upload">
        <label>CSV or JSON file <input type="file" name="import_file" accept=".csv,.json,text/csv,application/json" required></label>
        <button type="submit">Upload import file</button>
    </form>
</section>
<section class="card" id="screenshot-upload">
    <h2>Upload screenshots from another app</h2>
    <p class="muted">Use this for screenshots from TV Time or another watch tracker. The upload is staged for review; nothing is added to your library until import review is confirmed.</p>
    <div class="actions wrap-actions"><a class="button secondary" href="<?= e(app_href('upload-screenshot.php')) ?>">Open screenshot queue</a></div>
    <form method="post" action="<?= e(app_href('upload-screenshot.php')) ?>" enctype="multipart/form-data" class="stack">
        <input type="hidden" name="csrf_token" value="<?= e(app_csrf_token()) ?>">
        <input type="hidden" name="action" value="upload">
        <label>Screenshot image <input type="file" name="screenshot" accept="image/png,image/jpeg,image/webp" required></label>
        <button type="submit">Upload screenshot</button>
    </form>
    <p class="muted">Server-side image AI extraction is still the next required improvement. Until that is wired to a configured vision provider, uploaded screenshots are stored and reviewed through the existing screenshot queue.</p>
</section>
<?php endif; app_page_footer(); ?>
