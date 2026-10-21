'use strict';
const assert=require('node:assert/strict'),fs=require('node:fs'),vm=require('node:vm');
const source=fs.readFileSync(__dirname+'/review.js','utf8');
const elements=new Map();const element=id=>{if(!elements.has(id))elements.set(id,{value:'',hidden:false,addEventListener(){},replaceChildren(){}});return elements.get(id);};
const storage=new Map();const context=vm.createContext({document:{getElementById:element,querySelectorAll:()=>[]},localStorage:{getItem:k=>storage.get(k)||null,setItem:(k,v)=>storage.set(k,v),removeItem:k=>storage.delete(k)},Date,URL,console});
vm.runInContext(source,context);
assert.equal(context.savedLogin(),'');assert.equal(context.saveLogin('synthetic-token'),true);assert.equal(context.savedLogin(),'synthetic-token');
const key='freshservice-review-login-v1',saved=JSON.parse(storage.get(key));saved.expires=Date.now()-1;storage.set(key,JSON.stringify(saved));assert.equal(context.savedLogin(),'');assert.equal(storage.has(key),false);
storage.set(key,'broken JSON');assert.equal(context.savedLogin(),'');context.saveLogin('synthetic-token');element('lock').onclick();assert.equal(storage.has(key),false);
context.localStorage.getItem=()=>{throw Error('blocked');};assert.equal(context.savedLogin(),'');context.localStorage.setItem=()=>{throw Error('blocked');};assert.equal(context.saveLogin('synthetic-token'),false);
(async()=>{
 vm.runInContext("tickets=[{id:1},{id:2},{id:3}];analyze=async id=>{if(id===2)throw Error('evidence mismatch');return {ticket:{id}};};renderQueue=()=>{};renderDetail=()=>{};",context);
 await element('analyze-all').onclick();
 assert.equal(vm.runInContext('reviews.size',context),2);assert.match(element('message').textContent,/#2.*evidence mismatch/);
 console.log('Remembered login expiry, malformed/blocked storage, Lock, and batch failure continuation passed.');
})().catch(e=>{console.error(e);process.exitCode=1;});
