/* File Name: app.js
Author: Jason Lamb (with help from ChatGPT)
Description: Workout quick entry, editable review, and per-exercise trends.
Revision: 1.2.0
Created Date: 2026-10-21
Modified Date: 2026-10-21
Changelog: 1.2.0 - Add opt-in AI retry and private config status. 1.1.0 - Local parsing without API key and multi-exercise saving. */
'use strict';
const $=selector=>document.querySelector(selector);
const measures=[['distance_miles','Miles'],['duration_minutes','Minutes'],['speed_mph','Speed (mph)'],['reps','Reps'],['sets','Sets'],['weight_lb','Weight (lb)']];
let entries=[];
function localDate(d=new Date()){return [d.getFullYear(),String(d.getMonth()+1).padStart(2,'0'),String(d.getDate()).padStart(2,'0')].join('-');}
function localTime(d=new Date()){return [String(d.getHours()).padStart(2,'0'),String(d.getMinutes()).padStart(2,'0')].join(':');}
function offset(d=new Date()){const n=-d.getTimezoneOffset(),a=Math.abs(n);return (n>=0?'+':'-')+String(Math.floor(a/60)).padStart(2,'0')+':'+String(a%60).padStart(2,'0');}
function niceDay(day){return new Date(day+'T12:00:00').toLocaleDateString(undefined,{month:'short',day:'numeric',year:'numeric'});}
function titleCase(name){return name.replace(/\b\w/g,c=>c.toUpperCase());}
function message(text,error=false){$('#message').textContent=text;$('#message').classList.toggle('error',error);}
async function api(body){
 const response=await fetch('?api=1',body?{method:'POST',body,credentials:'same-origin'}:{credentials:'same-origin'});
 let data;try{data=await response.json();}catch(_){throw Error('Unable to reach workout service.');}
 if(!response.ok)throw Error(data.error||data.message||'Workout request failed.');return data;
}
function payload(action){const data=new FormData();data.set('csrf',$('#csrf').value);data.set('action',action);return data;}
function fieldInput(name,label,value,type='number'){
 const wrapper=document.createElement('label');wrapper.className='field';
 const caption=document.createElement('span');caption.textContent=label;
 const input=document.createElement('input');input.type=type;input.dataset.field=name;input.value=value??'';
 if(type==='number'){input.min='0';input.step='any';}if(type==='text')input.maxLength=80;
 wrapper.append(caption,input);return wrapper;
}
function describe(entry){
 return [entry.distance_miles!=null?entry.distance_miles+' mi':'',entry.duration_minutes!=null?entry.duration_minutes+' min':'',
 entry.speed_mph!=null?entry.speed_mph+' mph':'',entry.sets!=null?entry.sets+' sets':'',
 entry.reps!=null?entry.reps+' reps'+(entry.sets!=null?'/set':''):'',entry.weight_lb!=null?entry.weight_lb+' lb':''].filter(Boolean).join(' · ')||'Add measurements below';
}
function renderReview(suggestions){
 const list=$('#reviewList');list.replaceChildren();$('#sessionTime').value=localTime();
 $('#reviewHeading').textContent=suggestions.length+' exercise'+(suggestions.length===1?'':'s')+' recognized';
 suggestions.forEach(entry=>{
  const card=document.createElement('article');card.className='review-card';
  const top=document.createElement('div');top.className='review-top';
  const left=document.createElement('div'),name=document.createElement('h3');name.textContent=titleCase(entry.exercise);
  const summary=document.createElement('p');summary.textContent=describe(entry);left.append(name,summary);
  const date=document.createElement('span');date.className='date-chip';date.textContent=niceDay(entry.date);top.append(left,date);
  const details=document.createElement('details'),heading=document.createElement('summary');heading.textContent='Edit measurements or date';
  const grid=document.createElement('div');grid.className='field-grid';
  grid.append(fieldInput('exercise','Exercise',entry.exercise,'text'),fieldInput('date','Workout date',entry.date,'date'));
  measures.forEach(item=>grid.append(fieldInput(item[0],item[1],entry[item[0]])));
  const notes=document.createElement('label');notes.className='field full';
  const nt=document.createElement('span');nt.textContent='Notes';
  const ta=document.createElement('textarea');ta.dataset.field='notes';ta.rows=2;ta.maxLength=2000;ta.value=entry.notes||'';
  notes.append(nt,ta);grid.append(notes);details.append(heading,grid);card.append(top,details);list.append(card);
 });
 $('#review').hidden=false;$('#review').scrollIntoView({behavior:'smooth',block:'nearest'});
}
function collect(){
 return Array.from($('#reviewList').querySelectorAll('.review-card'),card=>{
  const row={};card.querySelectorAll('[data-field]').forEach(input=>{row[input.dataset.field]=input.value;});
  if(!row.exercise.trim())throw Error('Enter an exercise name.');if(!row.date)throw Error('Select an exercise date.');
  return row;
 });
}
async function review(){
 const note=$('#note').value.trim();if(!note){message('Describe your workout first.',true);$('#note').focus();return;}
 const button=$('#parseBtn');button.disabled=true;button.textContent='Reading your workout…';
 try{const data=payload('parse_local');data.set('note',note);data.set('today',localDate());
  const result=await api(data);renderReview(result.suggestions);message('Review the summary, then save. No AI key needed.');
 }catch(e){$('#review').hidden=true;message(e.message,true);}
 finally{button.disabled=false;button.textContent='Review workout';}
}
async function retryWithAi(){
 const note=$('#note').value.trim();
 if(!note){message('Describe your workout first.',true);$('#note').focus();return;}
 const button=$('#aiParseBtn');button.disabled=true;button.textContent='Asking AI to interpret…';
 try{
  const data=payload('parse_ai');data.set('note',note);data.set('today',localDate());
  const result=await api(data);
  renderReview(result.suggestions);
  message('AI suggested these details. Confirm each value before saving.');
 }catch(e){message(e.message,true);}
 finally{button.textContent='Try AI interpretation instead (optional)';button.disabled=!button.dataset.enabled;}
}
async function save(rows,photo=null){
 const button=$('#saveBtn');button.disabled=true;button.textContent='Saving…';
 try{const data=payload('save_batch');data.set('entries',JSON.stringify(rows));
  data.set('time_local',$('#sessionTime').value||localTime());data.set('utc_offset',offset());if(photo)data.set('photo',photo);
  const result=await api(data);$('#note').value='';$('#photo').value='';$('#review').hidden=true;
  message('Saved '+result.entries.length+' exercise'+(result.entries.length===1?'':'s')+'. Every session counts.');
  await load();window.scrollTo({top:0,behavior:'smooth'});
 }catch(e){message(e.message,true);}finally{button.disabled=false;button.textContent='Save workout';}
}
function refresh(){
 $('#sessions').textContent=new Set(entries.map(e=>e.session_id||e.date)).size;
 $('#count').textContent=entries.length;
 $('#minutes').textContent=entries.reduce((v,e)=>v+(Number(e.duration_minutes)||0),0).toFixed(0);
 $('#miles').textContent=entries.reduce((v,e)=>v+(Number(e.distance_miles)||0),0).toFixed(2);
 const recent=entries.slice().sort((a,b)=>(b.performed_at||b.date).localeCompare(a.performed_at||a.date)).slice(0,10);
 $('#recent').replaceChildren(...recent.map(e=>{
  const li=document.createElement('li'),name=document.createElement('strong');name.textContent=titleCase(e.exercise);
  const day=document.createElement('span');day.textContent=niceDay(e.date);
  const detail=document.createElement('small');detail.textContent=describe(e)+(e.photo?' · Photo saved':'');
  li.append(name,day,detail);return li;
 }));
 $('#empty').hidden=entries.length>0;
 const kinds=[...new Set(entries.map(e=>e.exercise))].sort(),select=$('#exerciseFilter'),old=select.value;
 select.replaceChildren(...kinds.map(k=>{const o=document.createElement('option');o.value=k;o.textContent=titleCase(k);return o;}));
 if(kinds.includes(old))select.value=old;
 $('#trendPanel').hidden=kinds.length===0;draw();
}
function draw(){
 const c=$('#chart'),ctx=c.getContext('2d');ctx.clearRect(0,0,c.width,c.height);
 const metric=$('#metric').value,exercise=$('#exerciseFilter').value;
 const points=entries.filter(e=>e.exercise===exercise&&e[metric]!=null&&Number.isFinite(Number(e[metric])))
  .sort((a,b)=>(a.performed_at||a.date).localeCompare(b.performed_at||b.date));
 const left=54,right=c.width-23,top=22,bottom=c.height-46;
 ctx.font='14px system-ui';ctx.strokeStyle='#36546d';ctx.fillStyle='#aebfd0';ctx.lineWidth=1;
 ctx.beginPath();ctx.moveTo(left,top);ctx.lineTo(left,bottom);ctx.lineTo(right,bottom);ctx.stroke();
 if(!points.length){$('#trend').textContent='Choose a logged measurement to see its trend.';return;}
 const vals=points.map(e=>Number(e[metric]));let high=Math.max(...vals),low=Math.min(0,...vals);if(high===low)high=low+1;
 for(let i=0;i<4;i++){const value=low+(high-low)*i/3,y=bottom-i*(bottom-top)/3;
  ctx.fillText(value.toFixed(value<10?1:0),5,y+5);
  ctx.strokeStyle='#21394d';ctx.beginPath();ctx.moveTo(left,y);ctx.lineTo(right,y);ctx.stroke();
 }
 const coords=vals.map((v,i)=>({x:left+(right-left)*(points.length===1?.5:i/(points.length-1)),y:bottom-(v-low)/(high-low)*(bottom-top)}));
 ctx.strokeStyle='#59dbc3';ctx.lineWidth=3;ctx.beginPath();
 coords.forEach((p,i)=>i?ctx.lineTo(p.x,p.y):ctx.moveTo(p.x,p.y));ctx.stroke();
 ctx.fillStyle='#59dbc3';coords.forEach(p=>{ctx.beginPath();ctx.arc(p.x,p.y,4,0,Math.PI*2);ctx.fill();});
 ctx.fillStyle='#aebfd0';ctx.fillText(niceDay(points[0].date),left,bottom+26);
 if(points.length>1){const text=niceDay(points.at(-1).date);ctx.fillText(text,right-ctx.measureText(text).width,bottom+26);}
 const change=vals.at(-1)-vals[0];
 $('#trend').textContent=points.length>1?points.length+' measurements · First '+vals[0]+' · Latest '+vals.at(-1)+' · Change '+(change>0?'+':'')+Number(change.toFixed(2)):'First measurement logged. Add another to see your trend.';
}
async function load(){try{const data=await api();entries=data.entries;$('#csrf').value=data.csrf;
 const retry=$('#aiParseBtn');retry.dataset.enabled=data.ai_available?'yes':'';
 retry.disabled=!data.ai_available;
 $('#aiHint').textContent=data.ai_available
  ?'AI fallback is configured. Select it only when the local interpretation needs another try. Your description will be sent to your configured AI provider.'
  :'AI fallback is optional and disabled until config.local.example.php is copied to config.local.php on Hostinger with a private API key.';
 refresh();}catch(e){message(e.message,true);}}
$('#dateLabel').textContent=new Date().toLocaleDateString(undefined,{weekday:'long',month:'long',day:'numeric'});
$('#parseBtn').addEventListener('click',review);
$('#aiParseBtn').addEventListener('click',retryWithAi);
$('#note').addEventListener('input',()=>{$('#review').hidden=true;});
$('#saveBtn').addEventListener('click',()=>{try{save(collect(),$('#photo').files[0]||null);}catch(e){message(e.message,true);}});
$('#exerciseFilter').addEventListener('change',draw);$('#metric').addEventListener('change',draw);
$('#manualForm').addEventListener('submit',async e=>{
 e.preventDefault();const fd=new FormData(e.currentTarget),row=Object.fromEntries(fd.entries());
 const file=fd.get('photo'),photo=file instanceof File&&file.size?file:null;
 delete row.photo;row.date=row.date||localDate();$('#sessionTime').value=localTime();
 await save([row],photo);if(!$('#message').classList.contains('error'))e.currentTarget.reset();
});
$('#manualDate').value=localDate();
load();
