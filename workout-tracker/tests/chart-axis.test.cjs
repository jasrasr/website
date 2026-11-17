/*
File Name: chart-axis.test.cjs
Author: Jason Lamb (with help from ChatGPT)
Description: Regression test for legible mobile dates, safe axis spacing, and accurate graph values.
Revision: 1.0.1
Created Date: 2026-11-14
Modified Date: 2026-11-14
Changelog: 1.0.1 - Confirm v2.0.0 asset refresh while preserving all mobile chart assertions. 1.0.0 - Verify all four cycling dates, Retina layout, clear Y axis, and dense-tick collision handling.
*/
'use strict';
const assert=require('node:assert/strict');
const fs=require('node:fs');
const path=require('node:path');
const vm=require('node:vm');
const root=path.join(__dirname,'..');
const helperSource=fs.readFileSync(path.join(root,'duration-trends.js'),'utf8');
const appSource=fs.readFileSync(path.join(root,'app.js'),'utf8');
const html=fs.readFileSync(path.join(root,'index.php'),'utf8');
const start=appSource.indexOf('function draw(){'),end=appSource.indexOf('async function load(){',start);
assert.ok(start>=0&&end>start,'chart rendering code exists');
const helper=vm.runInNewContext(helperSource+';WorkoutTrends;',{}, {filename:'duration-trends.js'});
const entries=[
 {date:'2026-11-06',exercise:'cycling',distance_miles:12},
 {date:'2026-11-08',exercise:'cycling',distance_miles:13.5},
 {date:'2026-11-10',exercise:'cycling',distance_miles:3.78},
 {date:'2026-11-14',exercise:'cycling',distance_miles:15.67}
];
const snapshot=JSON.stringify(entries);
const labels=[],paths=[],dots=[],scales=[];
let workingPath=[];
const context={
 setTransform(...args){scales.push(args);},
 clearRect(){},
 beginPath(){workingPath=[];},
 moveTo(x,y){workingPath.push({command:'move',x,y});},
 lineTo(x,y){workingPath.push({command:'line',x,y});},
 stroke(){paths.push({color:this.strokeStyle,segments:workingPath.slice()});},
 fill(){},
 arc(x,y,r){dots.push({x,y,r,color:this.fillStyle});},
 fillText(label,x,y){labels.push({text:String(label),x,y});},
 measureText:label=>({width:String(label).length*7}),
 font:'',fillStyle:'',strokeStyle:'',lineWidth:1
};
const canvas={
 width:650,height:280,clientWidth:320,style:{},
 getBoundingClientRect:()=>({width:320}),
 getContext:()=>context
};
const nodes=new Map([
 ['#chart',canvas],['#metric',{value:'distance_miles'}],
 ['#exerciseFilter',{value:'cycling'}],['#historyTag',{value:''}],
 ['#trendMode',{value:'both',disabled:false}],
 ['#trendModeLabel',{hidden:false}],['#trendScope',{textContent:''}],
 ['#trendLegend',{children:[],replaceChildren(...items){this.children=items;}}],
 ['#chartDateRange',{textContent:''}],['#trend',{textContent:''}]
]);
const runtime={
 entries,WorkoutTrends:helper,
 WorkoutAdvanced:{metricValue:(entry,key)=>entry[key]??null},
 window:{devicePixelRatio:2},
 $:selector=>{
  const node=nodes.get(selector);
  assert.ok(node,'selector available: '+selector);
  return node;
 },
 document:{
  createElement:tag=>({tagName:tag,className:'',style:{},children:[],attributes:{},
   setAttribute(key,value){this.attributes[key]=value;},
   append(...children){this.children.push(...children);}
  }),
  createTextNode:text=>({textContent:text})
 },
 niceDay:date=>new Date(date+'T12:00:00').toLocaleDateString('en-US',{
  month:'short',day:'numeric',year:'numeric'
 })
};
vm.runInNewContext(appSource.slice(start,end),runtime,{filename:'app.js'});
runtime.draw();

assert.equal(canvas.width,640,'320px canvas gets a 2x Retina backing bitmap');
assert.equal(canvas.height,530,'responsive canvas backing height remains 2x');
assert.equal(canvas.style.height,'265px','phone canvas stays readable at CSS size');
assert.equal(scales.length,1,'setTransform scales current pixels');
assert.equal(scales[0][0],2);
const axis=paths.find(p=>p.segments.length===3&&
 p.segments[0].command==='move' &&
 p.segments[1].x===p.segments[0].x &&
 p.segments[1].y>p.segments[0].y &&
 p.segments[2].y===p.segments[1].y);
assert.ok(axis,'visible left Y axis and bottom baseline exist');
const axisX=axis.segments[0].x;
assert.equal(dots.length,8,'all individual and cumulative markers are visible');
assert.ok(Math.min(...dots.map(p=>p.x))>=axisX+17,
 'first data point is separated from Y axis by at least 17 screen pixels');
assert.ok(Math.max(...dots.map(p=>p.x))<=320-20,
 'last marker has padding from chart edge');
assert.ok(dots.every(p=>p.x>=0&&p.x<=320&&p.y>=0&&p.y<=265),
 'all dots remain inside mobile canvas');
const dates=labels.filter(label=>/^Nov \d+/.test(label.text));
assert.deepEqual(dates.map(label=>label.text),['Nov 6','Nov 8','Nov 10','Nov 14'],
 'four logged workout dates, not just first/last, fit a mobile X axis');
assert.ok(dates.every((label,i)=>i===0||label.x>=dates[i-1].x+35),
 'date labels do not collide');
assert.match(nodes.get('#chartDateRange').textContent,/Nov 6, 2026.+Nov 14, 2026/,
 'full start/end dates are available outside compact plot labels');
assert.match(nodes.get('#trend').textContent,/Running total 44\.95 mi/,
 'running total is unchanged');
assert.equal(JSON.stringify(entries),snapshot,'drawing does not rewrite workout history');

const positions=Array.from({length:18},(_,i)=>58+i*13);
const dense=Array.from({length:18},(_,i)=>({
 date:'2026-11-'+String(i+1).padStart(2,'0')
}));
const ticks=helper.chartDateTicks(dense,positions,label=>label.length*7);
assert.equal(ticks[0].date,'2026-11-01','earliest workout date kept');
assert.equal(ticks.at(-1).date,'2026-11-18','latest workout date kept');
assert.ok(ticks.length>=3&&ticks.length<18,
 'dense workout history shows multiple ticks without cramming all dates');
for(let i=1;i<ticks.length;i++)
 assert.ok(ticks[i].start>=ticks[i-1].end+8,
  'dense dates have at least 8 pixels of text separation');
const duplicate=helper.chartDateTicks(
 [{date:'2026-11-06'},{date:'2026-11-06'},{date:'2026-11-08'}],
 [60,125,190],label=>label.length*7);
assert.deepEqual(Array.from(duplicate,t=>t.date),['2026-11-06','2026-11-08'],
 'more than one exercise on a date gets just one date label');

assert.match(html,/id="chartDateRange"/,'accessible full date range element exists');
assert.match(html,/app\.js\?v=2\.0\.0/,'mobile browser receives updated code with cache bust');
console.log('Passed: 320px Retina graph, four cycling date labels, axis inset, dense dates, duplicate days and unchanged totals.');
