/* Bump this version when changing cached files. Scoped to /oregon-trail/. */
const CACHE='oregon-trail-v1';
const FILES=['./','index.html','style.css','rules.js','saves.js','game.js','game-content.json'];
self.addEventListener('install',event=>{event.waitUntil(caches.open(CACHE).then(cache=>cache.addAll(FILES)));});
self.addEventListener('activate',event=>{event.waitUntil(caches.keys().then(keys=>Promise.all(keys.filter(k=>k.startsWith('oregon-trail-')&&k!==CACHE).map(k=>caches.delete(k)))).then(()=>self.clients.claim()));});
self.addEventListener('fetch',event=>{
  const url=new URL(event.request.url);
  const scope=new URL(self.registration.scope);
  if(event.request.method!=='GET'||url.origin!==scope.origin||!url.pathname.startsWith(scope.pathname))return;
  const relative=url.pathname.slice(scope.pathname.length);
  if(!['','index.html','style.css','rules.js','saves.js','game.js','game-content.json'].includes(relative))return;
  const key=new URL(relative||'./',scope).href;
  event.respondWith((async()=>{
    const cache=await caches.open(CACHE);
    // Installed app code stays together; content can update for new journeys.
    if(relative!=='game-content.json'){const cached=await cache.match(key);if(cached)return cached;}
    try{
      const response=await fetch(event.request,{signal:AbortSignal.timeout(5000)});
      if(!response.ok)throw Error('Failed response');await cache.put(key,response.clone());return response;
    }catch{const cached=await cache.match(key);if(cached)return cached;return Response.error();}
  })());
});
