<?php
/**
 * File: search.php
 * Project: TV Binge Board
 * Description: Mobile-first media search page with TMDB lookup and manual add fallback.
 * Author: Jason Lamb / ChatGPT
 * Created: 2026-07-02
 * Modified: 2026-07-02
 * Revision: 1.4.3
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
<section class="card">
    <h1>Add a show or movie</h1>
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
<section class="card">
    <h2>Manual add</h2>
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
<?php endif; app_page_footer(); ?>