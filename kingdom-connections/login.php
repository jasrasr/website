<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
$error='';
if ($_SERVER['REQUEST_METHOD']==='POST') {
    try {
        if (!$auth->validCsrf($_POST['csrf'] ?? null)) throw new InvalidArgumentException('Reload the page and try again.');
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
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Kingdom Connections login</title><link rel="stylesheet" href="assets/css/app.css"></head><body><?php if (!empty($config['demo'])): ?><aside class="notice">Demo directory · fictional businesses and simulated payments</aside><?php endif ?><header><a class="brand" href="./">Kingdom Connections</a></header><main><h1>Your account</h1><?php if($error): ?><p role="alert"><?=h($error)?></p><?php endif ?><div class="grid"><?php foreach ([false,true] as $register): ?><form class="card editor" method="post"><h2><?=$register?'Create an account':'Sign in'?></h2><input type="hidden" name="csrf" value="<?=h($auth->csrf())?>"><?php if($register): ?><label>Account type<select name="role"><option value="customer">Customer</option><option value="business">Business</option></select></label><label>Name<input required name="name"></label><?php endif ?><label>Username<input required name="username" autocomplete="username"></label><label>Password<input required type="password" name="password" autocomplete="<?=$register?'new-password':'current-password'?>" <?=$register?'minlength="12"':''?>></label><button <?=$register?'name="register"':''?>><?=$register?'Register':'Sign in'?></button></form><?php endforeach ?></div></main></body></html>
