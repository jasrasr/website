<?php
/**
 * File: install.php
 * Project: TV Binge Board
 * Description: PWA install and Add to Home Screen help page for iPhone, Android, and desktop users.
 * Author: Jason Lamb / ChatGPT
 * Created: 2026-07-03
 * Modified: 2026-07-03
 * Revision: 1.0.0
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';
app_page_header('Install App');
?>
<section class="hero-card install-card">
    <h1>Install TV Binge Board</h1>
    <p>Add this site to your phone Home Screen so it opens like a lightweight app.</p>
    <div class="actions">
        <button type="button" id="pwaInstallButton" hidden>Install app</button>
        <button type="button" class="secondary" onclick="window.location.reload()">Reload app</button>
    </div>
    <p class="muted" id="pwaInstallStatus">If an install button is not shown, use your browser's share/menu button and choose Add to Home Screen or Install app.</p>
</section>
<section class="card">
    <h2>iPhone / iPad</h2>
    <ol class="compact-list">
        <li>Open TV Binge Board in Safari.</li>
        <li>Tap the Share button.</li>
        <li>Choose Add to Home Screen.</li>
        <li>Keep Open as Web App enabled when prompted.</li>
        <li>Tap Add.</li>
    </ol>
    <p class="muted">If the Home Screen icon does not update after an icon change, delete the old Home Screen icon and add it again.</p>
</section>
<section class="card">
    <h2>Android / desktop</h2>
    <ol class="compact-list">
        <li>Open TV Binge Board in Chrome or Edge.</li>
        <li>Use Install app from the browser menu, or use the install button when available.</li>
        <li>Launch it from the Home Screen, app drawer, or desktop shortcut.</li>
    </ol>
</section>
<section class="card">
    <h2>Current PWA status</h2>
    <ul class="compact-list">
        <li>Manifest, icons, scope, start URL, and standalone display mode are configured.</li>
        <li>The app shell and offline fallback are cached by the service worker.</li>
        <li>Tracking actions still require the live PHP/JSON server.</li>
    </ul>
</section>
<?php app_page_footer(); ?>
