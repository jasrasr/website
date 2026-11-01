<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';
header('X-Content-Type-Options: nosniff');
header("Content-Security-Policy: default-src 'none'; style-src 'self'; form-action 'self'; frame-ancestors 'none'");
header('Referrer-Policy: no-referrer');
$auth = jasr_users_auth(); $config = jasr_users_config(); $error = ''; $sent = false;
function e(string $s): string { return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!$auth->validCsrf($_POST['csrf'] ?? null)) { http_response_code(403); exit('Invalid form token.'); }
        foreach ($_POST as $value) if (!is_string($value)) throw new InvalidArgumentException('Invalid form.');
        if (($_POST['password'] ?? '') !== ($_POST['confirm'] ?? '')) throw new InvalidArgumentException('Passwords do not match.');
        $auth->directory->requestRegistration($_POST['username'] ?? '', $_POST['name'] ?? '', $_POST['email'] ?? '', $_POST['password'] ?? '', $_SERVER['REMOTE_ADDR'] ?? 'unknown');
        // Recipient is server configuration only; never use applicant input in mail headers.
        $to = $config['registration_notify_email'] ?? '';
        if (is_string($to) && filter_var($to, FILTER_VALIDATE_EMAIL) && !preg_match('/[\r\n]/', $to)) {
            try { if (!mail($to, 'JASR: new account awaiting approval', 'A new account request is awaiting your approval. Sign in to User Management and open Pending new users. No access has been granted.')) error_log('Registration notification could not be handed to mail transport.'); } catch (Throwable $mailError) { error_log('Registration notification transport unavailable.'); }
        } else error_log('Registration notification recipient is not configured.');
        $sent = true;
    } catch (InvalidArgumentException $ex) { http_response_code(400); $error = $ex->getMessage(); }
    catch (Throwable $ex) { http_response_code(503); $error = 'Unable to submit registration. Contact the administrator.'; }
}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Request an account</title><link rel="stylesheet" href="<?= e($auth->portal()) ?>style.css"></head><body><main><h1>Add new user</h1><p>Your account requires Jason’s approval. Project access is assigned separately.</p>
<?php if ($error): ?><p role="alert"><?= e($error) ?></p><?php endif ?>
<?php if ($sent): ?><p role="status">Request received. You cannot sign in until your account is approved.</p><?php else: ?>
<form method="post"><input type="hidden" name="csrf" value="<?= e($auth->csrf()) ?>"><label>Username<input name="username" required minlength="3" maxlength="64" autocomplete="username"></label><label>Name<input name="name" required maxlength="120" autocomplete="name"></label><label>Email<input name="email" type="email" required maxlength="254" autocomplete="email"></label><label>Password<input name="password" type="password" required minlength="12" maxlength="72" autocomplete="new-password"></label><label>Confirm password<input name="confirm" type="password" required minlength="12" maxlength="72" autocomplete="new-password"></label><button>Request account</button></form><?php endif ?><p><a href="<?= e($auth->portal()) ?>">Back to sign in</a></p></main></body></html>
