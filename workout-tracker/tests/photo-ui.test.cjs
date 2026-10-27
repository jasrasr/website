/*
File Name: photo-ui.test.cjs
Author: Jason Lamb (with help from ChatGPT)
Description: Exercise the actual saved-workout photo upload handler without network or user records.
Revision: 1.0.0
Created Date: 2026-10-27
Modified Date: 2026-10-27
Changelog: 1.0.0 - Verify scoped ID/revision, multiple files, stale errors and unchanged original data.
*/
'use strict';
const assert=require('node:assert/strict');
const fs=require('node:fs');
const path=require('node:path');
const vm=require('node:vm');
const root=path.join(__dirname,'..');
const script=fs.readFileSync(path.join(root,'app.js'),'utf8');
const html=fs.readFileSync(path.join(root,'index.php'),'utf8');
const begin=script.indexOf('async function uploadSavedPhotos(){');
const end=script.indexOf('async function submitEdit(){',begin);
assert.ok(begin>=0&&end>begin,'saved-workout picture handler present');
const countStart=script.indexOf('function workoutPhotoCount(entry) {');
const countEnd=script.indexOf('/** Mobile-readable workout detail view.',countStart);
assert.ok(countStart>=0&&countEnd>countStart,'photo counting logic present');
const original={
 id:'aaaaaaaaaaaaaaaaaaaaaaaa',
 _revision:'a'.repeat(64),date:'2026-10-22',exercise:'treadmill',
 photo:'legacy.jpg',notes:'Do not replace me',distance_miles:2.06
};
const before=JSON.stringify(original);
const dom=new Map([
 ['#savedPhotos',{files:[{name:'new-1.png',size:1024},{name:'new-2.png',size:2048}],value:'selected'}],
 ['#photoStatus',{textContent:''}],
 ['#addPhotosButton',{disabled:false,textContent:'Add selected pictures'}],
 ['#existingPhotos',{textContent:''}]
]);
let requests=[],errors=[];
class Data{
 entries=[];
 set(k,v){this.entries.push(['set',k,v]);}
 append(k,v,name){this.entries.push(['append',k,v,name]);}
}
const updated={...original,photos:['new-1.png','new-2.png'],_revision:'b'.repeat(64)};
const ctx={
 entries:[original],editingId:original.id,editingRevision:original._revision,
 $:selector=>{assert.ok(dom.has(selector),'known selector '+selector);return dom.get(selector);},
 payload:action=>{assert.equal(action,'append_photos');return new Data();},
 api:async data=>{requests.push(data.entries);return {entry:updated,attached:2};},
 load:async()=>{ctx.entries=[updated];},
 message:msg=>errors.push(msg),
 Math,
};
vm.runInNewContext(script.slice(countStart,countEnd)+script.slice(begin,end),ctx,{filename:'app.js'});
(async()=>{
 await ctx.uploadSavedPhotos();
 assert.equal(requests.length,1,'exactly one upload request');
 const set=requests[0].filter(item=>item[0]==='set');
 assert.ok(set.some(item=>item[1]==='entry_id'&&item[2]===original.id),
  'request targets original workout ID');
 assert.ok(set.some(item=>item[1]==='expected_revision'&&item[2]===original._revision),
  'request uses original revision token');
 const appended=requests[0].filter(item=>item[0]==='append');
 assert.equal(appended.length,2,'both pictures are uploaded together');
 assert.ok(appended.every(item=>item[1]==='photos[]'),'multipart names match backend');
 assert.equal(ctx.editingRevision,updated._revision,'editor revision updates after successful attach');
 assert.equal(dom.get('#photoStatus').textContent,'2 pictures saved.');
 assert.match(dom.get('#existingPhotos').textContent,/3 pictures already saved/,
  'original and two additions are counted');
 assert.equal(JSON.stringify(original),before,'source saved entry is not changed by view code');
 assert.equal(dom.get('#savedPhotos').value,'','picker reset on success');

 requests=[];
 dom.get('#savedPhotos').files=[{name:'big.jpg',size:6*1024*1024}];
 await ctx.uploadSavedPhotos();
 assert.equal(requests.length,0,'oversized photo blocked before API call');
 assert.match(dom.get('#photoStatus').textContent,/5 MB/);

 dom.get('#savedPhotos').files=Array.from({length:6},(_,i)=>({name:i+'.jpg',size:1000}));
 await ctx.uploadSavedPhotos();
 assert.equal(requests.length,0,'more than five pictures blocked');

 assert.match(html,/id="photosForm"/);
 assert.match(html,/id="savedPhotos"[^>]*multiple/,'mobile image input allows multiple');
 assert.match(html,/name="action" value="logout"/,'central logout uses POST form');
 assert.match(html,/User Management/,'portal navigation displayed');
 const css=fs.readFileSync(path.join(root,'app.css'),'utf8');
 assert.match(css,/\.detail-gallery\{display:grid;grid-template-columns:repeat\(2,minmax\(0,1fr\)\)/);
 assert.match(css,/\.account-signout\{/, 'touch-friendly sign-out action');
 console.log('Passed: append-only mobile uploads, correct revision/owner, limits, gallery and account controls.');
})().catch(e=>{console.error(e);process.exitCode=1;});
