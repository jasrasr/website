<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
require_once __DIR__.'/FirstAdmin.php';
$firstInstall = !$auth->accounts() && empty($config['demo']);
$error='';
if ($_SERVER['REQUEST_METHOD']==='POST') {
    try {
        if (!$auth->validCsrf($_POST['csrf'] ?? null)) throw new InvalidArgumentException('Reload the page and try again.');
        if (isset($_POST['first_admin'])) {
            if (!$firstInstall) throw new InvalidArgumentException('The directory already has accounts.');
            foreach (['temporary', 'new_password', 'confirmation'] as $field) {
                if (!is_string($_POST[$field] ?? null) || strlen($_POST[$field]) > ($field === 'temporary' ? 512 : 72)) {
                    throw new InvalidArgumentException('Check the password fields.');
                }
            }
            FirstAdmin::complete($auth, $_POST['temporary'], $_POST['new_password'], $_POST['confirmation']);
            header('Location: login.php?setup=complete', true, 303);
            exit;
        }
        $username=KingdomDirectory::text($_POST,'username',60,true);
        $password=$_POST['password'] ?? '';
        if (!is_string($password) || strlen($password)>72) throw new InvalidArgumentException('Check the password.');
        if (isset($_POST['register'])) {
            $role=$_POST['role'] ?? '';
            if (!in_array($role,['customer','business'],true)) throw new InvalidArgumentException('Select a business or customer account.');
            $auth->account('', $username, KingdomDirectory::text($_POST,'name',160,true),$role,$password);
        }
        $auth->signIn($username,$password);
        header('Location: portal.php',true,303); exit;
    } catch (InvalidArgumentException $e) { $error=$e->getMessage(); }
}
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Kingdom Connections login</title><link rel="stylesheet" href="assets/css/app.css"></head><body><?php if (!empty($config['demo'])): ?><aside class="notice">Demo directory · fictional businesses and simulated payments</aside><?php endif ?><header><a class="brand" href="./">Kingdom Connections</a></header><main><h1>Your account</h1><?php if($error): ?><p role="alert"><?=h($error)?></p><?php endif ?><?php if ($firstInstall): ?>
<section class="card">
<h2>Set up your super admin login</h2>
<p>Use the temporary password and choose a new password before accessing the dashboard. Your username is <strong>super-admin</strong>.</p>
<form class="editor" method="post">
<input type="hidden" name="csrf" value="<?=h($auth->csrf())?>">
<label>Temporary password<input type="password" name="temporary" required maxlength="512" autocomplete="off" autocapitalize="none" spellcheck="false"></label>
<label>New password<input type="password" name="new_password" required minlength="12" maxlength="72" autocomplete="new-password"></label>
<label>Confirm new password<input type="password" name="confirmation" required minlength="12" maxlength="72" autocomplete="new-password"></label>
<label><input type="checkbox" id="show-setup-passwords" style="width:auto;display:inline"> Show passwords</label>
<button name="first_admin">Set password and create super admin</button>
</form>
</section>
<script>
document.getElementById('show-setup-passwords').addEventListener('change', function () {
    const form = this.closest('form');
    for (const name of ['temporary', 'new_password', 'confirmation']) {
        form.elements[name].type = this.checked ? 'text' : 'password';
    }
});
</script>
<?php else: ?>
<?php if (($_GET['setup'] ?? '') === 'complete'): ?><p role="status">Your super admin account is ready. Sign in as super-admin with your new password.</p><?php endif ?>
<div class="grid"><?php foreach ([false,true] as $register): ?><form class="card editor" method="post"><h2><?=$register?'Create an account':'Sign in'?></h2><input type="hidden" name="csrf" value="<?=h($auth->csrf())?>"><?php if($register): ?><label>Account type<select name="role"><option value="customer">Customer</option><option value="business">Business</option></select></label><label>Name<input required name="name"></label><?php endif ?><label>Username<input required name="username" autocomplete="username"></label><label>Password<input required type="password" name="password" autocomplete="<?=$register?'new-password':'current-password'?>" <?=$register?'minlength="12"':''?>></label><button <?=$register?'name="register"':''?>><?=$register?'Register':'Sign in'?></button></form><?php endforeach ?></div><?php endif ?></main></body></html>
