/*
File Name: attendance-ui.test.cjs
Author: Jason Lamb (with help from ChatGPT)
Description: DOM regression for mobile workout attendance dashboard.
Revision: 1.0.0
Created Date: 2026-10-27
Modified Date: 2026-10-27
Changelog: 1.0.0 - Check rendered percentages and streaks using historical dates only.
*/
'use strict';
const assert=require('node:assert/strict');
const fs=require('node:fs');
const path=require('node:path');
const vm=require('node:vm');
const root=path.join(__dirname,'..');
const attendance=fs.readFileSync(path.join(root,'attendance.js'),'utf8');
const app=fs.readFileSync(path.join(root,'app.js'),'utf8');
const index=fs.readFileSync(path.join(root,'index.php'),'utf8');
const css=fs.readFileSync(path.join(root,'app.css'),'utf8');
const begin=app.indexOf('function refreshAttendance(){');
const end=app.indexOf('function refresh(){',begin);
assert.ok(begin>=0&&end>begin,'attendance renderer must exist');
const allIds=[
 'attendancePanel','attendanceStart','attendance7Percentage','attendance7Count',
 'attendanceWeek7Percentage','attendanceWeek7Count','attendanceOverallPercentage',
 'attendanceOverallCount','attendanceOverallWeekPercentage','attendanceOverallWeekCount',
 'attendanceCurrentStreak','attendanceLongestStreak','attendanceCurrentWeekStreak',
 'attendanceLongestWeekStreak','attendanceStrip'
];
function element(){
 const classes=new Set();
 return {
  hidden:true,textContent:'',children:[],attributes:{},
  classList:{add:token=>{if(!token)throw Error('Empty CSS class');classes.add(token);},
   contains:token=>classes.has(token)},
  append(...children){this.children.push(...children);},
  replaceChildren(...children){this.children=children;},
  setAttribute(k,v){this.attributes[k]=v;}
 };
}
const ids=new Map(allIds.map(id=>[id,element()]));
const records=[
 {id:'a',date:'2026-10-21',exercise:'treadmill'},
 {id:'b',date:'2026-10-21',exercise:'push-ups'},
 {id:'c',date:'2026-10-22',exercise:'treadmill'},
 {id:'d',date:'2026-10-27',exercise:'treadmill'}
];
const snapshot=JSON.stringify(records);
const ctx={
 entries:records,
 document:{createElement:()=>element()},
 $:selector=>{
  const obj=ids.get(selector.slice(1));
  assert.ok(obj,'Unknown DOM selector '+selector);
  return obj;
 },
 localDate:()=> '2026-10-27',
 niceDay:value=>value,
 Date,
};
ctx.WorkoutAttendance=vm.runInNewContext(attendance+'; WorkoutAttendance;',ctx);
vm.runInNewContext(app.slice(begin,end),ctx,{filename:'app.js'});
ctx.refreshAttendance();
const value=id=>ids.get(id).textContent;
assert.equal(ids.get('attendancePanel').hidden,false,'dashboard becomes visible');
assert.equal(value('attendanceStart'),'Since 2026-10-21');
assert.equal(value('attendance7Percentage'),'42.9%');
assert.equal(value('attendance7Count'),'3 of 7 days');
assert.equal(value('attendanceWeek7Percentage'),'60.0%');
assert.equal(value('attendanceWeek7Count'),'3 of 5 weekdays');
assert.equal(value('attendanceOverallPercentage'),'42.9%');
assert.equal(value('attendanceOverallWeekPercentage'),'60.0%');
assert.equal(value('attendanceCurrentStreak'),'1 day');
assert.equal(value('attendanceLongestStreak'),'2 days');
assert.equal(value('attendanceCurrentWeekStreak'),'1 day');
assert.equal(value('attendanceLongestWeekStreak'),'2 days');
const strip=ids.get('attendanceStrip').children;
assert.equal(strip.length,7,'all seven days shown');
assert.equal(strip.filter(d=>d.classList.contains('attended')).length,3);
assert.equal(strip.filter(d=>d.classList.contains('weekend')).length,2);
assert.equal(strip[6].attributes['aria-label'].includes('workout recorded'),true);
assert.equal(JSON.stringify(records),snapshot,'rendering must never mutate original data');

ctx.entries=[];
ctx.refreshAttendance();
assert.equal(ids.get('attendancePanel').hidden,true,'hide attendance until first workout');

for(const id of allIds){
 assert.ok(index.includes('id="'+id+'"'),'missing dashboard element: '+id);
}
assert.ok(index.includes('attendance.js?v='),'attendance calculations loaded by page');
assert.ok(index.indexOf('attendance.js?v=')<index.indexOf('app.js?v='),
 'attendance module must load before main application');
assert.match(css,/\.attendance-grid\{display:grid;grid-template-columns:repeat\(2,minmax\(0,1fr\)\)/,
 'cards use compact two-column mobile layout');
assert.match(css,/@media\(max-width:340px\)/,'very narrow phones get single-column fallback');
assert.match(css,/\.attendance-strip\{display:grid;grid-template-columns:repeat\(7,minmax\(0,1fr\)\)/,
 'seven-day strip has seven responsive columns');
assert.ok(!app.slice(begin,end).includes('innerHTML'),'safe text only');
console.log('Passed: attendance dashboard labels, attendance rates, streaks, mobile layout, and read-only behavior.');
