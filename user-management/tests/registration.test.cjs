/* File Name: registration.test.cjs
Revision: 1.1.0
Description: Verify registration isolation, Jason-only approval, unique names and direct-IP limits.
Changelog: 1.1.0 - Cover duplicate/invalid attempts and 5-per-IP limits before password hashing.
*/
const {test}=require('node:test');
const fs=require('node:fs'),os=require('node:os'),path=require('node:path');
const {execFileSync}=require('node:child_process');
test('pending registration is isolated; only Jason approves; no grants; duplicate and replay rejected',()=>{
 const dir=fs.mkdtempSync(path.join(os.tmpdir(),'registration-'));
 const bootstrap=path.resolve(__dirname,'../bootstrap.php');
 const code=`<?php
 require ${JSON.stringify(bootstrap)};
 $d=new Jasr\\Users\\Directory(__DIR__);
 $d->setup('jasrasr','Jason','test-password-long');
 $owner=array_values($d->read()['users'])[0];
 function check($v){if(!$v)throw new RuntimeException('Assertion failed');}
 function denied($f){try{$f();}catch(InvalidArgumentException $e){return;}throw new RuntimeException('Expected rejection');}
 $d->requestRegistration('applicant','Applicant','test@example.test','test-password-long','127.0.0.1');
 $s=$d->read();check(count($s['users'])===1);$id=array_key_first($s['registrations']);
 check(password_verify('test-password-long',$s['registrations'][$id]['user']['hash']));
 denied(fn()=>$d->requestRegistration('applicant','Applicant','test@example.test','test-password-long','127.0.0.1'));
 denied(fn()=>$d->reviewRegistration($id,1,$id,true));
 $d->reviewRegistration($owner['id'],$owner['version'],$id,true);
 $u=$d->read()['users'][$id];check($u['active'] && $u['role']==='user' && !$u['admin'] && !$u['allProjects'] && $u['projects']===[]);
 denied(fn()=>$d->reviewRegistration($owner['id'],$owner['version'],$id,true));
 $d->requestRegistration('rejectme','Applicant','test@example.test','test-password-long','127.0.0.1');
 $id=array_key_first($d->read()['registrations']);$d->reviewRegistration($owner['id'],$owner['version'],$id,false);
 check(!isset($d->read()['users'][$id]) && empty($d->read()['registrations']));
 // Only two accepted registrations used this IP; rejections must not hash/store users.
 $stateBefore=$d->read();check(count($stateBefore['registrationAttempts'])===2);
 denied(fn()=>$d->requestRegistration('jasrasr','Impostor','test@example.test','test-password-long','127.0.0.1'));
 denied(fn()=>$d->requestRegistration('bad***','Invalid','test@example.test','test-password-long','127.0.0.1'));
 denied(fn()=>$d->requestRegistration('tiny','Too short','test@example.test','short','127.0.0.1'));
 check(count($d->read()['registrationAttempts'])===2);
 // Three additional successful submissions consume the five/day allowance.
 foreach (['thirdperson','fourthperson','fifthperson'] as $name)
   $d->requestRegistration($name,'Applicant','test@example.test','test-password-long','127.0.0.1');
 check(count($d->read()['registrationAttempts'])===5);
 denied(fn()=>$d->requestRegistration('sixthperson','Applicant','test@example.test','test-password-long','127.0.0.1'));
 check(count($d->read()['registrationAttempts'])===5);
 check(!in_array('sixthperson',array_column(array_map(fn($x)=>$x['user'],$d->read()['registrations']),'username'),true));
 // Rate limits are tied to direct-IP hash, not a forwarded client header.
 $d->requestRegistration('remoteuser','Other','test@example.test','test-password-long','127.0.0.2');
 check(count($d->read()['registrationAttempts'])===6);
 // Nobody can approve except the designated, active central administrator.
 $pending=array_key_first($d->read()['registrations']);
 denied(fn()=>$d->reviewRegistration($pending,1,$pending,true));
 check(isset($d->read()['registrations'][$pending]));

 `;
 try{fs.writeFileSync(path.join(dir,'test.php'),code);execFileSync('php',[path.join(dir,'test.php')]);}finally{fs.rmSync(dir,{recursive:true,force:true});}
});
