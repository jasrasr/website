<?php
declare(strict_types=1);
require_once __DIR__ . '/lib.php';
if (!read_store('users')) { header('Location: setup.php'); exit; }
if (user()) { header('Location: index.php'); exit; }
header('Cache-Control: no-store');
$error='';
if ($_SERVER['REQUEST_METHOD']==='POST') {
    if (!hash_equals(csrf(), $_POST['csrf'] ?? '')) $error='Session expired. Try again.';
    $email=strtolower(trim($_POST['email'] ?? '')); $password=$_POST['password'] ?? '';
    if (!$error) foreach (read_store('users') as $u) if (!($u['pendingRegistration'] ?? false) && ($u['active'] ?? true) && hash_equals($u['email'], $email) && password_verify($password, $u['passwordHash'])) {
        $role=($u['role']??'attendance')==='admin'?'super_admin':($u['role']??'attendance'); session_regenerate_id(true); $_SESSION['user']=['id'=>$u['id'],'name'=>$u['name'],'email'=>$u['email'],'role'=>$role]; csrf(); audit('login','user',$u['id']); header('Location: index.php'); exit;
    }
    if (!$error) $error='Email or password is incorrect.';
}
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Sign in · <?=e(APP_NAME)?></title><link rel="stylesheet" href="<?=e(asset_url('assets/app.css'))?>"></head><body class="auth-page"><main class="auth-card"><div class="brand-mark">LG</div><p class="eyebrow">LIFE GROUP TEAM</p><h1>Welcome back</h1><p>Sign in to record tonight’s attendance.</p><?php if($error): ?><div class="alert error"><?=e($error)?></div><?php endif; ?><form method="post" class="stack"><input type="hidden" name="csrf" value="<?=e(csrf())?>"><label>Email<input type="email" name="email" required autofocus autocomplete="email"></label><label>Password<input type="password" name="password" required autocomplete="current-password"></label><button class="button primary">Sign in</button></form><p class="registration-link">New leader? <a href="register.php">Register for an account</a></p><small class="muted">Version <?=e(APP_VERSION)?> · Updated <?=e(APP_UPDATED)?></small></main></body></html>
