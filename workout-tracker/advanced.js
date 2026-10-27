/* File Name: advanced.js
Author: Jason Lamb (with help from ChatGPT)
Description: Mobile-friendly per-set weight/repetition logging and configurable numeric measurements.
Revision: 1.0.0
Created Date: 2026-10-27
Modified Date: 2026-10-27
Changelog: 1.0.0 - Add optional ordered set rows, custom measurement rows and strength summaries.
*/
'use strict';
const WorkoutAdvanced = (() => {
 const strength = /^(bench press|deadlift|weight training|weight lifting|squats|lunges|push-ups|pushups|sit-ups|situps|crunches|jumping jacks)$/i;
 const el = (tag, cls = '', label = '') => {
  const node = document.createElement(tag);
  if (cls) node.className = cls;
  if (label) node.textContent = label;
  return node;
 };
 const button = (label, cls) => {
  const b = el('button', cls || 'secondary-action', label);
  b.type = 'button';
  return b;
 };
 const control = (label, name, value, options={}) => {
  const wrapper = el('label', 'field');
  const caption = el('span','',label);
  const input = el('input');
  input.type = options.type || 'number';
  input.name = name;
  input.dataset.advancedField = name;
  input.value = value ?? '';
  if(input.type==='number'){
   input.step=options.step || 'any';
   if(options.min!==undefined)input.min=String(options.min);
   if(options.max!==undefined)input.max=String(options.max);
   input.inputMode = 'decimal';
  } else {
   input.maxLength=options.maxLength || 48;
  }
  input.autocomplete='off';
  wrapper.append(caption,input);
  return wrapper;
 };
 const fieldsIn = (row) => {
  const result={};
  row.querySelectorAll('[data-advanced-field]').forEach(input=>{result[input.dataset.advancedField]=input.value;});
  return result;
 };
 const stats = record => {
  if (!Array.isArray(record?.sets_detail) || record.sets_detail.length===0) return null;
  let totalReps=0, totalVolume=0, maxWeight=null;
  record.sets_detail.forEach(set=>{
   const reps=Number(set.reps);
   if(!Number.isInteger(reps)||reps<1)return;
   totalReps+=reps;
   const weight=set.weight_lb;
   if(weight===null||weight===undefined||weight==='')return;
   const lbs=Number(weight);
   if(!Number.isFinite(lbs)||lbs<0)return;
   totalVolume+=lbs*reps;
   maxWeight=maxWeight===null?lbs:Math.max(maxWeight,lbs);
  });
  return {set_count:record.sets_detail.length,total_reps:totalReps,
   max_weight_lb:maxWeight,total_volume_lb:Math.round(totalVolume*100)/100};
 };
 const customKey = (name,unit) => 'custom:'+String(name).trim().toLowerCase()+'|'+String(unit).trim().toLowerCase();
 const metricValue = (record,key) => {
  const summary=stats(record);
  if(['total_reps','set_count','max_weight_lb','total_volume_lb'].includes(key))
   return summary?.[key]??null;
  if(key.startsWith('custom:')){
   const rows=Array.isArray(record.custom_metrics)?record.custom_metrics:[];
   const metric=rows.find(row=>customKey(row.name,row.unit)===key);
   return metric?Number(metric.value):null;
  }
  return record[key]??null;
 };
 function attach(grid, record, exerciseInput) {
  const existingSets=Array.isArray(record?.sets_detail)?record.sets_detail:[];
  const existingMetrics=Array.isArray(record?.custom_metrics)?record.custom_metrics:[];
  const setsPanel=el('section','advanced-section advanced-sets');
  setsPanel.dataset.hadSets=Array.isArray(record?.sets_detail)?'yes':'no';
  const setHeading=el('h3','','Individual sets');
  const setInfo=el('p','footnote','Keep each weight and rep count separately. One exercise per workout.');
  const setRows=el('div','advanced-rows set-rows');
  const addSet=button('+ Add set','secondary-action add-item');
  const setSummary=el('p','footnote set-summary','No individual sets recorded.');
  setsPanel.append(setHeading,setInfo,setRows,addSet,setSummary);
  function refreshSetNumbers(){
   const rows=Array.from(setRows.querySelectorAll('.set-row'));
   rows.forEach((row,index)=>{row.querySelector('.set-number').textContent='Set '+(index+1);});
   addSet.disabled=rows.length>=40;
   const draft=[];
   rows.forEach(row=>{
    const fields=fieldsIn(row);
    if(fields.reps!=='' && Number(fields.reps)>0){
     draft.push({reps:Number(fields.reps),
       weight_lb:fields.weight_lb===''?null:Number(fields.weight_lb)});
    }
   });
   const totals=stats({sets_detail:draft});
   setSummary.textContent=totals?
    totals.set_count+' sets · '+totals.total_reps+' total reps'+
     (totals.max_weight_lb!==null?' · '+totals.max_weight_lb+' lb heaviest · '+totals.total_volume_lb+' lb total volume':''):
    'Enter weight and reps for each set. Weight may be blank for bodyweight.';
  }
  function appendSet(item={}){
   if(setRows.children.length>=40)return;
   const row=el('div','advanced-row set-row');
   const number=el('span','set-number','Set '+(setRows.children.length+1));
   const weight=control('Weight (lb)','weight_lb',item.weight_lb,{min:0,max:10000});
   const reps=control('Reps','reps',item.reps,{min:1,max:1000,step:1});
   const remove=button('Remove','remove-item');
   remove.setAttribute('aria-label','Remove this set');
   remove.addEventListener('click',()=>{row.remove();refreshSetNumbers();});
   row.append(number,weight,reps,remove);
   row.addEventListener('input',refreshSetNumbers);
   setRows.append(row);
   refreshSetNumbers();
  }
  existingSets.forEach(appendSet);
  addSet.addEventListener('click',()=>{appendSet();setRows.lastElementChild?.querySelector('[name="weight_lb"]')?.focus();});
  function updateSetVisibility(){
   const name=String(exerciseInput?.value||'').trim();
   // Leave previously recorded sets visible even when changing an exercise name.
   setsPanel.hidden=!(strength.test(name)||setRows.children.length>0);
  }
  exerciseInput?.addEventListener('input',updateSetVisibility);
  updateSetVisibility();

  const metricsPanel=el('details','advanced-section custom-panel');
  metricsPanel.dataset.hadMetrics=Array.isArray(record?.custom_metrics)?'yes':'no';
  const heading=el('summary','','Additional measurements'+(existingMetrics.length?' ('+existingMetrics.length+')':''));
  const info=el('p','footnote','Track any numeric measurement, such as elevation gain, resistance level or heart rate. Use the same name and unit each time for trends.');
  const metricRows=el('div','advanced-rows custom-rows');
  const addMetric=button('+ Add measurement','secondary-action add-item');
  metricsPanel.append(heading,info,metricRows,addMetric);
  if(existingMetrics.length) metricsPanel.open=true;
  function refreshMetricNumbers(){addMetric.disabled=metricRows.children.length>=12;}
  function appendMetric(item={}){
   if(metricRows.children.length>=12)return;
   const row=el('div','advanced-row custom-row');
   const name=control('Measurement','name',item.name,{type:'text',maxLength:48});
   const unit=control('Unit','unit',item.unit,{type:'text',maxLength:16});
   const value=control('Value','value',item.value);
   const remove=button('Remove','remove-item');
   remove.setAttribute('aria-label','Remove custom measurement');
   remove.addEventListener('click',()=>{row.remove();refreshMetricNumbers();});
   row.append(name,unit,value,remove);metricRows.append(row);refreshMetricNumbers();
  }
  existingMetrics.forEach(appendMetric);
  addMetric.addEventListener('click',()=>{metricsPanel.open=true;appendMetric();metricRows.lastElementChild?.querySelector('[name="name"]')?.focus();});
  // Keep advanced editors before the metadata/notes, not at the bottom.
  const before=Array.from(grid.children).find(child=>{
   if(String(child.tagName).toLowerCase()!=='label')return false;
   const field=Array.from(child.children).find(el=>['notes','tag','time_local','photo'].includes(
    el.dataset?.field||el.name
   ));
   return !!field;
  });
  if(before){
   grid.insertBefore(setsPanel,before);
   grid.insertBefore(metricsPanel,before);
  }else{
   grid.append(setsPanel,metricsPanel);
  }
  return {setsPanel,metricsPanel};
 }
 function collect(container) {
  const result={};
  const setsPanel=container.querySelector('.advanced-sets');
  if(setsPanel){
   const rows=Array.from(setsPanel.querySelectorAll('.set-row'));
   if(rows.length || setsPanel.dataset.hadSets==='yes'){
    result.sets_detail=rows.map((row,index)=>{
     const item=fieldsIn(row);
     const reps=Number(item.reps);
     if(item.reps===''||!Number.isInteger(reps)||reps<1||reps>1000)
      throw Error('Set '+(index+1)+': enter 1 to 1000 repetitions.');
     const weight=item.weight_lb===''?null:Number(item.weight_lb);
     if(weight!==null&&(!Number.isFinite(weight)||weight<0||weight>10000))
      throw Error('Set '+(index+1)+': check weight (lb).');
     return {reps,weight_lb:weight,note:''};
    });
   }
  }
  const metricsPanel=container.querySelector('.custom-panel');
  if(metricsPanel){
   const rows=Array.from(metricsPanel.querySelectorAll('.custom-row'));
   if(rows.length||metricsPanel.dataset.hadMetrics==='yes'){
    result.custom_metrics=rows.map((row,index)=>{
     const item=fieldsIn(row),value=Number(item.value);
     if(!item.name.trim()||item.value===''||!Number.isFinite(value))
      throw Error('Measurement '+(index+1)+': enter its name and numeric value.');
     return {name:item.name.trim(),unit:item.unit.trim(),value};
    });
   }
  }
  return result;
 }
 return {attach,collect,stats,metricValue,customKey};
})();
