/*
File Name     : party.js
Author        : Jason Lamb (with help from ChatGPT)
Description   : Random family generation, persistent traveler health, and age-weighted illness targeting.
Revision      : 1.0.0
Created Date  : 2026-10-19
Modified Date : 2026-10-19
Changelog     :
  Rev 1.0.0 - Established independent file revision tracking; Random family generation, persistent traveler health, and age-weighted illness targeting.
*/
/* Party generation and individual health. Legacy name/age arrays mirror survivors. */
(function(root){
  'use strict';
  const clamp=n=>Math.max(0,Math.min(100,n));
  const living=s=>s.travelers.filter(t=>t.status==='alive');
  const healthLabel=h=>h>75?'Good':h>45?'Fair':h>20?'Poor':'Critical';
  const defaultIllnessRisk=Object.freeze({childBelowAge:12,childExtraWeightPerYear:.1,olderAboveAge:50,olderExtraWeightPerYear:.05,maxWeight:3});
  function illnessWeight(age,risk=defaultIllnessRisk){
    const extra=age<risk.childBelowAge?(risk.childBelowAge-age)*risk.childExtraWeightPerYear:age>risk.olderAboveAge?(age-risk.olderAboveAge)*risk.olderExtraWeightPerYear:0;
    return Math.min(risk.maxWeight,1+extra);
  }
  function family(random=Math.random){
    const pick=(a,b)=>a+Math.floor(random()*(b-a+1));
    const pool=['Sarah','Elizabeth','Abigail','Hannah','Clara','Ruth','James','Thomas','Henry','Samuel','William','Benjamin','Mary','Alice','Joseph','Emma','Charles','Eleanor'];
    const names=['You'];
    while(names.length<5)names.push(pool.splice(pick(0,pool.length-1),1)[0]);
    const parent=pick(30,50),other=Math.max(30,Math.min(50,parent+pick(-5,5)));
    const maxChild=Math.min(17,Math.min(parent,other)-18);
    const children=Array.from({length:3},()=>pick(1,maxChild)).sort((a,b)=>b-a);
    return {names,ages:[parent,other,...children]};
  }
  function sync(s){
    const survivors=living(s);
    s.names=survivors.map(t=>t.name);s.ages=survivors.map(t=>t.age);s.party=survivors.length;
    s.health=survivors.length?Math.round(survivors.reduce((sum,t)=>sum+t.health,0)/survivors.length):0;
  }
  function ensure(s,legacy=false){
    if(!Array.isArray(s.travelers)){
      s.travelers=s.names.map((name,i)=>({id:'traveler-'+i,name,age:s.ages[i],health:s.health,status:'alive',condition:''}));
      if(s.health===0)for(const t of s.travelers)death(s,t,'Journey ended before individual records were available');
      if(legacy)s.rosterHistoryIncomplete=true;
    }
    sync(s);
  }
  function death(s,t,cause){
    if(t.status!=='alive')return;
    t.status='deceased';t.health=0;t.condition='';
    t.death={cause,day:s.day,month:s.month,year:s.year,miles:s.miles};
  }
  function adjust(s,traveler,delta,cause='Health declined on the trail'){
    if(traveler.status!=='alive')return;
    traveler.health=clamp(traveler.health+delta);
    if(traveler.health===0)death(s,traveler,cause);
    else if(traveler.health===100)traveler.condition='';
  }
  function adjustAll(s,delta,cause){for(const t of living(s))adjust(s,t,delta,cause);sync(s);}
  function illness(s,delta,random=Math.random,risk=defaultIllnessRisk){
    const survivors=living(s);if(!survivors.length)return null;
    const weights=survivors.map(t=>illnessWeight(t.age,risk));
    let roll=random()*weights.reduce((sum,w)=>sum+w,0),t=survivors.at(-1);
    for(let i=0;i<survivors.length;i++){roll-=weights[i];if(roll<0){t=survivors[i];break;}}
    t.condition='Ill';adjust(s,t,delta,'Illness');sync(s);return t;
  }
  function loseTraveler(s,cause,random=Math.random){
    const survivors=living(s);if(!survivors.length)return null;
    const t=survivors[Math.floor(random()*survivors.length)];death(s,t,cause);sync(s);return t;
  }
  const api={family,ensure,sync,living,healthLabel,adjustAll,illness,loseTraveler,illnessWeight,defaultIllnessRisk};
  if(typeof module!=='undefined'&&module.exports)module.exports=api;else root.TrailParty=api;
})(typeof globalThis!=='undefined'?globalThis:this);
