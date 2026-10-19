/*
File Name     : tests/scenes.test.cjs
Author        : Jason Lamb (with help from ChatGPT)
Description   : Scene selection, unique landmark art, river orientation, and configuration tests.
Revision      : 1.0.0
Created Date  : 2026-10-19
Modified Date : 2026-10-19
Changelog     :
  Rev 1.0.0 - Established independent file revision tracking; Scene selection, unique landmark art, river orientation, and configuration tests.
*/
const test=require('node:test'),assert=require('node:assert/strict');
const scenes=require('../scenes.js'),rules=require('../rules.js'),content=require('../game-content.json');
function record(scene){const draws=[];scenes.background({fillStyle:'',fillRect(x,y,w,h){draws.push([x,y,w,h,this.fillStyle]);}},scene);return draws;}
test('Every built-in landmark has distinct scenery, including four forts',()=>{
  const art=content.stops.map(stop=>{const scene=scenes.resolve({miles:stop.mile,landmark:1},content,stop.id);assert.equal(scene.name,stop.name);return JSON.stringify(record(scene));});
  assert.equal(new Set(art).size,content.stops.length);
});
test('River runs across the road with the westbound wagon on the near dry bank',()=>{
  for(const id of ['kansas-river','the-dalles']){
    const scene=scenes.resolve({miles:120,landmark:1},content,id);
    assert.equal(scene.crossing,true);
    for(const y of [102,150,184,198,250]){
      const bank=scenes.riverBanks(y);assert.ok(bank.left<bank.right);
      assert.ok(bank.right<scenes.wagonX(scene,120)-93,'Even the leading ox must remain on dry land');
    }
    const rects=record(scene);
    assert.ok(rects.some(([x,y,w,h,color])=>y===182&&x<400&&x+w>400&&color.startsWith('#3c76')),'Water cuts the road at y=182');
  }
});
test('Scenery and captions restore from pending stops without mutating saves or consuming randomness',()=>{
  const state={miles:305,landmark:2,gameOver:false,health:100,party:5};
  const before=structuredClone(state);
  const scene=scenes.resolve(state,content,'fort-kearney');assert.equal(scene.kind,'fort');record(scene);
  assert.deepEqual(state,before);
  const legacy=structuredClone(content);legacy.stops.forEach(stop=>delete stop.scene);
  assert.equal(scenes.resolve(state,legacy,'fort-kearney').kind,'fort');
  assert.match(scenes.resolve(state,content,null).name,/Trail toward/);
});
test('New stops can choose scene types; invalid scene names are rejected',()=>{
  const edited=structuredClone(content);edited.stops[1].scene='town';rules.validate(edited);
  assert.equal(scenes.resolve({miles:305},edited,'fort-kearney').kind,'town');
  edited.stops[1].scene='unknown';assert.throws(()=>rules.validate(edited),/scene/);
});
