<?php
/**
 * Project: Family GPS Tracker
 * File: index.php
 * Revision: 1.3.5
 * Description: Mobile-first family tracker UI.
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
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIINfQOhrxgLGPaLGKcM4Xu9yZV9hC3Q7gk=" crossorigin="">
    <link rel="stylesheet" href="assets/css/style.css?v=<?= urlencode(APP_REVISION) ?>">
</head>
<body>
<div class="app-shell">
    <header class="hero">
        <div>
            <p class="eyebrow">Rev <?= htmlspecialchars(APP_REVISION, ENT_QUOTES, 'UTF-8') ?> • Updated <?= htmlspecialchars(APP_UPDATED, ENT_QUOTES, 'UTF-8') ?></p>
            <h1><?= htmlspecialchars(APP_NAME, ENT_QUOTES, 'UTF-8') ?></h1>
            <p class="hero-copy">Private family map app with persistent sessions, browser permission, JSON files, and OpenStreetMap.</p>
        </div>
    </header>

    <noscript><section class="card danger">This site requires JavaScript and browser permission.</section></noscript>

    <section id="statusCard" class="card status-card" aria-live="polite"><strong>Status:</strong> <span id="statusText">Loading…</span></section>

    <section id="authCard" class="grid auth-grid hidden">
        <form id="loginForm" class="card form-card">
            <h2>Login</h2>
            <label>Username <input name="username" autocomplete="username" required></label>
            <label>Password <input name="password" type="password" autocomplete="current-password" required></label>
            <label class="check-row"><input name="rememberMe" type="checkbox" checked><span>Keep me signed in on this device.</span></label>
            <button type="submit">Login</button>
        </form>

        <form id="registerForm" class="card form-card">
            <h2>Create Family</h2>
            <label>Your display name <input name="displayName" autocomplete="name" required></label>
            <label>Username <input name="username" autocomplete="username" required></label>
            <label>Password <input name="password" type="password" autocomplete="new-password" minlength="8" required></label>
            <label>Family name <input name="familyName" placeholder="The Lamb Family" required></label>
            <label class="check-row"><input name="consentAccepted" type="checkbox" required><span>I understand each user must use their own account and grant browser permission.</span></label>
            <label class="check-row"><input name="rememberMe" type="checkbox" checked><span>Keep me signed in on this device.</span></label>
            <button type="submit">Create Tracker</button>
        </form>

        <form id="joinForm" class="card form-card">
            <h2>Join Family</h2>
            <label>Invite code <input name="inviteCode" placeholder="ABCDE-12345" required></label>
            <label>Your display name <input name="displayName" autocomplete="name" required></label>
            <label>Username <input name="username" autocomplete="username" required></label>
            <label>Password <input name="password" type="password" autocomplete="new-password" minlength="8" required></label>
            <label class="check-row"><input name="consentAccepted" type="checkbox" required><span>I consent to sharing with this family group while signed in.</span></label>
            <label class="check-row"><input name="rememberMe" type="checkbox" checked><span>Keep me signed in on this device.</span></label>
            <button type="submit">Join Tracker</button>
        </form>
    </section>

    <main id="trackerApp" class="hidden">
        <section class="card account-card"><div><p class="eyebrow">Signed in</p><h2 id="accountTitle">Account</h2><p id="familyTitle" class="muted">Family loading…</p></div><button id="logoutBtn" type="button" class="secondary">Logout</button></section>

        <section id="inviteCard" class="card hidden">
            <h2>Invite Code</h2>
            <p class="muted">Owner-only. Regenerating a code invalidates the previous one.</p>
            <div class="invite-row"><code id="inviteCodeDisplay">Not generated this session</code><button id="copyInviteBtn" type="button" class="secondary">Copy Code</button><button id="regenerateInviteBtn" type="button">Regenerate Invite Code</button></div>
            <p class="muted">For security, the app stores the invite code as a hash plus the last four characters only. The full code can be copied only right after creation or regeneration.</p>
        </section>

        <section id="familyNoticeCard" class="card hidden"><h2>Family Notices</h2><div id="familyNoticeList" class="member-list">No new notices.</div></section>

        <section class="card controls-card"><h2>Sharing</h2><p class="muted">The app updates about every minute while this page is open. Start Sharing adds a higher-frequency watch.</p><div class="button-row"><button id="startSharingBtn" type="button">Start Sharing</button><button id="stopSharingBtn" type="button" class="secondary" disabled>Stop Sharing</button><button id="updateOnceBtn" type="button" class="secondary">Update Once</button><button id="refreshBtn" type="button" class="secondary">Refresh Family</button></div><div class="button-row danger-zone"><button id="deleteLocationBtn" type="button" class="danger-button">Delete My Saved Point</button></div></section>

        <section class="dashboard-grid">
            <article class="metric-card"><span class="metric-label">GPS Accuracy</span><strong id="accuracyValue">--</strong><span class="metric-unit">feet</span></article>
            <article class="metric-card"><span class="metric-label">Speed</span><strong id="speedValue">--</strong><span class="metric-unit">mph</span></article>
            <article class="metric-card"><span class="metric-label">Heading</span><strong id="headingValue">--</strong><span class="metric-unit">degrees</span></article>
            <article class="metric-card"><span class="metric-label">Last Update</span><strong id="lastUpdateValue">--</strong><span class="metric-unit">local time</span></article>
        </section>

        <section class="card map-card"><div class="section-header"><div><h2>Family Map</h2><p class="muted">Stale points are still shown, but marked in the member list.</p></div></div><div id="map" role="application" aria-label="Family map"></div></section>

        <section class="card"><h2>Family Members</h2><div id="memberList" class="member-list">No members loaded yet.</div></section>

        <section class="card warning-card"><h2>Reality Check</h2><p>This is a web app. Mobile browsers may pause updates when the page is closed, hidden, or limited by the operating system.</p></section>
    </main>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
<script src="assets/js/app.js?v=<?= urlencode(APP_REVISION) ?>"></script>
<script src="assets/js/member-badges.js?v=<?= urlencode(APP_REVISION) ?>"></script>
</body>
</html>
