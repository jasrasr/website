<?php
/**
 * Ticketing - index.php
 * File Revision: 1.4.1
 * Modified: 2026-09-15
 *
 * Revision History:
 * 1.4.1 - Updated frontend asset revisions so searchable requester/category controls load instead of cached native selects.
 * 1.4.0 - Added instance-local category runtime bootstrap from sample data and Manage Categories navigation.
 * 1.3.0 - Added hierarchical category JSON, category references, and controlled category selection.
 * 1.1.1 - Added password confirmation validation for requester and agent account creation.
 * 1.1.0 - Added passwordless test mode, email usernames, display names, profile avatars, and profile API.
 * 1.0.0 - Added session authentication, requester/agent authorization, persistent people lists, and protected ticket APIs.
 */

declare(strict_types=1);
session_start();

const DATA_FILE = __DIR__ . '/data/tickets.json';
const USERS_FILE = __DIR__ . '/data/users.json';
const DIRECTORY_FILE = __DIR__ . '/data/directory.json';
const CATEGORIES_FILE = __DIR__ . '/data/categories.json';
const CATEGORIES_SAMPLE_FILE = __DIR__ . '/data/categories.json.sample';
const PROJECT_FILE = __DIR__ . '/project.json';
const AVATAR_DIR = __DIR__ . '/avatars';
const TEST_MODE = true;
const PROJECT_REVISION = '1.7.2';
const PROJECT_MODIFIED = '2026-09-15';

function jsonResponse(array $data, int $status = 200): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}
function readJsonFile(string $path, array $fallback = []): array {
    if (!file_exists($path)) return $fallback;
    $contents = file_get_contents($path);
    if ($contents === false || trim($contents) === '') return $fallback;
    $decoded = json_decode($contents, true);
    return is_array($decoded) ? $decoded : $fallback;
}
function writeJsonFile(string $path, array $data): bool {
    $directory = dirname($path);
    if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) return false;
    $handle = fopen($path, 'c+');
    if ($handle === false) return false;
    try {
        if (!flock($handle, LOCK_EX)) return false;
        ftruncate($handle, 0); rewind($handle);
        $json = json_encode(array_values($data), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($json === false || fwrite($handle, $json . PHP_EOL) === false) return false;
        fflush($handle); flock($handle, LOCK_UN); return true;
    } finally { fclose($handle); }
}
function readTickets(): array { return readJsonFile(DATA_FILE, []); }
function readUsers(): array { return readJsonFile(USERS_FILE, []); }
function readDirectory(): array { return readJsonFile(DIRECTORY_FILE, []); }
function readCategories(): array {
    $categories = readJsonFile(CATEGORIES_FILE, []);
    if ($categories !== []) return $categories;
    $sample = readJsonFile(CATEGORIES_SAMPLE_FILE, []);
    if ($sample !== []) {
        writeJsonFile(CATEGORIES_FILE, $sample);
        return $sample;
    }
    return [['id'=>'general','name'=>'General','active'=>true,'children'=>[]]];
}
function readProject(): array { return readJsonFile(PROJECT_FILE, ['name'=>'Ticketing','revision'=>PROJECT_REVISION,'modified'=>PROJECT_MODIFIED]); }
function normalizeEmail(string $email): string { return strtolower(trim($email)); }
function currentUser(): ?array { return isset($_SESSION['ticketing_user']) && is_array($_SESSION['ticketing_user']) ? $_SESSION['ticketing_user'] : null; }
function requireUser(): array { $u=currentUser(); if($u===null) jsonResponse(['error'=>'Authentication required.'],401); return $u; }
function requireAgent(): array { $u=requireUser(); if(($u['role']??'')!=='agent') jsonResponse(['error'=>'Agent access required.'],403); return $u; }
function publicUser(array $user): array {
    $display=trim((string)($user['displayName']??$user['name']??''));
    return ['id'=>$user['id']??'','email'=>$user['email']??'','username'=>$user['email']??'','displayName'=>$display,'name'=>$display,'role'=>$user['role']??'requester','avatar'=>$user['avatar']??''];
}
function findUserIndexByEmail(array $users,string $email): ?int { $needle=normalizeEmail($email); foreach($users as $i=>$u) if(normalizeEmail((string)($u['email']??''))===$needle) return $i; return null; }
function findUserByEmail(array $users,string $email): ?array { $i=findUserIndexByEmail($users,$email); return $i===null?null:$users[$i]; }
function hasAgent(array $users): bool { foreach($users as $u) if(($u['role']??'')==='agent'&&($u['active']??true)) return true; return false; }
function validChoice(string $value,array $allowed,string $fallback): string { return in_array($value,$allowed,true)?$value:$fallback; }
function nextTicketNumber(array $tickets): int { $max=0; foreach($tickets as $t)$max=max($max,(int)($t['number']??0)); return $max+1; }
function normalizeTicketForRequester(array $ticket): array { $ticket['comments']=array_values(array_filter($ticket['comments']??[],static fn(array $c):bool=>($c['visibility']??'public')==='public')); return $ticket; }
function validatePasswordConfirmation(string $password,string $confirmation): void { if($password!==$confirmation) jsonResponse(['error'=>'Password and confirmation password do not match.'],422); }

function flattenCategories(array $nodes,string $prefix=''): array {
    $flat=[];
    foreach($nodes as $node){
        if(!is_array($node)||!($node['active']??true)) continue;
        $name=trim((string)($node['name']??'')); $id=trim((string)($node['id']??''));
        if($name===''||$id==='') continue;
        $path=$prefix===''?$name:$prefix.' > '.$name;
        $flat[]=['id'=>$id,'name'=>$name,'path'=>$path,'hasChildren'=>!empty($node['children'])];
        if(!empty($node['children'])&&is_array($node['children'])) $flat=array_merge($flat,flattenCategories($node['children'],$path));
    }
    return $flat;
}
function resolveCategory(string $categoryId,string $categoryPath=''): array {
    $flat=flattenCategories(readCategories());
    foreach($flat as $cat) if($categoryId!==''&&$cat['id']===$categoryId) return $cat;
    foreach($flat as $cat) if($categoryPath!==''&&strcasecmp($cat['path'],$categoryPath)===0) return $cat;
    foreach($flat as $cat) if($cat['id']==='general') return $cat;
    return $flat[0]??['id'=>'general','name'=>'General','path'=>'General','hasChildren'=>false];
}
function upsertDirectory(array &$directory,string $role,string $displayName,string $email,string $avatar=''): array {
    $email=normalizeEmail($email);
    foreach($directory as &$e){ if(($e['role']??'')===$role&&normalizeEmail((string)($e['email']??''))===$email){ $e['name']=$displayName;$e['displayName']=$displayName;if($avatar!=='')$e['avatar']=$avatar;$e['active']=true;$e['updatedAt']=gmdate('c');return $e; } }
    unset($e); $entry=['id'=>bin2hex(random_bytes(8)),'role'=>$role,'name'=>$displayName,'displayName'=>$displayName,'email'=>$email,'avatar'=>$avatar,'active'=>true,'createdAt'=>gmdate('c'),'updatedAt'=>gmdate('c')]; $directory[]=$entry; return $entry;
}
function saveAvatarData(string $userId,string $dataUrl): string {
    if($dataUrl==='') return '';
    if(!preg_match('#^data:image/(png|jpeg|webp|gif);base64,(.+)$#s',$dataUrl,$m)) jsonResponse(['error'=>'Avatar must be PNG, JPEG, WEBP, or GIF.'],422);
    $raw=base64_decode($m[2],true); if($raw===false) jsonResponse(['error'=>'Invalid avatar data.'],422); if(strlen($raw)>2*1024*1024) jsonResponse(['error'=>'Avatar must be 2 MB or smaller.'],422);
    if(!is_dir(AVATAR_DIR)&&!mkdir(AVATAR_DIR,0775,true)&&!is_dir(AVATAR_DIR)) jsonResponse(['error'=>'Could not create avatar folder.'],500);
    $ext=$m[1]==='jpeg'?'jpg':$m[1]; $safe=preg_replace('/[^a-zA-Z0-9_-]/','',$userId); $filename=$safe.'.'.$ext; foreach(glob(AVATAR_DIR.'/'.$safe.'.*')?:[] as $old) @unlink($old); if(file_put_contents(AVATAR_DIR.'/'.$filename,$raw)===false) jsonResponse(['error'=>'Could not save avatar.'],500); return 'avatars/'.$filename;
}

if(isset($_GET['api'])){
    $method=$_SERVER['REQUEST_METHOD']??'GET'; $action=strtolower(trim((string)($_GET['action']??'tickets'))); $payload=[];
    if($method!=='GET'){ $payload=json_decode(file_get_contents('php://input')?:'{}',true); if(!is_array($payload)) jsonResponse(['error'=>'Invalid JSON body.'],400); }

    if($action==='session'&&$method==='GET'){ $users=readUsers(); jsonResponse(['user'=>currentUser(),'agentBootstrapAvailable'=>!hasAgent($users),'testMode'=>TEST_MODE,'project'=>readProject()]); }
    if($action==='categories'&&$method==='GET'){ requireUser(); $tree=readCategories(); jsonResponse(['categories'=>$tree,'flat'=>flattenCategories($tree)]); }
    if($action==='login'&&$method==='POST'){
        $email=normalizeEmail((string)($payload['email']??''));$role=validChoice(strtolower((string)($payload['role']??'requester')),['requester','agent'],'requester');$users=readUsers();$user=findUserByEmail($users,$email);$password=(string)($payload['password']??'');$passwordOk=TEST_MODE||(($user['passwordHash']??'')!==''&&password_verify($password,(string)$user['passwordHash']));
        if($user===null||($user['role']??'')!==$role||!($user['active']??true)||!$passwordOk) jsonResponse(['error'=>TEST_MODE?'No active account matches that email and role.':'Invalid email, password, or account type.'],401);
        $_SESSION['ticketing_user']=publicUser($user);session_regenerate_id(true);jsonResponse(['user'=>$_SESSION['ticketing_user'],'testMode'=>TEST_MODE]);
    }
    if($action==='logout'&&$method==='POST'){ $_SESSION=[]; if(ini_get('session.use_cookies')){$p=session_get_cookie_params();setcookie(session_name(),'',time()-42000,$p['path'],$p['domain'],$p['secure'],$p['httponly']);} session_destroy(); jsonResponse(['ok'=>true]); }
    if(($action==='register-requester'||$action==='bootstrap-agent')&&$method==='POST'){
        $users=readUsers();$role=$action==='bootstrap-agent'?'agent':'requester';if($role==='agent'&&hasAgent($users))jsonResponse(['error'=>'The first agent account has already been created.'],403);$displayName=trim((string)($payload['displayName']??$payload['name']??''));$email=normalizeEmail((string)($payload['email']??''));$password=(string)($payload['password']??'');$passwordConfirm=(string)($payload['passwordConfirm']??'');validatePasswordConfirmation($password,$passwordConfirm);
        if($displayName===''||!filter_var($email,FILTER_VALIDATE_EMAIL))jsonResponse(['error'=>'Display name and valid email are required.'],422);if(!TEST_MODE&&strlen($password)<8)jsonResponse(['error'=>'Password must be at least 8 characters.'],422);if($password!==''&&strlen($password)<8)jsonResponse(['error'=>'If a password is provided, it must be at least 8 characters.'],422);if(findUserByEmail($users,$email)!==null)jsonResponse(['error'=>'An account with that email already exists.'],409);
        $id=bin2hex(random_bytes(8));$avatar=saveAvatarData($id,(string)($payload['avatarData']??''));$user=['id'=>$id,'role'=>$role,'name'=>$displayName,'displayName'=>$displayName,'email'=>$email,'passwordHash'=>$password!==''?password_hash($password,PASSWORD_DEFAULT):'','avatar'=>$avatar,'active'=>true,'createdAt'=>gmdate('c')];$users[]=$user;$directory=readDirectory();upsertDirectory($directory,$role,$displayName,$email,$avatar);if(!writeJsonFile(USERS_FILE,$users)||!writeJsonFile(DIRECTORY_FILE,$directory))jsonResponse(['error'=>'Could not save account data.'],500);$_SESSION['ticketing_user']=publicUser($user);session_regenerate_id(true);jsonResponse(['user'=>$_SESSION['ticketing_user']],201);
    }
    if($action==='profile'){
        $sessionUser=requireUser();if($method==='GET')jsonResponse(['user'=>$sessionUser]);if($method==='PUT'){ $users=readUsers();$index=findUserIndexByEmail($users,(string)$sessionUser['email']);if($index===null)jsonResponse(['error'=>'User account not found.'],404);$displayName=trim((string)($payload['displayName']??''));if($displayName==='')jsonResponse(['error'=>'Display name is required.'],422);$users[$index]['displayName']=$displayName;$users[$index]['name']=$displayName;$avatarData=(string)($payload['avatarData']??'');if($avatarData!=='')$users[$index]['avatar']=saveAvatarData((string)$users[$index]['id'],$avatarData);$users[$index]['updatedAt']=gmdate('c');$directory=readDirectory();upsertDirectory($directory,(string)$users[$index]['role'],$displayName,(string)$users[$index]['email'],(string)($users[$index]['avatar']??''));if(!writeJsonFile(USERS_FILE,$users)||!writeJsonFile(DIRECTORY_FILE,$directory))jsonResponse(['error'=>'Could not save profile.'],500);$_SESSION['ticketing_user']=publicUser($users[$index]);jsonResponse(['user'=>$_SESSION['ticketing_user']]); }
    }
    if($action==='directory'){
        if($method==='GET'){ $user=requireUser();$directory=readDirectory();if(($user['role']??'')!=='agent')$directory=array_values(array_filter($directory,static fn(array $e):bool=>normalizeEmail((string)($e['email']??''))===normalizeEmail((string)$user['email'])));jsonResponse(['directory'=>array_values($directory)]); }
        if($method==='POST'){ requireAgent();$role=validChoice(strtolower((string)($payload['role']??'requester')),['requester','agent'],'requester');$displayName=trim((string)($payload['displayName']??$payload['name']??''));$email=normalizeEmail((string)($payload['email']??''));$password=(string)($payload['password']??'');$passwordConfirm=(string)($payload['passwordConfirm']??'');validatePasswordConfirmation($password,$passwordConfirm);if($password!==''&&strlen($password)<8)jsonResponse(['error'=>'If a password is provided, it must be at least 8 characters.'],422);if($displayName===''||!filter_var($email,FILTER_VALIDATE_EMAIL))jsonResponse(['error'=>'Display name and valid email are required.'],422);$directory=readDirectory();$entry=upsertDirectory($directory,$role,$displayName,$email);if(!writeJsonFile(DIRECTORY_FILE,$directory))jsonResponse(['error'=>'Could not save directory entry.'],500);$users=readUsers();$existingIndex=findUserIndexByEmail($users,$email);if($existingIndex===null){$users[]=['id'=>bin2hex(random_bytes(8)),'role'=>$role,'name'=>$displayName,'displayName'=>$displayName,'email'=>$email,'passwordHash'=>$password!==''?password_hash($password,PASSWORD_DEFAULT):'','avatar'=>'','active'=>true,'createdAt'=>gmdate('c')];if(!writeJsonFile(USERS_FILE,$users))jsonResponse(['error'=>'Directory saved, but login account could not be saved.'],500);}elseif($password!==''){$users[$existingIndex]['passwordHash']=password_hash($password,PASSWORD_DEFAULT);$users[$existingIndex]['displayName']=$displayName;$users[$existingIndex]['name']=$displayName;$users[$existingIndex]['role']=$role;$users[$existingIndex]['updatedAt']=gmdate('c');if(!writeJsonFile(USERS_FILE,$users))jsonResponse(['error'=>'Directory saved, but login password could not be updated.'],500);}jsonResponse(['entry'=>$entry],201); }
    }
    if($action!=='tickets')jsonResponse(['error'=>'Unknown API action.'],404);

    $user=requireUser();$tickets=readTickets();$project=readProject();
    if($method==='GET'){
        if(($user['role']??'')==='requester'){ $email=normalizeEmail((string)$user['email']);$mine=array_values(array_filter($tickets,static fn(array $t):bool=>normalizeEmail((string)($t['email']??''))===$email));jsonResponse(['tickets'=>array_map('normalizeTicketForRequester',$mine),'project'=>$project]); }
        jsonResponse(['tickets'=>array_values($tickets),'project'=>$project]);
    }
    if($method==='POST'){
        $subject=trim((string)($payload['subject']??''));$description=trim((string)($payload['description']??''));if($subject===''||$description==='')jsonResponse(['error'=>'Subject and description are required.'],422);
        if(($user['role']??'')==='requester'){$requester=(string)($user['displayName']??$user['name']??'Requester');$email=(string)$user['email'];$assignedTo='';}else{$requester=trim((string)($payload['requester']??''));$email=normalizeEmail((string)($payload['email']??''));$assignedTo=trim((string)($payload['assignedTo']??''));if($requester===''||!filter_var($email,FILTER_VALIDATE_EMAIL))jsonResponse(['error'=>'Requester and valid requester email are required.'],422);}
        $category=resolveCategory(trim((string)($payload['categoryId']??'')),trim((string)($payload['category']??'')));$now=gmdate('c');$ticket=['id'=>bin2hex(random_bytes(8)),'number'=>nextTicketNumber($tickets),'subject'=>$subject,'description'=>$description,'requester'=>$requester,'email'=>normalizeEmail($email),'status'=>'Open','priority'=>validChoice((string)($payload['priority']??'Medium'),['Low','Medium','High','Urgent'],'Medium'),'categoryId'=>$category['id'],'category'=>$category['path'],'assignedTo'=>$assignedTo,'source'=>trim((string)($payload['source']??'Portal'))?:'Portal','createdAt'=>$now,'updatedAt'=>$now,'comments'=>[]];$tickets[]=$ticket;if(!writeJsonFile(DATA_FILE,$tickets))jsonResponse(['error'=>'Could not save ticket data.'],500);jsonResponse(['ticket'=>(($user['role']??'')==='requester'?normalizeTicketForRequester($ticket):$ticket)],201);
    }
    if($method==='PUT'){
        $id=trim((string)($payload['id']??''));$updated=null;foreach($tickets as &$ticket){if(($ticket['id']??'')!==$id)continue;if(($user['role']??'')==='requester'){if(normalizeEmail((string)($ticket['email']??''))!==normalizeEmail((string)$user['email']))jsonResponse(['error'=>'You do not have access to this ticket.'],403);}else{foreach(['subject','description','requester','email','assignedTo','source'] as $field)if(array_key_exists($field,$payload))$ticket[$field]=trim((string)$payload[$field]);if(array_key_exists('status',$payload))$ticket['status']=validChoice((string)$payload['status'],['Open','Pending','Resolved','Closed'],'Open');if(array_key_exists('priority',$payload))$ticket['priority']=validChoice((string)$payload['priority'],['Low','Medium','High','Urgent'],'Medium');if(array_key_exists('categoryId',$payload)||array_key_exists('category',$payload)){$cat=resolveCategory(trim((string)($payload['categoryId']??'')),trim((string)($payload['category']??'')));$ticket['categoryId']=$cat['id'];$ticket['category']=$cat['path'];}}
            $comment=trim((string)($payload['comment']??''));if($comment!==''){$visibility=($user['role']??'')==='requester'?'public':validChoice(strtolower((string)($payload['visibility']??'public')),['public','private'],'public');$ticket['comments'][]=['id'=>bin2hex(random_bytes(6)),'author'=>(string)($user['displayName']??$user['name']??ucfirst((string)$user['role'])),'role'=>$user['role'],'visibility'=>$visibility,'body'=>$comment,'createdAt'=>gmdate('c')];}$ticket['updatedAt']=gmdate('c');$updated=$ticket;break;}
        unset($ticket);if($updated===null)jsonResponse(['error'=>'Ticket not found.'],404);if(!writeJsonFile(DATA_FILE,$tickets))jsonResponse(['error'=>'Could not save ticket data.'],500);jsonResponse(['ticket'=>(($user['role']??'')==='requester'?normalizeTicketForRequester($updated):$updated)]);
    }
    jsonResponse(['error'=>'Method not allowed.'],405);
}
?>
<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="description" content="Lightweight JSON-backed ticketing system"><title>Ticketing</title><link rel="stylesheet" href="styles.css?v=1.2.1"><link id="ticketingAutocompleteCss" rel="stylesheet" href="autocomplete.css?v=1.0.0"></head><body>
<div id="loginScreen" class="login-screen"><div class="login-card"><div class="brand login-brand">Ticketing</div><h1>Sign in</h1><p class="muted">Your email address is your username.</p><div id="testModeNotice" class="notice warning hidden"><strong>Testing mode:</strong> passwords are temporarily disabled for sign-in.</div><div class="portal-switch login-switch"><button type="button" class="portal-button active" data-login-role="requester">Requester</button><button type="button" class="portal-button" data-login-role="agent">Agent</button></div><form id="loginForm"><label id="loginNameWrap" class="hidden">Display name<input id="loginName" autocomplete="name"></label><label>Email / username<input id="loginEmail" type="email" autocomplete="email" required></label><label id="loginPasswordWrap">Password <span class="muted small">optional while testing</span><input id="loginPassword" type="password" autocomplete="new-password"></label><label id="loginPasswordConfirmWrap" class="hidden">Confirm password<input id="loginPasswordConfirm" type="password" autocomplete="new-password"></label><label id="loginAvatarWrap" class="hidden">Profile picture<input id="loginAvatar" type="file" accept="image/png,image/jpeg,image/webp,image/gif"></label><button class="button primary full-button" type="submit" id="loginSubmitButton">Sign in</button></form><button type="button" class="link-button" id="registerRequesterButton">Create requester account</button><button type="button" class="link-button hidden" id="bootstrapAgentButton">Create first agent account</button><button type="button" class="link-button hidden" id="backToLoginButton">Back to sign in</button><div id="loginMessage" class="form-message"></div></div></div>
<div class="app-shell hidden" id="appShell"><aside class="sidebar"><div class="brand">Ticketing</div><div class="signed-in"><img id="signedInAvatar" class="avatar" alt=""><div><span id="signedInName"></span><small id="signedInRole"></small><small id="signedInEmail"></small></div></div><button class="button secondary profile-button" id="profileButton">Edit Profile</button><nav id="requesterNav" class="hidden"><button class="nav-button active" data-requester-view="requesterDashboard">My Dashboard</button><button class="nav-button" data-requester-view="myTickets">My Tickets</button><button class="nav-button primary" id="requesterNewTicketButton">+ Submit Ticket</button></nav><nav id="agentNav" class="hidden"><button class="nav-button active" data-agent-view="agentDashboard">Agent Dashboard</button><button class="nav-button" data-agent-view="allTickets">All Tickets</button><button class="nav-button primary" id="agentNewTicketButton">+ New Ticket</button><a class="nav-button" href="csv.php">CSV Import / Export</a><a class="nav-button" href="categories.php">Manage Categories</a></nav><div class="sidebar-footer"><a href="CHANGELOG.md" target="_blank" rel="noopener">View Changelog</a><a href="TODO.md" target="_blank" rel="noopener">Future Features</a><button class="link-button sidebar-link" id="logoutButton">Sign out</button></div></aside><main class="main-content"><header class="topbar"><div><h1 id="pageTitle">Dashboard</h1><p class="muted" id="pageSubtitle"></p></div><button class="button" id="refreshButton">Refresh</button></header><section id="requesterDashboardView" class="hidden"><div class="stats" id="requesterStats"></div><div class="panel"><div class="panel-heading"><h2>My Recent Tickets</h2></div><div id="requesterRecentTickets"></div></div></section><section id="myTicketsView" class="hidden"><div class="toolbar requester-toolbar"><input id="requesterSearchInput" type="search" placeholder="Search my tickets..."><select id="requesterStatusFilter"><option value="">All statuses</option><option>Open</option><option>Pending</option><option>Resolved</option><option>Closed</option></select></div><div class="panel"><div id="myTicketList"></div></div></section><section id="agentDashboardView" class="hidden"><div class="stats" id="agentStats"></div><div class="panel"><div class="panel-heading"><h2>Recently Updated</h2></div><div id="agentRecentTickets"></div></div></section><section id="allTicketsView" class="hidden"><div class="toolbar agent-toolbar"><input id="agentSearchInput" type="search" placeholder="Search ticket, requester, email, agent..."><select id="agentStatusFilter"><option value="">All statuses</option><option>Open</option><option>Pending</option><option>Resolved</option><option>Closed</option></select><select id="agentPriorityFilter"><option value="">All priorities</option><option>Low</option><option>Medium</option><option>High</option><option>Urgent</option></select><select id="agentFilter"><option value="">All agents</option></select></div><div class="panel"><div id="agentTicketList"></div></div></section><footer class="project-footer"><span id="projectRevision">Project rev --</span><span id="projectModified">Modified --</span><a href="CHANGELOG.md" target="_blank" rel="noopener">Changelog</a></footer></main></div>
<dialog id="ticketDialog"><form method="dialog" id="ticketForm"><div class="dialog-header"><div><div class="eyebrow" id="ticketEyebrow">New ticket</div><h2 id="dialogTitle">Create Ticket</h2></div><button type="button" class="icon-button" id="closeDialogButton">×</button></div><input type="hidden" id="ticketId"><div class="form-grid"><label class="full">Subject<input id="subject" required></label><label class="agent-field">Requester<select id="requesterSelect"></select></label><label class="agent-field">Requester Email<input id="email" type="email"></label><label>Priority<select id="priority"><option>Low</option><option selected>Medium</option><option>High</option><option>Urgent</option></select></label><label class="agent-field">Status<select id="status"><option>Open</option><option>Pending</option><option>Resolved</option><option>Closed</option></select></label><label>Category<select id="category" required><option value="">Loading categories...</option></select></label><label class="agent-field">Assigned To<select id="assignedTo"></select></label><label class="agent-field">Source<input id="source" value="Portal"></label><label class="full">Description<textarea id="description" rows="5" required></textarea></label></div><div id="replySection" class="reply-section hidden"><div class="reply-heading"><h3>Reply</h3><div id="visibilityControl" class="visibility-control"><label><input type="radio" name="replyVisibility" value="public" checked> Public reply</label><label><input type="radio" name="replyVisibility" value="private"> Private note</label></div></div><p class="muted small" id="replyHint"></p><textarea id="comment" rows="4"></textarea></div><div id="commentHistory" class="comments hidden"></div><div class="dialog-actions"><button type="button" class="button secondary" id="cancelButton">Cancel</button><button type="submit" class="button primary" id="saveButton">Create Ticket</button></div></form></dialog>
<dialog id="personDialog"><form method="dialog" id="personForm"><div class="dialog-header"><div><div class="eyebrow">Directory</div><h2 id="personDialogTitle">Add Person</h2></div><button type="button" class="icon-button" id="closePersonDialogButton">×</button></div><input type="hidden" id="personRole"><div class="form-grid"><label>Display name<input id="personName" required></label><label>Email / username<input id="personEmail" type="email" required></label><label>Password <span class="muted small">optional while testing</span><input id="personPassword" type="password" autocomplete="new-password"></label><label>Confirm password<input id="personPasswordConfirm" type="password" autocomplete="new-password"></label></div><p class="muted small">Both password fields may be blank in test mode. If you enter a password, enter the same value twice.</p><div class="dialog-actions"><button type="button" class="button secondary" id="cancelPersonButton">Cancel</button><button type="submit" class="button primary">Save</button></div></form></dialog>
<dialog id="profileDialog"><form method="dialog" id="profileForm"><div class="dialog-header"><div><div class="eyebrow">Account</div><h2>Edit Profile</h2></div><button type="button" class="icon-button" id="closeProfileDialogButton">×</button></div><div class="profile-editor"><img id="profileAvatarPreview" class="avatar avatar-large" alt=""><div class="form-grid"><label class="full">Email / username<input id="profileEmail" disabled></label><label class="full">Display name<input id="profileDisplayName" required></label><label class="full">Profile picture<input id="profileAvatar" type="file" accept="image/png,image/jpeg,image/webp,image/gif"></label></div></div><div class="dialog-actions"><button type="button" class="button secondary" id="cancelProfileButton">Cancel</button><button type="submit" class="button primary">Save Profile</button></div></form></dialog>
<script src="app.js?v=1.4.0"></script></body></html>
