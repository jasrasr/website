/* File Name: app.js
Author: Jason Lamb (with help from ChatGPT)
Description: Workout quick entry, editable review, and per-exercise trends.
Revision: 1.5.0
Created Date: 2026-10-21
Modified Date: 2026-10-27
Changelog: 1.5.0 - Add authenticated workout details/photo viewer and private backup download UI. 1.4.0 - Editable set-by-set weight/reps, custom measurements, calories and exercise-specific progress. 1.3.2 - Optimize mobile exercise fields, retain hidden measurements, and keep optional metrics near the measurements. 1.3.1 - Fix Edit button crash caused by adding an empty CSS class during form creation. 1.3.0 - Add safe entry editing, tags, incline and history filtering. 1.2.0 - Add opt-in AI retry and private config status. 1.1.0 - Local parsing without API key and multi-exercise saving. */
'use strict';
const $=selector=>document.querySelector(selector);
const measures=[['distance_miles','Miles'],['duration_minutes','Minutes'],['speed_mph','Speed (mph)'],['incline_percent','Treadmill incline (%)'],['calories_kcal','Calories (kcal)'],['reps','Reps'],['sets','Sets'],['weight_lb','Weight (lb)']];
let entries=[];
let editingId=null;
let editingRevision=null;
let showAllHistory=false;
let manualAdvanced=null;
let detailEntryId=null;
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
 if(type==='number'){input.min='0';input.step='any';}if(type==='text')input.maxLength=name==='tag'?64:80;
 if(name==='incline_percent')input.max='100';
 if(name==='calories_kcal')input.max='100000';
 if(type==='number')input.inputMode='decimal';
 wrapper.append(caption,input);return wrapper;
}
function describe(entry){
 const perSet=WorkoutAdvanced.stats(entry);
 const parts=[
  entry.distance_miles!=null?entry.distance_miles+' mi':'',
  entry.duration_minutes!=null?entry.duration_minutes+' min':'',
  entry.speed_mph!=null?entry.speed_mph+' mph':'',
  entry.incline_percent!=null?entry.incline_percent+'% incline':'',
  entry.calories_kcal!=null?entry.calories_kcal+' kcal':'',
 ];
 if(perSet){
  parts.push(perSet.set_count+' sets',perSet.total_reps+' total reps');
  if(perSet.max_weight_lb!==null){
   parts.push(perSet.max_weight_lb+' lb heaviest');
   parts.push(perSet.total_volume_lb+' lb volume');
  }
 }else{
  parts.push(entry.sets!=null?entry.sets+' sets':'',
    entry.reps!=null?entry.reps+' reps'+(entry.sets!=null?'/set':''):'',
    entry.weight_lb!=null?entry.weight_lb+' lb':'');
 }
 if(Array.isArray(entry.custom_metrics)&&entry.custom_metrics.length)
  parts.push(entry.custom_metrics.length+' custom metric'+(entry.custom_metrics.length===1?'':'s'));
 return parts.filter(Boolean).join(' · ')||'Add measurements below';
}

/**
 * Presentation only: no schema migration or data deletion. A hidden measurement
 * stays in the input and is submitted unchanged when editing an older record.
 */
function visibleFieldsForExercise(exercise){
 const name=String(exercise||'').toLowerCase().trim().replace(/\s+/g,' ');
 if(/tread\s?mill/.test(name)) return ['distance_miles','duration_minutes','speed_mph','incline_percent','calories_kcal'];
 if(/^(walking|walk|hiking|running|run|cycling|bike|biking|stationary bike|rowing|swimming|elliptical|stairs)$/.test(name))
  return ['distance_miles','duration_minutes','speed_mph','calories_kcal'];
 if(/^(bench press|deadlift|weight training|weight lifting)$/.test(name))
  return ['weight_lb','reps','sets'];
 if(/^(push-ups|pushups|sit-ups|situps|squats|lunges|crunches|jumping jacks)$/.test(name))
  return ['reps','sets'];
 if(/^(plank|yoga)$/.test(name)) return ['duration_minutes','sets'];
 return measures.map(([key])=>key); // Unknown/custom exercises can use all existing metrics.
}
/**
 * Hide unrelated fields by default but offer a safe override.
 * Empty/historical values remain in the DOM and are never discarded.
 */
function installExerciseVisibility(grid,exerciseInput){
 const button=document.createElement('button');
 button.type='button';button.className='secondary-action other-metrics';
 button.textContent='Show other measurements';
 button.hidden=true;button.dataset.expanded='no';
 // Keep the expand control alongside measurements, not below notes or photos.
 const detailsStart=Array.from(grid.children).find(label=>
  String(label.tagName).toLowerCase()==='label' &&
  Array.from(label.children).some(child=>['tag','notes','photo','time_local'].includes(
   child.dataset?.field||child.name
  ))
 );
 if(detailsStart)grid.insertBefore(button,detailsStart);
 else grid.append(button);
 const allKeys=new Set(measures.map(([key])=>key));
 function update(){
  const visible=new Set(visibleFieldsForExercise(exerciseInput?.value));
  let others=0,savedHidden=0;
  for(const label of Array.from(grid.children)){
   if(String(label.tagName).toLowerCase()!=='label')continue;
   const field=Array.from(label.children).find(child=>{
    const key=child.dataset?.field||child.name;
    return allKeys.has(key);
   });
   if(!field)continue;
   const key=field.dataset?.field||field.name;
   const extra=!visible.has(key);
   if(extra){others++;if(field.value!==''&&field.value!=null)savedHidden++;}
   label.hidden=extra&&button.dataset.expanded!=='yes';
  }
  button.hidden=others===0;
  button.textContent=button.dataset.expanded==='yes'?'Hide other measurements':
   'Show other measurements'+(savedHidden?' ('+savedHidden+' saved)':'');
 }
 button.addEventListener('click',()=>{button.dataset.expanded=button.dataset.expanded==='yes'?'no':'yes';update();});
 exerciseInput?.addEventListener('input',()=>{button.dataset.expanded='no';update();});
 update();
 return update;
}
function refreshChartMetrics(){
 const exercise=$('#exerciseFilter').value;
 const allowed=new Set(visibleFieldsForExercise(exercise));
 const existing=entries.filter(record=>record.exercise===exercise);
 const base=measures.filter(([key])=>allowed.has(key)||existing.some(row=>row[key]!=null));
 const detailed=existing.some(row=>Array.isArray(row.sets_detail)&&row.sets_detail.length>0);
 if(detailed)base.push(
  ['set_count','Set count'],['total_reps','Total reps'],
  ['max_weight_lb','Heaviest set (lb)'],['total_volume_lb','Total volume (lb)']
 );
 const known=new Set();
 for(const row of existing){
  if(!Array.isArray(row.custom_metrics))continue;
  for(const metric of row.custom_metrics){
   if(typeof metric?.name!=='string'||typeof metric?.unit!=='string')continue;
   const key=WorkoutAdvanced.customKey(metric.name,metric.unit);
   if(!known.has(key)){
    known.add(key);
    base.push([key,metric.name+(metric.unit?' ('+metric.unit+')':'')]);
   }
  }
 }
 const select=$('#metric'),previous=select.value;
 select.replaceChildren(...base.map(([key,label])=>{
  const option=document.createElement('option');option.value=key;
  option.textContent=label;return option;
 }));
 if(base.some(([key])=>key===previous))select.value=previous;
}
function renderReview(suggestions){
 const list=$('#reviewList');list.replaceChildren();$('#sessionTime').value=localTime();
 $('#reviewHeading').textContent=suggestions.length+' exercise'+(suggestions.length===1?'':'s')+' recognized';
 suggestions.forEach(entry=>{
  if(!entry.tag)entry.tag=$('#entryTag').value.trim();
  const card=document.createElement('article');card.className='review-card';
  const top=document.createElement('div');top.className='review-top';
  const left=document.createElement('div'),name=document.createElement('h3');name.textContent=titleCase(entry.exercise);
  const summary=document.createElement('p');summary.textContent=describe(entry);left.append(name,summary);
  const date=document.createElement('span');date.className='date-chip';date.textContent=niceDay(entry.date);top.append(left,date);
  const details=document.createElement('details'),heading=document.createElement('summary');heading.textContent='Edit measurements or date';
  const grid=document.createElement('div');grid.className='field-grid';
  const exerciseField=fieldInput('exercise','Exercise',entry.exercise,'text');
  exerciseField.classList.add('full');
  grid.append(exerciseField,fieldInput('date','Workout date',entry.date,'date'));
  measures.forEach(item=>grid.append(fieldInput(item[0],item[1],entry[item[0]])));
  const tagInput=fieldInput('tag','Tracking tag',entry.tag||'','text');tagInput.classList.add('full');grid.append(tagInput);
  const notes=document.createElement('label');notes.className='field full';
  const nt=document.createElement('span');nt.textContent='Notes';
  const ta=document.createElement('textarea');ta.dataset.field='notes';ta.rows=2;ta.maxLength=2000;ta.value=entry.notes||'';
  notes.append(nt,ta);grid.append(notes);
  installExerciseVisibility(grid,exerciseField.children[1]);
  WorkoutAdvanced.attach(grid,entry,exerciseField.children[1]);
  details.append(heading,grid);card.append(top,details);list.append(card);
 });
 $('#review').hidden=false;$('#review').scrollIntoView({behavior:'smooth',block:'nearest'});
}
function collect(){
 return Array.from($('#reviewList').querySelectorAll('.review-card'),card=>{
  const row={};card.querySelectorAll('[data-field]').forEach(input=>{row[input.dataset.field]=input.value;});
  if(!row.exercise.trim())throw Error('Enter an exercise name.');if(!row.date)throw Error('Select an exercise date.');
  Object.assign(row,WorkoutAdvanced.collect(card));
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
  const result=await api(data);$('#note').value='';$('#photo').value='';$('#entryTag').value='';$('#review').hidden=true;
  message('Saved '+result.entries.length+' exercise'+(result.entries.length===1?'':'s')+'. Every session counts.');
  await load();window.scrollTo({top:0,behavior:'smooth'});
  return true;
 }catch(e){message(e.message,true);return false;}finally{button.disabled=false;button.textContent='Save workout';}
}
function historyItems(){
 const tag=$('#historyTag').value;
 return entries.slice().filter(e=>!tag||(e.tag||'')===tag)
  .sort((a,b)=>(b.performed_at||b.date).localeCompare(a.performed_at||a.date));
}

/** Mobile-readable workout detail view. All user-provided content uses textContent. */
function openWorkoutDetails(id){
 const entry=entries.find(row=>row.id===id);
 if(!entry){message('That workout is no longer available. Refresh your history.',true);return;}
 detailEntryId=id;
 const title=$('#detailTitle');title.textContent=titleCase(entry.exercise)+' details';
 $('#detailDate').textContent=niceDay(entry.date)+
  (typeof entry.performed_at==='string'?' · '+entry.performed_at.slice(11,16)+' local time':'')+
  (entry.tag?' · #'+entry.tag:'');
 const region=$('#detailSummary');region.replaceChildren();
 const fact=(label,value)=>{
  const row=document.createElement('div');row.className='detail-fact';
  const term=document.createElement('span');term.className='detail-label';term.textContent=label;
  const amount=document.createElement('strong');amount.textContent=String(value);
  row.append(term,amount);region.append(row);
 };
 const labels={
  distance_miles:['Distance', ' miles'],
  duration_minutes:['Duration',' minutes'],
  speed_mph:['Speed',' mph'],
  incline_percent:['Treadmill incline','%'],
  calories_kcal:['Calories burned',' kcal'],
  reps:['Reps (legacy summary)',''],
  sets:['Sets (legacy summary)',''],
  weight_lb:['Weight (legacy summary)',' lb']
 };
 Object.entries(labels).forEach(([key,[label,unit]])=>{
  if(entry[key]!==null && entry[key]!==undefined && entry[key]!=='')
   fact(label,entry[key]+unit);
 });
 const stats=WorkoutAdvanced.stats(entry);
 if(stats){
  const heading=document.createElement('h3');heading.textContent='Individual sets';region.append(heading);
  const list=document.createElement('ol');list.className='detail-sets';
  entry.sets_detail.forEach((set,index)=>{
   const item=document.createElement('li');
   const label=document.createElement('span');label.textContent=
    'Set '+(index+1)+': '+(set.weight_lb==null?'Bodyweight':set.weight_lb+' lb')+' × '+set.reps+' reps';
   item.append(label);
   if(set.note){const note=document.createElement('small');note.textContent=String(set.note);item.append(note);}
   list.append(item);
  });
  region.append(list);
  fact('Total repetitions',stats.total_reps);
  if(stats.max_weight_lb!==null){
   fact('Heaviest set',stats.max_weight_lb+' lb');
   fact('Total lifted volume',stats.total_volume_lb+' lb');
  }
 }
 if(Array.isArray(entry.custom_metrics) && entry.custom_metrics.length){
  const heading=document.createElement('h3');heading.textContent='Additional measurements';region.append(heading);
  entry.custom_metrics.forEach(metric=>{
   if(typeof metric?.name==='string')
    fact(metric.name,String(metric.value??'')+(metric.unit?' '+metric.unit:''));
  });
 }
 if(entry.notes){
  const heading=document.createElement('h3');heading.textContent='Notes';region.append(heading);
  const note=document.createElement('p');note.className='detail-notes';note.textContent=entry.notes;
  region.append(note);
 }
 if(Array.isArray(entry.edit_history) && entry.edit_history.length){
  const history=document.createElement('details');
  const summary=document.createElement('summary');
  summary.textContent='Edit history · '+entry.edit_history.length+' previous version'+
   (entry.edit_history.length===1?'':'s');
  history.append(summary);
  // Read-only audit details: no restoration/write operation.
  entry.edit_history.slice(-10).reverse().forEach(change=>{
   const previous=change.previous;
   if(!previous || typeof previous!=='object')return;
   const paragraph=document.createElement('p');paragraph.className='footnote';
   paragraph.textContent=(change.edited_at||'Edited')+' — '+
    (previous.exercise||'Workout')+' · '+(previous.date||'')+
    ' · '+describe(previous)+(previous.notes?' · '+previous.notes:'');
   history.append(paragraph);
  });
  region.append(history);
 }
 const media=$('#detailImage');media.replaceChildren();media.hidden=true;
 if(typeof entry.photo==='string' && entry.photo!==''){
  const image=document.createElement('img');
  const src='?photo='+encodeURIComponent(id);
  image.src=src;image.alt='Private photo attached to '+entry.exercise+' workout';
  image.loading='lazy';
  const fallback=document.createElement('p');fallback.className='footnote';fallback.textContent=
   'Photo unavailable. Check that private storage is preserved on Hostinger.';
  fallback.hidden=true;
  image.addEventListener('error',()=>{image.hidden=true;fallback.hidden=false;});
  const link=document.createElement('a');link.href=src;link.target='_blank';
  link.rel='noopener noreferrer';link.textContent='Open full-size photo';
  const caption=document.createElement('p');caption.className='footnote';
  caption.textContent='Only your authenticated account can view this image.';
  media.append(image,link,fallback,caption);media.hidden=false;
 }
 $('#detailPanel').hidden=false;
 $('#detailPanel').scrollIntoView({behavior:'smooth',block:'start'});
}
function closeWorkoutDetails(){
 detailEntryId=null;$('#detailPanel').hidden=true;$('#detailImage').replaceChildren();
}

function renderHistory(){
 const history=historyItems(),visible=showAllHistory?history:history.slice(0,10);
 $('#recent').replaceChildren(...visible.map(e=>{
  const li=document.createElement('li'),name=document.createElement('strong');name.textContent=titleCase(e.exercise);
  const day=document.createElement('span');day.textContent=niceDay(e.date);
  const detail=document.createElement('small');detail.textContent=describe(e)+(e.tag?' · #'+e.tag:'')+(e.photo?' · Photo saved':'');
  const actions=document.createElement('div');actions.className='history-actions';
  const view=document.createElement('button');view.type='button';view.className='history-edit history-view';
  view.textContent='Details';view.disabled=!e.id;
  view.setAttribute('aria-label','View '+e.exercise+' details from '+niceDay(e.date));
  view.addEventListener('click',()=>openWorkoutDetails(e.id));
  const edit=document.createElement('button');edit.type='button';edit.className='history-edit';
  edit.textContent='Edit';edit.dataset.editId=e.id||'';
  edit.disabled=!e.id||!e._revision;edit.setAttribute('aria-label','Edit '+e.exercise+' from '+niceDay(e.date));
  actions.append(view,edit);li.append(name,day,detail,actions);return li;
 }));
 const toggle=$('#toggleHistory');toggle.hidden=history.length<=10;
 toggle.textContent=showAllHistory?'Show latest 10':'View all '+history.length+' entries';
 $('#empty').hidden=entries.length>0;
}
function refresh(){
 $('#sessions').textContent=new Set(entries.map(e=>e.session_id||e.date)).size;
 $('#count').textContent=entries.length;
 $('#minutes').textContent=entries.reduce((v,e)=>v+(Number(e.duration_minutes)||0),0).toFixed(0);
 $('#miles').textContent=entries.reduce((v,e)=>v+(Number(e.distance_miles)||0),0).toFixed(2);
 const selectedTag=$('#historyTag').value,tagSelect=$('#historyTag');
 const tags=[...new Set(entries.map(e=>e.tag||'').filter(Boolean))].sort();
 tagSelect.replaceChildren(...[{'value':'','label':'All tags'},...tags.map(value=>({value,label:'#'+value}))].map(item=>{
  const opt=document.createElement('option');opt.value=item.value;opt.textContent=item.label;return opt;
 }));
 if(tags.includes(selectedTag))tagSelect.value=selectedTag;
 renderHistory();
 const kinds=[...new Set(entries.map(e=>e.exercise))].sort(),select=$('#exerciseFilter'),old=select.value;
 select.replaceChildren(...kinds.map(k=>{const o=document.createElement('option');o.value=k;o.textContent=titleCase(k);return o;}));
 if(kinds.includes(old))select.value=old;
 $('#trendPanel').hidden=kinds.length===0;refreshChartMetrics();draw();
}
function editField(name,label,value,type='number'){
 const input=fieldInput(name,label,value,type);
 if(name==='exercise'||name==='tag')input.classList.add('full');
 return input;
}
function beginEdit(id){
 const entry=entries.find(e=>e.id===id);
 if(!entry||!entry._revision){message('Entry no longer available. Refresh the page.',true);return;}
 editingId=id;editingRevision=entry._revision;
 const grid=$('#editFields');grid.replaceChildren();
 const exerciseField=editField('exercise','Exercise',entry.exercise,'text');
 grid.append(exerciseField,editField('date','Workout date',entry.date,'date'));
 measures.forEach(([key,label])=>grid.append(editField(key,label,entry[key])));
 grid.append(editField('tag','Tracking tag (optional)',entry.tag||'','text'));
 const notes=document.createElement('label');notes.className='field full';
 const nt=document.createElement('span');nt.textContent='Notes';
 const textarea=document.createElement('textarea');textarea.rows=3;textarea.dataset.field='notes';textarea.maxLength=2000;textarea.value=entry.notes||'';
 notes.append(nt,textarea);grid.append(notes);
 const match=typeof entry.performed_at==='string'?
  /^\d{4}-\d{2}-\d{2}T(\d{2}:\d{2}):\d{2}([+-]\d{2}:\d{2})$/.exec(entry.performed_at):null;
 grid.append(editField('time_local','Local time (optional)',match?match[1]:'','time'));
 const timezone=document.createElement('input');timezone.type='hidden';timezone.dataset.field='utc_offset';
 timezone.value=match?match[2]:offset();grid.append(timezone);
 installExerciseVisibility(grid,exerciseField.children[1]);
 WorkoutAdvanced.attach(grid,entry,exerciseField.children[1]);
 $('#editPanel').hidden=false;$('#editStatus').textContent='Original entry and changes are kept in your private workout history.';
 $('#editPanel').scrollIntoView({behavior:'smooth',block:'nearest'});
}
function closeEdit(){
 editingId=null;editingRevision=null;$('#editPanel').hidden=true;$('#editFields').replaceChildren();
}
async function submitEdit(){
 if(!editingId||!editingRevision)return;
 const button=$('#confirmEdit'),row={};
 $('#editFields').querySelectorAll('[data-field]').forEach(input=>{row[input.dataset.field]=input.value;});
 if(!row.time_local){delete row.time_local;delete row.utc_offset;}
 try{Object.assign(row,WorkoutAdvanced.collect($('#editFields')));}catch(e){$('#editStatus').textContent=e.message;return;}
 if(!row.exercise.trim()||!row.date){$('#editStatus').textContent='Exercise and date are required.';return;}
 button.disabled=true;button.textContent='Saving changes…';
 try{
  const data=payload('edit_entry');data.set('entry_id',editingId);
  data.set('expected_revision',editingRevision);data.set('entry',JSON.stringify(row));
  await api(data);
  closeEdit();message('Updated only the selected workout. Previous values remain in its edit history.');
  await load();
 }catch(e){
  $('#editStatus').textContent=e.message;
  if(e.message.toLowerCase().includes('changed elsewhere'))message('This entry was changed in another session. Refresh before editing.',true);
 }finally{button.disabled=false;button.textContent='Save changes to this entry';}
}
function draw(){
 const c=$('#chart'),ctx=c.getContext('2d');ctx.clearRect(0,0,c.width,c.height);
 const metric=$('#metric').value,exercise=$('#exerciseFilter').value;
 const tag=$('#historyTag').value;
 const points=entries.filter(e=>e.exercise===exercise&&(!tag||(e.tag||'')===tag))
  .map(e=>({...e,_selectedValue:WorkoutAdvanced.metricValue(e,metric)}))
  .filter(e=>e._selectedValue!=null&&Number.isFinite(Number(e._selectedValue)))
  .sort((a,b)=>(a.performed_at||a.date).localeCompare(b.performed_at||b.date));
 const left=54,right=c.width-23,top=22,bottom=c.height-46;
 ctx.font='14px system-ui';ctx.strokeStyle='#36546d';ctx.fillStyle='#aebfd0';ctx.lineWidth=1;
 ctx.beginPath();ctx.moveTo(left,top);ctx.lineTo(left,bottom);ctx.lineTo(right,bottom);ctx.stroke();
 if(!points.length){$('#trend').textContent='Choose a logged measurement to see its trend.';return;}
 const vals=points.map(e=>Number(e._selectedValue));let high=Math.max(...vals),low=Math.min(0,...vals);if(high===low)high=low+1;
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
 $('#backupCsrf').value=data.csrf;
 $('#backupBtn').disabled=!data.backup_available;
 $('#backupStatus').textContent=data.backup_available
  ?'This ZIP contains your original workout records, edit history and attached photos. Store it privately.'
  :'ZIP exports are unavailable on this server; PHP ZipArchive must be enabled.';
 refresh();
 if(detailEntryId && !$('#detailPanel').hidden)openWorkoutDetails(detailEntryId);
 }catch(e){message(e.message,true);}}
$('#dateLabel').textContent=new Date().toLocaleDateString(undefined,{weekday:'long',month:'long',day:'numeric'});
$('#parseBtn').addEventListener('click',review);
$('#aiParseBtn').addEventListener('click',retryWithAi);
$('#note').addEventListener('input',()=>{$('#review').hidden=true;});
$('#saveBtn').addEventListener('click',()=>{try{save(collect(),$('#photo').files[0]||null);}catch(e){message(e.message,true);}});
$('#exerciseFilter').addEventListener('change',()=>{refreshChartMetrics();draw();});$('#metric').addEventListener('change',draw);
$('#historyTag').addEventListener('change',()=>{showAllHistory=false;renderHistory();draw();});
$('#toggleHistory').addEventListener('click',()=>{showAllHistory=!showAllHistory;renderHistory();});
$('#recent').addEventListener('click',e=>{const button=e.target.closest('[data-edit-id]');if(button)beginEdit(button.dataset.editId);});
$('#editForm').addEventListener('submit',e=>{e.preventDefault();submitEdit();});
$('#cancelEdit').addEventListener('click',closeEdit);
$('#closeDetail').addEventListener('click',closeWorkoutDetails);
$('#editFromDetail').addEventListener('click',()=>{if(detailEntryId)beginEdit(detailEntryId);});
$('#backupForm').addEventListener('submit',()=>{$('#backupStatus').textContent='Preparing your private backup download…';});
$('#manualForm').addEventListener('submit',async e=>{
 e.preventDefault();
 const fd=new FormData(e.currentTarget),row=Object.fromEntries(fd.entries());
 const file=fd.get('photo'),photo=file instanceof File&&file.size?file:null;
 delete row.photo;row.date=row.date||localDate();$('#sessionTime').value=localTime();
 try{Object.assign(row,WorkoutAdvanced.collect($('#manualForm .field-grid')));}
 catch(err){message(err.message,true);return;}
 const saved=await save([row],photo);
 if(saved){e.currentTarget.reset();manualAdvanced?.reset();$('#manualDate').value=localDate();}
});
$('#manualDate').value=localDate();
installExerciseVisibility($('#manualForm .field-grid'),$('#manualForm [name="exercise"]'));
manualAdvanced=WorkoutAdvanced.attach($('#manualForm .field-grid'),{},$('#manualForm [name="exercise"]'));
load();
