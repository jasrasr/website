<?php
/**
 * File: admin/site-settings.php
 * Project: TV Binge Board
 * Description: Admin-only site configuration page for registration and global operational settings.
 * Author: Jason Lamb / ChatGPT
 * Created: 2026-07-02
 * Modified: 2026-07-02
 * Revision: 1.4.2
 */
declare(strict_types=1);


require_once __DIR__ . '/../includes/functions.php';
$admin = app_require_admin();
$settings = app_get_settings();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    app_verify_csrf();
    $settings['public_registration_enabled'] = isset($_POST['public_registration_enabled']);
    app_save_settings($settings);
    app_log_activity((string)$admin['username'], 'site-settings-updated', 'settings', ['public_registration_enabled' => $settings['public_registration_enabled']]);
    app_flash('Site settings saved.', 'success');
    header('Location: site-settings.php');
    exit;
}
app_page_header('Site Settings');
?>
<section class="card narrow">
    <h1>Site settings</h1>
    <form method="post" class="stack">
        <input type="hidden" name="csrf_token" value="<?= e(app_csrf_token()) ?>">
        <label class="checkbox-row"><input type="checkbox" name="public_registration_enabled" value="1" <?= !empty($settings['public_registration_enabled']) ? 'checked' : '' ?>> Allow public registration</label>
        <button type="submit">Save settings</button>
    </form>
</section>

<section class="card narrow">
    <h2>Artwork cache maintenance</h2>
    <p class="muted">Remove cached poster and episode still files that are no longer referenced by any tracked library item.</p>
    <form method="post" action="<?= e(app_href('api/cleanup-artwork.php')) ?>" onsubmit="return confirm('Remove unused cached artwork files?');" class="stack">
        <input type="hidden" name="csrf_token" value="<?= e(app_csrf_token()) ?>">
        <input type="hidden" name="redirect" value="../admin/site-settings.php">
        <button class="secondary" type="submit">Remove unused artwork</button>
    </form>
</section>
<?php app_page_footer(); ?>
