const {test,after}=require('node:test');
const assert=require('node:assert/strict');
const fs=require('node:fs'),os=require('node:os'),path=require('node:path');
const {spawn,execFileSync}=require('node:child_process');
const root=path.resolve(__dirname,'../..'),temp=fs.mkdtempSync(path.join(os.tmpdir(),'jasr-roles-'));
for(const name of ['1-Framework','user-management'])fs.cpSync(path.join(root,name),path.join(temp,name),{recursive:true});
const data=path.join(temp,'user-management/data');
for(const f of fs.readdirSync(data))if(f!=='.htaccess')fs.rmSync(path.join(data,f),{recursive:true,force:true});
fs.writeFileSync(path.join(temp,'user-management/config.local.php'),"<?php return ['secure_cookie'=>false];");
const rootPassword='test-root-password-2026',jasonPassword='test-jason-password-2026';
execFileSync('php',[path.join(temp,'user-management/setup.php'),'owner','Initial Owner'],{input:rootPassword+'\n'});
const directory=path.join(data,'directory.json');
function state(){return JSON.parse(fs.readFileSync(directory,'utf8')).records;}
function user(name){return Object.values(state().users).find(u=>u.username===name);}
// Old deployed boolean-admin records retain their explicit existing rights.
let legacy=JSON.parse(fs.readFileSync(directory,'utf8'));for(const u of Object.values(legacy.records.users)){delete u.role;delete u.allProjects;delete u.directoryAdmin;}
fs.writeFileSync(directory,JSON.stringify(legacy));
for(const project of ['alpha','beta','gamma','future']){
 fs.mkdirSync(path.join(temp,project));
 fs.writeFileSync(path.join(temp,project,'index.php'),`<?php require_once dirname(__DIR__).'/1-Framework/bootstrap.php'; $a=\\Jasr\\Framework\\SharedIdentity::connect(); $u=$a->requireProject('${project}',$_GET['role']??'viewer',true); \\Jasr\\Framework\\Response::json(200,'Allowed',$u);`);
}
const sessions=path.join(temp,'sessions');fs.mkdirSync(sessions);
const server=spawn('php',['-d',`session.save_path=${sessions}`,'-S','127.0.0.1:18771','-t',temp],{stdio:['ignore','pipe','pipe']});
let logs='';server.stderr.on('data',d=>logs+=d);after(()=>{server.kill();fs.rmSync(temp,{recursive:true,force:true});});
const base='http://127.0.0.1:18771',portal='/user-management/';
class Browser{
 cookie='';csrf='';
 async request(url=portal,values=null){
  const headers={Cookie:this.cookie},options={headers,redirect:'manual'};
  if(values){options.method='POST';headers['Content-Type']='application/x-www-form-urlencoded';options.body=new URLSearchParams({csrf:this.csrf,...values});}
  const r=await fetch(base+url,options),c=r.headers.get('set-cookie');if(c)this.cookie=c.split(';')[0];
  const html=await r.text();this.csrf=html.match(/name="csrf" value="([a-f0-9]+)"/)?.[1]||this.csrf;return {status:r.status,html};
 }
 async post(values){const r=await this.request(portal,values);await this.request();return r;}
 async login(name,password){await this.request();return this.post({action:'login',username:name,password});}
}
test('scoped account roles, explicit all-project access, shared profile and safe provisioning',async()=>{
 let ready=false;for(let i=0;i<80;i++){try{await fetch(base+portal);ready=true;break;}catch{await new Promise(r=>setTimeout(r,100));}}assert.ok(ready,logs);
 const owner=new Browser();assert.equal((await owner.login('owner',rootPassword)).status,303);
 for(const project of ['alpha','beta','gamma'])assert.equal((await owner.post({action:'save-project',project,name:project,path:`/${project}/`})).status,303);
 assert.equal((await owner.request('/alpha/?role=super_admin')).status,200);
 assert.equal((await owner.post({action:'create-user',username:'jasrasr',name:'Jason Existing',password:jasonPassword,account_role:'user'})).status,303);
 const before=user('jasrasr');
 assert.equal((await owner.post({action:'provision-requested'})).status,400);
 assert.equal((await owner.post({action:'provision-requested',confirm_owner:'yes',csrf:'bad'})).status,403);
 const created=await owner.post({action:'provision-requested',confirm_owner:'yes'});assert.equal(created.status,200);
 const passwords=Object.fromEntries([...created.html.matchAll(/<strong>(demo-[a-z-]+)<\/strong>: <code>([a-f0-9]+)<\/code>/g)].map(m=>[m[1],m[2]]));
 assert.equal(Object.keys(passwords).length,3);assert.equal(new Set(Object.values(passwords)).size,3);
 const jason=user('jasrasr');assert.equal(jason.id,before.id);assert.equal(jason.hash,before.hash);
 assert.equal(jason.role,'super_admin');assert.equal(jason.allProjects,true);assert.equal(jason.directoryAdmin,true);
 assert.ok(jason.version>before.version);
 for(const name of ['demo-user','demo-admin','demo-super-admin']){
  const u=user(name);assert.equal(u.demo,true);assert.equal(u.allProjects,false);assert.equal(u.directoryAdmin,false);assert.deepEqual(u.projects,[]);assert.equal(u.mustChangePassword,true);
  assert.equal(fs.readFileSync(directory,'utf8').includes(passwords[name]),false);
 }
 const snapshots=Object.fromEntries(Object.keys(passwords).map(n=>[n,user(n).hash]));
 assert.match((await owner.post({action:'provision-requested',confirm_owner:'yes'})).html,/No new passwords/);
 for(const [n,h] of Object.entries(snapshots))assert.equal(user(n).hash,h);
 const demoUser=new Browser(),demoAdmin=new Browser(),demoSuper=new Browser(),me=new Browser();
 for(const [b,n,p] of [[demoUser,'demo-user',passwords['demo-user']],[demoAdmin,'demo-admin',passwords['demo-admin']],[demoSuper,'demo-super-admin',passwords['demo-super-admin']],[me,'jasrasr',jasonPassword]]){
  assert.equal((await b.login(n,p)).status,303);
  assert.equal((await b.request('/alpha/?role=viewer')).status,401);
  assert.equal((await b.post({action:'change-password',current_password:p,password:p+'-changed',confirm_password:p+'-changed'})).status,303);
  assert.equal((await b.login(n,p+'-changed')).status,303);
 }
 assert.equal((await demoSuper.request('/alpha/?role=viewer')).status,403);
 assert.equal((await demoSuper.post({action:'create-user',username:'intruder',name:'Intruder',password:rootPassword})).status,403);
 assert.equal((await demoSuper.post({action:'provision-requested',confirm_owner:'yes'})).status,403);
 assert.equal((await owner.post({action:'grant',id:user('demo-user').id,project:'alpha',role:'admin'})).status,400);
 for(const [name,alpha,beta] of [['demo-user','member','viewer'],['demo-admin','admin','member'],['demo-super-admin','super_admin','admin']]){
  for(const [project,role] of [['alpha',alpha],['beta',beta]])assert.equal((await owner.post({action:'grant',id:user(name).id,project,role})).status,303);
 }
 for(const [b,name] of [[demoUser,'demo-user'],[demoAdmin,'demo-admin'],[demoSuper,'demo-super-admin']])await b.login(name,passwords[name]+'-changed');
 assert.equal((await demoUser.request('/alpha/?role=member')).status,200);assert.equal((await demoUser.request('/beta/')).status,200);
 assert.equal((await demoUser.request('/alpha/?role=admin')).status,403);
 assert.equal((await demoAdmin.request('/alpha/?role=admin')).status,200);assert.equal((await demoAdmin.request('/beta/?role=member')).status,200);
 assert.equal((await demoAdmin.request('/alpha/?role=super_admin')).status,403);
 assert.equal((await demoSuper.request('/alpha/?role=super_admin')).status,200);assert.equal((await demoSuper.request('/beta/?role=admin')).status,200);
 assert.equal((await demoSuper.request('/gamma/')).status,403);
 assert.equal((await owner.post({action:'save-user',id:user('demo-super-admin').id,active:'on',account_role:'super_admin',all_projects:'on',directory_admin:'on'})).status,400);
 assert.equal((await demoSuper.post({action:'profile',name:'Demo Profile',email:'demo@example.test',all_projects:'on',directory_admin:'on'})).status,303);
 let profile=JSON.parse((await demoSuper.request('/alpha/')).html).data;assert.equal(profile.name,'Demo Profile');assert.equal(profile.email,'demo@example.test');assert.equal(profile.allProjects,false);assert.equal(profile.admin,false);assert.equal(profile.hash,undefined);
 assert.equal((await me.request('/gamma/?role=super_admin')).status,200);
 await owner.post({action:'save-project',project:'future',name:'Future',path:'/future/'});
 assert.equal((await me.request('/future/?role=super_admin')).status,200);assert.equal((await demoSuper.request('/future/')).status,403);
 // A legacy admin checkbox must not override an explicit scoped role selection.
 assert.equal((await owner.post({action:'create-user',username:'scoped',name:'Scoped Super',password:rootPassword,account_role:'super_admin',admin:'on'})).status,303);
 assert.equal(user('scoped').allProjects,false);assert.equal(user('scoped').admin,false);
 // Non-demo account occupying a demo name blocks the complete provisioning transaction.
 const collision=JSON.parse(fs.readFileSync(directory,'utf8'));collision.records.users[user('demo-admin').id].demo=false;
 fs.writeFileSync(directory,JSON.stringify(collision));const unchanged=fs.readFileSync(directory,'utf8');
 assert.equal((await owner.post({action:'provision-requested',confirm_owner:'yes'})).status,400);assert.equal(fs.readFileSync(directory,'utf8'),unchanged);
 assert.doesNotMatch(logs,/PHP Warning|Fatal error|Uncaught/);
});
