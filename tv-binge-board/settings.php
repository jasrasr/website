<?php
/**
 * File: settings.php
 * Project: TV Binge Board
 * Description: User profile, avatar, sharing preferences, exports, imports, and account actions.
 * Author: Jason Lamb / ChatGPT
 * Created: 2026-07-02
 * Modified: 2026-07-02
 * Revision: 1.4.2
 */
declare(strict_types=1);


require_once __DIR__ . '/includes/functions.php';
$user = app_require_login();
$profile = app_profile($user['username']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    app_verify_csrf();
    $profile['display_name'] = trim((string)($_POST['display_name'] ?? $profile['display_name'] ?? $user['username']));
    $profile['bio'] = trim((string)($_POST['bio'] ?? ''));
    $profile['avatar_url'] = trim((string)($_POST['avatar_url'] ?? ''));
    $profile['public_share_enabled'] = isset($_POST['public_share_enabled']);
    app_save_profile($user['username'], $profile);

    $user['display_name'] = $profile['display_name'];
    $user['public_share_enabled'] = $profile['public_share_enabled'];
    app_update_account($user);

    app_flash('Settings saved.', 'success');
    header('Location: settings.php');
    exit;
}

app_page_header('Settings');
?>
<section class="card">
    <h1>Settings</h1>
    <form method="post" class="stack">
        <input type="hidden" name="csrf_token" value="<?= e(app_csrf_token()) ?>">
        <div class="profile-heading">
            <?= app_render_avatar($profile, (string)$user['username'], 64) ?>
            <div><strong><?= e((string)($profile['display_name'] ?? $user['username'])) ?></strong><p class="muted">@<?= e((string)$user['username']) ?></p></div>
        </div>
        <label>Display name
            <input name="display_name" value="<?= e((string)($profile['display_name'] ?? $user['display_name'] ?? $user['username'])) ?>">
        </label>
        <label>Avatar image URL
            <input name="avatar_url" value="<?= e((string)($profile['avatar_url'] ?? '')) ?>" placeholder="https://example.com/avatar.png">
        </label>
        <label>Bio
            <textarea name="bio" rows="3"><?= e((string)($profile['bio'] ?? '')) ?></textarea>
        </label>
        <?php if (app_can_track($user)): ?>
        <label class="checkbox-row">
            <input type="checkbox" name="public_share_enabled" value="1" <?= !empty($profile['public_share_enabled']) ? 'checked' : '' ?>>
            Share my list publicly
        </label>
        <p class="muted">Public URL: <a href="public.php?u=<?= e($user['username']) ?>">public.php?u=<?= e($user['username']) ?></a></p>
        <?php else: ?>
        <p class="muted">Admin accounts do not have public tracking lists.</p>
        <?php endif; ?>
        <button type="submit">Save settings</button>
    </form>
</section>
<section class="card">
    <h2>Data</h2>
    <?php if (app_can_track($user)): ?>
    <div class="actions">
        <a class="button secondary" href="export.php?format=json">Export JSON</a>
        <a class="button secondary" href="export.php?format=csv">Export CSV</a>
        <a class="button secondary" href="import.php">Import data</a>
        <a class="button secondary" href="upload-screenshot.php">Upload screenshot</a>
    </div>
    <?php else: ?>
    <p class="muted">Use the admin user list to export individual user libraries.</p>
    <?php endif; ?>
</section>
<section class="card">
    <h2>Account</h2>
    <div class="actions"><a class="button secondary" href="change-password.php">Change password</a><a class="button secondary" href="logout.php">Sign out</a></div>
</section>
<?php app_page_footer(); ?>
