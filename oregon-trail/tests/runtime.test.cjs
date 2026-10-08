const test=require('node:test'),assert=require('node:assert/strict'),vm=require('node:vm'),fs=require('node:fs'),path=require('node:path');
const root=path.resolve(__dirname,'..');
const rules=require('../rules.js'),saves=require('../saves.js');
const defaults=JSON.parse(fs.readFileSync(path.join(root,'game-content.json')));
function environment(storage,config=defaults,failFetch=false,serviceWorker){
  const elements=new Map();
  class Element{
    constructor(tag='div'){this.tag=tag;this.children=[];this.style={};this.value='';this.textContent='';}
    append(...items){this.children.push(...items);}
    replaceChildren(){this.children=[];}
    setAttribute(){}
    querySelector(selector){return this.querySelectorAll(selector)[0];}
    querySelectorAll(selector){return this.children.flatMap(c=>[...(selector[0]==='.'&&c.className===selector.slice(1)?[c]:[]),...c.querySelectorAll(selector)]);}
    set innerHTML(value){
      this.children=[];
      if(value.includes('family-count')){
        for(const id of ['family-count','family-fields','begin-journey'])elements.set(id,new Element());
        elements.get('family-count').value='5';
      }
    }
  }
  const document={getElementById:id=>{if(!elements.has(id))elements.set(id,new Element());return elements.get(id);},createElement:tag=>new Element(tag),querySelectorAll:selector=>elements.get('family-fields')?.querySelectorAll(selector)||[],addEventListener(){}};
  document.getElementById('scene').getContext=()=>new Proxy({}, {get:()=>()=>{},set:()=>true});
  const context=vm.createContext({document,window:{addEventListener(){}},navigator:serviceWorker?{serviceWorker}:{},localStorage:storage,TrailRules:rules,TrailSaves:saves,structuredClone,crypto:require('node:crypto').webcrypto,Date,Math,AbortSignal,setTimeout:callback=>{queueMicrotask(callback);return 1;},clearTimeout(){},fetch:async()=>{if(failFetch)throw Error('offline');return {ok:true,json:async()=>structuredClone(config)};}});
  const ready=vm.runInContext(fs.readFileSync(path.join(root,'game.js'),'utf8'),context);
  return {ready,elements,click(label){const b=elements.get('actions').children.flatMap(c=>c.children).find(x=>x.tag==='button'&&x.textContent.startsWith(label));assert.ok(b,'Missing button '+label);b.onclick();},pack(){elements.get('begin-journey').onclick();}};
}
function memory(){let value=null;return {getItem:()=>value,setItem:(k,v)=>{value=v;}};}
test('Game runtime autosaves actions and restores exact pending stops with content snapshot',async()=>{
  const storage=memory();const first=environment(storage);await first.ready;first.pack();first.click('Continue');
  let data=saves.read(storage);const id=Object.keys(data.games)[0];const miles=data.games[id].state.miles;
  assert.ok(miles>0);const reload=environment(storage);await reload.ready;assert.equal(reload.elements.get('miles').textContent,miles);
  reload.click('Change pace');const paceReload=environment(storage);await paceReload.ready;paceReload.click('Grueling');assert.equal(saves.read(storage).games[id].state.pace,'Grueling');
  data=saves.read(storage);const g=data.games[id];g.state.miles=110;g.state.landmark=1;g.state.scene='river';g.view='stop';g.pendingStop='kansas-river';storage.setItem(saves.KEY,JSON.stringify(data));
  const modified=structuredClone(defaults);modified.crossings[0].successPercent=0;
  const river=environment(storage,modified);await river.ready;
  assert.ok(river.elements.get('actions').children[1].children.some(b=>b.textContent==='Ford the river · 40% safe'));
  river.click('Take the ferry');assert.equal(saves.read(storage).games[id].pendingStop,null);
  const offline=environment(storage,defaults,true);await offline.ready;assert.match(offline.elements.get('save-status').textContent,/Restored/);
  offline.click('Hunt');assert.match(offline.elements.get('save-status').textContent,/Saved/);
  // Old rules remain attached; a new journey uses the latest loaded JSON.
  const current=environment(storage,modified);await current.ready;current.elements.get('reset-button').onclick();current.pack();
  data=saves.read(storage);assert.equal(Object.keys(data.games).length,2);assert.equal(data.games[id].config.crossings[0].successPercent,40);
  assert.equal(Object.values(data.games).find(g=>g.id!==id).config.crossings[0].successPercent,0);
});
test('Final river stop offers its choices before completing the journey',async()=>{
  const config=structuredClone(defaults);config.totalMiles=10;config.events=[];config.stops=[{id:'end',name:'Oregon River',mile:10,type:'river',actions:[{label:'Final task',effects:{health:1}}]}];
  const serviceWorker={register:()=>Promise.resolve(),ready:new Promise(()=>{})};
  const storage=memory(),game=environment(storage,config,false,serviceWorker);await game.ready;game.pack();game.click('Continue');await new Promise(resolve=>setImmediate(resolve));
  assert.ok(game.elements.get('actions').children[1].children.some(b=>b.textContent.startsWith('Ford the river')));
  assert.ok(game.elements.get('actions').children[1].children.some(b=>b.textContent==='Final task'));
  assert.match(game.elements.get('offline-status').textContent,/Offline reload unavailable/);
  game.click('Final task');assert.equal(Object.values(saves.read(storage).games)[0].state.gameOver,true);
});
test('Game visits crossed stops in order, resolves custom failure, and retains completed journeys',async()=>{
  const config=structuredClone(defaults);
  config.totalMiles=30;config.events=[];config.stops=[{id:'a',name:'Camp A',mile:1,type:'stop',actions:[{label:'Risky task',days:2,successPercent:0,failureEffects:{health:-100}}]},{id:'b',name:'Camp B',mile:2,type:'stop'},{id:'end',name:'Oregon City',mile:30,type:'stop'}];
  const storage=memory();const game=environment(storage,config);await game.ready;game.pack();game.click('Continue');assert.equal(Object.values(saves.read(storage).games)[0].pendingStop,'a');
  game.click('Leave');game.click('Continue');assert.equal(Object.values(saves.read(storage).games)[0].pendingStop,'b');
  game.click('Leave');game.click('Continue');assert.equal(Object.values(saves.read(storage).games)[0].pendingStop,'end');game.click('Leave');assert.equal(Object.values(saves.read(storage).games)[0].state.gameOver,true);
  game.click('Start a new');game.pack();game.click('Continue');game.click('Risky task');
  const records=Object.values(saves.read(storage).games);assert.equal(records.length,2);assert.equal(records.filter(x=>x.state.gameOver).length,2);assert.ok(records.some(x=>x.state.health===0));
});
test('Service worker caches scoped game resources and returns them when offline',async()=>{
  const listeners={},maps=new Map();let offline=false;const scope='https://example.test/oregon-trail/';
  const caches={open:async name=>{if(!maps.has(name))maps.set(name,new Map());const map=maps.get(name);return {addAll:async files=>{for(const f of files)map.set(new URL(f,scope).href,new Response(f));},match:async key=>map.get(key)?.clone(),put:async(key,value)=>map.set(key,value)};},keys:async()=>[...maps.keys()],delete:async name=>maps.delete(name)};
  maps.set('another-app-v1',new Map());maps.set('oregon-trail-old',new Map());
  vm.runInNewContext(fs.readFileSync(path.join(root,'sw.js'),'utf8'),{self:{registration:{scope},addEventListener:(name,fn)=>listeners[name]=fn,clients:{claim:async()=>{}}},caches,URL,Response,AbortSignal,fetch:async()=>{if(offline)throw Error('offline');return new Response('updated content');}});
  let pending;listeners.install({waitUntil:p=>pending=p});await pending;listeners.activate({waitUntil:p=>pending=p});await pending;
  assert.ok(maps.has('another-app-v1'));assert.ok(!maps.has('oregon-trail-old'));
  offline=true;
  for(const file of ['', 'game.js','game-content.json']){
    let result;listeners.fetch({request:{method:'GET',url:scope+file},respondWith:p=>result=p});assert.ok(result);const response=await result;assert.equal(response.status,200);
  }
  let touched=false;listeners.fetch({request:{method:'GET',url:'https://example.test/other/'},respondWith:()=>touched=true});assert.equal(touched,false);
});
