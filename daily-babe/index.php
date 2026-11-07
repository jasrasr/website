<?php

declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
$centralUser = daily_require_user('viewer');
$configured = true;
$authenticated = true;
$authData = tracker_state();
$legacyAuth = false;
$account = current_account();
$requestedBabyId = (string) ($_GET['baby'] ?? '');
if (preg_match('/^[a-f0-9]{24}$/D', $requestedBabyId) === 1 && can_access_baby($account, $requestedBabyId, $authData)) {
    $_SESSION['daily_babe_selected_baby_id'] = $requestedBabyId;
}
$settings = tracker_settings();
$reminderTimes = normalized_reminder_times($settings);
$visibility = gallery_visibility($settings);
$gender = in_array((string) ($settings['gender'] ?? ''), ['boy', 'girl'], true) ? (string) $settings['gender'] : '';
$superAdmin = is_super_admin();
$canWrite = daily_auth()->can(BABY_TRACKER_PROJECT, 'member');
$requestedPhotoDate = (string) ($_GET['date'] ?? '');
$openUploadFromLink = $canWrite && (string) ($_GET['upload'] ?? '') === '1';
if (!valid_date($requestedPhotoDate)) {
    $requestedPhotoDate = '';
}
$accountPortal = daily_auth()->portal();
$availableBabies = [];
if ($authenticated) {
    foreach ($authData['babies'] ?? [] as $babyId => $baby) {
        if (can_access_baby($account, (string) $babyId, $authData)) {
            $availableBabies[$babyId] = $baby;
        }
    }
}
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
<body class="<?= $gender !== '' ? 'theme-' . $gender : '' ?>" data-authenticated="<?= $authenticated ? 'true' : 'false' ?>" data-can-write="<?= $canWrite ? 'true' : 'false' ?>" data-configured="<?= $configured ? 'true' : 'false' ?>" data-legacy-auth="<?= $legacyAuth ? 'true' : 'false' ?>">
<header class="topbar">
    <h1>Baby Daily<span class="brand-dot">.</span></h1>
    <?php if ($authenticated): ?>
    <div class="topbar-actions">
        <?php if (count($availableBabies) > 1): ?>
        <div class="baby-switcher">
            <label for="babySelector">Viewing baby</label>
            <select id="babySelector">
                <?php foreach ($availableBabies as $babyId => $baby): ?>
                    <option value="<?= htmlspecialchars((string) $babyId) ?>" <?= current_baby_id() === (string) $babyId ? 'selected' : '' ?>><?= htmlspecialchars((string) ($baby['name'] ?? 'Baby')) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>
        <div class="account-actions"><span><?= htmlspecialchars((string) $account['username']) ?></span><button class="secondary compact" id="logoutButton">Sign out</button></div>
    </div>
    <?php endif; ?>
</header>

<main>
        <section class="overview" aria-label="Gallery overview">
            <div class="hero-copy">
                <h2 id="heroTitle">A little face, one day at a time.</h2>
                <p id="heroStatus">Loading the gallery…</p>
                <p class="due-date">Due <?= htmlspecialchars((new DateTimeImmutable((string) $settings['dueDate']))->format('F j, Y')) ?></p>
            </div>
            <div class="stats" aria-label="Gallery summary">
                <div><strong id="photoCount">0</strong><span>photos</span></div>
                <div><strong id="streakCount">0</strong><span>day streak</span></div>
                <div><strong id="missingCount">0</strong><span>days missing</span></div>
            </div>
            <button id="openUpload" <?= $canWrite ? '' : 'disabled title="Member access is required to add photos."' ?>><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 7h3l2-2h6l2 2h3v12H4z"/><circle cx="12" cy="13" r="3.5"/></svg>Add today’s photo</button>
        </section>

        <section class="toolbar">
            <div class="segmented" role="group" aria-label="Gallery filter">
                <button class="active" data-filter="all">All</button>
                <button data-filter="missing">Missing</button>
            </div>
            <div class="toolbar-actions">
                <button class="secondary" id="collageButton"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>Make collage</button>
                <button class="secondary" id="gifButton"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round" aria-hidden="true"><path d="m8 4 12 8-12 8z"/></svg>Make GIF</button>
                <button class="secondary" id="settingsButton" <?= $canWrite ? '' : 'disabled title="Member access is required to change settings."' ?>><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="3"/><path d="M10 2h4l.5 2.1 1.6.7 1.9-1.1 2.8 2.8-1.1 1.9.7 1.6L22 10v4l-2.1.5-.7 1.6 1.1 1.9-2.8 2.8-1.9-1.1-1.6.7L14 22h-4l-.5-2.1-1.6-.7-1.9 1.1-2.8-2.8 1.1-1.9-.7-1.6L2 14v-4l2.1-.5.7-1.6-1.1-1.9 2.8-2.8 1.9 1.1 1.6-.7z"/></svg>Settings</button>
            </div>
        </section>

        <section id="gallery" class="gallery" aria-live="polite"></section>
        <p id="emptyState" class="empty card" hidden>No photos yet. The gallery is ready when your tiny model is.</p>
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
        <label>Gender <span>(optional)</span><select name="gender"><option value="" <?= $gender === '' ? 'selected' : '' ?>>Not selected</option><option value="boy" <?= $gender === 'boy' ? 'selected' : '' ?>>Boy</option><option value="girl" <?= $gender === 'girl' ? 'selected' : '' ?>>Girl</option></select><small>Changes this gallery to a subtle blue or pink theme.</small></label>
        <label>Due date<input type="date" name="dueDate" value="<?= htmlspecialchars((string) $settings['dueDate']) ?>" required></label>
        <label>Actual birth date<input type="date" name="birthDate" value="<?= htmlspecialchars((string) $settings['birthDate']) ?>"><small>Once entered, this date—not the due date—controls Day 1 and all age calculations.</small></label>
        <div class="settings-grid">
            <label>Time born <span>(optional)</span><input type="time" name="birthTime" value="<?= htmlspecialchars((string) ($settings['birthTime'] ?? '')) ?>"></label>
            <label>Length in inches <span>(optional)</span><input type="number" name="birthLengthInches" min="5" max="30" step="0.01" inputmode="decimal" value="<?= htmlspecialchars((string) ($settings['birthLengthInches'] ?? '')) ?>" placeholder="19.69"></label>
            <label>Weight pounds <span>(optional)</span><input type="number" name="birthWeightPounds" min="0" max="25" step="1" inputmode="numeric" value="<?= htmlspecialchars((string) ($settings['birthWeightPounds'] ?? '')) ?>" placeholder="7"></label>
            <label>Weight ounces <span>(optional)</span><input type="number" name="birthWeightOunces" min="0" max="15" step="1" inputmode="numeric" value="<?= htmlspecialchars((string) ($settings['birthWeightOunces'] ?? '')) ?>" placeholder="8"></label>
        </div>
        <div class="reminder-settings">
            <label class="reminder-toggle"><input type="checkbox" name="reminderEnabled" value="1" <?= !empty($settings['reminderEnabled']) ? 'checked' : '' ?>><span><strong>Enable missing-photo reminders</strong><small>Reminders stop for the day as soon as a photo is added.</small></span></label>
            <div class="settings-grid">
                <label>First reminder<input type="time" name="reminderTimes[]" value="<?= htmlspecialchars($reminderTimes[0]) ?>"></label>
                <label>Second reminder<input type="time" name="reminderTimes[]" value="<?= htmlspecialchars($reminderTimes[1]) ?>"></label>
                <label>Final reminder<input type="time" name="reminderTimes[]" value="<?= htmlspecialchars($reminderTimes[2]) ?>"></label>
                <label>Recipient email addresses<input type="email" name="reminderEmails" multiple value="<?= htmlspecialchars((string) ($settings['reminderEmails'] ?? '')) ?>" placeholder="name@example.com"><small>Separate multiple addresses with commas.</small></label>
                <label class="reminder-toggle"><input type="checkbox" name="reminderSmsEnabled" value="1" <?= !empty($settings['reminderSmsEnabled']) ? 'checked' : '' ?>><span><strong>Also send a text message</strong><small>Requires Twilio credentials in the server configuration.</small></span></label>
                <label>Text recipient phone number<input type="tel" name="reminderSmsNumber" inputmode="tel" autocomplete="tel" value="<?= htmlspecialchars((string) ($settings['reminderSmsNumber'] ?? '')) ?>" placeholder="+12165550123"><small>Use international E.164 format, including the + and country code.</small></label>
            </div>
            <div class="reminder-diagnostics">
                <div class="reminder-actions">
                    <button type="button" class="secondary" id="testReminderEmail">Send test email</button>
                    <button type="button" class="secondary" id="checkReminderStatus">Check scheduler status</button>
                </div>
                <p id="reminderStatus" class="reminder-status" role="status">Save recipient changes before sending a test. Automatic reminders also require the Hostinger cron job.</p>
            </div>
        </div>
        <div class="public-sharing">
            <fieldset class="visibility-options">
                <legend>Who can view this gallery?</legend>
                <label><input type="radio" name="visibility" value="private" <?= $visibility === 'private' ? 'checked' : '' ?>><span><strong>Private</strong><small>Only assigned users can view it. No public link.</small></span></label>
                <label><input type="radio" name="visibility" value="unlisted" <?= $visibility === 'unlisted' ? 'checked' : '' ?>><span><strong>Unlisted</strong><small>Anyone with the opaque link must enter the baby’s name and birth date. It is not listed publicly.</small></span></label>
                <label><input type="radio" name="visibility" value="public" <?= $visibility === 'public' ? 'checked' : '' ?>><span><strong>Public</strong><small>Anyone can open the link without a prompt, and the gallery appears in the public gallery list.</small></span></label>
            </fieldset>
            <div id="publicLinkPanel" <?= $visibility === 'private' ? 'hidden' : '' ?>>
                <label>Gallery link<input id="publicLink" type="text" readonly value=""></label>
                <div class="public-link-actions">
                    <button type="button" class="secondary" id="copyPublicLink">Copy link</button>
                    <button type="button" class="secondary" id="openPublicLink">Open shared gallery</button>
                </div>
                <small id="publicLinkHelp">The link details depend on the selected visibility.</small>
            </div>
        </div>
        <button type="submit">Save settings</button>
    </form>
    <div class="account-section">
        <p class="eyebrow">ACCOUNT</p>
        <p>Signed in through User Management as: <strong><?= htmlspecialchars((string) $account['username']) ?></strong><?= $superAdmin ? ' (Daily Babe admin)' : '' ?></p>
        <a class="button-link secondary" href="<?= htmlspecialchars($accountPortal) ?>">Manage password and central account</a>
        <?php if ($superAdmin): ?>
        <button type="button" id="openBabyDialog">＋ Add another baby</button>
        <button type="button" class="secondary" id="openUsersDialog">Manage users</button>
        <?php else: ?>
        <button type="button" id="openBabyDialog" hidden>＋ Add another baby</button>
        <?php endif; ?>
    </div>
</dialog>

<dialog id="babyDialog">
    <form id="babyForm">
        <div class="dialog-head"><div><p class="eyebrow">ANOTHER GALLERY</p><h2>Add a baby</h2></div><button type="button" class="icon-button close-dialog" aria-label="Close">×</button></div>
        <p class="save-help">Create a separate gallery. Add people to it from Manage users.</p>
        <label>Baby’s name<input name="babyName" maxlength="80" required></label>
        <button type="submit">Create and open gallery</button>
    </form>
</dialog>

<?php if ($superAdmin): ?>
<dialog id="usersDialog">
    <div class="dialog-head"><div><p class="eyebrow">ACCESS</p><h2>Manage users</h2></div><button type="button" class="icon-button close-dialog" aria-label="Close">×</button></div>
    <p class="save-help">People and passwords are managed in User Management. Assign existing central users to baby galleries here.</p>
    <div id="userList"></div>
    <a class="button-link secondary" href="<?= htmlspecialchars($accountPortal) ?>">Open User Management</a>
</dialog>
<?php endif; ?>

<dialog id="photoDialog"><div class="photo-view"><button type="button" class="icon-button close-dialog" aria-label="Close">×</button><img id="fullPhoto" alt=""><div id="photoDetails"></div>
    <?php if ($canWrite): ?>
    <section class="focus-editor" aria-labelledby="focusEditorTitle">
        <div><p class="eyebrow">GALLERY CROP</p><h3 id="focusEditorTitle">Adjust picture focus</h3><p>Move the focal point or rotate the picture in 90° steps. Gallery tiles, shared views, collages, and GIFs update while the original photo remains unchanged.</p></div>
        <div class="focus-preview rotatable-frame"><img id="focusPreview" alt="Gallery crop preview"></div>
        <div class="focus-controls">
            <label>Horizontal focus<input id="focusX" type="range" min="0" max="100" step="1" value="50"></label>
            <label>Vertical focus<input id="focusY" type="range" min="0" max="100" step="1" value="50"></label>
        </div>
        <div class="rotation-actions" role="group" aria-label="Photo rotation">
            <button type="button" class="secondary" id="rotateCounterclockwiseButton" aria-label="Rotate 90 degrees counterclockwise">↶ Counterclockwise</button>
            <span id="rotationLabel" aria-live="polite">No rotation</span>
            <button type="button" class="secondary" id="rotateClockwiseButton" aria-label="Rotate 90 degrees clockwise">Clockwise ↷</button>
        </div>
        <div class="focus-actions"><button type="button" class="secondary" id="centerFocusButton">Center focus</button><button type="button" id="saveFocusButton">Save crop &amp; rotation</button></div>
    </section>
    <?php endif; ?>
    <button type="button" class="danger" id="deletePhotoButton" <?= $canWrite ? '' : 'disabled title="Member access is required to remove photos."' ?>>Remove photo</button></div></dialog>

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
<footer>Baby Daily v<?= BABY_TRACKER_VERSION ?> - Modified <?= htmlspecialchars(project_last_modified_date(__DIR__)) ?></footer>
<script>window.BABY_TRACKER = {csrf: <?= json_encode(csrf_token()) ?>, openUpload: <?= $openUploadFromLink ? 'true' : 'false' ?>, uploadDate: <?= json_encode($requestedPhotoDate) ?>};</script>
<script src="assets/app.js?v=<?= BABY_TRACKER_VERSION ?>" defer></script>
</body>
</html>


