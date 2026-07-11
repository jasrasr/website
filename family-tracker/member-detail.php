<?php
/**
 * Project: Family GPS Tracker
 * File: member-detail.php
 * Revision: 1.4.5
 * Description: Signed-in member detail page with last-known location, external map links, and trail preview.
 * Author: Jason Lamb / ChatGPT scaffold
 * Created: 2026-07-09
 * Modified: 2026-07-09
 */

declare(strict_types=1);
require_once __DIR__ . '/includes/security.php';

init_app_storage();
require_user();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title><?= htmlspecialchars(APP_NAME, ENT_QUOTES, 'UTF-8') ?> Member Detail</title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?= urlencode(APP_REVISION) ?>">
</head>
<body>
<div class="app-shell" data-app-revision="<?= htmlspecialchars(APP_REVISION, ENT_QUOTES, 'UTF-8') ?>">
    <header class="hero">
        <div>
            <p class="eyebrow">Rev <?= htmlspecialchars(APP_REVISION, ENT_QUOTES, 'UTF-8') ?> • Member Detail</p>
            <h1>Member Detail</h1>
            <p class="hero-copy"><a class="secondary-link" href="index.php">Back to tracker</a> · <a class="secondary-link" href="history.php">History map</a></p>
        </div>
    </header>

    <section id="statusCard" class="card status-card" aria-live="polite"><strong>Status:</strong> <span id="statusText">Loading member detail…</span></section>

    <section class="card">
        <div class="section-header"><div><p class="eyebrow">Selection</p><h2 id="memberDetailTitle">Member</h2></div><label class="compact-label">Member<select id="memberDetailSelect"></select></label></div>
        <div id="memberDetailSummary" class="detail-grid">Loading…</div>
    </section>

    <section class="card map-card">
        <div class="section-header"><div><h2>Last Known Map</h2><p class="muted">Static OpenStreetMap preview centered on the selected member.</p></div></div>
        <div id="memberDetailMap" class="detail-map-box"></div>
    </section>

    <section class="card">
        <div class="section-header"><div><p class="eyebrow">Trail</p><h2>Recent Trail Points</h2></div><select id="memberDetailWindow"><option value="60">1 hour</option><option value="240" selected>4 hours</option><option value="720">12 hours</option><option value="1440">24 hours</option></select></div>
        <div id="memberTrailSummary" class="member-list">Loading trail…</div>
    </section>
</div>
<script src="assets/js/member-detail.js?v=<?= urlencode(APP_REVISION) ?>"></script>
</body>
</html>
