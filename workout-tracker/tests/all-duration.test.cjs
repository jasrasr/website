/*
File Name: all-duration.test.cjs
Author: Jason Lamb (with help from ChatGPT)
Description: Independent minutes per workout and overall-duration graph regression.
Revision: 1.0.0
Created Date: 2026-11-17
Modified Date: 2026-11-17
Changelog: 1.0.0 - Test all exercise types, same-day records, precise MM:SS, missing durations and mobile canvas.
*/
'use strict';
const assert=require('node:assert/strict');
const fs=require('node:fs'),path=require('node:path'),vm=require('node:vm');
const root=path.join(__dirname,'..');
const helperSrc=fs.readFileSync(path.join(root,'duration-trends.js'),'utf8');
const app=fs.readFileSync(path.join(root,'app.js'),'utf8');
const html=fs.readFileSync(path.join(root,'index.php'),'utf8');
const css=fs.readFileSync(path.join(root,'app.css'),'utf8');
const tracker=vm.runInNewContext(helperSrc+';WorkoutTrends;',{}, {filename:'duration-trends.js'});
const entries=[
 {id:'f',date:'2026-11-06',performed_at:'2026-11-06T09:15:00',
  exercise:'bench press',duration_minutes:25,tag:'Strength'},
 {id:'a',date:'2026-10-21',exercise:'treadmill',duration_minutes:22,tag:'Cardio'},
 {id:'g',date:'2026-11-08',exercise:'cycling',duration_minutes:12.5},
 {id:'e',date:'2026-11-06',performed_at:'2026-11-06T08:00:00',
  exercise:'cycling',duration_minutes:20,duration_seconds:45,tag:'Cardio'},
 {id:'b',date:'2026-10-22',exercise:'treadmill',duration_minutes:37,tag:'Cardio'},
 {id:'h',date:'2026-11-09',exercise:'yoga'}, // No time logged: do not invent it.
 {id:'d',date:'2026-10-31',exercise:'treadmill',duration_minutes:33,duration_seconds:18},
 {id:'i',date:'2026-11-14',exercise:'cycling',duration_minutes:35},
 {id:'j',date:'2026-11-14',exercise:'push-ups',reps:40},
 {id:'k',date:'invalid',exercise:'running',duration_minutes:999},
 {id:'l',date:'2026-11-15',exercise:'running',duration_minutes:-12}
];
const snapshot=JSON.stringify(entries);
const calculated=tracker.allDurationSeries(entries);
const ids=Array.from(calculated.points,p=>p.exercise);
assert.deepEqual(ids,['treadmill','treadmill','treadmill','cycling','bench press','cycling','cycling'],
 'global duration chart includes every exercise type in chronological order');
assert.equal(calculated.points.length,7,'one point per timed record, not one point per date');
assert.deepEqual(Array.from(calculated.points,p=>p.date),[
 '2026-10-21','2026-10-22','2026-10-31','2026-11-06','2026-11-06','2026-11-08','2026-11-14'
]);
assert.deepEqual(Array.from(calculated.points,p=>Number(p.individual.toFixed(2))),
 [22,37,33.3,20.75,25,12.5,35]);
assert.deepEqual(Array.from(calculated.points,p=>Number(p.running.toFixed(2))),
 [22,59,92.3,113.05,138.05,150.55,185.55]);
assert.equal(Number(calculated.total.toFixed(2)),185.55);
assert.equal(calculated.additive,true);
assert.equal(JSON.stringify(entries),snapshot,'all-workouts time calculation leaves JSON entries unchanged');
assert.equal(tracker.allDurationSeries([]).total,null);
assert.equal(tracker.allDurationSeries([{date:'2026-11-01',exercise:'cycling'}]).points.length,0);
const zero=tracker.allDurationSeries([{date:'2026-11-01',duration_minutes:0}]);
assert.equal(zero.points.length,1,'explicitly recorded zero time is distinct from missing duration');
assert.equal(zero.total,0);
const oldFraction=tracker.allDurationSeries([{date:'2026-11-01',duration_minutes:33.3}]);
assert.equal(oldFraction.total,33.3,'older fractional-minute data remains accurate');

const chartStart=app.indexOf('function draw(){');
const chartEnd=app.indexOf('async function load(){',chartStart);
assert.ok(chartStart>=0&&chartEnd>chartStart);
const strokes=[],dots=[],labels=[];
const context={
 setTransform(){},clearRect(){},beginPath(){},moveTo(){},lineTo(){},
 stroke(){strokes.push(this.strokeStyle);},arc(x,y){dots.push({x,y});},fill(){},
 fillText(text){labels.push(String(text));},
 measureText:s=>({width:String(s).length*7}),
 font:'',strokeStyle:'',fillStyle:'',lineWidth:1
};
const canvas={width:650,height:280,clientWidth:320,style:{},
 getBoundingClientRect:()=>({width:320}),getContext:()=>context};
const nodeMap=new Map([
 ['#allDurationChart',canvas],
 ['#allDurationMode',{value:'both',disabled:false}],
 ['#allDurationModeLabel',{hidden:false}],
 ['#allDurationScope',{textContent:''}],
 ['#allDurationLegend',{children:[],replaceChildren(...items){this.children=items;}}],
 ['#allDurationDateRange',{textContent:''}],
 ['#allDurationSummary',{textContent:''}],
 ['#exerciseFilter',{value:'cycling'}],
 ['#metric',{value:'distance_miles'}],
 ['#historyTag',{value:'Cardio'}]
]);
const runtime={
 entries,WorkoutTrends:tracker,WorkoutAdvanced:{metricValue:(r,k)=>r[k]},
 window:{devicePixelRatio:2},
 $:sel=>{
  const node=nodeMap.get(sel);
  assert.ok(node,'expected global chart selector '+sel);
  return node;
 },
 document:{
  createElement:tag=>({tagName:tag,style:{},children:[],attributes:{},
   append(...children){this.children.push(...children);},
   setAttribute(k,v){this.attributes[k]=v;}
  }),
  createTextNode:text=>({textContent:text})
 },
 niceDay:date=>new Date(date+'T12:00:00').toLocaleDateString('en-US',
  {month:'short',day:'numeric',year:'numeric'})
};
vm.runInNewContext(app.slice(chartStart,chartEnd),runtime);
runtime.drawAllDuration();
assert.equal(canvas.width,640,'independent graph uses 320px mobile width at 2x Retina');
assert.equal(dots.length,14,'each of seven workouts has a point on both lines');
assert.equal(nodeMap.get('#allDurationLegend').children.length,2);
assert.match(nodeMap.get('#allDurationSummary').textContent,/7 timed workouts/);
assert.match(nodeMap.get('#allDurationSummary').textContent,/Overall 185\.55 min/);
assert.match(nodeMap.get('#allDurationSummary').textContent,/3\.09 hours/);
assert.match(nodeMap.get('#allDurationScope').textContent,/All logged exercise types and tags/);
assert.match(nodeMap.get('#allDurationDateRange').textContent,/Oct 21, 2026.+Nov 14, 2026/);
assert.ok(strokes.includes('#59dbc3')&&strokes.includes('#e2b56e'),'both data series drawn');
assert.equal(JSON.stringify(entries),snapshot,'rendering is also read-only');
nodeMap.get('#allDurationMode').value='running';
dots.length=0;runtime.drawAllDuration();
assert.equal(dots.length,7,'running-only mode renders just cumulative points');
nodeMap.get('#allDurationMode').value='individual';
dots.length=0;runtime.drawAllDuration();
assert.equal(dots.length,7,'individual-only mode renders just per-workout points');
assert.match(html,/id="allDurationPanel"/);
assert.match(html,/id="allDurationChart"/);
assert.match(html,/id="allDurationMode"/);
assert.match(html,/id="allDurationSummary"/);
assert.match(html,/app\.js\?v=2\.0\.0/,'cache bust updated for mobile browsers');
assert.match(css,/#chart,#allDurationChart\{display:block;width:100%/,'both charts fill their phone containers');
assert.match(app,/\$\('#allDurationPanel'\)\.hidden=timed\.points\.length===0/,'hide global chart when no durations are logged');
assert.match(app,/\$\('#allDurationMode'\)\.addEventListener\('change',drawAllDuration\)/,'own graph mode listener');
console.log('Passed: all exercise types, per-workout durations, 185.55 cumulative minutes, MM:SS, mobile graph and zero writes.');
