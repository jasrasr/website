/*
File Name: advanced.test.cjs
Author: Jason Lamb (with help from ChatGPT)
Description: Node DOM test of set-by-set, custom metric, and mobile editor behavior.
Revision: 1.0.0
Created Date: 2026-10-27
Modified Date: 2026-10-27
Changelog: 1.0.0 - Ensure weights and reps are collected separately without changing historical workout objects.
*/
'use strict';
const assert=require('node:assert/strict');
const fs=require('node:fs');
const path=require('node:path');
const vm=require('node:vm');
class Element {
 constructor(tag){
  this.tagName=tag.toUpperCase();this.children=[];this.parentNode=null;
  this.dataset={};this.className='';this.textContent='';this.value='';
  this.name='';this.hidden=false;this.disabled=false;this.listeners={};
 }
 append(...nodes){nodes.forEach(node=>{node.parentNode=this;this.children.push(node);});}
 insertBefore(node,other){
  const at=this.children.indexOf(other);
  assert.ok(at>=0,'inserting before a child of the grid');
  node.parentNode=this;this.children.splice(at,0,node);
 }
 replaceChildren(...nodes){this.children=[];this.append(...nodes);}
 remove(){
  const children=this.parentNode?.children;if(!children)return;
  const at=children.indexOf(this);if(at>=0)children.splice(at,1);
 }
 addEventListener(name,handler){this.listeners[name]=handler;}
 setAttribute(name,value){this[name]=value;}
 focus(){this.focused=true;}
 get lastElementChild(){return this.children[this.children.length-1]??null;}
 matches(selector){
  if(selector.startsWith('.'))return this.className.split(/\s+/).includes(selector.slice(1));
  if(selector==='[data-advanced-field]')return this.dataset.advancedField!==undefined;
  const m=/^\[data-advanced-field="([^"]+)"\]$/.exec(selector);
  if(m)return this.dataset.advancedField===m[1];
  return false;
 }
 querySelectorAll(selector){
  const result=[];function walk(node){node.children.forEach(child=>{if(child.matches(selector))result.push(child);walk(child);});}
  walk(this);return result;
 }
 querySelector(selector){return this.querySelectorAll(selector)[0]??null;}
}
const script=fs.readFileSync(path.join(__dirname,'..','advanced.js'),'utf8');
const context={document:{createElement:tag=>new Element(tag)},console};
const advanced=vm.runInNewContext(script+'; WorkoutAdvanced;',context,{filename:'advanced.js'});
function assertNumbers(obj,key,value){assert.equal(Number(obj[key]),value,key);}
const bench={
 exercise:'bench press',date:'2026-10-22',
 sets_detail:[
  {weight_lb:95,reps:12,note:'warmup'},
  {weight_lb:115,reps:10,note:''},
  {weight_lb:135,reps:8,note:''},
  {weight_lb:115,reps:10,note:'backoff'}
 ],
 custom_metrics:[{name:'Tempo',unit:'seconds',value:3.5}],
 calories_kcal:null,tag:'Strength',photo:'saved-photo.jpg'
};
const untouched=JSON.stringify(bench);
const sum=advanced.stats(bench);
assert.equal(sum.set_count,4);
assert.equal(sum.total_reps,40);
assert.equal(sum.max_weight_lb,135);
assert.equal(sum.total_volume_lb,4520);
assert.equal(advanced.metricValue(bench,'total_reps'),40);
assert.equal(advanced.metricValue(bench,'max_weight_lb'),135);
assert.equal(advanced.metricValue(bench,'total_volume_lb'),4520);
assert.equal(advanced.metricValue(bench,'set_count'),4);
assert.equal(advanced.metricValue(bench,advanced.customKey('TEMPO','seconds')),3.5);
assert.equal(advanced.stats({}),null,'older records without detailed sets remain supported');

const grid=new Element('div'),tag=new Element('label'),tagInput=new Element('input');
tagInput.dataset.field='tag';tag.append(tagInput);grid.append(tag);
const exercise=new Element('input');exercise.value='bench press';
const editor=advanced.attach(grid,bench,exercise);
assert.equal(editor.setsPanel.hidden,false,'strength workout can show set controls');
assert.equal(editor.metricsPanel.open,true,'saved measurements are visible');
assert.equal(editor.setsPanel.dataset.hadSets,'yes','existing sets preserved');
assert.equal(editor.metricsPanel.dataset.hadMetrics,'yes','existing metrics preserved');
const children=grid.children;
assert.ok(children.indexOf(editor.setsPanel)<children.indexOf(tag),'advanced rows precede tag/notes');
assert.ok(children.indexOf(editor.metricsPanel)<children.indexOf(tag),'custom metrics precede tag/notes');
const sets=grid.querySelectorAll('.set-row');
assert.equal(sets.length,4,'four individual sets rendered');
assert.equal(sets[2].querySelector('[data-advanced-field="weight_lb"]').value,135);
assert.equal(sets[2].querySelector('[data-advanced-field="reps"]').value,8);
assert.equal(sets[0].querySelector('[data-advanced-field="weight_lb"]').name,'',
 'advanced weight field must not collide with legacy scalar in FormData');
const rows=grid.querySelectorAll('.custom-row');
assert.equal(rows.length,1);
assert.equal(rows[0].querySelector('[data-advanced-field="name"]').value,'Tempo');
let payload=advanced.collect(grid);
assert.equal(payload.sets_detail.length,4);
assertNumbers(payload.sets_detail[3],'weight_lb',115);
assert.equal(payload.custom_metrics[0].unit,'seconds');
assert.equal(JSON.stringify(bench),untouched,'opening and reading editor does not alter original record');
sets[3].querySelector('[data-advanced-field="weight_lb"]').value='120';
payload=advanced.collect(grid);
assertNumbers(payload.sets_detail[3],'weight_lb',120);
assertNumbers(payload.sets_detail[2],'weight_lb',135);
assert.equal(advanced.stats({sets_detail:payload.sets_detail}).total_volume_lb,4570);
assert.equal(JSON.stringify(bench),untouched,'editing inputs does not mutate source records');
assert.match(editor.setsPanel.querySelector('.set-summary').textContent,/total volume/i);

exercise.value='treadmill';exercise.listeners.input();
assert.equal(editor.setsPanel.hidden,false,'previous detailed sets stay accessible even when exercise name changes');

const blankGrid=new Element('div'),blankExercise=new Element('input');blankExercise.value='treadmill';
const manual=advanced.attach(blankGrid,{},blankExercise);
assert.equal(manual.setsPanel.hidden,true,'treadmill does not show weightlifting rows');
assert.equal(Object.keys(advanced.collect(blankGrid)).length,0,'blank advanced fields are not backfilled');
blankExercise.value='bench press';blankExercise.listeners.input();
assert.equal(manual.setsPanel.hidden,false,'bench press shows Add set');
const add=manual.setsPanel.querySelector('.add-item');
add.listeners.click();
const newSet=blankGrid.querySelector('.set-row');
newSet.querySelector('[data-advanced-field="weight_lb"]').value='95';
newSet.querySelector('[data-advanced-field="reps"]').value='12';
assertNumbers(advanced.collect(blankGrid).sets_detail[0],'weight_lb',95);
assert.equal(advanced.collect(blankGrid).sets_detail[0].reps,12);
newSet.querySelector('[data-advanced-field="reps"]').value='';
assert.throws(()=>advanced.collect(blankGrid),/repetitions/,'unfinished rows rejected before saving');
newSet.querySelector('[data-advanced-field="reps"]').value='12';
manual.reset();
assert.equal(blankGrid.querySelectorAll('.set-row').length,0,'reset clears newly entered rows');
assert.equal(Object.keys(advanced.collect(blankGrid)).length,0,'reset restores blank state');

const metricAdd=manual.metricsPanel.querySelector('.add-item');
metricAdd.listeners.click();
const metric=blankGrid.querySelector('.custom-row');
metric.querySelector('[data-advanced-field="name"]').value='Elevation gain';
metric.querySelector('[data-advanced-field="unit"]').value='ft';
metric.querySelector('[data-advanced-field="value"]').value='250';
assert.equal(advanced.collect(blankGrid).custom_metrics[0].value,250);
assert.equal(advanced.customKey('Elevation gain','ft'),advanced.customKey('elevation gain','FT'));
assert.ok(!script.includes('innerHTML'),'no HTML injection through user-defined metric text');
console.log('Passed: ordered set weights/reps, volume, custom metrics, mobile editor, validation and legacy preservation.');
