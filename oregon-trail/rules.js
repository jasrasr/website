/* Editable content and probability rules. No code from JSON is executed. */
(function(root) {
  'use strict';
  const EFFECTS = ['food','health','miles'];
  function range(value, name, min=-100000, max=100000) {
    const values=Array.isArray(value)?value:[value];
    if(values.length!==1 && values.length!==2 || values.some(v=>!Number.isInteger(v)||v<min||v>max) || values.length===2 && values[0]>values[1]) throw Error('Invalid '+name);
    return value;
  }
  function integer(value,name,min=0,max=100000){if(!Number.isInteger(value)||value<min||value>max)throw Error('Invalid '+name);return value;}
  function percent(value, name) {if(!Number.isFinite(value)||value<0||value>100) throw Error('Invalid '+name);return value;}
  function effects(value={}) {if(!value || typeof value!=='object'||Array.isArray(value)) throw Error('Invalid effects');for(const [key,v] of Object.entries(value)){if(!EFFECTS.includes(key))throw Error('Unknown effect: '+key);range(v,key);}return value;}
  function text(value,name) {if(typeof value!=='string'||!value.trim()||value.length>1000)throw Error('Invalid '+name);}
  function action(a) {
    text(a.label,'action label'); effects(a.effects);
    if(a.days!==undefined) range(a.days,'action days',0,365);
    if(a.successPercent!==undefined)percent(a.successPercent,'action successPercent');
    effects(a.failureEffects);
    for(const key of ['message','failureMessage'])if(a[key]!==undefined)text(a[key],key);
    return a;
  }
  function validate(c) {
    if(!c||c.version!==1)throw Error('Unsupported content version');
    integer(c.totalMiles,'totalMiles',1,100000);
    if(!Array.isArray(c.stops)||!c.stops.length||!Array.isArray(c.events)||!Array.isArray(c.actions)||!Array.isArray(c.crossings)||!c.crossings.length)throw Error('Missing content lists');
    const ids=new Set();let prior=0;
    for(const stop of c.stops){text(stop.id,'stop id');text(stop.name,'stop name');if(ids.has(stop.id))throw Error('Duplicate stop id');ids.add(stop.id);integer(stop.mile,'stop mile',1,c.totalMiles);if(stop.mile<=prior)throw Error('Stops must have increasing mile markers');prior=stop.mile;if(!['stop','river'].includes(stop.type))throw Error('Invalid stop type');if(stop.actions!==undefined){if(!Array.isArray(stop.actions))throw Error('Invalid stop actions');stop.actions.forEach(action);}}
    if(prior!==c.totalMiles)throw Error('Last stop must match totalMiles');
    let sum=0;ids.clear();
    for(const event of c.events){text(event.id,'event id');if(ids.has(event.id))throw Error('Duplicate event id');ids.add(event.id);text(event.title,'event title');text(event.message,'event message');sum+=percent(event.chancePercent,'event chancePercent');effects(event.effects);}
    if(sum>100)throw Error('Event percentages must total 100 or less');
    c.actions.forEach(action);ids.clear();
    for(const cross of c.crossings){text(cross.id,'crossing id');if(ids.has(cross.id))throw Error('Duplicate crossing id');ids.add(cross.id);text(cross.label,'crossing label');percent(cross.successPercent,'crossing successPercent');integer(cross.days,'crossing days',0,365);if(cross.costFood!==undefined)integer(cross.costFood,'crossing costFood',0,100000);}
    percent(c.hunting.failurePercent,'hunting failurePercent');range(c.hunting.food,'hunting food',0,100000);integer(c.hunting.days,'hunting days',0,365);
    range(c.crossingFailure.foodLoss,'foodLoss',0,100000);range(c.crossingFailure.milesLost,'milesLost',0,100000);percent(c.crossingFailure.travelerLossPercent,'travelerLossPercent');
    return c;
  }
  function sample(value, random=Math.random){return Array.isArray(value)?value[0]+Math.floor(random()*(value[1]-value[0]+1)):value;}
  function selectEvent(events,random=Math.random){const roll=random()*100;let cumulative=0;return events.find(e=>{cumulative+=e.chancePercent;return roll<cumulative;})||null;}
  function advanceDate(state,days){const date=new Date(Date.UTC(state.year,state.month-1,state.day+days));state.year=date.getUTCFullYear();state.month=date.getUTCMonth()+1;state.day=date.getUTCDate();}
  function applyEffects(state,effects={},random=Math.random){for(const [key,value] of Object.entries(effects))state[key]+=sample(value,random);state.food=Math.max(0,state.food);state.health=Math.max(0,Math.min(100,state.health));state.miles=Math.max(0,state.miles);}
  const api={validate,sample,selectEvent,advanceDate,applyEffects};
  if(typeof module!=='undefined'&&module.exports)module.exports=api;else root.TrailRules=api;
})(typeof globalThis!=='undefined'?globalThis:this);
