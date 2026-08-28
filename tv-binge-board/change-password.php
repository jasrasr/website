<?php
/**
 * File: change-password.php
 * Project: TV Binge Board
 * Description: Lets signed-in users change their own password after confirming the current password.
 * Author: Jason Lamb / ChatGPT
 * Created: 2026-07-02
 * Modified: 2026-07-02
 * Revision: 1.4.2
 */
declare(strict_types=1);


require_once __DIR__ . '/includes/functions.php';
$user = app_require_login();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    app_verify_csrf();
    try {
        $ok = app_change_password((string)$user['username'], (string)($_POST['current_password'] ?? ''), (string)($_POST['new_password'] ?? ''));
        if ($ok) {
            app_flash('Password changed.', 'success');
            header('Location: settings.php');
            exit;
        }
        $error = 'Current password was not correct.';
    } catch (Throwable $ex) {
        $error = $ex->getMessage();
    }
}

app_page_header('Change Password');
?>
<section class="card narrow">
    <h1>Change password</h1>
    <?php if ($error): ?><div class="alert danger"><?= e($error) ?></div><?php endif; ?>
    <form method="post" class="stack">
        <input type="hidden" name="csrf_token" value="<?= e(app_csrf_token()) ?>">
        <label>Current password <input type="password" name="current_password" autocomplete="current-password" required></label>
        <label>New password <input type="password" name="new_password" autocomplete="new-password" minlength="8" required></label>
        <button type="submit">Change password</button>
    </form>
</section>
<?php app_page_footer(); ?>
