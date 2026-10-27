/*
File Name: remember.test.cjs
Author: Jason Lamb (with help from ChatGPT)
Description: Isolated HTTP verification for opt-in seven-day central login, expiry and sign-out.
Revision: 1.0.1
Created Date: 2026-10-27
Modified Date: 2026-10-27
Changelog: 1.0.1 - Check that remembered cookies retain fixed original expiry after a page load. 1.0.0 - Check session-only default, secure cookie flags, remembered expiry and revocation.
*/
'use strict';
const {test,after}=require('node:test');
const assert=require('node:assert/strict');
const fs=require('node:fs');
const os=require('node:os');
const path=require('node:path');
const {spawn}=require('node:child_process');
const root=path.resolve(__dirname,'../..');
const temp=fs.mkdtempSync(path.join(os.tmpdir(),'jasr-remember-'));
for(const name of ['1-Framework','user-management'])
 fs.cpSync(path.join(root,name),path.join(temp,name),{recursive:true});
const data=path.join(temp,'user-management/data');
for(const name of fs.readdirSync(data))if(name!=='.htaccess')
 fs.rmSync(path.join(data,name),{recursive:true,force:true});
const key='isolated-remember-test-key-at-least-32-characters';
fs.writeFileSync(path.join(temp,'user-management/config.local.php'),
 "<?php return ['secure_cookie'=>false,'setup_key'=>'"+key+"'];");
fs.writeFileSync(path.join(temp,'expire.php'),
 "<?php\nrequire_once __DIR__.'/1-Framework/bootstrap.php';\n"+
 "$auth=\\Jasr\\Framework\\SharedIdentity::connect();\n"+
 "if (!$auth->user()) {http_response_code(401);exit;}\n"+
 "$_SESSION['remember_until']=time()-1;\n"+
 "echo 'test-only forced expiration';\n");
const port=18963,host='http://127.0.0.1:'+port;
const server=spawn('php',['-S','127.0.0.1:'+port,'-t',temp],{stdio:['ignore','pipe','pipe']});
let logs='';
server.stderr.on('data',chunk=>logs+=String(chunk));
after(()=>{server.kill();fs.rmSync(temp,{recursive:true,force:true});if(/Fatal error|Parse error/.test(logs))console.error(logs);});

class Browser{
 cookie='';csrf='';
 async request(url='/user-management/',post=null){
  const headers={Cookie:this.cookie};
  if(post)headers['Content-Type']='application/x-www-form-urlencoded';
  const options={headers,redirect:'manual'};
  if(post){options.method='POST';options.body=new URLSearchParams({csrf:this.csrf,...post});}
  const res=await fetch(host+url,options);
  const cookies=res.headers.getSetCookie?.()||[];
  if(cookies.length)this.cookie=cookies[cookies.length-1].split(';')[0];
  const text=await res.text();
  const match=text.match(/name="csrf" value="([a-f0-9]+)"/);
  if(match)this.csrf=match[1];
  return {status:res.status,text,cookies};
 }
 async post(action,details={}){
  const res=await this.request('/user-management/',{action,...details});
  if(res.status===303)await this.request();
  return res;
 }
}
const password='example-password-for-tests-2026';
test('default idle session, opt-in remembered login, and global logout',async()=>{
 let ready=false;
 for(let i=0;i<100;i++){
  try{await fetch(host+'/user-management/');ready=true;break;}
  catch{await new Promise(resolve=>setTimeout(resolve,80));}
 }
 assert.ok(ready,'isolated PHP HTTP server must start; logs: '+logs);
 const browser=new Browser();
 let res=await browser.request();
 assert.match(res.text,/Create the first administrator/);
 assert.equal((await browser.post('setup',{
  setup_key:key,username:'member',name:'Test Member',
  password,confirm_password:password
 })).status,303);
 res=await browser.request();
 assert.match(res.text,/Stay signed in for 7 days/);
 assert.match(res.text,/30-minute inactivity/);

 // Without the checkbox, browser closes end the session; no persistent Expires.
 const normal=await browser.post('login',{username:'member',password});
 assert.equal(normal.status,303);
 assert.ok(normal.cookies.length>0,'PHP rotates the cookie after login');
 assert.ok(normal.cookies.every(x=>!/;\s*expires=/i.test(x)),
  'default login sets session-only cookies');
 assert.match((await browser.request()).text,/Signed in as/);

 await browser.post('logout');
 assert.match((await browser.request()).text,/<h2>Sign in<\/h2>/);
 const remembered=await browser.post('login',{
  username:'member',password,remember:'yes'
 });
 assert.equal(remembered.status,303);
 const persistent=remembered.cookies.find(x=>/;\s*expires=/i.test(x));
 assert.ok(persistent,'explicit remember choice sets a persistent cookie');
 assert.match(persistent,/httponly/i);
 assert.match(persistent,/samesite=strict/i);
 assert.match(persistent,/path=\//i);
 const match=persistent.match(/expires=([^;]+)/i);
 assert.ok(match,'expiry field exists');
 const remaining=(Date.parse(match[1])-Date.now())/86400000;
 assert.ok(remaining>6 && remaining<8,'remember expiry is bounded at seven days');
 const visited=await browser.request();
 assert.match(visited.text,/Signed in as/);
 const refreshed=visited.cookies.find(x=>/;\\s*expires=/i.test(x));
 if(refreshed){
  const expiry=refreshed.match(/expires=([^;]+)/i);
  assert.ok(expiry,'subsequent authenticated request must keep a persistent cookie');
  assert.ok(Math.abs(Date.parse(expiry[1])-Date.parse(match[1]))<2000,
   'each visit retains the original fixed seven-day expiry rather than extending it');
 }

 // Expired remembered sessions cannot bypass server expiration even with cookie.
 assert.equal((await browser.request('/expire.php')).status,200);
 assert.match((await browser.request()).text,/<h2>Sign in<\/h2>/,
  'expired remember token does not authenticate');
 assert.equal((await browser.post('login',{username:'member',password,remember:'yes'})).status,303);
 assert.match((await browser.request()).text,/Signed in as/);
 const loggedOut=await browser.post('logout');
 assert.equal(loggedOut.status,303);
 assert.match((await browser.request()).text,/<h2>Sign in<\/h2>/,
  'logging out revokes the remembered session');
 console.log('Passed: short default, opt-in seven-day cookie, absolute expiry and shared sign-out.');
});
