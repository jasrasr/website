/*
File Name     : saves.js
Author        : Jason Lamb (with help from ChatGPT)
Description   : Versioned journey storage with legacy-save and individual-roster validation.
Revision      : 1.0.1
Created Date  : 2026-10-08
Modified Date : 2026-10-20
Changelog     :
  Rev 1.0.1 - Validated optional persistent event and burial data without discarding legacy saves.
  Rev 1.0.0 - Established independent file revision tracking; Versioned journey storage with legacy-save and individual-roster validation.
*/
(function(root){
  'use strict';
  const KEY='oregon-trail.journeys.v1';
  function read(storage){const raw=storage.getItem(KEY);if(raw===null)return {version:1,games:{}};const data=JSON.parse(raw);if(data.version!==1||!data.games||typeof data.games!=='object'||Array.isArray(data.games))throw Error('Saved journey data is unreadable');return data;}
  function write(storage,record){const data=read(storage);data.games[record.id]=record;storage.setItem(KEY,JSON.stringify(data));}
  function valid(record,rules){
    if(!record||typeof record.id!=='string'||!record.state||!record.story||typeof record.log!=='string')throw Error('Invalid saved journey');
    rules.validate(record.config);
    const s=record.state;
    for(const key of ['miles','day','month','year','food','health','party','landmark'])if(!Number.isInteger(s[key]))throw Error('Invalid saved '+key);
    if(s.miles<0||s.food<0||s.health<0||s.health>100||s.party<0||s.party>5||s.names.length!==s.party||s.ages.length!==s.party||s.day<1||s.day>31||s.month<1||s.month>12||s.year<1848||s.year>9999||s.landmark<0||s.landmark>record.config.stops.length)throw Error('Invalid saved state');
    if(!s.names.every(n=>typeof n==='string'&&n.length<=16)||!s.ages.every(a=>Number.isInteger(a)&&a>=1&&a<=90)||!['Steady','Grueling','Leisurely'].includes(s.pace)||!['Filling','Meager','Bare bones'].includes(s.rations)||typeof s.gameOver!=='boolean'||s.started!==true)throw Error('Invalid saved party');
    if(!['camp','pace','rations','stop','end'].includes(record.view))throw Error('Invalid saved view');
    if(record.view==='stop'&&!record.config.stops.some(x=>x.id===record.pendingStop))throw Error('Missing pending stop');
    for(const key of ['title','message','phase'])if(typeof record.story[key]!=='string')throw Error('Invalid saved story');
    if(s.notice!=null&&(!s.notice||typeof s.notice.title!=='string'||typeof s.notice.message!=='string'))throw Error('Invalid saved notice');
    if(s.notice?.kind!==undefined&&typeof s.notice.kind!=='string')throw Error('Invalid notice kind');
    if(s.attentionVersion!==undefined&&s.attentionVersion!==1)throw Error('Invalid attention version');
    if(s.travelers!==undefined){
      if(!Array.isArray(s.travelers)||s.travelers.length>5)throw Error('Invalid traveler roster');
      const ids=new Set();
      for(const t of s.travelers){
        if(!t||typeof t.id!=='string'||!t.id||ids.has(t.id)||typeof t.name!=='string'||!t.name||t.name.length>16||!Number.isInteger(t.age)||t.age<1||t.age>90||!Number.isInteger(t.health)||t.health<0||t.health>100||!['alive','deceased'].includes(t.status)||typeof t.condition!=='string')throw Error('Invalid traveler');
        if(t.buried!==undefined&&typeof t.buried!=='boolean')throw Error('Invalid burial record');
        ids.add(t.id);
        if(t.status==='alive'&&t.health===0)throw Error('Invalid living traveler health');
        if(t.status==='deceased'&&(t.health!==0||!t.death||typeof t.death.cause!=='string'||!Number.isInteger(t.death.miles)||t.death.miles<0||!Number.isInteger(t.death.day)||t.death.day<1||t.death.day>31||!Number.isInteger(t.death.month)||t.death.month<1||t.death.month>12||!Number.isInteger(t.death.year)))throw Error('Invalid deceased traveler');
      }
      const alive=s.travelers.filter(t=>t.status==='alive');
      const health=alive.length?Math.round(alive.reduce((sum,t)=>sum+t.health,0)/alive.length):0;
      if(alive.length!==s.party||health!==s.health||alive.some((t,i)=>t.name!==s.names[i]||t.age!==s.ages[i]))throw Error('Roster does not match party');
    }
    return record;
  }
  const api={KEY,read,write,valid};if(typeof module!=='undefined'&&module.exports)module.exports=api;else root.TrailSaves=api;
})(typeof globalThis!=='undefined'?globalThis:this);
