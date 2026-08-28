/* GPS ETA table render helpers - Rev 1.5.1 */
(function(){
function q(v){var x=Number(v);return Number.isFinite(x)?x:null;}
function c(r,v){var d=document.createElement('td');d.textContent=v==null?'':String(v);r.appendChild(d);}
function z(b,n,m){b.replaceChildren();var r=document.createElement('tr'),d=document.createElement('td');d.colSpan=n;d.className='small';d.textContent=m;r.appendChild(d);b.appendChild(r);}
renderLog=function(){if(!logEntries||!logEntries.length){z(els.logBody,7,'No log entries yet.');return;}els.logBody.replaceChildren();logEntries.slice(0,25).forEach(function(e){var r=document.createElement('tr'),a=q(e.tracked),b=q(e.remaining),s=q(e.speed);c(r,e.time);c(r,e.elapsed);c(r,(a!==null?a.toFixed(2):'--')+' '+label());c(r,(b!==null?b.toFixed(2):'--')+' '+label());c(r,(s!==null?s.toFixed(1):'--')+' '+speedLabel());c(r,e.heading||'--');c(r,e.accuracy||'--');els.logBody.appendChild(r);});};
renderHistory=function(list){if(!list||!list.length){z(els.historyBody,7,'No server history found for this device.');return;}els.historyBody.replaceChildren();list.slice(0,50).forEach(function(e){var r=document.createElement('tr'),a=q(e.tracked),b=q(e.remaining),s=q(e.speed);c(r,e.time||e.serverSavedAt||'--');c(r,e.elapsed||'--');c(r,(a!==null?a.toFixed(2):'--')+' '+(e.unit||label()));c(r,(b!==null?b.toFixed(2):'--')+' '+(e.unit||label()));c(r,(s!==null?s.toFixed(1):'--')+' '+(e.speedUnit||speedLabel()));c(r,e.heading||'--');c(r,e.reason||'--');els.historyBody.appendChild(r);});};
try{renderLog();if(typeof loadServerHistory==='function')loadServerHistory();}catch(e){}
})();
