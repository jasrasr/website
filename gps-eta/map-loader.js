/* GPS ETA Map Loader - Rev 1.8.5 */
(function(){
function addScript(src,onload){var s=document.createElement('script');s.src=src;s.onload=onload||function(){};s.onerror=function(){var el=document.getElementById('liveMapInfo');if(el)el.textContent='Map library failed to load.';};document.body.appendChild(s);}
function addCss(href){var l=document.createElement('link');l.rel='stylesheet';l.href=href;document.head.appendChild(l);}
function loadMap(){addScript('live-map.js?v=1.8.5');}
if(window.L){loadMap();return;}
var base='https://'+'unpkg.com'+'/leaflet@1.9.4/dist/';
addCss(base+'leaflet.css');
addScript(base+'leaflet.js',loadMap);
})();
