<?php
/**
 * File: register.php
 * Project: TV Binge Board
 * Description: Self-service account registration for normal tracking users, controlled by site settings.
 * Author: Jason Lamb / ChatGPT
 * Created: 2026-07-02
 * Modified: 2026-07-02
 * Revision: 1.4.2
 */
declare(strict_types=1);


require_once __DIR__ . '/includes/functions.php';

$error = '';
if (!app_public_registration_enabled()) {
    app_page_header('Register');
    echo '<section class="card narrow"><h1>Registration disabled</h1><p>Ask the site admin to create or enable an account.</p><p><a href="login.php">Back to sign in</a></p></section>';
    app_page_footer();
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    app_verify_csrf();
    try {
        app_create_user((string)$_POST['username'], (string)$_POST['password'], (string)($_POST['display_name'] ?? ''));
        app_login((string)$_POST['username'], (string)$_POST['password']);
        header('Location: dashboard.php');
        exit;
    } catch (Throwable $ex) {
        $error = $ex->getMessage();
    }
}

app_page_header('Register');
?>
<section class="card narrow">
    <h1>Create account</h1>
    <?php if ($error): ?><div class="alert danger"><?= e($error) ?></div><?php endif; ?>
    <form method="post" class="stack">
        <input type="hidden" name="csrf_token" value="<?= e(app_csrf_token()) ?>">
        <label>Display name
            <input name="display_name" autocomplete="name">
        </label>
        <label>Username
            <input name="username" autocomplete="username" minlength="3" required>
        </label>
        <label>Password
            <input type="password" name="password" autocomplete="new-password" minlength="8" required>
        </label>
        <button type="submit">Create account</button>
    </form>
    <p class="muted"><a href="login.php">Back to sign in</a></p>
</section>
<?php app_page_footer(); ?>
