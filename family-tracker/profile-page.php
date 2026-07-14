<?php
/**
 * Project: Family GPS Tracker
 * File: profile-page.php
 * Revision: 1.0.0
 * Description: Dedicated account profile page for member-controlled display, nickname, and avatar preferences.
 * Author: Jason Lamb / ChatGPT scaffold
 * Created: 2026-07-14
 * Modified: 2026-07-14
 */

declare(strict_types=1);
require_once __DIR__ . '/includes/config.php';
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Profile - <?= htmlspecialchars(APP_NAME, ENT_QUOTES, 'UTF-8') ?></title>
<link rel="manifest" href="manifest.webmanifest">
<link rel="stylesheet" href="assets/css/style.css?v=<?= urlencode(APP_REVISION) ?>">
</head>
<body>
<div class="app-shell" data-app-revision="<?= htmlspecialchars(APP_REVISION, ENT_QUOTES, 'UTF-8') ?>">
    <header class="hero"><div><p class="eyebrow">Account Profile</p><h1>Profile</h1><p class="hero-copy">Manage the name, nickname, and avatar that represent you in the active group.</p></div></header>
    <section id="statusCard" class="card status-card" aria-live="polite"><strong>Status:</strong> <span id="statusText">Loading profile…</span></section>
    <main id="trackerApp">
        <section class="card account-card"><div><p class="eyebrow">Signed in</p><h2 id="accountTitle">Account</h2><p id="familyTitle" class="muted">Group loading…</p></div><div class="button-row"><a class="secondary-link" href="index.php">Back to Tracker</a></div></section>
        <section id="accountSettingsCard" class="card profile-edit"><div><p class="eyebrow">Profile</p><h2>Account Profile</h2></div><form id="displayNameForm" class="profile-edit"><label>Display name<input id="displayNameInput" name="displayName" maxlength="80" required></label><label>Personal nickname<input id="nicknameInput" name="nickname" maxlength="80" placeholder="Optional shorter name"></label><label>Avatar type<select id="avatarModeSelect" name="avatarMode"><option value="generated">Generated initials</option><option value="picture">Picture URL</option></select></label><label>Profile picture URL<input id="avatarUrlInput" name="avatarUrl" maxlength="300" placeholder="https://example.com/photo.jpg"></label><button type="submit" class="secondary">Save Profile</button><div class="profile-edit-note">Leave the picture URL blank to use a generated initials avatar.</div></form></section>
    </main>
</div>
<script src="assets/js/appearance.js?v=<?= urlencode(APP_REVISION) ?>"></script>
<script src="assets/js/account-settings.js?v=<?= urlencode(APP_REVISION) ?>"></script>
</body>
</html>