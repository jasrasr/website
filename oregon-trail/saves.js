(function(root){
  'use strict';
  const KEY='oregon-trail.journeys.v1';
  function read(storage){const raw=storage.getItem(KEY);if(!raw)return {version:1,games:{}};const data=JSON.parse(raw);if(data.version!==1||!data.games||typeof data.games!=='object'||Array.isArray(data.games))throw Error('Saved journey data is unreadable');return data;}
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
    return record;
  }
  const api={KEY,read,write,valid};if(typeof module!=='undefined'&&module.exports)module.exports=api;else root.TrailSaves=api;
})(typeof globalThis!=='undefined'?globalThis:this);
