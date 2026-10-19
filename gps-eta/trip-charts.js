/* GPS ETA Elevation and Direction Timeline
 * File: gps-eta/trip-charts.js
 * Author: Jason Lamb
 * Revision: 1.8.15
 * Description: Local SVG charts of altitude and compass heading from logged snapshots.
 * Created: 2026-10-19
 * Changelog: 1.8.15 initial charts; no external chart or directions API.
 */
(function () {
 'use strict';
 const NS='http://www.w3.org/2000/svg';
 const $=id=>document.getElementById(id);
 function node(tag,attrs,text){const n=document.createElementNS(NS,tag);Object.keys(attrs||{}).forEach(k=>n.setAttribute(k,String(attrs[k])));if(text!==undefined)n.textContent=text;return n;}
 function rowPoints(){if(typeof logEntries==='undefined'||!Array.isArray(logEntries))return [];return logEntries.slice().reverse().map(e=>({time:Date.parse(e.iso),alt:e.altitudeMeters===null||e.altitudeMeters===undefined?null:Number(e.altitudeMeters),dir:e.headingDegrees===null||e.headingDegrees===undefined?null:Number(e.headingDegrees)})).filter(e=>Number.isFinite(e.time));}
 function card(title,id){const c=document.createElement('div');c.className='metric full';c.id=id;const h=document.createElement('div');h.className='metric-title';h.textContent=title;c.appendChild(h);const meta=document.createElement('div');meta.className='metric-unit';meta.id=id+'Info';meta.textContent='Waiting for logged GPS samples';c.appendChild(meta);const svg=node('svg',{viewBox:'0 0 640 210',role:'img','aria-label':title,width:'100%',style:'display:block;margin-top:8px;max-height:260px;overflow:visible'});svg.id=id+'Svg';c.appendChild(svg);return c;}
 function init(){if($('elevationTimelineCard'))return;const grid=document.querySelector('section.grid');if(!grid)return;const elevation=card('Elevation Timeline','elevationTimelineCard'),heading=card('Travel Direction Timeline','directionTimelineCard');const map=$('liveMapCard');if(map&&map.parentNode===grid){grid.insertBefore(elevation,map);grid.insertBefore(heading,map);}else{grid.appendChild(elevation);grid.appendChild(heading);}}
 function draw(id,records,isDir){const svg=$(id+'Svg'),info=$(id+'Info');if(!svg||!info)return;while(svg.firstChild)svg.removeChild(svg.firstChild);
 const pts=records.filter(p=>isDir?(p.dir!==null&&Number.isFinite(p.dir)):(p.alt!==null&&Number.isFinite(p.alt)));
 if(!pts.length){info.textContent='No '+(isDir?'heading':'altitude')+' samples logged yet; values appear as snapshots are recorded.';return;}
 const x0=62,x1=620,y0=20,y1=176,t0=pts[0].time,t1=pts[pts.length-1].time,vals=pts.map(p=>isDir?p.dir:p.alt);
 const lo=isDir?0:Math.floor((Math.min(...vals)-5)/5)*5,hi=isDir?360:Math.ceil((Math.max(...vals)+5)/5)*5;
 const x=t=>x0+((t-t0)/Math.max(1,t1-t0))*(x1-x0),y=v=>y1-((v-lo)/Math.max(1,hi-lo))*(y1-y0);
 [0,.25,.5,.75,1].forEach(f=>{const v=lo+(hi-lo)*f,yy=y(v);svg.appendChild(node('line',{x1:x0,y1:yy,x2:x1,y2:yy,stroke:'currentColor','stroke-opacity':'.16'}));svg.appendChild(node('text',{x:52,y:yy+4,fill:'currentColor','text-anchor':'end','font-size':12},isDir?(['N','E','S','W','N'][Math.round(f)]):Math.round(v)+' m'));});
 svg.appendChild(node('text',{x:x0,y:203,fill:'currentColor','font-size':12},new Date(t0).toLocaleTimeString([],{hour:'numeric',minute:'2-digit'})));
 svg.appendChild(node('text',{x:x1,y:203,fill:'currentColor','font-size':12,'text-anchor':'end'},new Date(t1).toLocaleTimeString([],{hour:'numeric',minute:'2-digit'})));
 if(!isDir&&pts.length>1){const d=pts.map((p,i)=>(i?'L':'M')+x(p.time).toFixed(2)+','+y(p.alt).toFixed(2)).join(' ');svg.appendChild(node('path',{d,fill:'none',stroke:'#60a5fa','stroke-width':3,'stroke-linecap':'round','stroke-linejoin':'round'}));}
 pts.forEach(p=>{const v=isDir?p.dir:p.alt;const dot=node('circle',{cx:x(p.time),cy:y(v),r:isDir?4:3,fill:isDir?'#fbbf24':'#93c5fd'});dot.appendChild(node('title',{},new Date(p.time).toLocaleTimeString()+' — '+(isDir?Math.round(v)+'°':v.toFixed(1)+' m')));svg.appendChild(dot);});
 info.textContent=pts.length+' logged samples · '+(isDir?'latest heading '+Math.round(vals[vals.length-1])+'° (N 0°, E 90°, S 180°, W 270°)':'minimum '+Math.min(...vals).toFixed(1)+' m · maximum '+Math.max(...vals).toFixed(1)+' m');
 }
 let lastSig='';function update(){init();const a=rowPoints(),sig=a.length+':'+(a[a.length-1]?.time||0)+':'+(a[a.length-1]?.alt||'')+':'+(a[a.length-1]?.dir||'');if(sig===lastSig)return;lastSig=sig;draw('elevationTimelineCard',a,false);draw('directionTimelineCard',a,true);}
 if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',update);else update();setInterval(update,3000);
})();