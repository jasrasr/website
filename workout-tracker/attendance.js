/*
File Name: attendance.js
Author: Jason Lamb (with help from ChatGPT)
Description: Read-only workout attendance, weekday metrics and streaks by local workout date.
Revision: 1.0.0
Created Date: 2026-10-27
Modified Date: 2026-10-27
Changelog: 1.0.0 - Count unique workout dates with rolling seven-day, since-first, weekday and streak tracking.
*/
'use strict';
const WorkoutAttendance = (() => {
 const DAY_MS = 86400000;
 // Date-only arithmetic in UTC avoids daylight-saving changes and browser timezone shifts.
 function dayNumber(value) {
  if(typeof value!=='string'||!/^20\d\d-\d\d-\d\d$/.test(value))return null;
  const year=Number(value.slice(0,4)),month=Number(value.slice(5,7)),day=Number(value.slice(8,10));
  const millis=Date.UTC(year,month-1,day);
  if(!Number.isFinite(millis))return null;
  const parsed=new Date(millis);
  if(parsed.getUTCFullYear()!==year||parsed.getUTCMonth()!==month-1||parsed.getUTCDate()!==day)return null;
  return Math.floor(millis/DAY_MS);
 }
 function isoDate(day) { return new Date(day*DAY_MS).toISOString().slice(0,10); }
 function isWeekday(day) { const weekday=new Date(day*DAY_MS).getUTCDay();return weekday!==0&&weekday!==6; }
 function previousEligible(day,onlyWeekdays) {
  let previous=day-1;
  if(onlyWeekdays)while(!isWeekday(previous))previous--;
  return previous;
 }
 function streaks(days,first,today,onlyWeekdays) {
  // The user might not have worked out today yet. Yesterday (or Friday for a
  // weekend/early Monday) still counts as a live streak until the day ends.
  let end=onlyWeekdays && !isWeekday(today)?previousEligible(today+1,true):today;
  if(!days.has(end))end=previousEligible(end,onlyWeekdays);
  let current=0;
  for(let day=end;day>=first && days.has(day);day=previousEligible(day,onlyWeekdays))current++;
  let longest=0,run=0;
  for(let day=first;day<=today;day++){
   if(onlyWeekdays&&!isWeekday(day))continue;
   if(days.has(day)){run++;longest=Math.max(longest,run);}
   else run=0;
  }
  return {current,longest};
 }
 function rate(attended,eligible) {
  return eligible===0?0:Math.round((attended/eligible)*1000)/10;
 }
 function summary(allDates,first,last,onlyWeekdays) {
  let attended=0,eligible=0;
  for(let day=first;day<=last;day++){
   if(onlyWeekdays&&!isWeekday(day))continue;
   eligible++;
   if(allDates.has(day))attended++;
  }
  return {attended,eligible,percentage:rate(attended,eligible)};
 }
 function calculate(records,todayDate){
  const today=dayNumber(todayDate);
  if(today===null)throw Error('Attendance needs a valid local date.');
  const unique=new Set();
  for(const record of Array.isArray(records)?records:[]){
   const day=dayNumber(record?.date);
   if(day!==null&&day<=today)unique.add(day);
  }
  if(unique.size===0)return {
   hasData:false,firstDate:null,throughDate:todayDate,
   calendar:summary(unique,today,today,false),
   weekdays:summary(unique,today,today,true),
   rollingCalendar:summary(unique,today,today,false),
   rollingWeekdays:summary(unique,today,today,true),
   calendarStreak:{current:0,longest:0},
   weekdayStreak:{current:0,longest:0},lastSeven:[]
  };
  const first=Math.min(...unique);
  const rollingStart=Math.max(first,today-6);
  const lastSeven=[];
  for(let day=rollingStart;day<=today;day++)lastSeven.push({
   date:isoDate(day),attended:unique.has(day),weekday:isWeekday(day),today:day===today
  });
  return {
   hasData:true,firstDate:isoDate(first),throughDate:todayDate,
   calendar:summary(unique,first,today,false),
   weekdays:summary(unique,first,today,true),
   rollingCalendar:summary(unique,rollingStart,today,false),
   rollingWeekdays:summary(unique,rollingStart,today,true),
   calendarStreak:streaks(unique,first,today,false),
   weekdayStreak:streaks(unique,first,today,true),
   lastSeven
  };
 }
 return {calculate,dayNumber,isWeekday};
})();
