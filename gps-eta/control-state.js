/* GPS ETA Control State Labels - Rev 1.8.12 */
(function(){
function btn(id){return document.getElementById(id);}
function isTrue(name){try{return typeof window[name]!=='undefined'&&window[name]===true;}catch(e){return false;}}
function globalValue(name){try{return eval(name);}catch(e){return undefined;}}
function sync(){var start=btn('startBtn'),pause=btn('pauseBtn'),status=document.getElementById('statusText');if(!start)return;var tracking=globalValue('tracking')===true;var tripActive=globalValue('tripActive')===true;var watchId=globalValue('watchId');if(tracking&&tripActive){start.textContent='Tracking Active';start.disabled=true;start.setAttribute('aria-label','Tracking is active');if(pause){pause.textContent='Pause';pause.disabled=false;}return;}if(tripActive&&!tracking){start.textContent='Resume Tracking';start.disabled=false;start.setAttribute('aria-label','Resume GPS tracking');if(pause){pause.textContent='Paused';pause.disabled=true;}return;}start.textContent='Start Trip Tracking';start.disabled=false;start.setAttribute('aria-label','Start trip tracking');if(pause){pause.textContent='Pause';pause.disabled=true;}if(status&&watchId===null){} }
window.gpsEtaSyncControlState=sync;document.addEventListener('visibilitychange',sync);window.addEventListener('focus',sync);window.addEventListener('pageshow',sync);setInterval(sync,500);sync();
})();
