<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
$user = $auth->requireRole('super','customer','business');
$super = $user['role'] === 'super';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$auth->validCsrf($_POST['csrf'] ?? null)) { http_response_code(403); exit('Reload the form.'); }
    try {
        $action = KingdomDirectory::text($_POST,'action',40,true);
        $id = KingdomDirectory::text($_POST,'id',80);
        if ($action === 'logout') {
            $_SESSION = []; session_destroy(); header('Location: ./',true,303); exit;
        } elseif ($action === 'seed-demo' && $super) {
            if (($_POST['confirm'] ?? '') !== 'yes') throw new InvalidArgumentException('Confirm adding fictional demonstration data.');
            $_SESSION['demo_credentials'] = DemoData::populate($config['storage_path'], $auth, $directory, $user['id']);
        } elseif ($action === 'account' && $super) {
            $role = KingdomDirectory::text($_POST,'role',20,true);
            if (!in_array($role,['business','customer'],true)) throw new InvalidArgumentException('Choose a business or customer.');
            $id = $auth->account($id, KingdomDirectory::text($_POST,'username',60,true), KingdomDirectory::text($_POST,'name',160,true), $role, KingdomDirectory::text($_POST,'password',72));
        } elseif ($action === 'remove-account' && $super) {
            if (($_POST['confirm'] ?? '') !== 'yes') throw new InvalidArgumentException('Confirm permanent deletion.');
            $account = $auth->accounts()[$id] ?? [];
            if (!in_array($account['role'] ?? '',['customer','business'],true)) throw new InvalidArgumentException('Choose a customer or business account.');
            $directory->removeAccountData($id);
            $auth->remove($id);
        } elseif ($action === 'profile' && ($super || $user['role']==='customer')) {
            $target = $super ? $id : $user['id'];
            if (($auth->accounts()[$target]['role'] ?? '') !== 'customer') throw new InvalidArgumentException('Choose a customer account.');
            $directory->saveCustomer($target,$_POST);
        } elseif ($super || ($user['role']==='customer' && in_array($action,['claim','review'],true))) {
            if (str_starts_with($action,'delete-') && ($_POST['confirm'] ?? '') !== 'yes') throw new InvalidArgumentException('Confirm permanent deletion.');
            $actor = $super && $action==='claim' ? KingdomDirectory::text($_POST,'customer',80,true) : $user['id'];
            $directory->serviceAction($action,$_POST,$actor,$super);
        } else throw new InvalidArgumentException('Action unavailable.');
        header('Location: portal.php?saved=1',true,303); exit;
    } catch (InvalidArgumentException $e) { $error=$e->getMessage(); http_response_code(422); }
    catch (Throwable $e) { $error='Unable to save changes. Check storage and try again.'; http_response_code(503); }
}
function formStart(string $action,string $id='',string $key=''): void {
    global $auth;
    echo '<form method="post" class="editor"><input type="hidden" name="csrf" value="'.h($auth->csrf()).'">';
    foreach (compact('action','id','key') as $field=>$value) echo '<input type="hidden" name="'.h($field).'" value="'.h($value).'">';
}
function field(string $name,string $label,string $value='',bool $required=true): void {
    echo '<label>'.h($label).'<input name="'.h($name).'" value="'.h($value).'" '.($required?'required':'').'></label>';
}
function finish(string $label,bool $confirm=false): void {
    if ($confirm) echo '<label><input style="width:auto;display:inline" type="checkbox" required name="confirm" value="yes"> Confirm permanent deletion</label>';
    echo '<button>'.h($label).'</button></form>';
}
function stars(): void { echo '<label>Stars<select name="stars">'; foreach(range(1,5) as $n) echo "<option>$n</option>"; echo '</select></label>'; }
$customers = $directory->customers();
$companies = $directory->all();
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Account dashboard · Kingdom Connections</title><link rel="stylesheet" href="assets/css/app.css"></head><body><?php if (!empty($config['demo'])): ?><aside class="notice">Demo directory · fictional businesses and simulated payments</aside><?php endif ?><header><a class="brand" href="./">Kingdom Connections</a><?php formStart('logout');finish('Sign out'); ?></header><main><h1><?=h(['super'=>'Super admin','business'=>'Business','customer'=>'Customer'][$user['role']])?> dashboard</h1><p>Signed in as <?=h($user['name'])?></p>
<?php if ($error): ?><p class="alert" role="alert"><?=h($error)?></p><?php elseif(isset($_GET['saved'])): ?><p class="notice">Changes saved.</p><?php endif ?>
<?php if ($super || $user['role']==='business'): ?><p><a class="button" href="admin.php">Register or edit <?= $super?'a business':'your business' ?></a></p><?php endif ?>
<?php if ($super): ?><section class="card"><h2>Demo data</h2><p>Add 10 fictional businesses, 10 customer logins and 10 reviews backed by simulated payments. Repeating the load reuses the same demo accounts and listings.</p><?php formStart('seed-demo'); ?><label><input type="checkbox" required name="confirm" value="yes" style="width:auto;display:inline"> Add fictional demo data to this directory</label><?php finish('Load demo data'); ?>
<?php if (isset($_SESSION['demo_credentials'])): ?><details open><summary>Demo account credentials</summary><p>Save these generated passwords privately. Loading demo data again shows these passwords again.</p><div style="overflow:auto"><table><thead><tr><th>Username</th><th>Role</th><th>Password</th></tr></thead><tbody><?php foreach ($_SESSION['demo_credentials'] as $username=>$entry): ?><tr><td><?=h($username)?></td><td><?=h($entry['role'])?></td><td><code><?=h($entry['password'])?></code></td></tr><?php endforeach ?></tbody></table></div></details><?php unset($_SESSION['demo_credentials']); endif ?></section><section class="card"><h2>Manage accounts</h2><?php
$accounts=$auth->accounts();
foreach (array_merge([['id'=>'','username'=>'','name'=>'','role'=>'customer']],array_values($accounts)) as $a) {
    if ($a['role']==='super') continue;
    echo '<details><summary>'.h($a['username'] ?: 'Add an account').'</summary>';
    formStart('account',$a['id']); field('username','Username',$a['username']); field('name','Display name',$a['name']);
    if ($a['id']==='') echo '<label>Role<select name="role"><option>customer</option><option>business</option></select></label>';
    else echo '<input type="hidden" name="role" value="'.h($a['role']).'">';
    echo '<label>Password (12–72 characters; leave blank to keep existing)<input type="password" autocomplete="new-password" name="password"></label>'; finish('Save account');
    if ($a['id']!=='') { formStart('remove-account',$a['id']); finish('Delete account and associated directory data',true); }
    echo '</details>';
}
?></section><?php endif ?>
<?php if ($super || $user['role']==='customer'): ?><section class="card"><h2>Customer details</h2><?php
$people=$super?array_filter($accounts,static fn($a)=>$a['role']==='customer'):[$user];
foreach ($people as $person) {
    $profile=$customers[$person['id']] ?? [];
    formStart('profile',$person['id']);field('name','Name for '.$person['username'],$profile['name'] ?? $person['name']);field('email','Contact email',$profile['email'] ?? '',false);finish('Save customer details');
}
?></section><?php foreach ($companies as $b): ?><section class="card"><h2><?=h($b['name'])?></h2><?php
if ($super) { echo '<a href="admin.php?edit='.h($b['id']).'">Edit business profile</a>'; formStart('delete-business',$b['id']); finish('Delete business, payments and reviews',true); }
if ($super ? $customers : isset($customers[$user['id']])) {
    echo '<details><summary>Submit a paid service</summary>'; formStart('claim',$b['id']);
    if ($super) { echo '<label>Customer<select name="customer">'; foreach ($customers as $cid=>$c) echo '<option value="'.h($cid).'">'.h($c['name']).'</option>'; echo '</select></label>'; }
    field('service','Completed service');field('reference','Receipt or invoice reference (no card numbers)');finish('Submit for verification');echo '</details>';
}
foreach ($b['payments'] ?? [] as $key=>$p) {
    if (!$super && $p['customer']!==$user['id']) continue;
    echo '<h3>'.h($p['service']).'</h3><p>'.h($customers[$p['customer']]['name'] ?? 'Customer').' · '.h($p['reference']).' · '.($p['verified']?'Payment verified':'Pending verification').'</p>';
    if ($super) {
        if (!$p['verified']) { formStart('verify',$b['id'],$key); echo '<label><input type="checkbox" required name="evidence" value="checked" style="width:auto;display:inline"> I matched this customer, business and completed service to a settled payment in the merchant records.</label>';finish('Verify payment'); }
        echo '<details><summary>Manage payment</summary>';formStart('edit-payment',$b['id'],$key);field('service','Service',$p['service']);field('reference','Payment reference',$p['reference']);finish('Save correction and invalidate linked review');formStart('delete-payment',$b['id'],$key);finish('Delete payment and linked review',true);echo '</details>';
    } elseif ($p['verified'] && !in_array($key,array_column($b['ratings'],'payment'),true)) {
        formStart('review',$b['id'],$key);stars();field('comment','Review your service');finish('Publish review');
    }
}
if ($super) foreach ($b['ratings'] as $r) {
    echo '<details><summary>Manage review · '.h($r['stars']).'/5</summary>';formStart('edit-review',$b['id'],$r['id']);field('stars','Stars (1–5)',(string)$r['stars']);field('comment','Feedback',$r['comment']);finish('Save review');formStart('delete-review',$b['id'],$r['id']);finish('Delete review',true);echo '</details>';
}
?></section><?php endforeach; endif ?></main></body></html>
