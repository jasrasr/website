/*
File Name: duration-trends.js
Author: Jason Lamb (with help from ChatGPT)
Description: Exact workout mm:ss presentation, per-workout trends and running totals.
Revision: 1.0.0
Created Date: 2026-10-31
Modified Date: 2026-10-31
Changelog: 1.0.0 - Combine minutes and seconds, chart additive totals without summing rate-based metrics.
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
 return {totalMinutes,formatDuration,measurement,chartSeries,isAdditive:key=>additive.has(key)};
})();
