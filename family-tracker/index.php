<?php
/**
 * Project: Family GPS Tracker
 * File: index.php
 * Revision: 1.2.0
 * Description: Mobile-first family location sharing UI.
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
    <title><?= htmlspecialchars(APP_NAME, ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
          integrity="sha256-p4NxAoJBhIINfQOhrxgLGPaLGKcM4Xu9yZV9hC3Q7gk=" crossorigin="">
    <link rel="stylesheet" href="assets/css/style.css?v=<?= urlencode(APP_REVISION) ?>">
</head>
<body>
<div class="app-shell">
    <header class="hero">
        <div>
            <p class="eyebrow">Rev <?= htmlspecialchars(APP_REVISION, ENT_QUOTES, 'UTF-8') ?> • Updated <?= htmlspecialchars(APP_UPDATED, ENT_QUOTES, 'UTF-8') ?></p>
            <h1><?= htmlspecialchars(APP_NAME, ENT_QUOTES, 'UTF-8') ?></h1>
            <p class="hero-copy">Consent-based family location sharing with PHP, JSON files, browser GPS, and an OpenStreetMap-powered live map.</p>
        </div>
    </header>

    <noscript>
        <section class="card danger">This site requires JavaScript. Browser GPS also requires HTTPS and user permission.</section>
    </noscript>

    <section id="statusCard" class="card status-card" aria-live="polite">
        <strong>Status:</strong> <span id="statusText">Loading…</span>
    </section>

    <section id="authCard" class="grid auth-grid hidden">
        <form id="loginForm" class="card form-card">
            <h2>Login</h2>
            <label>Username
                <input name="username" autocomplete="username" required>
            </label>
            <label>Password
                <input name="password" type="password" autocomplete="current-password" required>
            </label>
            <button type="submit">Login</button>
        </form>

        <form id="registerForm" class="card form-card">
            <h2>Create Family</h2>
            <label>Your display name
                <input name="displayName" autocomplete="name" required>
            </label>
            <label>Username
                <input name="username" autocomplete="username" required>
            </label>
            <label>Password
                <input name="password" type="password" autocomplete="new-password" minlength="8" required>
            </label>
            <label>Family name
                <input name="familyName" placeholder="The Lamb Family" required>
            </label>
            <label class="check-row">
                <input name="consentAccepted" type="checkbox" required>
                <span>I understand this app only tracks users who create/login to their own account and grant GPS permission.</span>
            </label>
            <button type="submit">Create Tracker</button>
        </form>

        <form id="joinForm" class="card form-card">
            <h2>Join Family</h2>
            <label>Invite code
                <input name="inviteCode" placeholder="ABCDE-12345" required>
            </label>
            <label>Your display name
                <input name="displayName" autocomplete="name" required>
            </label>
            <label>Username
                <input name="username" autocomplete="username" required>
            </label>
            <label>Password
                <input name="password" type="password" autocomplete="new-password" minlength="8" required>
            </label>
            <label class="check-row">
                <input name="consentAccepted" type="checkbox" required>
                <span>I consent to sharing my location with this family group while sharing is active.</span>
            </label>
            <button type="submit">Join Tracker</button>
        </form>
    </section>

    <main id="trackerApp" class="hidden">
        <section class="card account-card">
            <div>
                <p class="eyebrow">Signed in</p>
                <h2 id="accountTitle">Account</h2>
                <p id="familyTitle" class="muted">Family loading…</p>
            </div>
            <button id="logoutBtn" type="button" class="secondary">Logout</button>
        </section>

        <section id="inviteCard" class="card hidden">
            <h2>Invite Code</h2>
            <p class="muted">Owner-only. Regenerating a code invalidates the previous one.</p>
            <div class="invite-row">
                <code id="inviteCodeDisplay">Not generated this session</code>
                <button id="copyInviteBtn" type="button" class="secondary">Copy Code</button>
                <button id="regenerateInviteBtn" type="button">Regenerate Invite Code</button>
            </div>
            <p class="muted">Copy only works while the full generated code is visible. After a page refresh, only the last four characters are shown.</p>
        </section>

        <section id="familyNoticeCard" class="card hidden">
            <h2>Family Notices</h2>
            <div id="familyNoticeList" class="member-list">No new notices.</div>
        </section>

        <section class="card controls-card">
            <h2>Location Sharing</h2>
            <p class="muted">Sharing starts only when you press the button and approve the browser GPS prompt.</p>
            <div class="button-row">
                <button id="startSharingBtn" type="button">Start Sharing</button>
                <button id="stopSharingBtn" type="button" class="secondary" disabled>Stop Sharing</button>
                <button id="updateOnceBtn" type="button" class="secondary">Update Once</button>
                <button id="refreshBtn" type="button" class="secondary">Refresh Family</button>
            </div>
            <div class="button-row danger-zone">
                <button id="deleteLocationBtn" type="button" class="danger-button">Delete My Stored Location</button>
            </div>
        </section>

        <section class="dashboard-grid">
            <article class="metric-card">
                <span class="metric-label">GPS Accuracy</span>
                <strong id="accuracyValue">--</strong>
                <span class="metric-unit">feet</span>
            </article>
            <article class="metric-card">
                <span class="metric-label">Speed</span>
                <strong id="speedValue">--</strong>
                <span class="metric-unit">mph</span>
            </article>
            <article class="metric-card">
                <span class="metric-label">Heading</span>
                <strong id="headingValue">--</strong>
                <span class="metric-unit">degrees</span>
            </article>
            <article class="metric-card">
                <span class="metric-label">Last Update</span>
                <strong id="lastUpdateValue">--</strong>
                <span class="metric-unit">local time</span>
            </article>
        </section>

        <section class="card map-card">
            <div class="section-header">
                <div>
                    <h2>Family Map</h2>
                    <p class="muted">Stale locations are still shown, but marked in the member list.</p>
                </div>
            </div>
            <div id="map" role="application" aria-label="Family location map"></div>
        </section>

        <section class="card">
            <h2>Family Members</h2>
            <div id="memberList" class="member-list">No members loaded yet.</div>
        </section>

        <section class="card warning-card">
            <h2>Reality Check</h2>
            <p>This is a web app. It cannot guarantee background tracking when the phone is locked, the tab is closed, battery saver kicks in, or the browser decides to go take a nap. For “where is my kid right now” production use, native apps still win.</p>
        </section>
    </main>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
        integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
<script src="assets/js/app.js?v=<?= urlencode(APP_REVISION) ?>"></script>
</body>
</html>
