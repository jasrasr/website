/*
File Name     : sw.js
Author        : Jason Lamb (with help from ChatGPT)
Description   : Scoped offline caching for the game, artwork modules, revision, and changelog.
Revision      : 1.0.2
Created Date  : 2026-10-08
Modified Date : 2026-10-20
Changelog     :
  Rev 1.0.2 - Cached the Rev 1.1.0 application assets with a new offline cache.
  Rev 1.0.1 - Versioned cached asset keys and allowed explicit waiting-worker activation.
  Rev 1.0.0 - Established independent file revision tracking; Scoped offline caching for the game, artwork modules, revision, and changelog.
*/
/* Bump RELEASE and CACHE when cached application files change. */
const RELEASE='1.1.0',CACHE='oregon-trail-v7';
const FILES=['./','index.html','style.css','rules.js','party.js','scenes.js','version.js','CHANGELOG.md','saves.js','game.js','game-content.json'];
const versioned=file=>/\.(js|css|json|md)$/.test(file)?file+'?v='+RELEASE:file;
self.addEventListener('install',event=>{event.waitUntil(caches.open(CACHE).then(cache=>cache.addAll(FILES.map(versioned))));});
self.addEventListener('message',event=>{if(event.data?.type==='ACTIVATE_UPDATE')self.skipWaiting();});
self.addEventListener('activate',event=>{event.waitUntil(caches.keys().then(keys=>Promise.all(keys.filter(k=>k.startsWith('oregon-trail-')&&k!==CACHE).map(k=>caches.delete(k)))).then(()=>self.clients.claim()));});
self.addEventListener('fetch',event=>{
  const url=new URL(event.request.url),scope=new URL(self.registration.scope);
  if(event.request.method!=='GET'||url.origin!==scope.origin||!url.pathname.startsWith(scope.pathname))return;
  const relative=url.pathname.slice(scope.pathname.length);
  if(!FILES.includes(relative||'./'))return;
  // Never substitute another release's assets, even when this worker controls an older tab.
  if(url.searchParams.has('v')&&url.searchParams.get('v')!==RELEASE)return;
  const file=relative||'./',key=new URL(versioned(file),scope).href;
  event.respondWith((async()=>{
    const cache=await caches.open(CACHE);
    if(relative!=='game-content.json'){const cached=await cache.match(key);if(cached)return cached;}
    try{
      const response=await fetch(key,{cache:'no-cache',signal:AbortSignal.timeout(5000)});
      if(!response.ok)throw Error('Failed response');await cache.put(key,response.clone());return response;
    }catch{const cached=await cache.match(key);if(cached)return cached;return Response.error();}
  })());
});
