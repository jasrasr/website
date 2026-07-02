<?php
/**
 * File: admin/users.php
 * Project: TV Binge Board
 * Description: Admin-only user list, account creation, account state controls, password reset, exports, and activity audit.
 * Author: Jason Lamb / ChatGPT
 * Created: 2026-07-02
 * Modified: 2026-07-02
 * Revision: 1.4.3
 */
declare(strict_types=1);


require_once __DIR__ . '/../includes/functions.php';
$user = app_require_admin();
$accounts = app_get_accounts()['users'];
$activity = app_activity_events(25);
app_page_header('Manage Users');
?>
<section class="card">
    <h1>Manage users</h1>
    <p>The admin account can create users, inspect and edit normal user libraries, reset passwords, and disable accounts. It does not track its own shows.</p>
    <div class="actions"><a class="button secondary" href="site-settings.php">Site settings</a></div>
</section>
<section class="card narrow">
    <h2>Create user</h2>
    <form method="post" action="../api/admin-user-action.php" class="stack">
        <input type="hidden" name="csrf_token" value="<?= e(app_csrf_token()) ?>">
        <input type="hidden" name="action" value="create_user">
        <label>Display name
            <input name="display_name" autocomplete="name">
        </label>
        <label>Username
            <input name="username" autocomplete="username" minlength="3" required>
        </label>
        <label>Password
            <input type="password" name="new_password" autocomplete="new-password" minlength="8" required>
        </label>
        <button type="submit">Create user</button>
    </form>
</section>
<section class="card">
    <div class="user-list">
    <?php foreach ($accounts as $account): $username = (string)($account['username'] ?? ''); ?>
        <article class="user-card stacked-card">
            <div class="user-card-main">
                <div>
                    <strong><?= e((string)($account['display_name'] ?? $username)) ?></strong>
                    <p class="muted">@<?= e($username) ?> · <?= e((string)$account['role']) ?> <?= !empty($account['public_share_enabled']) ? '· public' : '' ?> <?= !empty($account['disabled']) ? '· disabled' : '' ?></p>
                </div>
                <?php if (($account['role'] ?? '') !== 'admin'): ?>
                    <a class="button secondary" href="user-library.php?u=<?= e($username) ?>">Manage list</a>
                <?php else: ?>
                    <span class="pill admin">Admin</span>
                <?php endif; ?>
            </div>
            <?php if (($account['role'] ?? '') !== 'admin'): ?>
            <div class="actions small wrap-actions">
                <a class="button secondary" href="../export.php?format=json&u=<?= e($username) ?>">JSON</a>
                <a class="button secondary" href="../export.php?format=csv&u=<?= e($username) ?>">CSV</a>
                <form method="post" action="../api/admin-user-action.php">
                    <input type="hidden" name="csrf_token" value="<?= e(app_csrf_token()) ?>">
                    <input type="hidden" name="target_user" value="<?= e($username) ?>">
                    <button name="action" value="<?= !empty($account['disabled']) ? 'enable' : 'disable' ?>" class="secondary" type="submit"><?= !empty($account['disabled']) ? 'Enable' : 'Disable' ?></button>
                </form>
            </div>
            <details class="edit-panel"><summary>Reset password</summary>
                <form method="post" action="../api/admin-user-action.php" class="stack">
                    <input type="hidden" name="csrf_token" value="<?= e(app_csrf_token()) ?>">
                    <input type="hidden" name="target_user" value="<?= e($username) ?>">
                    <input type="hidden" name="action" value="reset_password">
                    <label>New password <input type="password" name="new_password" minlength="8" required></label>
                    <button type="submit">Reset password</button>
                </form>
            </details>
            <?php endif; ?>
        </article>
    <?php endforeach; ?>
    </div>
</section>
<section class="card">
    <h2>Activity log</h2>
    <?php if (!$activity): ?><p class="muted">No activity recorded yet.</p><?php endif; ?>
    <ul class="compact-list">
        <?php foreach ($activity as $event): ?>
            <li><strong><?= e((string)($event['action'] ?? 'event')) ?></strong> · actor: <?= e((string)($event['actor'] ?? '')) ?> · target: <?= e((string)($event['target'] ?? '')) ?> <span class="muted"><?= e((string)($event['at'] ?? '')) ?></span></li>
        <?php endforeach; ?>
    </ul>
</section>
<?php app_page_footer(); ?>
