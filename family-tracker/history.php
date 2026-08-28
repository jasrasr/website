<?php
/**
 * Project: Family GPS Tracker
 * File: history.php
 * Revision: 1.1.0
 * Description: Shared family trail-history and individual member map view.
 * Author: Jason Lamb / ChatGPT scaffold
 * Created: 2026-07-06
 * Modified: 2026-07-06
 */

declare(strict_types=1);
require_once __DIR__ . '/includes/config.php';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#101827">
    <title>History Map - <?= htmlspecialchars(APP_NAME, ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
          integrity="sha256-p4NxAoJBhIINfQOhrxgLGPaLGKcM4Xu9yZV9hC3Q7gk=" crossorigin="">
    <link rel="stylesheet" href="assets/css/style.css?v=<?= urlencode(APP_REVISION) ?>">
</head>
<body>
<div class="app-shell">
    <header class="hero">
        <div>
            <p class="eyebrow">Rev <?= htmlspecialchars(APP_REVISION, ENT_QUOTES, 'UTF-8') ?> • Shared history</p>
            <h1>History Map</h1>
            <p class="hero-copy">View all connected family members, focus one member, and see breadcrumb trails from stored GPS updates.</p>
        </div>
    </header>

    <section class="card status-card" aria-live="polite">
        <strong>Status:</strong> <span id="statusText">Loading history map…</span>
    </section>

    <main id="trackerApp">
        <section class="card account-card">
            <div>
                <p class="eyebrow">Navigation</p>
                <h2>Family Tracker</h2>
                <p class="muted">Login on the main tracker page first if this page says authentication is required.</p>
            </div>
            <a class="button-link" href="index.php">Back to Live Map</a>
        </section>

        <section class="card history-card">
            <div class="section-header map-header">
                <div>
                    <h2>Family History</h2>
                    <p class="muted">Select all members or one member. Trail visibility is limited to logged-in members of the same family group.</p>
                </div>
                <div class="map-controls">
                    <button id="historyRefreshBtn" type="button" class="secondary">Refresh History</button>
                    <button id="historyClearFocusBtn" type="button" class="secondary">Show Everyone</button>
                    <label>Member
                        <select id="historyMemberFilter">
                            <option value="">All members</option>
                        </select>
                    </label>
                    <label>History window
                        <select id="historyRangeFilter">
                            <option value="60">Last hour</option>
                            <option value="240" selected>Last 4 hours</option>
                            <option value="720">Last 12 hours</option>
                            <option value="1440">Last 24 hours</option>
                        </select>
                    </label>
                </div>
            </div>
            <div id="historyMap" role="application" aria-label="Family trail history map"></div>
            <div id="historyMemberDetail" class="detail-grid history-detail"></div>
        </section>
    </main>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
        integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
<script src="assets/js/history.js?v=<?= urlencode(APP_REVISION) ?>"></script>
</body>
</html>
