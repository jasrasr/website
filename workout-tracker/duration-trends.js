/*
File Name: duration-trends.js
Author: Jason Lamb (with help from ChatGPT)
Description: Exact workout mm:ss presentation, per-workout trends and running totals.
Revision: 1.1.0
Created Date: 2026-10-31
Modified Date: 2026-10-31
Changelog: 1.1.0 - Select readable distinct date ticks without collisions or altering workout values. 1.0.0 - Combine minutes and seconds, chart additive totals without summing rate-based metrics.
*/
'use strict';
const WorkoutTrends = (() => {
 const additive = new Set([
  'distance_miles', 'duration_minutes', 'calories_kcal',
  'total_reps', 'set_count', 'total_volume_lb'
 ]);
 function hasNumber(value){
  return value !== null && value !== undefined && value !== '' &&
   Number.isFinite(Number(value));
 }
 function totalMinutes(entry){
  const minutes=entry?.duration_minutes,seconds=entry?.duration_seconds;
  if(!hasNumber(minutes)&&!hasNumber(seconds))return null;
  const base=hasNumber(minutes)?Number(minutes):0;
  const extra=hasNumber(seconds)?Number(seconds)/60:0;
  return base+extra;
 }
 function formatDuration(entry){
  if(!hasNumber(entry?.duration_minutes) && !hasNumber(entry?.duration_seconds))return null;
  const seconds=entry?.duration_seconds;
  if(hasNumber(seconds)){
   const mins=hasNumber(entry.duration_minutes)?Number(entry.duration_minutes):0;
   if(Number.isInteger(mins)&&Number.isInteger(Number(seconds))) {
    return mins+':'+String(Number(seconds)).padStart(2,'0')+' min:sec';
   }
  }
  return String(Number(totalMinutes(entry).toFixed(3)))+' min';
 }
 function measurement(entry,key,metricValue){
  if(key==='duration_minutes')return totalMinutes(entry);
  return metricValue(entry,key);
 }
 function chartSeries(entries,exercise,key,tag,metricValue){
  const scoped=(Array.isArray(entries)?entries:[]).filter(entry=>
    entry?.exercise===exercise && (!tag||(entry.tag||'')===tag))
   .map((entry,index)=>({
     ...entry,
     _order:index,
     _selectedValue:measurement(entry,key,metricValue)
   }))
   .filter(entry=>hasNumber(entry._selectedValue))
   .sort((a,b)=>{
     const comparison=String(a.performed_at||a.date||'').localeCompare(String(b.performed_at||b.date||''));
     return comparison||a._order-b._order;
   });
  let sum=0;
  const points=scoped.map(entry=>{
   const value=Number(entry._selectedValue);
   sum+=value;
   return {date:entry.date,individual:value,running:sum};
  });
  return {points,additive:additive.has(key),total:points.length?sum:null};
 }

 /**
  * Short, timezone-safe tick labels: every visible mark denotes an actual
  * logged date rather than a fabricated evenly spaced calendar day.
  * The full first/last dates remain available in the caption.
  */
 function shortChartDate(iso){
  if(typeof iso!=='string'||!/^20\d\d-\d\d-\d\d$/.test(iso))return '';
  const date=new Date(iso+'T12:00:00Z');
  if(!Number.isFinite(date.getTime()))return '';
  return date.toLocaleDateString('en-US',{month:'short',day:'numeric',timeZone:'UTC'});
 }
 /**
  * Show as many actual distinct workout dates as fit without text collisions.
  * First and last dates are reserved; interim ticks are added only when their
  * rendered label does not overlap any other selected date label.
  * Positions are calculated by the chart and remain aligned to the dots.
  */
 function chartDateTicks(points,positions,measureWidth){
  if(!Array.isArray(points)||!Array.isArray(positions)||points.length!==positions.length)
   return [];
  const candidates=[];
  const dates=new Set();
  points.forEach((point,index)=>{
   const date=point?.date,label=shortChartDate(date);
   if(!label||dates.has(date)||!Number.isFinite(positions[index]))return;
   dates.add(date);
   const width=Math.max(0,Number(measureWidth(label))||0);
   candidates.push({index,date,label,x:positions[index],start:positions[index]-width/2,end:positions[index]+width/2});
  });
  if(!candidates.length)return [];
  const selected=[candidates[0]];
  const last=candidates.at(-1);
  const gap=9;
  const fits=candidate=>selected.every(current=>
   candidate.end+gap<=current.start||current.end+gap<=candidate.start);
  if(last!==selected[0]&&fits(last))selected.push(last);
  for(const candidate of candidates.slice(1,-1)){
   if(fits(candidate))selected.push(candidate);
  }
  return selected.sort((a,b)=>a.index-b.index);
 }
 return {totalMinutes,formatDuration,measurement,chartSeries,shortChartDate,chartDateTicks,isAdditive:key=>additive.has(key)};
})();
