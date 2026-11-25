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
const httpRoot = path.join(temp, 'http');
fs.mkdirSync(httpRoot);
for (const name of ['webstats', '1-Framework', 'user-management', 'webstats-demo']) {
  fs.cpSync(path.join(root, name), path.join(httpRoot, name), {
    recursive: true, filter: source => !['data', 'config.local.php'].includes(path.basename(source))
  });
}
const identityData = path.join(temp, 'identity');
fs.mkdirSync(identityData);
fs.writeFileSync(path.join(httpRoot, 'user-management/config.local.php'),
  '<?php return ["data_path"=>'+quote(identityData)+',"secure_cookie"=>false];');
const identityBootstrap = quote(path.join(httpRoot, 'user-management/bootstrap.php'));
const identity = '$directory=new \\Jasr\\Users\\Directory('+quote(identityData)+'); ';
const phpIdentity = code => execFileSync('php', ['-r', 'require '+identityBootstrap+'; '+identity+code]);
phpIdentity('$directory->setup("owner","Owner","correct-horse-staple"); '+
  '$owner=current($directory->read()["users"]); '+
  '$directory->administer($owner["id"],$owner["version"],"save-project",["project"=>"webstats","name"=>"Webstats","path"=>"/webstats/"]); '+
  '$directory->administer($owner["id"],$owner["version"],"create-user",["username"=>"reader","name"=>"Reader","password"=>"reader-temporary-secret"]); '+
  '$reader=array_values($directory->read()["users"])[1]; '+
  '$directory->changePassword($reader["id"],$reader["version"],"reader-temporary-secret","reader-normal-secret");');
const server = spawn('php',['-S','127.0.0.1:18765','-t',httpRoot],{env:{...process.env,JASR_WEBSTATS_CONFIG:config},stdio:['ignore','pipe','pipe']});
let logs='';server.stderr.on('data',d=>logs+=d);
after(()=>{server.kill();fs.rmSync(temp,{recursive:true,force:true});});
const base='http://127.0.0.1:18765/webstats/';
const event={id:'1'.repeat(32),kind:'pageview',page:'https://source.test/index.php?secret=remove#private',target:'',label:'',referrer:'https://ref.test/path?secret=remove',session:'a'.repeat(32)};
const send=(value,origin='https://source.test')=>fetch(base+'collect.php',{method:'POST',headers:{Origin:origin,'Content-Type':'text/plain'},body:typeof value==='string'?value:JSON.stringify(value)});
const portal = new URL('../user-management/', base);
const token = html => html.match(/name="csrf" value="([a-f0-9]+)"/)[1];
const cookieOf = r => r.headers.get('set-cookie').split(';')[0];
const post = (url, cookie, fields) => fetch(url, {
  method:'POST', redirect:'manual', headers:{Cookie:cookie,'Content-Type':'application/x-www-form-urlencoded'},
  body:new URLSearchParams(fields)
});
async function signIn(username, password) {
  const loginPage = await fetch(portal);
  const signedIn = await post(portal, cookieOf(loginPage), {
    action:'login', csrf:token(await loginPage.text()), username, password, return:'/webstats/'
  });
  assert.equal(signedIn.status,303);
  return cookieOf(signedIn);
}
test('collector, dashboard authentication, filtering, escaping and storage protections',async()=>{
  let ready=false;
  for(let i=0;i<60;i++){try{await fetch(base);ready=true;break;}catch{await new Promise(r=>setTimeout(r,100));}}
  assert.ok(ready,logs);
  assert.equal((await send(event,'https://evil.test')).status,403);
  assert.equal((await send({...event,page:'https://another.test/'})).status,400);
  for (const page of ['/webstats', '/webstats/', '/github/webstats/index.php']) {
    assert.equal((await send({...event,page:'https://source.test'+page})).status,400);
  }
  assert.equal((await send('x'.repeat(8193))).status,413);
  assert.equal((await send({...event,kind:'other'})).status,400);
  assert.equal((await send({...event,page:[]})).status,400);
  const accepted=await fetch(base+'collect.php',{
    method:'POST',
    headers:{Origin:'https://source.test','Content-Type':'text/plain','X-Forwarded-For':'198.51.100.10'},
    body:JSON.stringify({...event,ip:'198.51.100.20'})
  });
  assert.equal(accepted.status,200);assert.equal((await accepted.json()).success,true);
  await send(event); // retry must not inflate totals
  const files=fs.readdirSync(temp).filter(x=>/^events-.*json$/.test(x));assert.equal(files.length,1);
  const data=JSON.parse(fs.readFileSync(path.join(temp,files[0])));
  assert.equal(Object.keys(data.records).length,1);
  const saved=Object.values(data.records)[0];assert.equal(saved.page,'https://source.test/index.php');assert.equal(saved.referrer,'https://ref.test');assert.notEqual(saved.session,event.session);
  assert.equal(saved.ip,'127.0.0.1');
  const {ip, ...legacy}=saved;
  const legacyId='2'.repeat(32);
  data.records['source.test:'+legacyId]={...legacy,id:legacyId,page:'https://source.test/legacy'};
  const game = {...saved,kind:'click',page:'https://source.test/tic-tac-toe/',label:'play-square'};
  const addActivity = (id, fields) => {
    data.records['source.test:'+id]={...game,...fields,id};
  };
  addActivity('earlier-game',{occurred:saved.occurred-600,label:'earlier-game'});
  for (let move=0;move<38;move++) {
    if (move===15) {
      addActivity('other-tab',{occurred:saved.occurred-60,session:'other-tab',label:'other-tab'});
      addActivity('other-ip',{occurred:saved.occurred-60,ip:'192.0.2.5',label:'other-ip'});
      addActivity('other-page',{occurred:saved.occurred-60,page:'https://source.test/another-game/',label:'other-page'});
      addActivity('game-view',{occurred:saved.occurred-60,kind:'pageview'});
    }
    addActivity('move-'+move,{occurred:saved.occurred-75+move,label:'play-square-'+move});
  }
  fs.writeFileSync(path.join(temp,files[0]),JSON.stringify(data));
  const olderTime = saved.occurred - 49 * 3600;
  const olderDay = new Date(olderTime * 1000).toISOString().slice(0,10);
  const older = {...game,id:'older',occurred:olderTime,label:'older-than-48-hours'};
  fs.writeFileSync(path.join(temp,`events-${olderDay}.json`),JSON.stringify({...data,records:{older}}));
  assert.equal((await fetch(base+'setup.php')).status,404);
  const anonymous = await fetch(base,{redirect:'manual'});
  assert.equal(anonymous.status,303);
  assert.equal(anonymous.headers.get('location'),'/user-management/?return=%2Fwebstats%2F');
  const reader = await signIn('reader','reader-normal-secret');
  assert.equal((await fetch(base,{headers:{Cookie:reader}})).status,403);
  const cookie = await signIn('owner','correct-horse-staple');
  assert.equal((await post(base,cookie,{action:'logout',csrf:'invalid'})).status,403);
  const report=await fetch(base,{headers:{Cookie:cookie}});const html=await report.text();assert.equal(report.status,200);assert.match(html,/Top pages/);assert.match(html,/source.test\/index.php/);assert.doesNotMatch(html,/secret=remove/);
  assert.match(html,/<th scope="col" aria-sort="descending"><a [^>]+>Time /);
  assert.match(html,/<td>\s*<a href="https:\/\/source\.test\/index\.php" target="_blank" rel="noopener noreferrer">https:\/\/source\.test\/index\.php<\/a>\s*<\/td><td>\s*pageview\s*<\/td><td>127\.0\.0\.1 - <strong class="activity-me">ME<\/strong><\/td>/);
  assert.match(html,/<td>\s*click: other-ip<\/td><td>192\.0\.2\.5<\/td>/);
  assert.match(html,/<td>\s*<a href="https:\/\/source\.test\/legacy" [^>]+>https:\/\/source\.test\/legacy<\/a>\s*<\/td><td>\s*pageview\s*<\/td><td>—<\/td>/);
  for (const direction of ['asc', 'desc']) {
    const sorted = await (await fetch(`${base}?sort=ip&direction=${direction}&site=source.test`, {headers:{Cookie:cookie}})).text();
    const mine = sorted.indexOf('127.0.0.1 - <strong');
    const other = sorted.indexOf('<td>192.0.2.5</td>');
    assert.ok(mine >= 0 && other >= 0);
    assert.equal(mine < other, direction === 'asc');
    assert.match(sorted, /href="\?site=source\.test&amp;start=[^&]+&amp;end=[^&]+&amp;sort=ip&amp;direction=(asc|desc)#recent-activity"/);
  }
  const group = html.match(/<details><summary>38 clicks<\/summary>([\s\S]*?)<\/details>/);
  assert.ok(group);
  assert.equal((group[1].match(/<li>/g) ?? []).length,38);
  assert.doesNotMatch(group[1],/earlier-game|other-tab|other-ip|other-page/);
  assert.match(html,/<p class="eyebrow">CLICKS<\/p><strong>43<\/strong>/);
  assert.doesNotMatch(html,/click: older-than-48-hours/);
  const more = html.match(/href="(\?[^\"]*activity_limit=60#recent-activity)">Show more<\/a>/);
  assert.ok(more);
  const expanded = await (await fetch(new URL(more[1].replaceAll('&amp;', '&'),base),{headers:{Cookie:cookie}})).text();
  assert.match(expanded,/click: older-than-48-hours/);
  assert.doesNotMatch(expanded,/>Show more<\/a>/);
  const filtered = await (await fetch(base+'?hide_me=1&sort=ip&activity_limit=60',{headers:{Cookie:cookie}})).text();
  const recentOnly = filtered.split('id="recent-activity"')[1];
  assert.ok(recentOnly);
  assert.match(recentOnly,/value="hide" selected/);
  assert.match(recentOnly,/sort=ip&amp;direction=asc&amp;activity_limit=60&amp;activity=hide#recent-activity/);
  assert.match(recentOnly,/<td>192\.0\.2\.5<\/td>/);
  assert.match(recentOnly,/https:\/\/source\.test\/legacy/);
  assert.doesNotMatch(recentOnly,/activity-me|38 clicks|older-than-48-hours/);
  assert.match(filtered,/<p class="eyebrow">CLICKS<\/p><strong>43<\/strong>/);
  const mine = await (await fetch(base+'?activity=show&sort=ip',{headers:{Cookie:cookie}})).text();
  const mineRecent = mine.split('id="recent-activity"')[1];
  assert.match(mineRecent,/value="show" selected/);
  assert.match(mineRecent,/activity-me/);
  assert.doesNotMatch(mineRecent,/192\.0\.2\.5|source\.test\/legacy/);
  assert.match(mineRecent,/sort=ip&amp;direction=asc&amp;activity=show#recent-activity/);
  assert.match(mineRecent,/activity_limit=60#recent-activity">Show more/);
  assert.match(mine,/<p class="eyebrow">CLICKS<\/p><strong>43<\/strong>/);
  assert.equal((await fetch(base+'?start=2026-02-30',{headers:{Cookie:cookie}})).status,400);
  assert.equal((await fetch(base+'?site=unknown',{headers:{Cookie:cookie}})).status,400);
  assert.equal((await post(base,cookie,{action:'logout',csrf:token(html)})).status,303);
  assert.equal((await fetch(base,{redirect:'manual',headers:{Cookie:cookie}})).status,303);
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
  const symlink=path.join(temp,'unsafe-link');fs.symlinkSync(root+'/webstats',symlink,process.platform==='win32'?'junction':'dir');
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
