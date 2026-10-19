const test=require('node:test'),assert=require('node:assert/strict');
const party=require('../party.js'),saves=require('../saves.js');
const content=require('../game-content.json');
function state(){return {started:true,...party.family(()=>.5),party:5,health:100,miles:100,day:1,month:4,year:1848,food:200,landmark:0,pace:'Steady',rations:'Filling',gameOver:false};}
test('Suggested family ages stay plausible at random boundaries; names are distinct',()=>{
  for(const random of [()=>0,()=>.999999,Math.random]){
    for(let i=0;i<200;i++){
      const family=party.family(random);
      assert.equal(family.names[0],'You');assert.equal(new Set(family.names).size,5);
      assert.equal(family.ages.length,5);assert.ok(family.ages.every(a=>a>=1&&a<=50));
      assert.ok(family.ages.slice(0,2).every(a=>a>=30));
      for(const age of family.ages.slice(2)){assert.ok(age<=17);assert.ok(Math.min(...family.ages.slice(0,2))-age>=18);}
    }
  }
  assert.notDeepEqual(party.family(()=>0),party.family(()=>.999999));
});
test('Manual older relatives survive validation, serialization and migration without randomization',()=>{
  const s=state();s.names[1]='Granny';s.ages[1]=80;party.ensure(s,true);
  const record={id:'test',state:s,config:content,story:{title:'Test',message:'Test',phase:'Test'},log:'Test',view:'camp',pendingStop:null};
  saves.valid(record,require('../rules.js'));
  let value=null;const storage={getItem:()=>value,setItem:(k,v)=>value=v};saves.write(storage,record);
  const restored=saves.read(storage).games.test;saves.valid(restored,require('../rules.js'));
  party.ensure(restored.state,true);
  assert.equal(restored.state.travelers[1].age,80);assert.equal(restored.state.travelers[1].name,'Granny');
  assert.equal(restored.state.rosterHistoryIncomplete,true);
});
test('Illness affects one traveler; recovery and death preserve individual identity',()=>{
  const s=state();party.ensure(s);const names=[...s.names];
  const sick=party.illness(s,-20,()=>.5);assert.equal(sick.id,'traveler-2');assert.equal(sick.health,80);
  assert.equal(s.travelers.filter(t=>t.health<100).length,1);assert.equal(s.health,96);assert.equal(sick.condition,'Ill');
  party.adjustAll(s,5);assert.equal(sick.health,85);assert.equal(sick.condition,'Ill');
  const lost=party.loseTraveler(s,'River crossing',()=>0);assert.equal(lost.name,names[0]);assert.equal(s.party,4);assert.equal(s.travelers.length,5);
  party.adjustAll(s,100);assert.equal(lost.status,'deceased');assert.equal(lost.health,0);assert.equal(sick.condition,'');assert.equal(s.health,100);
  assert.equal(lost.death.miles,100);
  const invalid={id:'test',state:structuredClone(s),config:content,story:{title:'Test',message:'Test',phase:'Test'},log:'Test',view:'camp'};
  invalid.state.travelers[0].health=100;assert.throws(()=>saves.valid(invalid,require('../rules.js')),/deceased/);
});
test('Individual health reaching zero records death and updates remaining members',()=>{
  const s=state();party.ensure(s);const t=party.illness(s,-100,()=>.999);
  assert.equal(t.status,'deceased');assert.equal(t.death.cause,'Illness');assert.equal(s.party,4);
  party.adjustAll(s,-100,'Starvation');assert.equal(s.party,0);assert.equal(s.health,0);assert.equal(s.travelers.filter(t=>t.status==='deceased').length,5);
});
