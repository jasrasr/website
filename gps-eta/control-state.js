/* GPS ETA Control State Labels - Rev 1.8.12 */
(function(){
function btn(id){return document.getElementById(id);}
function sync(){var start=btn('startBtn'),pause=btn('pauseBtn');if(!start)return;var trackingOn=false,tripOn=false;try{trackingOn=typeof tracking!=='undefined'&&tracking===true;}catch(e){}try{tripOn=typeof tripActive!=='undefined'&&tripActive===true;}catch(e){}if(trackingOn&&tripOn){start.textContent='Tracking Active';start.disabled=true;start.setAttribute('aria-label','Tracking is active');if(pause){pause.textContent='Pause';pause.disabled=false;}return;}if(tripOn&&!trackingOn){start.textContent='Resume Tracking';start.disabled=false;start.setAttribute('aria-label','Resume GPS tracking');if(pause){pause.textContent='Paused';pause.disabled=true;}return;}start.textContent='Start Trip Tracking';start.disabled=false;start.setAttribute('aria-label','Start trip tracking');if(pause){pause.textContent='Pause';pause.disabled=true;}}
window.gpsEtaSyncControlState=sync;document.addEventListener('visibilitychange',sync);window.addEventListener('focus',sync);window.addEventListener('pageshow',sync);setInterval(sync,500);sync();
})();
