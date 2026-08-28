<?php
declare(strict_types=1);
require_once __DIR__ . '/lib.php';
$done = count(read_store('users')) > 0;
$error = '';
if (!$done && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals(csrf(), $_POST['csrf'] ?? '')) $error = 'Session expired. Try again.';
    $name = trim($_POST['name'] ?? ''); $email = strtolower(trim($_POST['email'] ?? '')); $password = $_POST['password'] ?? '';
    if (!$error && ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 12)) $error = 'Enter a name, valid email, and password of at least 12 characters.';
    if (!$error) {
        $id = uuid();
        write_store('users', [['id'=>$id,'name'=>$name,'email'=>$email,'role'=>'super_admin','passwordHash'=>password_hash($password, PASSWORD_DEFAULT),'active'=>true,'createdAt'=>now_iso()]]);
        write_store('groups', []); write_store('students', frontlines_roster_students()); write_store('attendance', []); write_store('audit', []);
        $_SESSION['user'] = ['id'=>$id,'name'=>$name,'email'=>$email,'role'=>'super_admin'];
        header('Location: index.php'); exit;
    }
}
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Set up <?=e(APP_NAME)?></title><link rel="stylesheet" href="assets/app.css"></head><body class="auth-page"><main class="auth-card"><div class="brand-mark">LG</div><p class="eyebrow">FIRST-RUN SETUP</p><h1><?=e(APP_NAME)?></h1><?php if($done): ?><p>Setup is already complete.</p><a class="button primary" href="index.php">Open portal</a><?php else: ?><p>Create the first administrator. Setup locks automatically afterward.</p><?php if($error): ?><div class="alert error"><?=e($error)?></div><?php endif; ?><form method="post" class="stack"><input type="hidden" name="csrf" value="<?=e(csrf())?>"><label>Your name<input name="name" required autocomplete="name"></label><label>Email<input type="email" name="email" required autocomplete="email"></label><label>Password<input type="password" name="password" required minlength="12" autocomplete="new-password"></label><button class="button primary">Create administrator</button></form><?php endif; ?></main></body></html>
