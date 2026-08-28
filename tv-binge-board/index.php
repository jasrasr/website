<?php
/**
 * File: index.php
 * Project: TV Binge Board
 * Description: Entry point that routes authenticated users to the dashboard and shows guests sign-in and registration options.
 * Author: Jason Lamb / ChatGPT
 * Created: 2026-07-02
 * Modified: 2026-07-02
 * Revision: 1.4.3
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';

if (app_current_user()) {
    header('Location: dashboard.php');
    exit;
}

app_page_header('Welcome');
?>
<section class="card narrow">
    <h1><?= e(APP_NAME) ?></h1>
    <p>Track TV shows and movies, manage your watchlist, and share progress with connected users.</p>
    <div class="actions">
        <a class="button" href="login.php">Sign in</a>
        <?php if (app_public_registration_enabled()): ?>
            <a class="button secondary" href="register.php">Create account</a>
        <?php endif; ?>
    </div>
    <?php if (!app_public_registration_enabled()): ?>
        <p class="muted">Public registration is currently disabled. Ask the site admin to create an account.</p>
    <?php endif; ?>
</section>
<?php app_page_footer(); ?>
