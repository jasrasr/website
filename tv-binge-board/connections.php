<?php
/**
 * File: connections.php
 * Project: TV Binge Board
 * Description: User discovery and simple mutual connection management for shared lists with avatar display.
 * Author: Jason Lamb / ChatGPT
 * Created: 2026-07-02
 * Modified: 2026-07-02
 * Revision: 1.4.2
 */
declare(strict_types=1);


require_once __DIR__ . '/includes/functions.php';
$user = app_require_login();
$connections = app_connections($user['username']);
$accounts = app_get_accounts()['users'];
app_page_header('Connections');
if (!app_can_track($user)):
?>
<section class="card"><h1>Connections</h1><p>Admin accounts do not create watch-list connections.</p></section>
<?php else: ?>
<section class="card"><h1>Connections</h1><p>Connect with other users to share lists without making everything fully public.</p></section>
<?php if ($connections['incoming_requests']): ?>
<section class="card"><h2>Incoming requests</h2><?php foreach ($connections['incoming_requests'] as $from): ?><form method="post" action="api/respond-connection.php" class="inline-form"><input type="hidden" name="csrf_token" value="<?= e(app_csrf_token()) ?>"><input type="hidden" name="from_user" value="<?= e($from) ?>"><span><?= e($from) ?></span><button name="response" value="accept">Accept</button><button name="response" value="decline" class="secondary">Decline</button></form><?php endforeach; ?></section>
<?php endif; ?>
<section class="card"><h2>People</h2><div class="user-list">
<?php foreach ($accounts as $account):
    $username = (string)($account['username'] ?? '');
    if ($username === $user['username'] || ($account['role'] ?? '') === 'admin' || !empty($account['disabled'])) continue;
    $profile = app_profile($username);
    $connected = in_array($username, $connections['connections'], true);
    $pending = in_array($username, $connections['outgoing_requests'], true);
?>
<article class="user-card"><div class="profile-heading small-profile"><?= app_render_avatar($profile, $username, 44) ?><div><strong><?= e((string)($profile['display_name'] ?? $account['display_name'] ?? $username)) ?></strong><p class="muted">@<?= e($username) ?> <?= !empty($profile['public_share_enabled']) ? '· public list' : '' ?></p></div></div><div class="actions small"><?php if (app_can_view_library($user, $username)): ?><a class="button secondary" href="public.php?u=<?= e($username) ?>">View</a><?php endif; ?><?php if ($connected): ?><span class="pill success">Connected</span><?php elseif ($pending): ?><span class="pill">Pending</span><?php else: ?><form method="post" action="api/request-connection.php"><input type="hidden" name="csrf_token" value="<?= e(app_csrf_token()) ?>"><input type="hidden" name="target_user" value="<?= e($username) ?>"><button type="submit">Connect</button></form><?php endif; ?></div></article>
<?php endforeach; ?>
</div></section>
<?php endif; app_page_footer(); ?>
