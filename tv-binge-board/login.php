<?php
/**
 * File: login.php
 * Project: TV Binge Board
 * Description: Authenticates users against the JSON account store with rate limiting and disabled-account awareness.
 * Author: Jason Lamb / ChatGPT
 * Created: 2026-07-02
 * Modified: 2026-07-02
 * Revision: 1.4.2
 */
declare(strict_types=1);


require_once __DIR__ . '/includes/functions.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    app_verify_csrf();
    $username = (string)($_POST['username'] ?? '');
    $password = (string)($_POST['password'] ?? '');
    if (app_login($username, $password)) {
        header('Location: dashboard.php');
        exit;
    }
    $error = app_login_is_limited($username)
        ? 'Too many failed attempts. Wait 15 minutes and try again.'
        : 'Invalid username, password, or disabled account.';
}

app_page_header('Sign in');
?>
<section class="card narrow">
    <h1>Sign in</h1>
    <?php if ($error): ?><div class="alert danger"><?= e($error) ?></div><?php endif; ?>
    <form method="post" class="stack">
        <input type="hidden" name="csrf_token" value="<?= e(app_csrf_token()) ?>">
        <label>Username
            <input name="username" autocomplete="username" required>
        </label>
        <label>Password
            <input type="password" name="password" autocomplete="current-password" required>
        </label>
        <button type="submit">Sign in</button>
    </form>
    <?php if (app_public_registration_enabled()): ?>
        <p class="muted">New here? <a href="register.php">Create an account</a>.</p>
    <?php else: ?>
        <p class="muted">Public registration is currently disabled.</p>
    <?php endif; ?>
</section>
<?php app_page_footer(); ?>
