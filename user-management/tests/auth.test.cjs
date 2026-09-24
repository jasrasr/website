// Real HTTP integration tests; all credentials/data are disposable and isolated.
const {test, after} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs'), os = require('node:os'), path = require('node:path');
const {spawn, execFileSync} = require('node:child_process');
const root = path.resolve(__dirname, '../..');
const temp = fs.mkdtempSync(path.join(os.tmpdir(), 'jasr-users-'));
for (const name of ['1-Framework','user-management']) fs.cpSync(path.join(root,name),path.join(temp,name),{recursive:true});
const data = path.join(temp,'user-management/data');
for (const name of fs.readdirSync(data)) if (name !== '.htaccess') fs.rmSync(path.join(data,name),{recursive:true,force:true});
const setupKey = 'test-only-setup-key-with-at-least-32-characters';
fs.writeFileSync(path.join(temp,'user-management/config.local.php'),`<?php return ['secure_cookie'=>false,'setup_key'=>'${setupKey}'];`);
for (const project of ['alpha','beta']) {
  fs.mkdirSync(path.join(temp,project));
  fs.writeFileSync(path.join(temp,project,'index.php'),`<?php
require_once dirname(__DIR__).'/1-Framework/bootstrap.php';
$auth=\\Jasr\\Framework\\SharedIdentity::connect();
$user=$auth->requireProject('${project}', $_GET['role'] ?? 'viewer', isset($_GET['json']));
\\Jasr\\Framework\\Response::json(200,'Allowed',$user);
`);
}
const server = spawn('php',['-S','127.0.0.1:18769','-t',temp],{stdio:['ignore','pipe','pipe']});
let logs='';server.stderr.on('data',d=>logs+=d);
after(()=>{server.kill();fs.rmSync(temp,{recursive:true,force:true});if (/Warning:|Fatal error/.test(logs)) console.error(logs);});
const base='http://127.0.0.1:18769';
const portal='/user-management/';
class Browser {
  cookie=''; csrf='';
  async request(route=portal, values=null) {
    const headers={Cookie:this.cookie};
    const options={headers,redirect:'manual'};
    if(values){options.method='POST';headers['Content-Type']='application/x-www-form-urlencoded';options.body=new URLSearchParams({csrf:this.csrf,...values});}
    const r=await fetch(base+route,options);
    const cookie=r.headers.get('set-cookie');if(cookie)this.cookie=cookie.split(';')[0];
    const html=await r.text();this.csrf=html.match(/name="csrf" value="([a-f0-9]+)"/)?.[1]||this.csrf;
    return {status:r.status,html,headers:r.headers};
  }
  async post(values){const r=await this.request(portal,values);await this.request();return r;}
  async login(username,password){await this.request();return this.post({action:'login',username,password});}
}
const adminPassword='test-admin-password-2026';
const temporary='test-temporary-password';
const permanent='test-permanent-password';
function state(){return JSON.parse(fs.readFileSync(path.join(data,'directory.json'),'utf8')).records;}
function userId(username){return Object.values(state().users).find(u=>u.username===username).id;}
test('central setup, shared sessions, project permissions, revocation and persistence',async()=>{
  let ready=false;for(let i=0;i<80;i++){try{await fetch(base+portal);ready=true;break;}catch{await new Promise(r=>setTimeout(r,100));}}assert.ok(ready,logs);
  const admin=new Browser();
  assert.match((await admin.request()).html,/Create the first administrator/);
  assert.equal((await admin.post({action:'setup',setup_key:'wrong',username:'admin',name:'Admin',password:adminPassword,confirm_password:adminPassword})).status,403);
  assert.equal((await admin.post({action:'setup',setup_key:setupKey,username:'admin',name:'Admin',password:adminPassword,confirm_password:adminPassword})).status,303);
  assert.equal((await admin.request('/user-management/setup.php')).status,404);
  assert.equal((await admin.post({action:'setup',setup_key:setupKey,username:'another',name:'Admin',password:adminPassword,confirm_password:adminPassword})).status,400);
  assert.equal((await admin.request('/alpha/?json=1')).status,401);
  const before=admin.cookie;
  assert.equal((await admin.login('ADMIN',adminPassword)).status,303);assert.notEqual(admin.cookie,before);
  assert.equal((await admin.post({action:'save-project',csrf:'bad',project:'alpha',name:'Alpha',path:'/alpha/'})).status,403);
  assert.equal((await admin.post({action:'save-project',project:'alpha',name:'Alpha',path:'//evil.example/'})).status,400);
  for(const p of ['alpha','beta']) assert.equal((await admin.post({action:'save-project',project:p,name:p,path:`/${p}/`})).status,303);
  const create=await admin.post({action:'create-user',username:'reader',name:'Reader <script>alert(1)</script>',password:temporary});assert.equal(create.status,303);
  assert.equal((await admin.post({action:'create-user',username:'READER',name:'Duplicate',password:temporary})).status,400);
  const readerId=userId('reader'), adminId=userId('admin');
  assert.equal((await admin.post({action:'grant',id:readerId,project:'alpha',role:'viewer'})).status,303);
  assert.equal((await admin.post({action:'save-user',id:adminId})).status,400); // Last admin cannot be disabled/demoted.
  assert.equal(state().users[adminId].active,true);
  const reader=new Browser(), second=new Browser();
  assert.equal((await reader.login('reader',temporary)).status,303);
  await second.login('reader',temporary);
  assert.match((await reader.request()).html,/Change your temporary password/);
  assert.equal((await reader.request('/alpha/?json=1')).status,401);
  assert.equal((await reader.post({action:'save-project',project:'evil',name:'Evil',path:'/evil/'})).status,403);
  assert.equal((await reader.post({action:'change-password',current_password:temporary,password:permanent,confirm_password:'no'})).status,400);
  assert.equal((await reader.post({action:'change-password',current_password:'incorrect',password:permanent,confirm_password:permanent})).status,400);
  assert.equal((await reader.post({action:'change-password',current_password:temporary,password:permanent,confirm_password:permanent})).status,303);
  assert.equal((await second.request('/alpha/?json=1')).status,401);
  assert.equal((await reader.login('reader',temporary)).status,401);
  assert.equal((await reader.login('reader',permanent)).status,303);
  let page=await reader.request();assert.match(page.html,/&lt;script&gt;/);assert.doesNotMatch(page.html,/<script>/);
  let allowed=await reader.request('/alpha/?json=1');assert.equal(allowed.status,200);assert.doesNotMatch(allowed.html,/"hash"/);
  assert.equal((await reader.request('/alpha/?json=1&role=member')).status,403);
  assert.equal((await reader.request('/alpha/?json=1&role=invalid')).status,403);
  assert.equal((await reader.request('/beta/?json=1')).status,403);
  assert.equal((await reader.post({action:'grant',id:readerId,project:'beta',role:'admin'})).status,403);
  assert.equal((await admin.request('/beta/?json=1&role=admin')).status,200);
  assert.equal((await admin.request('/beta/?json=1&role=invalid')).status,403);
  assert.equal((await admin.post({action:'grant',id:readerId,project:'beta',role:'member'})).status,303);
  assert.equal((await reader.request('/alpha/?json=1')).status,401);
  await reader.login('reader',permanent);
  assert.equal((await reader.request('/beta/?json=1&role=member')).status,200);
  assert.equal((await reader.request('/beta/?json=1&role=admin')).status,403);
  assert.equal((await reader.post({action:'logout'})).status,303);
  assert.equal((await reader.request('/alpha/?json=1')).status,401);
  assert.equal((await reader.request('/beta/?json=1')).status,401);
  await reader.login('reader',permanent);
  assert.equal((await admin.post({action:'reset-password',id:readerId,password:temporary})).status,303);
  assert.equal((await reader.request('/alpha/?json=1')).status,401);
  await reader.login('reader',temporary);
  assert.equal((await reader.request('/alpha/?json=1')).status,401);
  assert.equal((await admin.post({action:'save-user',id:readerId})).status,303);
  assert.equal((await reader.login('reader',temporary)).status,401);
  const stored=fs.readFileSync(path.join(data,'directory.json'),'utf8');
  assert.doesNotMatch(stored,/test-admin-password|test-temporary-password|test-permanent-password/);
  fs.copyFileSync(path.join(root,'user-management/config.example.php'),path.join(temp,'user-management/config.example.php'));
  assert.equal(fs.readFileSync(path.join(data,'directory.json'),'utf8'),stored);
  const ignored=execFileSync('git',['check-ignore','user-management/config.local.php','user-management/data/directory.json','user-management/data/attempts.json','1-Framework/config/config.php'],{cwd:root,encoding:'utf8'});
  assert.equal(ignored.trim().split('\n').length,4);
  // A disabled account never gains a session; throttling survives new browsers.
  assert.equal((await admin.post({action:'create-user',username:'throttle',name:'Throttle',password:temporary})).status,303);
  for(let i=0;i<10;i++) assert.equal((await new Browser().login('throttle','bad-password')).status,401);
  assert.equal((await new Browser().login('throttle',temporary)).status,401);
  assert.doesNotMatch(logs,/Fatal error|Warning:|Uncaught/);
});
