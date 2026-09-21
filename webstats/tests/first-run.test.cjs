// Default initialization and forced password change, using isolated test credentials.
const {test,after}=require('node:test');
const assert=require('node:assert/strict');
const fs=require('node:fs'),os=require('node:os'),path=require('node:path');
const {execFileSync,spawn}=require('node:child_process');
const root=path.resolve(__dirname,'../..');
const temp=fs.mkdtempSync(path.join(os.tmpdir(),'webstats-first-'));
const deploy=path.join(temp,'deployment');fs.mkdirSync(deploy);
for(const name of ['webstats','1-Framework'])fs.cpSync(path.join(root,name),path.join(deploy,name),{recursive:true});
const data=path.join(deploy,'webstats/data');
// A checkout contains only the access rule, never live settings or test artifacts.
for(const name of fs.readdirSync(data))if(name!=='.htaccess')fs.rmSync(path.join(data,name),{recursive:true,force:true});
const quote=s=>"'"+s.replaceAll('\\','\\\\').replaceAll("'","\\'")+"'";
const temporary='test-only-bootstrap-password',replacement='test-only-replacement-password';
execFileSync('php',['-r', 'file_put_contents('+quote(path.join(deploy,'webstats/initial-admin.php'))+',"<?php return ".var_export(["username"=>"admin","password_hash"=>password_hash('+quote(temporary)+',PASSWORD_DEFAULT)],true).";");']);
const env={...process.env};delete env.JASR_WEBSTATS_CONFIG;
const server=spawn('php',['-S','127.0.0.1:18766','-t',deploy],{env,stdio:['ignore','pipe','pipe']});let logs='';server.stderr.on('data',d=>logs+=d);
after(()=>{server.kill();fs.rmSync(temp,{recursive:true,force:true});});
const base='http://127.0.0.1:18766/webstats/';
function cookieOf(r){return r.headers.get('set-cookie')?.split(';')[0];}
async function screen(cookie=''){const r=await fetch(base,{headers:{Cookie:cookie}});return {r,html:await r.text(),cookie:cookieOf(r)||cookie};}
function token(html){return html.match(/name="csrf" value="([a-f0-9]+)"/)[1];}
const post=(cookie,values)=>fetch(base,{method:'POST',redirect:'manual',headers:{Cookie:cookie,'Content-Type':'application/x-www-form-urlencoded'},body:new URLSearchParams(values)});
async function signIn(password){let s=await screen();const r=await post(s.cookie,{csrf:token(s.html),username:'admin',password});return {r,cookie:cookieOf(r)||s.cookie};}
test('fresh install, mandatory reset, persistence, and stale-session rejection',async()=>{
  let ready=false;for(let i=0;i<60;i++){try{await fetch(base);ready=true;break;}catch{await new Promise(r=>setTimeout(r,100));}}assert.ok(ready,logs);
  const settings=JSON.parse(fs.readFileSync(path.join(data,'settings.json'))).records;
  assert.equal(settings.username,'admin');assert.equal(settings.must_change_password,true);assert.ok(settings.secret.length>=32);
  assert.equal((await screen()).r.status,200);
  const first=await signIn(temporary),second=await signIn(temporary);assert.equal(first.r.status,303);assert.equal(second.r.status,303);
  let s=await screen(first.cookie);assert.match(s.html,/Change your temporary password/);assert.doesNotMatch(s.html,/Top pages|Recent activity/);
  let r=await post(first.cookie,{action:'change-password',csrf:'invalid',new_password:replacement,confirm_password:replacement});assert.equal(r.status,403);
  const change=(a,b)=>post(first.cookie,{action:'change-password',csrf:token(s.html),new_password:a,confirm_password:b});
  assert.equal((await change('short','short')).status,400);
  assert.equal((await change(replacement,'mismatch')).status,400);
  assert.equal((await change(temporary,temporary)).status,400);
  r=await change(replacement,replacement);assert.equal(r.status,303);
  s=await screen(second.cookie);assert.match(s.html,/Sign in to your dashboard/);assert.doesNotMatch(s.html,/Top pages/);
  assert.equal((await signIn(temporary)).r.status,401);
  const final=await signIn(replacement);assert.equal(final.r.status,303);
  assert.match((await screen(final.cookie)).html,/Top pages/);
  const admin=fs.readFileSync(path.join(data,'admin.json'),'utf8');assert.doesNotMatch(admin,/test-only-replacement-password/);
  // A normal tracked-file update leaves ignored runtime files in place.
  fs.copyFileSync(path.join(root,'webstats/initial-admin.php'),path.join(deploy,'webstats/initial-admin.php'));
  assert.equal(fs.readFileSync(path.join(data,'admin.json'),'utf8'),admin);
  assert.equal((await signIn(replacement)).r.status,303);
  const check=execFileSync('git',['check-ignore','webstats/data/settings.json','webstats/data/admin.json','webstats/data/events-2026-09-21.json'],{cwd:root,encoding:'utf8'});
  assert.equal(check.trim().split('\n').length,3);
  assert.doesNotMatch(logs,/Fatal error|Warning:/);
});
