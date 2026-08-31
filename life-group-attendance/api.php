<?php
declare(strict_types=1);
require_once __DIR__ . '/lib.php';
$me=require_user();
$action=$_GET['action'] ?? 'bootstrap';
if ($_SERVER['REQUEST_METHOD'] !== 'GET') require_csrf();

if ($action==='bootstrap') {
    $students=read_store('students'); $groups=read_store('groups'); $attendance=read_store('attendance');
    $safeStudents=$students;
    $users=[]; if(is_super_admin($me)) foreach(read_store('users') as $u) $users[]=array_intersect_key($u,array_flip(['id','name','email','role','active','createdAt']));
    json_out(['groups'=>$groups,'students'=>$safeStudents,'attendance'=>$attendance,'users'=>$users,'version'=>APP_VERSION]);
}
if ($action==='save-group') {
    require_super_admin();
    $b=body(); $groups=read_store('groups'); $id=trim($b['id'] ?? '') ?: uuid();
    $row=['id'=>$id,'name'=>trim($b['name'] ?? ''),'leader'=>trim($b['leader'] ?? ''),'meetingDay'=>trim($b['meetingDay'] ?? ''),'active'=>(bool)($b['active'] ?? true),'createdAt'=>$b['createdAt'] ?? now_iso()];
    if ($row['name']==='') json_out(['error'=>'Group name is required.'],422);
    $found=false; foreach($groups as &$g) if($g['id']===$id){$row['createdAt']=$g['createdAt'] ?? $row['createdAt'];$g=$row;$found=true;break;} unset($g);
    if(!$found)$groups[]=$row; write_store('groups',$groups); audit($found?'update':'create','group',$id); json_out(['group'=>$row],$found?200:201);
}
if ($action==='save-student') {
    $b=body(); $students=read_store('students'); $id=trim($b['id'] ?? '') ?: uuid();
    $row=['id'=>$id,'firstName'=>trim($b['firstName'] ?? ''),'lastName'=>trim($b['lastName'] ?? ''),'preferredName'=>trim($b['preferredName'] ?? ''),'gender'=>trim($b['gender'] ?? ''),'grade'=>trim($b['grade'] ?? ''),'groupIds'=>array_values(array_unique(array_filter($b['groupIds'] ?? []))),'siblingIds'=>array_values(array_unique(array_filter($b['siblingIds'] ?? []))),'serves'=>(bool)($b['serves'] ?? false),'serveLocations'=>array_values(array_filter(array_map('trim',$b['serveLocations'] ?? []))),'baptized'=>(bool)($b['baptized'] ?? false),'baptismDate'=>trim($b['baptismDate'] ?? ''),'guardianName'=>trim($b['guardianName'] ?? ''),'guardianPhone'=>trim($b['guardianPhone'] ?? ''),'notes'=>trim($b['notes'] ?? ''),'active'=>(bool)($b['active'] ?? true),'createdAt'=>$b['createdAt'] ?? now_iso(),'updatedAt'=>now_iso()];
    if($row['firstName']===''||$row['lastName']==='')json_out(['error'=>'First and last name are required.'],422);
    $found=false; foreach($students as &$s) if($s['id']===$id){$row['createdAt']=$s['createdAt']??$row['createdAt'];$s=$row;$found=true;break;} unset($s); if(!$found)$students[]=$row;
    foreach($students as &$s){ if($s['id']===$id)continue; $linked=in_array($s['id'],$row['siblingIds'],true); $has=in_array($id,$s['siblingIds']??[],true); if($linked&&!$has)$s['siblingIds'][]=$id; if(!$linked&&$has)$s['siblingIds']=array_values(array_diff($s['siblingIds'],[$id])); } unset($s);
    write_store('students',$students); audit($found?'update':'create','student',$id); json_out(['student'=>$row],$found?200:201);
}
if ($action==='delete-student') {
    $b=body(); $ids=array_values(array_unique(array_filter(array_map('strval',$b['ids']??[]))));
    if(!$ids&&trim($b['id']??'')!=='')$ids=[trim($b['id'])];
    if(!$ids)json_out(['error'=>'At least one student ID is required.'],422);
    $students=read_store('students'); $deleted=[];
    foreach($students as &$s){
        if(in_array($s['id'],$ids,true)){$s['active']=false;$s['deletedAt']=now_iso();$s['updatedAt']=now_iso();$deleted[]=$s['id'];continue;}
        if(array_intersect($ids,$s['siblingIds']??[]))$s['siblingIds']=array_values(array_diff($s['siblingIds'],$ids));
    } unset($s);
    if(!$deleted)json_out(['error'=>'No matching students were found.'],404);
    write_store('students',$students); foreach($deleted as $id)audit('delete','student',$id,['method'=>'soft-delete','bulk'=>count($ids)>1]);
    json_out(['ids'=>$deleted,'count'=>count($deleted)]);
}
if ($action==='save-attendance') {
    $b=body(); $groupId=trim($b['groupId']??''); $date=trim($b['date']??date('Y-m-d')); $present=array_values(array_unique(array_filter($b['studentIds']??[])));
    if(!$groupId)json_out(['error'=>'Choose a life group.'],422);
    $rows=read_store('attendance'); $id=''; $found=false;
    foreach($rows as &$r) if($r['groupId']===$groupId&&$r['date']===$date){$r['studentIds']=$present;$r['updatedAt']=now_iso();$r['recordedBy']=$me['id'];$id=$r['id'];$found=true;break;} unset($r);
    if(!$found){$id=uuid();$rows[]=['id'=>$id,'groupId'=>$groupId,'date'=>$date,'studentIds'=>$present,'recordedBy'=>$me['id'],'createdAt'=>now_iso(),'updatedAt'=>now_iso()];}
    write_store('attendance',$rows); audit($found?'update':'create','attendance',$id,['groupId'=>$groupId,'date'=>$date,'count'=>count($present)]); json_out(['attendance'=>end($rows),'count'=>count($present)]);
}
if ($action==='import-frontlines') {
    require_super_admin();
    $students=read_store('students'); $incoming=frontlines_roster_students(); $byName=[];
    foreach($students as $i=>$s) $byName[strtolower(trim(($s['firstName']??'').' '.($s['lastName']??'')))]=$i;
    $added=0; $updated=0;
    foreach($incoming as $row){
        $key=strtolower(trim($row['firstName'].' '.$row['lastName']));
        if(!isset($byName[$key])){$students[]=$row;$byName[$key]=array_key_last($students);$added++;continue;}
        $i=$byName[$key]; $changed=false;
        foreach(['gender','grade'] as $field) if(($students[$i][$field]??'')===''&&$row[$field]!==''){$students[$i][$field]=$row[$field];$changed=true;}
        if($changed){$students[$i]['updatedAt']=now_iso();$updated++;}
    }
    write_store('students',$students); audit('import','student','frontlines',['added'=>$added,'updated'=>$updated]);
    json_out(['students'=>$students,'added'=>$added,'updated'=>$updated,'sourceCount'=>count($incoming)]);
}
if ($action==='save-user') {
    require_super_admin(); $b=body(); $users=read_store('users'); $id=trim($b['id']??'')?:uuid();
    $name=trim($b['name']??''); $email=strtolower(trim($b['email']??'')); $role=trim($b['role']??'attendance'); $active=(bool)($b['active']??true); $password=(string)($b['password']??'');
    if($name===''||!filter_var($email,FILTER_VALIDATE_EMAIL))json_out(['error'=>'Name and a valid email are required.'],422);
    if(!in_array($role,['super_admin','attendance'],true))json_out(['error'=>'Invalid role.'],422);
    foreach($users as $u)if($u['id']!==$id&&strcasecmp($u['email'],$email)===0)json_out(['error'=>'That email already has an account.'],422);
    $found=false; foreach($users as &$u)if($u['id']===$id){
        if($id===$me['id']&&(!$active||$role!=='super_admin'))json_out(['error'=>'You cannot deactivate or demote your own account.'],422);
        $u['name']=$name;$u['email']=$email;$u['role']=$role;$u['active']=$active;if($password!==''){if(strlen($password)<12)json_out(['error'=>'Passwords must be at least 12 characters.'],422);$u['passwordHash']=password_hash($password,PASSWORD_DEFAULT);}$found=true;break;
    } unset($u);
    if(!$found){if(strlen($password)<12)json_out(['error'=>'A password of at least 12 characters is required for new users.'],422);$users[]=['id'=>$id,'name'=>$name,'email'=>$email,'role'=>$role,'passwordHash'=>password_hash($password,PASSWORD_DEFAULT),'active'=>$active,'createdAt'=>now_iso()];}
    write_store('users',$users);audit($found?'update':'create','user',$id,['role'=>$role,'active'=>$active]);
    $safe=[];foreach($users as $u)$safe[]=array_intersect_key($u,array_flip(['id','name','email','role','active','createdAt']));json_out(['users'=>$safe],$found?200:201);
}
json_out(['error'=>'Unknown action.'],404);
