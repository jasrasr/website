<?php
/**
 * File: offline.php
 * Project: TV Binge Board
 * Description: PWA offline fallback page shown by the service worker when navigation fails without network access.
 * Author: Jason Lamb / ChatGPT
 * Created: 2026-07-03
 * Modified: 2026-07-03
 * Revision: 1.0.0
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';
app_page_header('Offline');
?>
<section class="card narrow offline-card">
    <h1>You are offline</h1>
    <p>TV Binge Board needs the server for sign-in, library changes, imports, TMDB lookups, and saving watched episodes.</p>
    <p class="muted">The app shell is available, but live tracking actions will work again after the connection returns.</p>
    <div class="actions">
        <a class="button" href="<?= e(app_href('dashboard.php')) ?>">Try dashboard</a>
        <button class="secondary" type="button" onclick="window.location.reload()">Retry</button>
    </div>
</section>
<?php app_page_footer(); ?>
