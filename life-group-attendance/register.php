<?php
declare(strict_types=1);
require_once __DIR__ . '/registration.php';
header('Cache-Control: no-store');
if (!read_store('users')) { header('Location: setup.php'); exit; }
if (user()) { header('Location: index.php'); exit; }
$error = ''; $result = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals(csrf(), (string)($_POST['csrf'] ?? ''))) {
        $error = 'Session expired. Refresh and try again.';
    } elseif (time() - ($_SESSION['registrationAttempt'] ?? 0) < 30) {
        $error = 'Please wait 30 seconds before trying again.';
    } else {
        $_SESSION['registrationAttempt'] = time();
        try {
            $result = register_leader($_POST, (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        } catch (InvalidArgumentException $e) {
            $error = $e->getMessage();
        } catch (Throwable $e) {
            $error = 'Registration is temporarily unavailable. Please contact a Super Admin.';
        }
    }
}
?><!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Register as a leader · <?=e(APP_NAME)?></title><link rel="stylesheet" href="<?=e(asset_url('assets/app.css'))?>"></head>
<body class="auth-page"><main class="auth-card"><div class="brand-mark">LG</div><p class="eyebrow">LIFE GROUP TEAM</p>
<h1>Leader registration</h1><p>Request your own login. A Super Admin must approve it before you can access students or attendance.</p>
<?php if ($result): ?>
<div class="alert success" role="status">
<?php if ($result['status'] === 'pending'): ?>
<?= $result['created'] ? 'Your new leader registration was saved and verified.' : 'Your existing leader registration is still awaiting approval.' ?> A Super Admin must approve it before you can sign in. No email notification is sent.
<p><strong>Registration reference: <?=e(substr($result['id'],0,12))?></strong></p>
<?php elseif ($result['status'] === 'active'): ?>
Your account already exists and is active. No new request was created and your password was not changed. You can sign in.
<?php else: ?>
Your account exists but is disabled. Please contact your Super Admin. No account or password was changed.
<?php endif; ?>
</div>
<?php else: ?>
<?php if ($error): ?><div class="alert error" role="alert"><?=e($error)?></div><?php endif; ?>
<form method="post" class="stack">
<input type="hidden" name="csrf" value="<?=e(csrf())?>">
<label>Your name<input name="name" required maxlength="120" autocomplete="name" value="<?=e((string)($_POST['name'] ?? ''))?>"></label>
<label>Email<input type="email" name="email" required maxlength="254" autocomplete="email" value="<?=e((string)($_POST['email'] ?? ''))?>"></label>
<label>Password<input type="password" name="password" required minlength="12" maxlength="72" autocomplete="new-password"></label>
<label>Confirm password<input type="password" name="confirmPassword" required minlength="12" maxlength="72" autocomplete="new-password"></label>
<small class="muted">Use at least 12 characters. You will receive the Attendance role, which can take attendance and manage students.</small>
<button class="button primary">Request leader account</button>
</form>
<?php endif; ?>
<p><a class="button" href="login.php">Back to sign in</a></p>
<small class="muted">Version <?=e(APP_VERSION)?> · Updated <?=e(APP_UPDATED)?></small>
</main></body></html>
