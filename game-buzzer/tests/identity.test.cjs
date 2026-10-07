const {test,after}=require('node:test');
const assert=require('node:assert/strict');
const fs=require('node:fs'),os=require('node:os'),path=require('node:path');
const {spawn,execFileSync}=require('node:child_process');
const root=path.resolve(__dirname,'../..'),temp=fs.mkdtempSync(path.join(os.tmpdir(),'gb-identity-'));
for(const name of ['1-Framework','user-management','game-buzzer'])fs.cpSync(path.join(root,name),path.join(temp,name),{recursive:true});
for(const project of ['user-management','game-buzzer']){
 const data=path.join(temp,project,'data');
 for(const name of fs.readdirSync(data))if(name!=='.htaccess'&&name!=='.gitkeep')fs.rmSync(path.join(data,name),{recursive:true,force:true});
 fs.rmSync(path.join(temp,project,'config.local.php'),{force:true});
}
fs.writeFileSync(path.join(temp,'user-management/config.local.php'),"<?php return ['secure_cookie'=>false];");
const password='test-only-owner-password';
execFileSync('php',[path.join(temp,'user-management/setup.php'),'jasrasr','Jason'],{input:password+'\n'});
const directory=path.join(temp,'user-management/data/directory.json');
const original=fs.readFileSync(directory,'utf8');
const owner=Object.values(JSON.parse(original).records.users)[0];
// Verify owner gate independently of HTTP session setup.
const gate=path.join(temp,'gate.php');
fs.writeFileSync(gate,`<?php require '${temp}/game-buzzer/identity.php'; $u=json_decode(file_get_contents('${directory}'),true)['records']['users'];$u=array_values($u)[0];$checks=[gb_setup_owner($u),!gb_setup_owner(null)];foreach([['username','someone-else'],['role','admin'],['admin',false],['active',false],['demo',true],['mustChangePassword',true]] as [$key,$value]){$bad=$u;$bad[$key]=$value;$checks[]=!gb_setup_owner($bad);}if(in_array(false,$checks,true))exit(1);`);
execFileSync('php',[gate]);
const sessions=path.join(temp,'sessions');fs.mkdirSync(sessions);
const server=spawn('php',['-d',`session.save_path=${sessions}`,'-S','127.0.0.1:18776','-t',temp],{stdio:['ignore','pipe','pipe']});let logs='';server.stderr.on('data',d=>logs+=d);
after(()=>{server.kill();fs.rmSync(temp,{recursive:true,force:true});});
const base='http://127.0.0.1:18776';let cookie='',csrf='';
async function api(action,extra={},signed=true){
 const r=await fetch(base+'/game-buzzer/api.php',{method:'POST',headers:{'Content-Type':'application/json',Cookie:signed?cookie:''},body:JSON.stringify({action,...extra})});
 const c=r.headers.get('set-cookie');if(c&&signed)cookie=c.split(';')[0];
 const text=await r.text();let data;try{data=JSON.parse(text);}catch{throw Error(text);}return {status:r.status,data};
}
async function portal(values){const r=await fetch(base+'/user-management/',{redirect:'manual',method:values?'POST':'GET',headers:{Cookie:cookie,...(values?{'Content-Type':'application/x-www-form-urlencoded'}:{})},body:values?new URLSearchParams(values):undefined});const c=r.headers.get('set-cookie');if(c)cookie=c.split(';')[0];const html=await r.text();csrf=html.match(/name="csrf" value="([a-f0-9]+)"/)?.[1]||csrf;return r.status;}
test('owner-only setup, CSRF, preservation, shared creation and revocation',async()=>{
 let ready=false;for(let i=0;i<80;i++){try{await fetch(base+'/game-buzzer/');ready=true;break;}catch{await new Promise(r=>setTimeout(r,100));}}assert.ok(ready,logs);
 let status=await api('identityStatus');assert.equal(status.data.setupAllowed,false);
 assert.equal((await api('enableIdentity',{csrf:status.data.csrf})).status,403);
 await portal();assert.equal(await portal({action:'login',username:'jasrasr',password,csrf}),303);
 status=await api('identityStatus');csrf=status.data.csrf;assert.equal(status.data.setupAllowed,true);assert.equal(status.data.enabled,false);
 assert.equal((await api('enableIdentity',{csrf:'wrong'})).status,403);
 assert.equal(fs.existsSync(path.join(temp,'game-buzzer/data/identity-enabled.php')),false);
 // A conflicting project registration cannot be overwritten.
 let doc=JSON.parse(original);doc.records.projects={'game-buzzer':{name:'Existing',path:'/elsewhere/'}};fs.writeFileSync(directory,JSON.stringify(doc));
 assert.equal((await api('enableIdentity',{csrf})).status,409);assert.equal(JSON.parse(fs.readFileSync(directory)).records.projects['game-buzzer'].path,'/elsewhere/');
 fs.writeFileSync(directory,original);
 // A private legacy config and preexisting room must survive activation untouched.
 const legacy="<?php return ['create_password'=>'legacy-password-123'];\n";
 fs.writeFileSync(path.join(temp,'game-buzzer/config.local.php'),legacy);
 fs.writeFileSync(path.join(temp,'game-buzzer/data/preserved.php'),'<?php exit; ?>\nexisting-data');
 const enabled=await api('enableIdentity',{csrf});assert.equal(enabled.status,200);assert.equal(enabled.data.canCreate,true);
 const marker=path.join(temp,'game-buzzer/data/identity-enabled.php'),before=fs.readFileSync(marker,'utf8');
 assert.equal((await api('enableIdentity',{csrf})).status,200);assert.equal(fs.readFileSync(marker,'utf8'),before);
 assert.equal(fs.readFileSync(path.join(temp,'game-buzzer/config.local.php'),'utf8'),legacy);
 assert.equal(fs.readFileSync(path.join(temp,'game-buzzer/data/preserved.php'),'utf8'),'<?php exit; ?>\nexisting-data');
 doc=JSON.parse(fs.readFileSync(directory,'utf8'));assert.deepEqual(doc.records.users,JSON.parse(original).records.users);assert.deepEqual(doc.records.projects['game-buzzer'],{name:'Game Buzzer',path:'/game-buzzer/'});
 assert.equal((await api('create',{title:'No CSRF'})).status,403);
 assert.equal((await api('create',{title:'Legacy bypass',password:'legacy-password-123'},false)).status,403);
 const room=await api('create',{title:'Shared login game',csrf});assert.equal(room.status,200);assert.ok(room.data.host);
 // A revoked central account cannot create more rooms; existing guest links still work.
 doc.records.users[owner.id].active=false;doc.records.users[owner.id].version++;fs.writeFileSync(directory,JSON.stringify(doc));
 assert.equal((await api('create',{title:'Revoked',csrf})).status,403);
 assert.equal((await api('state',{room:room.data.id},false)).status,200);
 assert.equal((await api('identityStatus')).data.canCreate,false);
 assert.doesNotMatch(logs,/Fatal error|Warning:/);
});
