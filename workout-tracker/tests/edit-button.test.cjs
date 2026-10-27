/*
File Name: edit-button.test.cjs
Author: Jason Lamb (with help from ChatGPT)
Description: Regression test for Edit button opening saved workouts without a DOMException.
Revision: 1.1.3
Created Date: 2026-10-27
Modified Date: 2026-10-27
Changelog: 1.1.3 - Isolate previous edit button regression from advanced measurement editor. 1.1.2 - Verify mobile touch targets, optional fields location, and hidden-value indicator. 1.1.1 - Check hidden historical rep/set/weight values remain intact and manual entry adapts. 1.1.0 - Verify exercise-specific field visibility and safe retention of hidden measurements. 1.0.0 - Exercise the actual event handler and editing form against DOM class-token validation.
*/
'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const code = fs.readFileSync(path.join(__dirname, '..', 'app.js'), 'utf8');
function functionSection(begin, end) {
  const start = code.indexOf(begin), finish = code.indexOf(end, start + begin.length);
  assert.ok(start >= 0 && finish > start, 'Missing code section ' + begin);
  return code.slice(start, finish);
}

// No network, browser, Hostinger, or production workout data is used.
function element(name) {
  const el = {
    tagName:name, dataset:{}, children:[], value:'', hidden:false, textContent:'',
    classes:new Set(), listeners:new Map(),
    append(...nodes){this.children.push(...nodes);},
    insertBefore(node,reference){
      const at=this.children.indexOf(reference);
      if(at<0) throw Error('Unknown reference child');
      this.children.splice(at,0,node);
    },
    replaceChildren(...nodes){this.children=nodes;},
    scrollIntoView(){this.scrolled=true;},
    addEventListener(type,fn){this.listeners.set(type,fn);}
  };
  el.classList = {
    add(...tokens){
      // The actual browser throws a DOMException for an empty class name.
      for(const token of tokens){
        if(!token || /\s/.test(token)) throw new DOMException('The token provided must not be empty.', 'SyntaxError');
        el.classes.add(token);
      }
    }
  };
  return el;
}
const dom = new Map([
  ['#recent',element('ul')],
  ['#editFields',element('div')],
  ['#editPanel',element('section')],
  ['#editStatus',element('p')]
]);
dom.get('#editPanel').hidden=true;
const original={
  id:'aaaaaaaaaaaaaaaaaaaaaaaa',
  _revision:'a'.repeat(64),
  exercise:'treadmill',date:'2026-10-22',
  distance_miles:2.06,duration_minutes:37,speed_mph:3.5,
  incline_percent:null,reps:6,sets:2,weight_lb:10,notes:'Existing workout',
  photo:'unchanged.jpg',created_at:'2026-10-22T11:00:00Z'
};
const saved=JSON.stringify(original);
const context={
  document:{createElement:element},
  $:key=>{assert.ok(dom.has(key),'Unknown DOM element '+key);return dom.get(key);},
  entries:[original],
  editingId:null,editingRevision:null,
  measures:[
    ['distance_miles','Miles'],['duration_minutes','Minutes'],
    ['speed_mph','Speed (mph)'],['incline_percent','Treadmill incline (%)'],
    ['reps','Reps'],['sets','Sets'],['weight_lb','Weight (lb)']
  ],
  message:()=>{},
  offset:()=>'-04:00',
  // The advanced editor is independently tested in advanced.test.cjs.
  WorkoutAdvanced:{attach:()=>({})},
  DOMException
};
const declarations =
  functionSection('function fieldInput(', 'function describe(') +
  functionSection('function visibleFieldsForExercise(', 'function renderReview(suggestions){') +
  functionSection('function editField(', 'function closeEdit(');
vm.runInNewContext(declarations, context, {filename:'workout-tracker/app.js'});
const clickListener=code.split('\n').find(line=>line.includes("$('#recent').addEventListener('click'"));
assert.ok(clickListener,'Workout history click listener must be present.');
vm.runInNewContext(clickListener,context,{filename:'workout-tracker/app.js'});

const callback=dom.get('#recent').listeners.get('click');
assert.equal(typeof callback,'function','Edit click listener must be registered.');
callback({target:{closest:selector=>{
  assert.equal(selector,'[data-edit-id]');
  return {dataset:{editId:original.id}};
}}});
assert.equal(dom.get('#editPanel').hidden,false,'Edit panel should open after tapping Edit.');
assert.equal(dom.get('#editPanel').scrolled,true,'Edit panel should scroll into view.');
assert.equal(context.editingId,original.id,'Correct saved record selected.');
assert.equal(context.editingRevision,original._revision,'Existing revision used for safe editing.');
const fields=dom.get('#editFields').children
  .flatMap(wrapper=>(wrapper.children||[]).filter(child=>child.dataset?.field));
const byName=name=>fields.find(input=>input.dataset.field===name);
assert.equal(byName('date')?.value,'2026-10-22','Date prepopulated without invalid class token.');
assert.equal(byName('distance_miles')?.value,2.06,'Saved distance prepopulated.');
assert.equal(byName('speed_mph')?.value,3.5,'Saved speed prepopulated.');
assert.equal(byName('incline_percent')?.value,'','Missing optional incline remains empty.');
assert.equal(byName('tag')?.value,'','Missing optional tag remains empty.');
const fieldLabel=name=>dom.get('#editFields').children.find(
  label=>label.tagName==='label'&&label.children.some(child=>child.dataset?.field===name));
assert.equal(fieldLabel('date').hidden,false,'Date must remain visible.');
assert.equal(fieldLabel('time_local').hidden,false,'Time must remain visible.');
assert.equal(fieldLabel('distance_miles').hidden,false,'Treadmill distance visible.');
assert.equal(fieldLabel('duration_minutes').hidden,false,'Treadmill duration visible.');
assert.equal(fieldLabel('speed_mph').hidden,false,'Treadmill speed visible.');
assert.equal(fieldLabel('incline_percent').hidden,false,'Treadmill incline visible.');
assert.equal(fieldLabel('reps').hidden,true,'Treadmill does not need reps by default.');
assert.equal(fieldLabel('sets').hidden,true,'Treadmill does not need sets by default.');
assert.equal(fieldLabel('weight_lb').hidden,true,'Treadmill does not need weight by default.');
assert.equal(fields.filter(f=>['reps','sets','weight_lb'].includes(f.dataset.field)).length,3,
  'Hidden fields must stay in the form so legacy values are not lost.');
assert.equal(byName('reps').value,6,'Legacy rep count must remain in a collapsed field.');
assert.equal(byName('sets').value,2,'Legacy set count must remain in a collapsed field.');
assert.equal(byName('weight_lb').value,10,'Legacy lifting weight must remain in a collapsed field.');
const reveal=dom.get('#editFields').children.find(child=>child.tagName==='button');
assert.ok(reveal,'There should be a button to access unusual measurements.');
const gridChildren=dom.get('#editFields').children;
assert.ok(gridChildren.indexOf(reveal)<gridChildren.indexOf(fieldLabel('tag')),
 'Optional measurements control should appear above tracking tag and notes.');
assert.match(reveal.textContent,/3 saved/,
 'Hidden historical measurements must be signaled instead of silently concealed.');
reveal.listeners.get('click')();
assert.equal(fieldLabel('sets').hidden,false,'Optional unusual measurements can be revealed.');
reveal.listeners.get('click')();
assert.equal(fieldLabel('sets').hidden,true,'Unrelated measurements can be collapsed.');
const exercise=byName('exercise');
exercise.value='bench press';exercise.listeners.get('input')();
assert.equal(fieldLabel('weight_lb').hidden,false,'Strength exercises show weight.');
assert.equal(fieldLabel('reps').hidden,false,'Strength exercises show reps.');
assert.equal(fieldLabel('sets').hidden,false,'Strength exercises show sets.');
assert.equal(fieldLabel('distance_miles').hidden,true,'Bench press hides distance.');
exercise.value='treadmill';exercise.listeners.get('input')();
assert.equal(fieldLabel('distance_miles').hidden,false,'Switching back reveals treadmill fields.');
assert.equal(JSON.stringify(original),saved,'Opening/changing displayed fields does not mutate saved workout.');

// Manual entry uses the same workout-to-fields mapping.
const manualGrid=element('div'),manualExercise=element('input');
manualExercise.name='exercise';manualExercise.value='treadmill';
for(const key of context.measures.map(([name])=>name)){
 const label=element('label'),input=element('input');input.name=key;input.value='';
 label.append(input);manualGrid.append(label);
}
context.installExerciseVisibility(manualGrid,manualExercise);
const manualLabel=name=>manualGrid.children.find(
 label=>label.tagName==='label'&&label.children.some(child=>child.name===name));
assert.equal(manualLabel('reps').hidden,true,'Manual treadmill form hides reps.');
assert.equal(manualLabel('incline_percent').hidden,false,'Manual treadmill form shows incline.');
manualExercise.value='bench press';manualExercise.listeners.get('input')();
assert.equal(manualLabel('reps').hidden,false,'Manual bench press shows reps.');
assert.equal(manualLabel('weight_lb').hidden,false,'Manual bench press shows weight.');
assert.equal(manualLabel('distance_miles').hidden,true,'Manual bench press hides miles.');
manualExercise.value='a custom workout';manualExercise.listeners.get('input')();
assert.equal(manualLabel('distance_miles').hidden,false,'Custom exercise can use all existing fields.');
assert.equal(manualLabel('reps').hidden,false,'Custom exercise can use reps.');

const css=fs.readFileSync(path.join(__dirname,'..','app.css'),'utf8');
assert.match(css,/grid-template-columns:minmax\(0,1fr\)/,
  'Narrow/mobile fields need a single minmax(0,1fr) column.');
assert.match(css,/input\[type="date"\]/,'Date controls must span both desktop grid columns.');
assert.match(css,/input\[type="time"\]/,'Time controls must span both desktop grid columns.');
assert.match(css,/font-size:16px/,'Mobile input fonts should prevent Safari focus zoom.');
assert.match(css,/min-height:44px/,'Mobile actions must have usable touch targets.');
assert.match(css,/@media\(max-width:560px\)/,'Mobile breakpoint must cover phone widths.');
assert.match(css,/@media\(max-width:360px\)/,'Very narrow phones must use a one-column fallback.');
console.log('Passed: Edit and manual fields adapt by exercise; legacy values stay intact; mobile date/time CSS protected.');
