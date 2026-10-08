const test=require('node:test');
const assert=require('node:assert/strict');
const fs=require('node:fs');
const http=require('node:http');
const path=require('node:path');
const rules=require('../rules.js');
const saves=require('../saves.js');
const content=JSON.parse(fs.readFileSync(path.join(__dirname,'../game-content.json'),'utf8'));
test('Content validates, rejects invalid probabilities and unsorted stops',()=>{
  rules.validate(content);
  const bad=structuredClone(content);bad.events[0].chancePercent=99;
  assert.throws(()=>rules.validate(bad),/total/);
  const order=structuredClone(content);order.stops[1].mile=5;assert.throws(()=>rules.validate(order),/increasing/);
  const scalar=structuredClone(content);scalar.totalMiles=[1,2040];assert.throws(()=>rules.validate(scalar),/totalMiles/);
});
test('Absolute event probabilities have precise boundaries and quiet remainder',()=>{
  assert.equal(rules.selectEvent(content.events,()=>0).id,'broken-wheel');
  assert.equal(rules.selectEvent(content.events,()=>.12).id,'illness');
  assert.equal(rules.selectEvent(content.events,()=>.21).id,'wild-game');
  assert.equal(rules.selectEvent(content.events,()=>.31),null);
  assert.equal(rules.selectEvent(content.events,()=>.999),null);
});
test('Dates use real month lengths, and effects clamp health and supplies',()=>{
  const state={year:1848,month:4,day:30,health:95,food:5,miles:4};
  rules.advanceDate(state,2);assert.deepEqual([state.month,state.day],[5,2]);
  rules.applyEffects(state,{health:20,food:-10,miles:[-10,-5]},()=>0);
  assert.deepEqual([state.health,state.food,state.miles],[100,0,0]);
});
test('Saving a journey preserves others and never overwrites unreadable storage',()=>{
  let value=null;const storage={getItem:()=>value,setItem:(k,v)=>value=v};
  saves.write(storage,{id:'a',state:{miles:1}});saves.write(storage,{id:'b',state:{miles:2}});
  saves.write(storage,{id:'a',state:{miles:3}});
  assert.equal(saves.read(storage).games.b.state.miles,2);
  value='broken';assert.throws(()=>saves.write(storage,{id:'c'}));assert.equal(value,'broken');
  value='';assert.throws(()=>saves.write(storage,{id:'c'}));assert.equal(value,'');
});
test('Browser restores pending choices, keeps multiple games and works offline',{skip:process.env.RUN_BROWSER_TESTS!=='1'},async()=>{
  const {chromium}=require('playwright');
  const root=path.resolve(__dirname,'..');
  const browser=await chromium.launch({headless:true});
  const server=http.createServer((req,res)=>{
    const requested=new URL(req.url,'http://localhost').pathname;
    let file=requested.replace(/^\/oregon-trail\//,'');
    if(!file)file='index.html';
    const full=path.resolve(root,file);
    if(!full.startsWith(root+path.sep)){res.writeHead(404);res.end();return;}
    try{const data=fs.readFileSync(full);res.setHeader('Content-Type',file.endsWith('.js')?'text/javascript':file.endsWith('.json')?'application/json':file.endsWith('.css')?'text/css':'text/html');res.end(data);}catch{res.writeHead(404);res.end();}
  });
  await new Promise(resolve=>server.listen(0,'127.0.0.1',resolve));
  const context=await browser.newContext();const page=await context.newPage();const errors=[];
  page.on('pageerror',error=>errors.push(error.message));
  // Local game assets are the only test dependencies.
  await page.route('https://fonts.googleapis.com/**',r=>r.abort());
  const url='http://127.0.0.1:'+server.address().port+'/oregon-trail/';
  try{
    await page.goto(url);await page.getByRole('button',{name:'Pack the wagon and begin'}).click();
    await page.getByRole('button',{name:'Continue down the trail'}).click();
    const key=saves.KEY;
    let data=await page.evaluate(k=>JSON.parse(localStorage.getItem(k)),key);
    const first=Object.keys(data.games)[0];const miles=data.games[first].state.miles;
    assert.ok(miles>0);
    await page.reload();await page.waitForFunction(()=>document.getElementById('save-status').textContent.startsWith('Restored'));
    assert.equal(await page.locator('#miles').textContent(),String(miles));
    await page.getByRole('button',{name:'Change pace'}).click();await page.reload();
    await page.getByRole('button',{name:'Grueling'}).click();
    // Seed a pending river crossing as if the tab closed immediately after arrival.
    await page.evaluate(({key,id})=>{const d=JSON.parse(localStorage.getItem(key)),g=d.games[id];g.state.miles=110;g.state.landmark=1;g.state.scene='river';g.view='stop';g.pendingStop='kansas-river';localStorage.setItem(key,JSON.stringify(d));},{key,id:first});
    await page.reload();await page.getByRole('button',{name:'Ford the river'}).waitFor();
    assert.equal(await page.locator('#miles').textContent(),'110');
    await page.getByRole('button',{name:'Take the ferry'}).click();
    data=await page.evaluate(k=>JSON.parse(localStorage.getItem(k)),key);assert.equal(data.games[first].pendingStop,null);
    await page.getByRole('button',{name:'Start a new journey'}).click();await page.getByRole('button',{name:'Pack the wagon and begin'}).click();
    data=await page.evaluate(k=>JSON.parse(localStorage.getItem(k)),key);assert.equal(Object.keys(data.games).length,2);
    // Active saves keep their original rules when JSON changes.
    assert.equal(data.games[first].config.stops[0].id,'kansas-river');
    await page.locator('.saved-journeys summary').click();await page.locator('#saved-games button').last().click();
    assert.equal(await page.locator('#miles').textContent(),String(data.games[first].state.miles));
    await page.waitForFunction(()=>navigator.serviceWorker.controller);
    await page.waitForFunction(()=>document.getElementById('offline-status').textContent.includes('Ready for offline'));
    await context.setOffline(true);
    await page.reload();await page.waitForFunction(()=>document.getElementById('save-status').textContent.startsWith('Restored'));
    await page.getByRole('button',{name:'Hunt for food'}).click();
    assert.match(await page.locator('#save-status').textContent(),/^Saved/);
    assert.deepEqual(errors,[]);
    console.log('Verified reload, pace choice, pending river, separate journeys and offline reload/save.');
  }finally{await browser.close();await new Promise(resolve=>server.close(resolve));}
});
