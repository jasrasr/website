<?php

declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
$configured = is_configured();
$authenticated = is_authenticated();
$authData = auth_data();
$legacyAuth = $configured && isset($authData['passwordHash']);
$settings = $authenticated ? tracker_settings() : [];
$account = $authenticated ? current_account() : [];
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
    <meta name="theme-color" content="#19233f">
    <title><?= htmlspecialchars((string) ($config['app_name'] ?? 'Baby Daily')) ?></title>
    <link rel="stylesheet" href="assets/app.css?v=<?= BABY_TRACKER_VERSION ?>">
</head>
<body data-authenticated="<?= $authenticated ? 'true' : 'false' ?>" data-configured="<?= $configured ? 'true' : 'false' ?>" data-legacy-auth="<?= $legacyAuth ? 'true' : 'false' ?>">
<header class="topbar">
    <div>
        <p class="eyebrow">ONE PHOTO · EVERY DAY</p>
        <h1>Baby Daily</h1>
    </div>
    <?php if ($authenticated): ?><div class="account-actions"><span><?= htmlspecialchars((string) $account['username']) ?></span><button class="secondary compact" id="logoutButton">Sign out</button></div><?php endif; ?>
</header>

<main>
    <?php if (!$authenticated): ?>
        <section class="auth-card card">
            <div class="baby-mark" aria-hidden="true">♥</div>
            <h2><?= $legacyAuth ? 'Name the existing gallery' : ($configured ? 'Welcome back' : 'Create the private gallery') ?></h2>
            <p><?= $legacyAuth ? 'Enter the baby’s first name and your existing password. This upgrades the gallery without deleting the current photos.' : ($configured ? 'Sign in with the baby’s first name to view that gallery.' : 'The baby’s first name becomes the username for this private gallery.') ?></p>
            <form id="authForm">
                <label>Baby’s first name<input name="username" maxlength="40" pattern="[A-Za-z][A-Za-z'-]{0,39}" autocomplete="username" autocapitalize="words" required></label>
                <label>Password<input type="password" name="password" minlength="10" autocomplete="<?= $configured ? 'current-password' : 'new-password' ?>" required></label>
                <?php if (!$configured): ?><label>Confirm password<input type="password" name="confirmPassword" minlength="10" autocomplete="new-password" required></label><?php endif; ?>
                <button type="submit"><?= $configured ? 'Sign in' : 'Create gallery' ?></button>
            </form>
        </section>
    <?php else: ?>
        <section class="hero card">
            <div>
                <p class="eyebrow">DUE <?= htmlspecialchars((new DateTimeImmutable((string) $settings['dueDate']))->format('F j, Y')) ?></p>
                <h2 id="heroTitle">A little face, one day at a time.</h2>
                <p id="heroStatus">Loading the gallery…</p>
            </div>
            <button id="openUpload">＋ Add today’s photo</button>
        </section>

        <section class="stats" aria-label="Gallery summary">
            <article class="card"><strong id="photoCount">0</strong><span>photos</span></article>
            <article class="card"><strong id="streakCount">0</strong><span>day streak</span></article>
            <article class="card"><strong id="missingCount">0</strong><span>days missing</span></article>
        </section>

        <section class="toolbar">
            <div class="segmented" role="group" aria-label="Gallery filter">
                <button class="active" data-filter="all">All</button>
                <button data-filter="missing">Missing</button>
            </div>
            <div class="toolbar-actions">
                <button class="secondary" id="collageButton">Make collage</button>
                <button class="secondary" id="gifButton">Make GIF</button>
                <button class="secondary" id="settingsButton">Settings</button>
            </div>
        </section>

        <section id="gallery" class="gallery" aria-live="polite"></section>
        <p id="emptyState" class="empty card" hidden>No photos yet. The gallery is ready when your tiny model is.</p>
    <?php endif; ?>
</main>

<?php if ($authenticated): ?>
<dialog id="uploadDialog" class="upload-dialog">
    <form id="uploadForm">
        <div class="dialog-head"><div><p class="eyebrow">DAILY MEMORY</p><h2>Add a photo</h2></div><button type="button" class="icon-button close-dialog" aria-label="Close">×</button></div>
        <label>Photo date<input type="date" name="photoDate" required></label>
        <label>Photo<input type="file" name="photo" accept="image/jpeg,image/png,image/webp" required><small>Choose an existing photo or take a new one.</small></label>
        <div id="preview" class="preview">Photo preview</div>
        <label>Note <span>(optional)</span><textarea name="note" maxlength="300" placeholder="First smile, sleepy stretch, favorite outfit…"></textarea></label>
        <button type="submit">Save daily photo</button>
    </form>
</dialog>

<dialog id="settingsDialog">
    <form id="settingsForm">
        <div class="dialog-head"><div><p class="eyebrow">GALLERY DETAILS</p><h2>Settings</h2></div><button type="button" class="icon-button close-dialog" aria-label="Close">×</button></div>
        <label>Baby’s name<input name="babyName" maxlength="80" value="<?= htmlspecialchars((string) $settings['babyName']) ?>" placeholder="Optional for now"></label>
        <label>Due date<input type="date" name="dueDate" value="<?= htmlspecialchars((string) $settings['dueDate']) ?>" required></label>
        <label>Actual birth date<input type="date" name="birthDate" value="<?= htmlspecialchars((string) $settings['birthDate']) ?>"><small>Once entered, this date—not the due date—controls Day 1 and all age calculations.</small></label>
        <div class="settings-grid">
            <label>Time born <span>(optional)</span><input type="time" name="birthTime" value="<?= htmlspecialchars((string) ($settings['birthTime'] ?? '')) ?>"></label>
            <label>Length in inches <span>(optional)</span><input type="number" name="birthLengthInches" min="5" max="30" step="0.25" inputmode="decimal" value="<?= htmlspecialchars((string) ($settings['birthLengthInches'] ?? '')) ?>" placeholder="20.5"></label>
            <label>Weight pounds <span>(optional)</span><input type="number" name="birthWeightPounds" min="0" max="25" step="1" inputmode="numeric" value="<?= htmlspecialchars((string) ($settings['birthWeightPounds'] ?? '')) ?>" placeholder="7"></label>
            <label>Weight ounces <span>(optional)</span><input type="number" name="birthWeightOunces" min="0" max="15" step="1" inputmode="numeric" value="<?= htmlspecialchars((string) ($settings['birthWeightOunces'] ?? '')) ?>" placeholder="8"></label>
        </div>
        <button type="submit">Save settings</button>
    </form>
    <div class="account-section">
        <p class="eyebrow">ACCOUNT</p>
        <p>Username: <strong><?= htmlspecialchars((string) $account['username']) ?></strong></p>
        <form id="passwordForm">
            <label>Current password<input type="password" name="currentPassword" minlength="10" autocomplete="current-password" required></label>
            <label>New password<input type="password" name="newPassword" minlength="10" autocomplete="new-password" required></label>
            <label>Confirm new password<input type="password" name="confirmNewPassword" minlength="10" autocomplete="new-password" required></label>
            <button type="submit" class="secondary">Change password</button>
        </form>
        <button type="button" id="openBabyDialog">＋ Add another baby</button>
    </div>
</dialog>

<dialog id="babyDialog">
    <form id="babyForm">
        <div class="dialog-head"><div><p class="eyebrow">ANOTHER GALLERY</p><h2>Add a baby</h2></div><button type="button" class="icon-button close-dialog" aria-label="Close">×</button></div>
        <p class="save-help">This creates a separate photo and metadata folder. The baby’s first name is the username.</p>
        <label>Baby’s first name<input name="babyUsername" maxlength="40" pattern="[A-Za-z][A-Za-z'-]{0,39}" autocomplete="off" autocapitalize="words" required></label>
        <label>Password<input type="password" name="babyPassword" minlength="10" autocomplete="new-password" required></label>
        <label>Confirm password<input type="password" name="confirmBabyPassword" minlength="10" autocomplete="new-password" required></label>
        <button type="submit">Create and open gallery</button>
    </form>
</dialog>

<dialog id="photoDialog"><div class="photo-view"><button type="button" class="icon-button close-dialog" aria-label="Close">×</button><img id="fullPhoto" alt=""><div id="photoDetails"></div></div></dialog>

<dialog id="collageDialog">
    <div class="dialog-head"><div><p class="eyebrow">READY TO KEEP</p><h2 id="exportTitle">Your collage</h2></div><button type="button" class="icon-button close-dialog" aria-label="Close">×</button></div>
    <img id="exportPreview" class="collage-preview" alt="Generated Baby Daily export">
    <p id="exportHelp" class="save-help">On iPhone, tap <strong>Share or save</strong> and choose Save Image. You can also press and hold the preview.</p>
    <div class="collage-actions">
        <button type="button" id="shareExportButton">Share or save</button>
        <button type="button" class="secondary" id="downloadExportButton">Download file</button>
    </div>
</dialog>
<?php endif; ?>

<div id="toast" class="toast" role="status" aria-live="polite"></div>
<footer>Baby Daily v<?= BABY_TRACKER_VERSION ?> · <a href="CHANGELOG.md">View changelog</a></footer>
<script>window.BABY_TRACKER = {csrf: <?= json_encode(csrf_token()) ?>};</script>
<script src="assets/app.js?v=<?= BABY_TRACKER_VERSION ?>" defer></script>
</body>
</html>
