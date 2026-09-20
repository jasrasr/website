// Actual HTTP tests against PHP, isolated config and storage; never touches production.
const {test, after} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const {spawn, execFileSync} = require('node:child_process');
const root = path.resolve(__dirname, '../..');
const temp = fs.mkdtempSync(path.join(os.tmpdir(), 'webstats-test-'));
const config = path.join(temp,'config.php');
const quote = s => "'" + s.replaceAll('\\','\\\\').replaceAll("'","\\'") + "'";
execFileSync('php', ['-r', `$c=require ${quote(root+'/webstats/config.example.php')}; $c['storage']=${quote(temp)}; $c['username']='tester'; $c['password_hash']=password_hash('correct-horse-staple',PASSWORD_DEFAULT); $c['secret']=str_repeat('a',64); $c['origins']=['https://source.test'=>'source.test']; file_put_contents(${quote(config)},'<?php return '.var_export($c,true).';');`]);
const server = spawn('php',['-S','127.0.0.1:18765','-t',root],{env:{...process.env,JASR_WEBSTATS_CONFIG:config},stdio:['ignore','pipe','pipe']});
let logs='';server.stderr.on('data',d=>logs+=d);
after(()=>{server.kill();fs.rmSync(temp,{recursive:true,force:true});});
const base='http://127.0.0.1:18765/webstats/';
const event={id:'1'.repeat(32),kind:'pageview',page:'https://source.test/index.php?secret=remove#private',target:'',label:'',referrer:'https://ref.test/path?secret=remove',session:'a'.repeat(32)};
const send=(value,origin='https://source.test')=>fetch(base+'collect.php',{method:'POST',headers:{Origin:origin,'Content-Type':'text/plain'},body:typeof value==='string'?value:JSON.stringify(value)});
let cookie='',csrf='';
async function getLogin(){const r=await fetch(base);cookie=r.headers.get('set-cookie').split(';')[0]; const html=await r.text();csrf=html.match(/name="csrf" value="([a-f0-9]+)"/)[1];return r;}
async function login(password,token=csrf){return fetch(base,{method:'POST',redirect:'manual',headers:{Cookie:cookie,'Content-Type':'application/x-www-form-urlencoded'},body:new URLSearchParams({username:'tester',password,csrf:token})});}
test('collector, dashboard authentication, filtering, escaping and storage protections',async()=>{
  let ready=false;
  for(let i=0;i<60;i++){try{await fetch(base);ready=true;break;}catch{await new Promise(r=>setTimeout(r,100));}}
  assert.ok(ready,logs);
  assert.equal((await send(event,'https://evil.test')).status,403);
  assert.equal((await send({...event,page:'https://another.test/'})).status,400);
  assert.equal((await send({...event,page:'https://source.test/github/webstats/'})).status,400);
  assert.equal((await send('x'.repeat(8193))).status,413);
  assert.equal((await send({...event,kind:'other'})).status,400);
  assert.equal((await send({...event,page:[]})).status,400);
  const accepted=await send(event);assert.equal(accepted.status,200);assert.equal((await accepted.json()).success,true);
  await send(event); // retry must not inflate totals
  const files=fs.readdirSync(temp).filter(x=>/^events-.*json$/.test(x));assert.equal(files.length,1);
  const data=JSON.parse(fs.readFileSync(path.join(temp,files[0])));
  assert.equal(Object.keys(data.records).length,1);
  const saved=Object.values(data.records)[0];assert.equal(saved.page,'https://source.test/index.php');assert.equal(saved.referrer,'https://ref.test');assert.notEqual(saved.session,event.session);
  assert.equal((await fetch(base+'setup.php')).status,404);
  await getLogin();assert.equal((await login('correct-horse-staple','invalid')).status,403);
  assert.equal((await login('wrong')).status,401);
  const signedIn=await login('correct-horse-staple');assert.equal(signedIn.status,303);
  const newCookie=signedIn.headers.get('set-cookie').split(';')[0];assert.notEqual(newCookie,cookie);cookie=newCookie;
  const report=await fetch(base,{headers:{Cookie:cookie}});const html=await report.text();assert.equal(report.status,200);assert.match(html,/Top pages/);assert.match(html,/source.test\/index.php/);assert.doesNotMatch(html,/secret=remove/);
  assert.equal((await fetch(base+'?start=2026-02-30',{headers:{Cookie:cookie}})).status,400);
  assert.equal((await fetch(base+'?site=unknown',{headers:{Cookie:cookie}})).status,400);
  // Daily storage is bounded and fail-closed; simulate capacity without flooding.
  execFileSync('php',['-r',`putenv('JASR_WEBSTATS_CONFIG=${config}'); require ${quote(root+'/webstats/bootstrap.php')}; if(allowance('test',1,60)!==true || allowance('test',1,60)!==false) exit(1);`]);
  const fixture=path.join(temp,'pages'); fs.mkdirSync(fixture);
  const htmlSource='<html>\n<body>Example\n</body>\n</html>\n';
  fs.writeFileSync(path.join(fixture,'index.html'),htmlSource);
  fs.writeFileSync(path.join(fixture,'page.php'),'<?php echo "hi"; ?>\n'+htmlSource);
  const tracker='https://stats.test/github/webstats/assets/js/tracker.js';
  execFileSync('php',[root+'/webstats/install.php',fixture,tracker]);
  assert.equal(fs.readFileSync(path.join(fixture,'index.html'),'utf8'),htmlSource);
  execFileSync('php',[root+'/webstats/install.php',fixture,tracker,'--write']);
  for(const filename of ['index.html','page.php']) assert.match(fs.readFileSync(path.join(fixture,filename),'utf8'),/script defer src=/);
  execFileSync('php',[root+'/webstats/install.php',fixture,tracker,'--write']);
  assert.equal(fs.readFileSync(path.join(fixture,'index.html'),'utf8').split(tracker).length,2);
  // Demo pages must remain outside the dashboard's excluded path.
  for (const [file,id] of [['index.html','e'],['sample.php','f']]) {
    const sample=await fetch(base+'../webstats-demo/'+file);
    assert.equal(sample.status,200);
    const markup=await sample.text();
    assert.match(markup,/assets\/js\/tracker.js/);
    assert.match(markup,/data-analytics-event="demo-primary"/);
    assert.match(markup,/data-analytics-ignore id="ignored-button"/);
    assert.equal((await send({...event,id:id.repeat(32),page:'https://source.test/github/webstats-demo/'+file})).status,200);
  }
  const boot=quote(root+'/webstats/bootstrap.php');
  const reject = candidate => execFileSync('php',['-r',
    'require '+boot+'; try {validate_storage_path('+quote(candidate)+'); exit(2);} catch (RuntimeException $e) {echo "rejected";}'], {encoding:'utf8'});
  assert.equal(reject(root+'/webstats'),'rejected');
  const symlink=path.join(temp,'unsafe-link');fs.symlinkSync(root+'/webstats',symlink);
  assert.equal(reject(symlink),'rejected');

  // Replace a disposable source deployment; retain and reread external events.
  const deploy=path.join(temp,'deployment'), persistent=path.join(temp,'private-data');
  fs.mkdirSync(persistent);const externalConfig=path.join(temp,'persistent-config.php');
  const copySource=()=>{fs.mkdirSync(deploy,{recursive:true}); for(const dir of ['webstats','1-Framework']) fs.cpSync(path.join(root,dir),path.join(deploy,dir),{recursive:true});};
  copySource();
  execFileSync('php',['-r', '$c=require '+quote(config)+'; $c["storage"]='+quote(persistent)+'; file_put_contents('+quote(externalConfig)+',"<?php return ".var_export($c,true).";");']);
  const env={...process.env,JASR_WEBSTATS_CONFIG:externalConfig};
  const prefix='require '+quote(deploy+'/webstats/bootstrap.php')+'; ';
  execFileSync('php',['-r',prefix+'save_event(normalize_event(json_decode('+quote(JSON.stringify(event))+',true),"https://source.test"));'],{env});
  const stored=fs.readdirSync(persistent).find(x=>/^events-.*json$/.test(x));
  const before=fs.readFileSync(path.join(persistent,stored),'utf8');
  fs.rmSync(deploy,{recursive:true,force:true});copySource();
  assert.equal(fs.readFileSync(path.join(persistent,stored),'utf8'),before);
  assert.equal(execFileSync('php',['-r',prefix+'echo count(iterator_to_array(events_between(time()-60,time()+60,"")));'],{env,encoding:'utf8'}),'1');
  assert.doesNotMatch(logs,/Fatal error|Warning:/);
});
