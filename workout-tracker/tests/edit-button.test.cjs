/*
File Name: edit-button.test.cjs
Author: Jason Lamb (with help from ChatGPT)
Description: Regression test for Edit button opening saved workouts without a DOMException.
Revision: 1.0.0
Created Date: 2026-10-27
Modified Date: 2026-10-27
Changelog: 1.0.0 - Exercise the actual event handler and editing form against DOM class-token validation.
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
  incline_percent:null,notes:'Existing workout',
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
  DOMException
};
const declarations =
  functionSection('function fieldInput(', 'function describe(') +
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
assert.equal(JSON.stringify(original),saved,'Opening edit does not mutate saved workout.');
console.log('Passed: tapping Edit opens a populated form without a DOMException or data mutation.');
