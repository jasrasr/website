/*
File Name: chart-modes.test.cjs
Author: Jason Lamb (with help from ChatGPT)
Description: DOM-backed canvas test for both session and running total workout charts.
Revision: 1.0.0
Created Date: 2026-10-31
Modified Date: 2026-10-31
Changelog: 1.0.0 - Verify dual-series mobile chart and disable misleading cumulative speed/heart rate.
*/
'use strict';
const assert=require('node:assert/strict');
const fs=require('node:fs');
const path=require('node:path');
const vm=require('node:vm');
const root=path.join(__dirname,'..');
const source=fs.readFileSync(path.join(root,'app.js'),'utf8');
const helper=fs.readFileSync(path.join(root,'duration-trends.js'),'utf8');
const index=fs.readFileSync(path.join(root,'index.php'),'utf8');
const start=source.indexOf('function draw(){'),end=source.indexOf('async function load(){',start);
assert.ok(start>=0&&end>start,'chart renderer exists');
const entries=[
 {exercise:'treadmill',date:'2026-10-21',distance_miles:1.16,duration_minutes:22,speed_mph:3.3},
 {exercise:'treadmill',date:'2026-10-22',distance_miles:2.06,duration_minutes:37,speed_mph:3.5},
 {exercise:'treadmill',date:'2026-10-27',distance_miles:2.05,duration_minutes:27,speed_mph:5},
 {exercise:'treadmill',date:'2026-10-31',distance_miles:2.07,duration_minutes:33,duration_seconds:18,speed_mph:4},
];
const original=JSON.stringify(entries);
const drawColors=[];
const canvasContext={
 clearRect(){},beginPath(){},moveTo(){},lineTo(){},stroke(){drawColors.push(this.strokeStyle);},
 fillText(){},arc(){},fill(){},measureText:label=>({width:String(label).length*7}),
 font:'',fillStyle:'',strokeStyle:'',lineWidth:1
};
const nodes=new Map([
 ['#chart',{width:650,height:280,getContext:()=>canvasContext}],
 ['#metric',{value:'distance_miles'}],['#exerciseFilter',{value:'treadmill'}],
 ['#historyTag',{value:''}],['#trendMode',{value:'both',disabled:false}],
 ['#trendModeLabel',{hidden:false}],['#trendScope',{textContent:''}],
 ['#trendLegend',{children:[],replaceChildren(...items){this.children=items;}}],
 ['#trend',{textContent:''}]
]);
const ctx={
 entries,
 $:selector=>{assert.ok(nodes.has(selector),'unknown chart selector '+selector);return nodes.get(selector);},
 document:{
  createElement(tag){
   return {tagName:tag,children:[],className:'',style:{},attributes:{},
    append(...elements){this.children.push(...elements);},
    setAttribute(name,value){this.attributes[name]=value;}
   };
  },
  createTextNode:text=>({textContent:text})
 },
 WorkoutAdvanced:{metricValue:(entry,key)=>entry[key]??null},
 niceDay:x=>x,
};
ctx.WorkoutTrends=vm.runInNewContext(helper+';WorkoutTrends;',ctx);
vm.runInNewContext(source.slice(start,end),ctx);
ctx.draw();
assert.equal(nodes.get('#trendMode').disabled,false,'cumulative control enabled for miles');
assert.equal(nodes.get('#trendModeLabel').hidden,false);
assert.equal(nodes.get('#trendLegend').children.length,2,'both legend items shown');
assert.ok(drawColors.includes('#59dbc3'),'session line drawn');
assert.ok(drawColors.includes('#e2b56e'),'cumulative line drawn');
assert.match(nodes.get('#trend').textContent,/Running total 7\.34 mi/);
assert.match(nodes.get('#trend').textContent,/Latest 2\.07 mi/);
assert.equal(JSON.stringify(entries),original,'drawing must never change workout data');

drawColors.length=0;
nodes.get('#trendMode').value='individual';
ctx.draw();
assert.equal(nodes.get('#trendLegend').children.length,1);
assert.ok(drawColors.includes('#59dbc3'));
assert.ok(!drawColors.includes('#e2b56e'),'running series hidden when per-workout mode selected');

drawColors.length=0;
nodes.get('#trendMode').value='running';
ctx.draw();
assert.equal(nodes.get('#trendLegend').children.length,1);
assert.ok(drawColors.includes('#e2b56e'));
assert.ok(!drawColors.includes('#59dbc3'),'per-workout series hidden when cumulative selected');

nodes.get('#metric').value='speed_mph';
nodes.get('#trendMode').value='running';
drawColors.length=0;ctx.draw();
assert.equal(nodes.get('#trendMode').disabled,true,'speed cannot show meaningless accumulated MPH');
assert.equal(nodes.get('#trendModeLabel').hidden,true,'cumulative chooser hidden for rate-based metrics');
assert.ok(!drawColors.includes('#e2b56e'),'no speed cumulative line');
assert.match(nodes.get('#trendScope').textContent,/not an additive total/);

nodes.get('#metric').value='duration_minutes';
nodes.get('#trendMode').value='both';
ctx.draw();
assert.match(nodes.get('#trend').textContent,/Running total 119\.3 min/);
assert.match(nodes.get('#trend').textContent,/Latest 33\.3 min/);

assert.ok(index.includes('duration-trends.js?v='),'chart library loaded on webpage');
assert.ok(index.indexOf('duration-trends.js?v=')<index.indexOf('app.js?v='),
 'trend calculations available before application code');
assert.match(index,/id="trendMode"/);
assert.match(index,/id="trendLegend"/);
assert.match(index,/name="duration_seconds"/,'manual entry has seconds field');
console.log('Passed: both session and cumulative mileage, running duration with seconds, nonadditive guard and history safety.');
