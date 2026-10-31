/*
File Name: workout-detail.test.cjs
Author: Jason Lamb (with help from ChatGPT)
Description: DOM regression for mobile workout detail and private photo viewing.
Revision: 1.0.1
Created Date: 2026-10-27
Modified Date: 2026-10-27
Changelog: 1.0.1 - Verify saved BPM appears in authenticated mobile workout details. 1.0.0 - Verify safe UI rendering, set details, photo ID URL, and old entries without photos.
*/
'use strict';
const assert=require('node:assert/strict');
const fs=require('node:fs');
const path=require('node:path');
const vm=require('node:vm');
const script=fs.readFileSync(path.join(__dirname,'..','app.js'),'utf8');
const start=script.indexOf('function openWorkoutDetails(id){');
const end=script.indexOf('function renderHistory(){',start);
assert.ok(start>=0 && end>start,'Detail-view implementation is present');

class Element {
 constructor(tag='div'){
  this.tagName=tag;this.children=[];this.hidden=false;this.textContent='';
  this.listeners={};this.className='';this.id='';this.src='';this.href='';
 }
 append(...elements){this.children.push(...elements);}
 replaceChildren(...elements){this.children=elements;}
 addEventListener(type,fn){this.listeners[type]=fn;}
 scrollIntoView(){this.scrolled=true;}
}

const ids=['#detailTitle','#detailDate','#detailSummary','#detailImage','#detailPanel'];
const elements=new Map(ids.map(id=>[id,new Element()]));
elements.get('#detailPanel').hidden=true;
const id='aaaaaaaaaaaaaaaaaaaaaaaa';
const old='bbbbbbbbbbbbbbbbbbbbbbbb';
const first={
 id,exercise:'bench press',date:'2026-10-22',performed_at:'2026-10-22T08:37:00-04:00',
 notes:'Warmup <img src=x onerror=alert(1)>',tag:'Strength',heart_rate_bpm:148,
 photo:id+'.png',sets_detail:[{weight_lb:95,reps:12},{weight_lb:115,reps:10},{weight_lb:135,reps:8}],
 custom_metrics:[{name:'Tempo<script>',unit:'sec',value:2.5}],
 edit_history:[{edited_at:'2026-10-23T00:00:00Z',previous:{exercise:'bench press',date:'2026-10-22',notes:'previous value'}}]
};
const second={id:old,exercise:'treadmill',date:'2026-10-21',distance_miles:1.16,notes:'Original',photo:null};
const before=JSON.stringify([first,second]);
const ctx={
 entries:[first,second],document:{createElement:tag=>new Element(tag)},
 $:selector=>{assert.ok(elements.has(selector),'known element '+selector);return elements.get(selector);},
 WorkoutAdvanced:{stats:record=>{
  if(!Array.isArray(record.sets_detail))return null;
  return {total_reps:30,max_weight_lb:135,total_volume_lb:3550};
 }},
 titleCase:x=>x[0].toUpperCase()+x.slice(1),
 niceDay:x=>x,
 describe:x=>'legacy summary',
 message:()=>{},
 detailEntryId:null,
 encodeURIComponent,
};
vm.runInNewContext(script.slice(start,end),ctx,{filename:'app.js'});
ctx.openWorkoutDetails(id);
assert.equal(elements.get('#detailPanel').hidden,false,'detail panel becomes visible');
assert.equal(elements.get('#detailPanel').scrolled,true,'detail panel scrolls into view');
const flatten=node=>[node,...(node.children||[]).flatMap(flatten)];
const shown=flatten(elements.get('#detailSummary'));
assert.ok(shown.some(x=>x.textContent.includes('135 lb')),'heaviest set is visible');
assert.ok(shown.some(x=>x.textContent.includes('95 lb')&&x.textContent.includes('12')),'first weighted set is visible');
assert.ok(shown.some(x=>x.textContent.includes('previous value')),'edit history remains inspectable');
assert.ok(shown.some(x=>x.textContent.includes('Tempo<script>')),'custom label is rendered as text');
assert.ok(shown.some(x=>x.textContent.includes('<img src=x')),'untrusted workout notes remain literal text');
assert.ok(shown.some(x=>x.textContent==='Heart rate'),'Detail view labels the BPM value.');
assert.ok(shown.some(x=>x.textContent==='148 BPM'),'Detail view shows saved BPM.');
const imageNodes=flatten(elements.get('#detailImage'));
const img=imageNodes.find(x=>x.tagName==='img');
const link=imageNodes.find(x=>x.tagName==='a');
assert.ok(img && link,'authenticated photo preview and full-size link exist');
assert.equal(img.src,'?photo='+id,'image source uses only a record ID');
assert.equal(link.href,'?photo='+id,'full-size link does not expose storage filenames');
assert.equal(link.rel,'noopener noreferrer','external tab is isolated');
assert.equal(imageNodes.some(x=>x.tagName==='script'),false,'no injected script element');
assert.equal(JSON.stringify([first,second]),before,'opening details makes no storage mutations');

ctx.openWorkoutDetails(old);
assert.equal(elements.get('#detailImage').hidden,true,'legacy entry without a photo has no preview');
assert.equal(flatten(elements.get('#detailImage')).length,1,'previous preview removed on navigation');
ctx.closeWorkoutDetails();
assert.equal(elements.get('#detailPanel').hidden,true,'detail panel can close');
assert.equal(JSON.stringify([first,second]),before,'closing details does not change workout data');
assert.ok(!script.slice(start,end).includes('innerHTML'),'user content never inserted as HTML');

const css=fs.readFileSync(path.join(__dirname,'..','app.css'),'utf8');
assert.match(css,/history-actions/,'history must have accessible details/edit controls');
assert.match(css,/min-height:44px/,'tap targets must be at least 44px');
assert.match(css,/max-height:min\(70vh/,'attached photos must fit mobile screens');
console.log('Passed: details, notes, sets, private photo URLs, legacy records and mobile layout.');
