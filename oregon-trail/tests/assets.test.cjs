/*
File Name     : tests/assets.test.cjs
Author        : Jason Lamb (with help from ChatGPT)
Description   : Release integrity, recovery safety, and offline cache isolation tests.
Revision      : 1.0.0
Created Date  : 2026-10-19
Modified Date : 2026-10-19
Changelog     :
  Rev 1.0.0 - Release integrity, recovery safety, and offline cache isolation tests.
*/
const test=require('node:test'),assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path'),crypto=require('node:crypto'),vm=require('node:vm');
const root=path.resolve(__dirname,'..'),version=require('../version.js');
test('HTML assets use one release and integrity hashes matching the shipped files',()=>{
  const html=fs.readFileSync(path.join(root,'index.html'),'utf8');
  const assets=[...html.matchAll(/<(?:script|link)\b[^>]*(?:src|href)="([^"]+\.(?:js|css)\?v=[^"]+)"[^>]*>/g)];
  assert.equal(assets.length,7);
  for(const [tag,url] of assets){
    assert.equal(new URL(url,'https://example.test/').searchParams.get('v'),version.revision);
    const file=url.split('?')[0],hash=crypto.createHash('sha256').update(fs.readFileSync(path.join(root,file))).digest('base64');
    assert.ok(tag.includes('integrity="sha256-'+hash+'"'),file+' integrity differs');
  }
  assert.ok(html.includes('Project Rev '+version.revision+' · Updated '+version.updated));
  assert.ok(html.includes('id="startup-error"'));assert.ok(html.includes('window.__trailBooted'));
});
test('Recovery page applies the update without removing saved journeys',()=>{
  const update=fs.readFileSync(path.join(root,'update.html'),'utf8');
  assert.ok(update.includes('sw.js?v='+version.revision));assert.ok(update.includes("updateViaCache:'none'"));
  assert.ok(update.includes('ACTIVATE_UPDATE'));assert.ok(!/localStorage|sessionStorage|\.unregister\(|caches\.delete/.test(update));
  const styles=fs.readFileSync(path.join(root,'style.css'),'utf8');
  assert.ok(styles.includes('-webkit-appearance:none'));assert.ok(styles.includes('max-width:100%'));
  const headers=fs.readFileSync(path.join(root,'.htaccess'),'utf8');assert.ok(headers.includes('no-cache, max-age=0, must-revalidate'));
});
test('Offline worker never serves a cached script from a different release',async()=>{
  const listeners={},scope='https://example.test/oregon-trail/',stored=new Map();
  const cache={addAll:async files=>{for(const file of files)stored.set(new URL(file,scope).href,new Response(file));},match:async key=>stored.get(key)?.clone(),put:async(key,response)=>stored.set(key,response)};
  let activated=false;
  vm.runInNewContext(fs.readFileSync(path.join(root,'sw.js'),'utf8'),{self:{registration:{scope},addEventListener:(name,fn)=>listeners[name]=fn,skipWaiting:()=>activated=true},caches:{open:async()=>cache},URL,Response,AbortSignal,fetch:async()=>{throw Error('offline');}});
  let install;listeners.install({waitUntil:p=>install=p});await install;
  let result;listeners.fetch({request:{method:'GET',url:scope+'game.js?v='+version.revision},respondWith:p=>result=p});
  assert.equal(await(await result).text(),'game.js?v='+version.revision);
  let handled=false;listeners.fetch({request:{method:'GET',url:scope+'game.js?v=0.9.0'},respondWith:()=>handled=true});
  assert.equal(handled,false);listeners.message({data:{type:'ACTIVATE_UPDATE'}});assert.equal(activated,true);
  assert.ok(stored.has(scope+'style.css?v='+version.revision));
});
