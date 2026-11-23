// Default collector initialization with isolated test storage.
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
const temporary='test-only-bootstrap-password';
execFileSync('php',['-r', 'file_put_contents('+quote(path.join(deploy,'webstats/initial-admin.php'))+',"<?php return ".var_export(["username"=>"admin","password_hash"=>password_hash('+quote(temporary)+',PASSWORD_DEFAULT)],true).";");']);
const env={...process.env};delete env.JASR_WEBSTATS_CONFIG;
const server=spawn('php',['-S','127.0.0.1:18766','-t',deploy],{env,stdio:['ignore','pipe','pipe']});let logs='';server.stderr.on('data',d=>logs+=d);
after(()=>{server.kill();fs.rmSync(temp,{recursive:true,force:true});});
const base='http://127.0.0.1:18766/webstats/collect.php';
const collect = () => fetch(base,{
  method:'POST', headers:{Origin:'https://jasr.me','Content-Type':'text/plain'},
  body:JSON.stringify({id:'1'.repeat(32),kind:'pageview',page:'https://jasr.me/demo',target:'',label:'',referrer:'',session:'a'.repeat(32)})
});
test('first collection initializes settings and preserves events across source updates',async()=>{
  let ready=false;for(let i=0;i<60;i++){try{await fetch(base);ready=true;break;}catch{await new Promise(r=>setTimeout(r,100));}}assert.ok(ready,logs);
  const accepted = await collect();
  assert.equal(accepted.status,200);
  assert.equal((await accepted.json()).success,true);
  const settingsFile = path.join(data,'settings.json');
  const settings = fs.readFileSync(settingsFile,'utf8');
  assert.ok(JSON.parse(settings).records.secret.length>=32);
  const eventFile = path.join(data,fs.readdirSync(data).find(name=>/^events-.*\.json$/.test(name)));
  const events = fs.readFileSync(eventFile,'utf8');
  fs.copyFileSync(path.join(root,'webstats/initial-admin.php'),path.join(deploy,'webstats/initial-admin.php'));
  assert.equal((await collect()).status,200);
  assert.equal(fs.readFileSync(settingsFile,'utf8'),settings);
  assert.deepEqual(JSON.parse(fs.readFileSync(eventFile,'utf8')).records,JSON.parse(events).records);
  const check=execFileSync('git',['check-ignore','webstats/data/settings.json','webstats/data/admin.json','webstats/data/events-2026-09-21.json'],{cwd:root,encoding:'utf8'});
  assert.equal(check.trim().split('\n').length,3);
  assert.doesNotMatch(logs,/Fatal error|Warning:/);
});
