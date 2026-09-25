<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header("Content-Security-Policy: default-src 'none'; style-src 'self'; form-action 'self'; frame-ancestors 'none'; base-uri 'none'");
function e(string $value): string { return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function field(array $input, string $key): string { return is_string($input[$key] ?? null) ? $input[$key] : ''; }
$error = '';
try {
    $auth = jasr_users_auth();
    $config = jasr_users_config();
    $directory = $auth->directory;
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!$auth->validCsrf($_POST['csrf'] ?? null)) { http_response_code(403); exit('Invalid form token. Reload the page.'); }
        // Reject nested/array values before passing any form data to typed domain methods.
        foreach ($_POST as $value) if (!is_string($value)) throw new InvalidArgumentException('Invalid form data.');
        $action = field($_POST, 'action');
        $user = $auth->user();
        if ($action === 'setup') {
            $key = (string)$config['setup_key'];
            if (strlen($key) < 32 || !hash_equals($key, field($_POST, 'setup_key'))) {
                http_response_code(403); throw new InvalidArgumentException('Setup key is incorrect or browser setup is disabled.');
            }
            if (field($_POST, 'password') !== field($_POST, 'confirm_password')) throw new InvalidArgumentException('Passwords do not match.');
            $directory->setup(field($_POST, 'username'), field($_POST, 'name'), field($_POST, 'password'));
        } elseif ($action === 'login') {
            if (!$auth->login(field($_POST, 'username'), field($_POST, 'password'))) {
                http_response_code(401); throw new InvalidArgumentException('Sign-in failed. Check your credentials or wait 15 minutes if attempts were limited.');
            }
        } elseif ($action === 'logout') {
            $auth->logout();
        } elseif ($action === 'change-password' && $user) {
            if (field($_POST, 'password') !== field($_POST, 'confirm_password')) throw new InvalidArgumentException('Passwords do not match.');
            $directory->changePassword($user['id'], $user['version'], field($_POST, 'current_password'), field($_POST, 'password'));
            $auth->logout(); // All browsers must sign in with the new password.
        } elseif ($user && $user['admin'] && !$user['mustChangePassword']) {
            $directory->administer($user['id'], $user['version'], $action, $_POST);
        } else {
            http_response_code(403); throw new InvalidArgumentException('Access denied.');
        }
        header('Location: ' . $auth->portal(), true, 303); exit;
    }
    $state = $directory->read();
    $user = $auth->user();
} catch (InvalidArgumentException $exception) {
    if (http_response_code() < 400) http_response_code(400);
    $error = $exception->getMessage();
    $state = $directory->read();
    $user = $auth->user();
} catch (Throwable $exception) {
    error_log('User management: ' . $exception->getMessage());
    http_response_code(503);
    exit('User management is unavailable. Check HTTPS, configuration, and private storage permissions.');
}
function form(string $action): void
{
    global $auth;
    echo '<form method="post" action="' . e($auth->portal()) . '"><input type="hidden" name="csrf" value="' . e($auth->csrf()) . '"><input type="hidden" name="action" value="' . e($action) . '">';
}
function passwordFields(): void
{
    echo '<label>New password <input type="password" name="password" minlength="12" maxlength="72" autocomplete="new-password" required></label><label>Confirm password <input type="password" name="confirm_password" minlength="12" maxlength="72" autocomplete="new-password" required></label>';
}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>User Management · JASR</title><link rel="stylesheet" href="<?= e($auth->portal()) ?>style.css"></head><body><main>
<header><p class="eyebrow">JASR · Shared accounts</p><h1>User Management</h1><p>One account. Access to the projects you need.</p></header>
<?php if ($error !== ''): ?><p class="notice" role="alert"><?= e($error) ?></p><?php endif ?>
<?php if (!$state['users']): ?>
<section><h2>Create the first administrator</h2>
<?php if (strlen((string)$config['setup_key']) >= 32): ?>
<p>Use the private setup key configured on your server. This form closes after the first account is created.</p>
<?php form('setup'); ?><label>Setup key <input type="password" name="setup_key" autocomplete="off" required></label><label>Username <input name="username" autocomplete="username" required></label><label>Display name <input name="name" required></label><?php passwordFields(); ?><button>Create administrator</button></form>
<?php else: ?><p>Browser setup is disabled. Set a private setup key in <code>config.local.php</code> or run the CLI setup described in the README.</p><?php endif ?></section>
<?php elseif (!$user): ?>
<section class="compact"><h2>Sign in</h2><?php form('login'); ?><label>Username <input name="username" autocomplete="username" maxlength="64" required></label><label>Password <input type="password" name="password" autocomplete="current-password" maxlength="72" required></label><button>Sign in</button></form><p>Need a password reset? Ask your site administrator.</p></section>
<?php else: ?>
<div class="account"><p>Signed in as <strong><?= e($user['name']) ?></strong></p><?php form('logout'); ?><button class="secondary">Sign out of all connected projects in this browser</button></form></div>
<?php if ($user['mustChangePassword']): ?><p class="notice">Change your temporary password before opening a project or managing users.</p><?php else: ?>
<section><h2>Your projects</h2><div class="projects"><?php foreach ($state['projects'] as $id => $project): if (!$auth->can($id)) continue; ?><a class="project" href="<?= e($project['path']) ?>"><strong><?= e($project['name']) ?></strong><span><?= e($user['admin'] ? 'Site administrator' : $user['projects'][$id]) ?></span></a><?php endforeach ?></div><p class="muted">An administrator grants access here. Each project must also install the server-side integration to enforce it.</p></section>
<?php endif ?>
<section><h2>Change password</h2><p>Changing your password signs out every browser. Use 12–72 bytes.</p><?php form('change-password'); ?><label>Current password <input type="password" name="current_password" autocomplete="current-password" required></label><?php passwordFields(); ?><button>Change password and sign out</button></form></section>
<?php if ($user['admin'] && !$user['mustChangePassword']): ?>
<section><h2>Link existing project accounts</h2><p>Preview and validate account mappings before enabling shared login. Existing data stays in place.</p><p><a href="<?= e($auth->portal()) ?>links.php">Open account linking — Finances pilot</a></p></section>
<section><h2>Register a project</h2><p>Register its URL, then add the framework guard to its PHP routes. This does not migrate an existing app automatically.</p><?php form('save-project'); ?><label>Project ID <input name="project" placeholder="mpg" pattern="[a-z0-9][a-z0-9_-]{0,63}" required></label><label>Display name <input name="name" placeholder="Fuel log" maxlength="120" required></label><label>Project path <input name="path" placeholder="/mpg/" required></label><button>Save project</button></form><ul><?php foreach ($state['projects'] as $id => $p): ?><li><code><?= e($id) ?></code> — <?= e($p['name']) ?> (<?= e($p['path']) ?>)</li><?php endforeach ?></ul></section>
<section><h2>Add a user</h2><?php form('create-user'); ?><label>Username <input name="username" autocomplete="off" pattern="[a-zA-Z0-9][a-zA-Z0-9._-]{2,63}" required></label><label>Display name <input name="name" maxlength="120" required></label><label>Temporary password <input type="password" name="password" minlength="12" maxlength="72" autocomplete="new-password" required></label><label class="check"><input type="checkbox" name="admin"> Site administrator (all registered projects)</label><button>Create user</button></form></section>
<section><h2>Accounts and access</h2><p>Account changes revoke all of that user’s sessions. Disabled accounts retain their ID and data associations.</p>
<?php foreach ($state['users'] as $managed): ?>
<article><h3><?= e($managed['name']) ?> <small>@<?= e($managed['username']) ?></small></h3><p><?= $managed['active'] ? 'Active' : 'Disabled' ?> · <?= $managed['admin'] ? 'Site administrator' : 'Project user' ?><?= $managed['mustChangePassword'] ? ' · Password change required' : '' ?></p>
<?php form('save-user'); ?><input type="hidden" name="id" value="<?= e($managed['id']) ?>"><label class="check"><input type="checkbox" name="active" <?= $managed['active'] ? 'checked' : '' ?>> Active</label><label class="check"><input type="checkbox" name="admin" <?= $managed['admin'] ? 'checked' : '' ?>> Site administrator</label><button>Save account</button></form>
<?php form('grant'); ?><input type="hidden" name="id" value="<?= e($managed['id']) ?>"><label>Project <select name="project" required><option value="">Choose a project</option><?php foreach ($state['projects'] as $id => $p): ?><option value="<?= e($id) ?>"><?= e($p['name']) ?> — <?= e($managed['projects'][$id] ?? 'No access') ?></option><?php endforeach ?></select></label><label>Access <select name="role"><option value="">No access</option><option value="viewer">Viewer</option><option value="member">Member</option><option value="admin">Project admin</option></select></label><button>Save access</button></form>
<details><summary>Reset password</summary><?php form('reset-password'); ?><input type="hidden" name="id" value="<?= e($managed['id']) ?>"><label>Temporary password <input type="password" name="password" minlength="12" maxlength="72" autocomplete="new-password" required></label><button>Reset password and revoke sessions</button></form></details></article>
<?php endforeach ?></section>
<?php endif; endif ?>
<footer>User Management · v1.1.0</footer></main></body></html>
