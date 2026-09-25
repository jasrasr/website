// Account-linking pilot: isolated deployment, real HTTP requests, exact budget-byte checks.
const {test,after}=require('node:test');
const assert=require('node:assert/strict');
const fs=require('node:fs'),os=require('node:os'),path=require('node:path');
const {spawn,execFileSync}=require('node:child_process');
const root=path.resolve(__dirname,'../..'),temp=fs.mkdtempSync(path.join(os.tmpdir(),'jasr-linking-'));
for(const name of ['1-Framework','user-management','finances'])fs.cpSync(path.join(root,name),path.join(temp,name),{recursive:true});
for(const name of ['user-management','finances']){
  const dir=path.join(temp,name,'data');
  for(const file of fs.readdirSync(dir))if(file!=='.htaccess')fs.rmSync(path.join(dir,file),{recursive:true,force:true});
}
fs.writeFileSync(path.join(temp,'user-management/config.local.php'),"<?php return ['secure_cookie'=>false];");
const adminPassword='test-admin-secret-2026',userPassword='test-member-secret-2026';
execFileSync('php',[path.join(temp,'user-management/setup.php'),'admin','Administrator'],{input:adminPassword+'\n'});
const configPath=path.join(temp,'finances/config.private.php');
function config(mode='legacy',extra=''){
  fs.writeFileSync(configPath,`<?php return ['authentication'=>'${mode}','users'=>['old-jason'=>['password'=>'legacy-jason-password'],'old-hannah'=>['password'=>'legacy-hannah-password'],'missing'=>['password'=>'legacy-missing-password'],'broken'=>['password'=>'legacy-broken-password']${extra}]];`);
}
config();
const budgetDir=path.join(temp,'finances/data'),linkDir=path.join(budgetDir,'identity');
const jasonPath=path.join(budgetDir,'old-jason.json'),hannahPath=path.join(budgetDir,'old-hannah.json');
const jasonBytes='{\n "income":{"hourlyRate":37.5}, "expenses":[{"id":"existing-123","name":"Existing expense","amount":140}], "legacyExtra":"preserve me"\n}\n';
const hannahBytes='{"income":{"hourlyRate":42},"expenses":[],"marker":"Hannah private"}\n';
fs.writeFileSync(jasonPath,jasonBytes);fs.writeFileSync(hannahPath,hannahBytes);
fs.writeFileSync(path.join(budgetDir,'broken.json'),'{invalid');
fs.writeFileSync(path.join(budgetDir,'orphan.json'),'{"income":{},"expenses":[]}');
const sessions=path.join(temp,'sessions');fs.mkdirSync(sessions);
const server=spawn('php',['-d',`session.save_path=${sessions}`,'-S','127.0.0.1:18770','-t',temp],{stdio:['ignore','pipe','pipe']});
let logs='';server.stderr.on('data',d=>logs+=d);
after(()=>{server.kill();fs.rmSync(temp,{recursive:true,force:true});});
const base='http://127.0.0.1:18770',portal='/user-management/',linkPage=portal+'links.php';
class Browser{
 cookie='';csrf='';nonce='';cookies=new Map();
 async request(url=portal,values=null,json=false){
  const headers={Cookie:this.cookie};const options={headers,redirect:'manual'};
  if(values){options.method='POST';headers['Content-Type']=json?'application/json':'application/x-www-form-urlencoded';
    if(json){headers['X-CSRF-Token']=this.csrf;options.body=JSON.stringify(values);}
    else options.body=new URLSearchParams({csrf:this.csrf,...values});}
  const r=await fetch(base+url,options);const c=r.headers.get('set-cookie');if(c){const pair=c.split(';')[0];this.cookies.set(pair.split('=')[0],pair);this.cookie=[...this.cookies.values()].join('; ');}
  const html=await r.text();this.csrf=html.match(/name="csrf" value="([a-f0-9]+)"/)?.[1]||this.csrf;
  this.nonce=html.match(/name="nonce" value="([a-f0-9]+)"/)?.[1]||this.nonce;
  return {status:r.status,html,headers:r.headers};
 }
 async adminPost(values){const r=await this.request(portal,values);await this.request(portal);return r;}
 async login(username,password=userPassword){await this.request();return this.adminPost({action:'login',username,password});}
 async preview(legacy,id){return this.request(linkPage,{action:'preview',project:'finances',legacy_id:legacy,target_id:id});}
 async apply(extra={}){return this.request(linkPage,{action:'apply',project:'finances',nonce:this.nonce,confirm:'yes',...extra});}
}
function state(){return JSON.parse(fs.readFileSync(path.join(temp,'user-management/data/directory.json'),'utf8')).records;}
function id(username){return Object.values(state().users).find(u=>u.username===username).id;}
function mappings(){return JSON.parse(fs.readFileSync(path.join(linkDir,'links.json'),'utf8')).records;}
function sameBudgets(){assert.equal(fs.readFileSync(jasonPath,'utf8'),jasonBytes);assert.equal(fs.readFileSync(hannahPath,'utf8'),hannahBytes);}
test('preview, stale rejection, explicit mapping, existing-data access, isolation and rollback',async()=>{
 let ready=false;for(let i=0;i<80;i++){try{await fetch(base+portal);ready=true;break;}catch{await new Promise(r=>setTimeout(r,100));}}assert.ok(ready,logs);
 const admin=new Browser();assert.equal((await admin.login('admin',adminPassword)).status,303);
 assert.equal((await admin.adminPost({action:'save-project',project:'finances',name:'Finances',path:'/finances/'})).status,303);
 for(const username of ['new-jason','new-hannah','unlinked','no-access']){
   assert.equal((await admin.adminPost({action:'create-user',username,name:username,password:userPassword})).status,303);
   if(username!=='no-access')assert.equal((await admin.adminPost({action:'grant',id:id(username),project:'finances',role:username==='new-hannah'?'viewer':'member'})).status,303);
 }
 const jason=new Browser(),hannah=new Browser(),unlinked=new Browser();
 for(const [browser,username] of [[jason,'new-jason'],[hannah,'new-hannah'],[unlinked,'unlinked']]){
   await browser.login(username);
   assert.equal((await browser.adminPost({action:'change-password',current_password:userPassword,password:userPassword+'-changed',confirm_password:userPassword+'-changed'})).status,303);
   assert.equal((await browser.login(username,userPassword+'-changed')).status,303);
 }
 assert.equal((await jason.request(linkPage)).status,403);
 assert.equal((await jason.preview('old-jason',id('new-jason'))).status,403);
 assert.equal((await new Browser().request(linkPage)).status,303);
 let report=await admin.request(linkPage);assert.equal(report.status,200);assert.match(report.html,/Not linked/);assert.match(report.html,/Blocked/);assert.match(report.html,/unconfigured file/);
 assert.equal(fs.existsSync(linkDir),false);sameBudgets();
 assert.equal((await admin.preview('missing',id('new-jason'))).status,400);
 assert.equal((await admin.preview('broken',id('new-jason'))).status,400);
 assert.equal((await admin.preview('../escape',id('new-jason'))).status,400);
 assert.equal((await admin.preview('old-jason',id('no-access'))).status,400);
 // Names whose sanitization collides are rejected.
 config('legacy',",'a.b'=>['password'=>'collision-test-password'],'a@b'=>['password'=>'collision-test-password']");
 fs.writeFileSync(path.join(budgetDir,'a_b.json'),'{"income":{},"expenses":[]}');
 assert.equal((await admin.preview('a.b',id('new-jason'))).status,400);config();fs.unlinkSync(path.join(budgetDir,'a_b.json'));
 assert.equal((await admin.preview('old-jason',id('new-jason'))).status,200);
 assert.equal(fs.existsSync(linkDir),false);sameBudgets();
 assert.equal((await admin.apply({csrf:'invalid'})).status,403);assert.equal(fs.existsSync(linkDir),false);
 fs.appendFileSync(jasonPath,' ');
 assert.equal((await admin.apply()).status,400);assert.equal(fs.existsSync(path.join(linkDir,'links.json')),false);
 fs.writeFileSync(jasonPath,jasonBytes);
 assert.equal((await admin.apply()).status,400); // consumed preview cannot be replayed
 assert.equal((await admin.preview('old-jason',id('new-jason'))).status,200);
 const previewNonce=admin.nonce;
 assert.equal((await admin.apply()).status,303);sameBudgets();
 let links=mappings();assert.equal(links.links[id('new-jason')].legacyId,'old-jason');assert.equal(links.history.length,1);
 const backups=fs.readdirSync(linkDir).filter(n=>/^backup-.*\.json$/.test(n));assert.equal(backups.length,1);
 const backup=JSON.parse(fs.readFileSync(path.join(linkDir,backups[0]))).records;
 assert.equal(backup.snapshot.budget,jasonBytes);assert.equal(backup.snapshot.legacyAccount.password,'legacy-jason-password');
 assert.equal((await admin.apply({nonce:previewNonce})).status,400);
 assert.equal((await admin.preview('old-jason',id('new-hannah'))).status,400);
 assert.equal((await admin.preview('old-hannah',id('new-jason'))).status,400);
 // An unrelated mapping change invalidates other outstanding previews.
 const otherAdmin=new Browser();await otherAdmin.login('admin',adminPassword);
 await admin.preview('old-hannah',id('new-hannah'));
 await otherAdmin.preview('old-hannah',id('new-hannah'));
 assert.equal((await otherAdmin.apply()).status,303);
 assert.equal((await admin.apply()).status,400);sameBudgets();
 report=await admin.request(linkPage);assert.match(report.html,/Linked/);assert.doesNotMatch(report.html,/legacy-jason-password|Hannah private|Existing expense/);
 // Legacy login remains intact before the explicit mode switch.
 const legacy=new Browser();await legacy.request('/finances/');
 assert.equal((await legacy.request('/finances/',{action:'login',username:'old-jason',password:'legacy-jason-password'})).status,302);
 assert.equal((await legacy.request('/finances/?api=budget')).status,200);sameBudgets();
 config('shared');
 assert.equal((await legacy.request('/finances/?api=budget')).status,401); // No legacy-session bypass.
 assert.equal((await unlinked.request('/finances/?api=budget')).status,403);
 assert.equal((await admin.request('/finances/?api=budget')).status,403); // Site admin isn't automatically an owner.
 let response=await jason.request('/finances/?api=budget');assert.equal(response.status,200);
 assert.deepEqual(JSON.parse(response.html).budget,JSON.parse(jasonBytes));assert.equal(JSON.parse(response.html).username,'old-jason');
 response=await hannah.request('/finances/?api=budget&username=old-jason');assert.equal(response.status,200);
 assert.equal(JSON.parse(response.html).budget.marker,'Hannah private');sameBudgets();
 const updated={income:{hourlyRate:55},expenses:[{id:'existing-123',name:'Updated safely',amount:140}]};
 assert.equal((await hannah.request('/finances/?api=budget',updated,true)).status,403);
 const csrf=jason.csrf;jason.csrf='bad';assert.equal((await jason.request('/finances/?api=budget',updated,true)).status,403);jason.csrf=csrf;sameBudgets();
 assert.equal((await jason.request('/finances/?api=budget',updated,true)).status,200);
 assert.equal(JSON.parse(fs.readFileSync(jasonPath)).income.hourlyRate,55);assert.equal(fs.readFileSync(hannahPath,'utf8'),hannahBytes);
 assert.equal(JSON.parse(fs.readFileSync(jasonPath)).legacyExtra,'preserve me');
 assert.equal(fs.existsSync(path.join(budgetDir,'new-jason.json')),false);
 // Loss/corruption never produces an empty replacement budget.
 const saved=fs.readFileSync(jasonPath,'utf8');fs.unlinkSync(jasonPath);
 assert.equal((await jason.request('/finances/?api=budget')).status,503);assert.equal(fs.existsSync(jasonPath),false);
 fs.writeFileSync(jasonPath,'invalid');assert.equal((await jason.request('/finances/?api=budget',updated,true)).status,503);
 assert.equal(fs.readFileSync(jasonPath,'utf8'),'invalid');fs.writeFileSync(jasonPath,saved);
 assert.equal((await admin.adminPost({action:'save-user',id:id('new-jason')})).status,303);
 assert.equal((await jason.request('/finances/?api=budget')).status,401);
 // Rollback changes login mode only: old account reads the latest budget, not its snapshot.
 config('legacy');assert.equal((await legacy.request('/finances/?api=budget')).status,200);
 assert.equal(JSON.parse((await legacy.request('/finances/?api=budget')).html).budget.income.hourlyRate,55);
 const ignored=execFileSync('git',['check-ignore','finances/config.private.php','finances/data/identity/links.json','finances/data/identity/backup-test.json','finances/data/old-jason.json.lock'],{cwd:root,encoding:'utf8'});
 assert.equal(ignored.trim().split('\n').length,4);
 assert.doesNotMatch(logs,/PHP Warning|Fatal error|Uncaught/);
});
