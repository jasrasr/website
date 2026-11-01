/*
File Name: registration-http.test.cjs
Revision: 1.0.0
Description: Isolated public signup/owner approval HTTP workflow with real CSRF and sessions.
Created: 2026-11-01
Modified: 2026-11-01
Changelog: 1.0.0 - Test public GET/POST, unverified pending account denial, Jason-only approval, no implicit grants.
*/
'use strict';
const {test,after}=require('node:test');
const assert=require('node:assert/strict');
const fs=require('node:fs'),os=require('node:os'),path=require('node:path');
const {spawn}=require('node:child_process');
const root=path.resolve(__dirname,'../..');
const temp=fs.mkdtempSync(path.join(os.tmpdir(),'jasr-register-http-'));
for(const name of ['1-Framework','user-management'])
 fs.cpSync(path.join(root,name),path.join(temp,name),{recursive:true});
const directory=path.join(temp,'user-management/data');
for(const file of fs.readdirSync(directory))
 if(file!=='.htaccess')fs.rmSync(path.join(directory,file),{recursive:true,force:true});
const setupKey='isolated-registration-test-key-2026-secure';
fs.writeFileSync(path.join(temp,'user-management/config.local.php'),
 "<?php return ['secure_cookie'=>false,'setup_key'=>'"+setupKey+"'];");
const host='http://127.0.0.1:18975';
const server=spawn('php',['-S','127.0.0.1:18975','-t',temp],{stdio:['ignore','pipe','pipe']});
let serverLog='';
server.stderr.on('data',chunk=>serverLog+=String(chunk));
after(()=>{server.kill();fs.rmSync(temp,{recursive:true,force:true});if(/Fatal error|Parse error/.test(serverLog))console.error(serverLog);});
class Browser {
 cookie='';csrf='';
 async request(route='/user-management/',values=null){
  const headers={Cookie:this.cookie};
  const options={headers,redirect:'manual'};
  if(values){
   options.method='POST';
   headers['Content-Type']='application/x-www-form-urlencoded';
   options.body=new URLSearchParams({csrf:this.csrf,...values});
  }
  const response=await fetch(host+route,options);
  const cookie=response.headers.get('set-cookie');
  if(cookie)this.cookie=cookie.split(';')[0];
  const html=await response.text();
  const match=html.match(/name="csrf" value="([a-f0-9]+)"/);
  if(match)this.csrf=match[1];
  return {status:response.status,html};
 }
 async post(route,values){return this.request(route,values);}
}
const authRoute='/user-management/';
const signupRoute='/user-management/register.php';
const password='registration-test-password-2026';
function state(){
 return JSON.parse(fs.readFileSync(path.join(directory,'directory.json'),'utf8')).records;
}
test('unauthenticated registration needs CSRF and Jason approval, grants nothing',async()=>{
 let ready=false;
 for(let i=0;i<90;i++){
  try{await fetch(host+authRoute);ready=true;break;}
  catch{await new Promise(resolve=>setTimeout(resolve,90));}
 }
 assert.ok(ready,'isolated server failed: '+serverLog);
 const admin=new Browser();
 await admin.request();
 let response=await admin.post(authRoute,{
  action:'setup',setup_key:setupKey,username:'jasrasr',name:'Administrator',
  password,confirm_password:password
 });
 assert.equal(response.status,303,'initial administrator setup succeeds');
 const applicant=new Browser();
 response=await applicant.request(signupRoute);
 assert.equal(response.status,200);
 assert.match(response.html,/Request an account/);
 assert.match(response.html,/requires Jason/);
 assert.ok(applicant.csrf,'registration form contains CSRF token');
 const invalid=await applicant.post(signupRoute,{
  csrf:'wrong',username:'requesteduser',name:'Applicant',email:'applicant@example.invalid',
  password,confirm:password
 });
 assert.equal(invalid.status,403,'reject forged CSRF token');
 assert.ok(!state().registrations || !Object.keys(state().registrations).length,
  'forged form makes no private pending record');
 response=await applicant.post(signupRoute,{
  username:'requesteduser',name:'Applicant <script>',email:'applicant@example.invalid',
  password,confirm:password
 });
 assert.equal(response.status,200);
 assert.match(response.html,/Request received/);
 const snapshot=state();
 assert.equal(Object.keys(snapshot.users).length,1,'signup is pending, never immediately active');
 assert.equal(Object.keys(snapshot.registrations).length,1,'only one request stored');
 const pending=Object.values(snapshot.registrations)[0].user;
 assert.equal(pending.active,false);
 assert.equal(pending.allProjects,false);
 assert.equal(pending.admin,false);
 assert.deepEqual(pending.projects,[]);
 assert.equal(pending.role,'user');
 assert.ok(password!==pending.hash && pending.hash.startsWith('$2'),
  'pending password securely hashed, never cleartext');
 assert.ok(!JSON.stringify(snapshot).includes(password),'private JSON never exposes submitted password');
 const attacker=new Browser();
 await attacker.request();
 assert.equal((await attacker.post(authRoute,{action:'login',username:'requesteduser',password})).status,401,
  'pending account cannot authenticate');
 assert.equal((await attacker.post(authRoute,{
  action:'approve-registration',id:Object.keys(snapshot.registrations)[0]
 })).status,403,'unauthenticated visitor cannot approve');
 await admin.request();
 assert.equal((await admin.post(authRoute,{action:'login',username:'jasrasr',password})).status,303);
 response=await admin.request();
 assert.match(response.html,/Pending new users/);
 assert.match(response.html,/Applicant &lt;script&gt;/,'applicant name HTML-escaped in administrator view');
 assert.equal((await admin.post(authRoute,{
  action:'approve-registration',id:Object.keys(snapshot.registrations)[0]
 })).status,303,'only central Jason administrator can approve');
 const afterApproval=state();
 assert.equal(Object.keys(afterApproval.registrations).length,0,'approved request removed');
 const approved=Object.values(afterApproval.users).find(u=>u.username==='requesteduser');
 assert.ok(approved && approved.active);
 assert.equal(approved.role,'user');
 assert.equal(approved.admin,false);
 assert.equal(approved.allProjects,false);
 assert.deepEqual(approved.projects,[]);
 assert.equal((await admin.post(authRoute,{
  action:'approve-registration',id:Object.keys(snapshot.registrations)[0]
 })).status,400,'replayed approval is rejected');
 assert.equal((await attacker.post(authRoute,{action:'login',username:'requesteduser',password})).status,303,
  'account may sign in after approval with requested password');
 const publicPage=await attacker.request();
 assert.doesNotMatch(publicPage.html,/Pending new users/,'regular users never see approval controls');
 console.log('Passed: CSRF, pending login denial, escaped admin queue, owner-only approval, replay denial, no grants.');
});
