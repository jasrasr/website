<?php
/**
 * Project: Family GPS Tracker
 * File: privacy.php
 * Revision: 1.4.9
 * Description: Plain-language privacy and background-location disclosure page.
 * Author: Jason Lamb / ChatGPT scaffold
 * Created: 2026-07-11
 * Modified: 2026-07-11
 */

declare(strict_types=1);
require_once __DIR__ . '/includes/config.php';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>Privacy - <?= htmlspecialchars(APP_NAME, ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?= urlencode(APP_REVISION) ?>">
</head>
<body>
<div class="app-shell">
    <header class="hero"><div><p class="eyebrow">Rev <?= htmlspecialchars(APP_REVISION, ENT_QUOTES, 'UTF-8') ?> • Privacy</p><h1>Privacy Details</h1><p class="hero-copy"><a href="index.php">Back to tracker</a></p></div></header>

    <section class="card">
        <h2>What the app stores</h2>
        <p>The app may store account information, display name, username, password hash, group memberships, owner/member roles, latest shared location, recent trail points, group notices, audit records, invite metadata, and remembered-device login records.</p>
    </section>

    <section class="card">
        <h2>Location behavior</h2>
        <p>The app requests location permission when the signed-in tracker launches. Location updates are consent-based and depend on the browser and device operating system.</p>
        <p>Because this is a website, updates may pause when the browser is hidden, closed, backgrounded, the phone is locked, battery-saving restrictions apply, or the operating system suspends the page. This app does not guarantee continuous background location.</p>
    </section>

    <section class="card">
        <h2>Who can see shared locations</h2>
        <p>Members of the selected active group can see location information for members whose latest saved location belongs to that group. Group owners have additional administrative and export controls.</p>
    </section>

    <section class="card">
        <h2>Your controls</h2>
        <ul>
            <li>Delete your latest saved point and trail.</li>
            <li>Download your personal data export.</li>
            <li>Revoke remembered devices.</li>
            <li>Change your password.</li>
            <li>Delete your account, subject to group-ownership safeguards.</li>
        </ul>
    </section>

    <section class="card warning-card">
        <h2>Account deletion safeguard</h2>
        <p>An owner cannot delete their account while an owned group still contains other members. Ownership must first be transferred, or the other members must be removed. A group owned only by the deleting user may be removed with the account.</p>
    </section>
</div>
</body>
</html>
