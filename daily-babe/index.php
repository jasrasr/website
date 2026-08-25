<?php

declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
$settings = tracker_settings();
$configured = is_configured();
$authenticated = is_authenticated();
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
<body data-authenticated="<?= $authenticated ? 'true' : 'false' ?>" data-configured="<?= $configured ? 'true' : 'false' ?>">
<header class="topbar">
    <div>
        <p class="eyebrow">ONE PHOTO · EVERY DAY</p>
        <h1>Baby Daily</h1>
    </div>
    <?php if ($authenticated): ?><button class="secondary compact" id="logoutButton">Sign out</button><?php endif; ?>
</header>

<main>
    <?php if (!$authenticated): ?>
        <section class="auth-card card">
            <div class="baby-mark" aria-hidden="true">♥</div>
            <h2><?= $configured ? 'Welcome back' : 'Create the private gallery' ?></h2>
            <p><?= $configured ? 'Sign in to view and add family photos.' : 'Choose the password that protects this gallery.' ?></p>
            <form id="authForm">
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
                <button class="secondary" id="settingsButton">Settings</button>
            </div>
        </section>

        <section id="gallery" class="gallery" aria-live="polite"></section>
        <p id="emptyState" class="empty card" hidden>No photos yet. The gallery is ready when your tiny model is.</p>
    <?php endif; ?>
</main>

<?php if ($authenticated): ?>
<dialog id="uploadDialog">
    <form id="uploadForm">
        <div class="dialog-head"><div><p class="eyebrow">DAILY MEMORY</p><h2>Add a photo</h2></div><button type="button" class="icon-button close-dialog" aria-label="Close">×</button></div>
        <label>Photo date<input type="date" name="photoDate" required></label>
        <label>Photo<input type="file" name="photo" accept="image/jpeg,image/png,image/webp" capture="user" required></label>
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
        <label>Actual birth date<input type="date" name="birthDate" value="<?= htmlspecialchars((string) $settings['birthDate']) ?>"><small>Add this after birth to calculate the correct day number.</small></label>
        <button type="submit">Save settings</button>
    </form>
</dialog>

<dialog id="photoDialog"><div class="photo-view"><button type="button" class="icon-button close-dialog" aria-label="Close">×</button><img id="fullPhoto" alt=""><div id="photoDetails"></div></div></dialog>
<?php endif; ?>

<div id="toast" class="toast" role="status" aria-live="polite"></div>
<footer>Baby Daily v<?= BABY_TRACKER_VERSION ?> · <a href="CHANGELOG.md">View changelog</a></footer>
<script>window.BABY_TRACKER = {csrf: <?= json_encode(csrf_token()) ?>};</script>
<script src="assets/app.js?v=<?= BABY_TRACKER_VERSION ?>" defer></script>
</body>
</html>
