(async () => {
  'use strict';
  const $=id=>document.getElementById(id);
  const canvas=$('scene'),ctx=canvas.getContext('2d');
  const monthNames=['January','February','March','April','May','June','July','August','September','October','November','December'];
  const initial=()=>({started:false,miles:0,day:1,month:4,year:1848,food:200,health:100,party:5,pace:'Steady',rations:'Filling',landmark:0,scene:'prairie',gameOver:false,names:['You','Sarah','James','Mary','Thomas'],ages:[35,32,14,11,8]});
  const rand=(a,b)=>TrailRules.sample([a,b]);
  let content,s=initial(),gameId=null,view='camp',pendingStop=null,story={},logText='',latestContent;
  let soundEnabled=false,audioContext;
  function pixelRect(x,y,w,h,c){ctx.fillStyle=c;ctx.fillRect(x,y,w,h)}
  function draw(){
    const c=ctx; c.clearRect(0,0,800,260); pixelRect(0,0,800,260,'#080a08');
    // distant prairie and a warm, low sun
    pixelRect(0,97,800,2,'#334129'); pixelRect(650,39,31,31,'#db9b55');pixelRect(656,33,19,6,'#db9b55');pixelRect(656,70,20,6,'#db9b55');
    // far ridge
    for(let i=0;i<32;i++){const x=i*27; const h=3+(i*17%10);pixelRect(x,98-h,30,h,'#283622')}
    // tree silhouettes
    for(const [x,y,k] of [[78,100,1],[145,101,.8],[215,97,1.2],[314,101,.8],[710,98,1]]){pixelRect(x+8*k,y-35*k,8*k,35*k,'#644727');pixelRect(x,y-40*k,23*k,18*k,'#33472e');pixelRect(x+4*k,y-51*k,16*k,16*k,'#3c5233');pixelRect(x+2*k,y-29*k,28*k,14*k,'#33472e')}
    // ground, pixel grass
    pixelRect(0,103,800,157,'#38613b');pixelRect(0,109,800,5,'#49764a');
    for(let i=0;i<160;i++){let x=(i*71+11)%800,y=116+(i*37)%139;pixelRect(x,y,2,2,i%4===0?'#709054':i%3===0?'#2c5034':'#557a47')}
    // trail ribbon
    pixelRect(0,184,800,3,'#7e6744');pixelRect(0,190,800,2,'#947b50');
    // wagon oxen
    const shift=Math.min(s.miles/content.totalMiles*430,400);const wx=492-(shift%240);const ox=wx-93;
    // harness and oxen pair
    pixelRect(ox+11,173,82,4,'#4c3525');
    for(const x of [ox,ox+37]){pixelRect(x+7,144,23,27,'#e8e3d3');pixelRect(x+2,151,8,16,'#c9c5b9');pixelRect(x+11,137,17,10,'#e8e3d3');pixelRect(x+22,141,12,9,'#e8e3d3');pixelRect(x+29,145,7,6,'#191a16');pixelRect(x+10,169,6,11,'#4b3c2f');pixelRect(x+24,168,6,12,'#4b3c2f');pixelRect(x+31,143,3,3,'#171815')}
    // wagon wheels, chassis and canvas
    pixelRect(wx-4,169,151,6,'#704a2f');pixelRect(wx+8,132,113,39,'#8e5c35');pixelRect(wx+13,135,103,6,'#b17b4c');pixelRect(wx+22,142,93,26,'#a46d40');
    pixelRect(wx+3,106,122,4,'#5d432f');pixelRect(wx+9,102,5,34,'#795535');pixelRect(wx+112,102,5,34,'#795535');
    pixelRect(wx+8,70,110,35,'#e4dfcc');pixelRect(wx+17,64,96,7,'#f0ecde');pixelRect(wx+12,101,106,5,'#d2cbb8');pixelRect(wx+25,75,8,27,'#cbc5b4');pixelRect(wx+98,75,8,27,'#cbc5b4');
    for(const x of [wx+25,wx+101]){c.fillStyle='#271d15';c.beginPath();c.arc(x,173,16,0,Math.PI*2);c.fill();c.fillStyle='#cf9b65';c.beginPath();c.arc(x,173,10,0,Math.PI*2);c.fill();c.fillStyle='#30271e';c.beginPath();c.arc(x,173,4,0,Math.PI*2);c.fill();pixelRect(x-1,164,2,18,'#30271e');pixelRect(x-8,172,16,2,'#30271e')}
    // little traveler at the reins
    pixelRect(wx-21,121,9,20,'#b17b4c');pixelRect(wx-22,113,11,10,'#d5a76f');pixelRect(wx-24,111,13,4,'#4c3926');pixelRect(wx-20,140,4,20,'#30342b');pixelRect(wx-12,140,4,20,'#30342b');
    if(s.scene==='river'){pixelRect(0,183,800,77,'#345f68');for(let i=0;i<15;i++){pixelRect((i*63+30)%800,199+(i%3)*13,32,2,'#74a0a0')}}
    if(s.scene==='mountain'){for(let i=0;i<6;i++){let x=i*155-30;pixelRect(x,35,95,65,'#475247');pixelRect(x+22,18,50,40,'#5b6254');pixelRect(x+39,22,18,20,'#d2d0bb')}}
    if(s.gameOver){
      pixelRect(0,0,800,260,'#050605aa');
      pixelRect(105,72,590,104,'#151811');
      pixelRect(105,72,590,3,s.health>0&&s.party>0&&s.miles>=content.totalMiles?'#a4bd72':'#d28c59');
      ctx.textAlign='center';ctx.font='700 22px "DM Sans",sans-serif';ctx.fillStyle=s.health>0&&s.party>0&&s.miles>=content.totalMiles?'#d9e8ae':'#f0c19b';ctx.fillText(s.health>0&&s.party>0&&s.miles>=content.totalMiles?'JOURNEY COMPLETE':'JOURNEY ENDED',400,111);
      ctx.font='11px "DM Mono",monospace';ctx.fillStyle='#d8d8c8';ctx.fillText(s.health>0&&s.party>0&&s.miles>=content.totalMiles?'OREGON CITY IS IN SIGHT':'THE TRAIL HAS CLAIMED YOUR PARTY',400,139);ctx.fillStyle='#989d8c';ctx.fillText('Choose an option below to continue',400,159);ctx.textAlign='start';
    }
  }

  function setStory(title,message,phase='ON THE TRAIL'){story={title,message,phase};$('headline').textContent=title;$('message').textContent=message;$('phase-label').textContent=phase;}
  function log(message){logText=message;$('log-text').textContent=message;}
  function update(){
    $('date').textContent=`${monthNames[s.month-1].toUpperCase()} ${s.day}, ${s.year}`;
    $('miles').textContent=s.miles;$('total-miles').textContent=content.totalMiles.toLocaleString();$('food').textContent=s.food;$('party').textContent=s.party;
    $('health').textContent=s.health>75?'Good':s.health>45?'Fair':s.health>20?'Poor':'Critical';
    $('health-meter').style.width=s.health+'%';$('health-meter').style.background=s.health>45?'#a6bd79':'#d28c59';
    $('food-meter').style.width=Math.min(s.food/200*100,100)+'%';
    $('party-names').textContent=s.names.map((n,i)=>n+' · '+s.ages[i]).join('  ');
    const stop=content.stops[s.landmark]||content.stops.at(-1);
    $('landmark').textContent=stop.name;$('to-landmark').textContent=Math.max(0,stop.mile-s.miles);
    $('distance-fill').style.width=Math.min(s.miles/content.totalMiles*100,100)+'%';
    $('scene-place').textContent=s.miles===0?'INDEPENDENCE, MISSOURI':(pendingStop?content.stops.find(x=>x.id===pendingStop).name:stop.name).toUpperCase();
    draw();
  }
  function tone(frequency=660,duration=.08){if(!soundEnabled)return;try{audioContext??=new(window.AudioContext||window.webkitAudioContext)();const oscillator=audioContext.createOscillator(),gain=audioContext.createGain();oscillator.frequency.value=frequency;gain.gain.setValueAtTime(.035,audioContext.currentTime);gain.gain.exponentialRampToValueAtTime(.001,audioContext.currentTime+duration);oscillator.connect(gain);gain.connect(audioContext.destination);oscillator.start();oscillator.stop(audioContext.currentTime+duration);}catch{}}
  function save(){
    if(!s.started||!gameId)return;
    try{TrailSaves.write(localStorage,{id:gameId,updatedAt:new Date().toISOString(),state:s,config:content,story,log:logText,view,pendingStop});$('save-status').textContent='Saved on this device · '+new Date().toLocaleTimeString();renderSavedGames();}
    catch{$('save-status').textContent='Progress could not be saved. Browser storage may be full, blocked, or unreadable.';}
  }
  function buttons(items,label='WHAT WILL YOU DO?'){
    const area=$('actions');area.replaceChildren();const p=document.createElement('p');p.className='action-label';p.textContent=label;area.append(p);
    const row=document.createElement('div');row.className='button-row';area.append(row);
    items.forEach((item,i)=>{const button=document.createElement('button');button.type='button';button.className='game-button'+(i===0?' primary':'');button.textContent=item.label;button.disabled=!!item.disabled;button.onclick=()=>{tone(520+i*90);item.run();save();};row.append(button);});
  }
  function renderSavedGames(){
    const list=$('saved-games');list.replaceChildren();
    try{
      const records=Object.values(TrailSaves.read(localStorage).games).sort((a,b)=>String(b.updatedAt).localeCompare(String(a.updatedAt)));
      for(const record of records){const button=document.createElement('button');button.type='button';button.className='game-button';
        try{TrailSaves.valid(record,TrailRules);button.textContent=(record.state.gameOver?'View':'Continue')+' · '+record.state.names[0]+' · '+record.state.miles+' mi · '+new Date(record.updatedAt).toLocaleString();button.onclick=()=>{if(gameId!==record.id){save();}loadGame(record.id);};}
        catch{button.textContent='Unreadable saved journey';button.disabled=true;}
        list.append(button);
      }
      if(!records.length)list.textContent='No saved journeys yet. Pack the wagon to create one.';
    }catch{list.textContent='Saved journeys cannot be read. Existing data has been left intact.';}
  }
  function loadGame(id){
    try{
      const record=TrailSaves.valid(TrailSaves.read(localStorage).games[id],TrailRules);
      s=structuredClone(record.state);content=structuredClone(record.config);gameId=record.id;view=record.view;pendingStop=record.pendingStop;
      renderView();setStory(record.story.title,record.story.message,record.story.phase);log(record.log);update();
      $('save-status').textContent='Restored saved journey · '+new Date(record.updatedAt).toLocaleString();
    }catch{$('save-status').textContent='This journey could not be restored. Its save has been left intact.';}
  }
  function setup(){
    if(!latestContent){$('save-status').textContent='Connect and reload to load the rules for a new journey.';return;}
    save();content=structuredClone(latestContent);s=initial();gameId=null;pendingStop=null;
    $('save-status').textContent='Pack the wagon to create a new saved journey.';
    setStory('Who is heading west?','Build your party before leaving Independence. Each journey saves on this device.','PARTY SETUP');log('Choose your family, then pack the wagon.');
    $('actions').innerHTML='<p class="action-label">YOUR WAGON PARTY</p><div class="setup-row"><label class="count-label">TRAVELERS <select id="family-count"><option>1</option><option>2</option><option>3</option><option>4</option><option selected>5</option></select></label><div id="family-fields" class="family-fields"></div></div><button class="game-button primary" id="begin-journey">Pack the wagon and begin →</button>';
    let draft=s.names.map((name,i)=>({name,age:s.ages[i]}));
    function remember(){document.querySelectorAll('.traveler-field').forEach((field,i)=>{draft[i]={name:field.querySelector('.traveler-name').value,age:field.querySelector('.traveler-age').value};});}
    function fields(){
      const area=$('family-fields');area.replaceChildren();
      for(let i=0;i<Number($('family-count').value);i++){const label=document.createElement('label');label.className='traveler-field';const span=document.createElement('span');span.textContent='TRAVELER '+(i+1);const name=document.createElement('input');name.className='traveler-name';name.maxLength=16;name.placeholder='Name';name.value=draft[i]?.name||'';const age=document.createElement('input');age.className='traveler-age';age.type='number';age.min=1;age.max=90;age.value=draft[i]?.age||18;age.setAttribute('aria-label','Age');label.append(span,name,age);area.append(label);}
    }
    $('family-count').onchange=()=>{remember();fields();};fields();
    $('begin-journey').onclick=()=>{
      s.names=[...document.querySelectorAll('.traveler-name')].map((x,i)=>x.value.trim().slice(0,16)||'Traveler '+(i+1));
      s.ages=[...document.querySelectorAll('.traveler-age')].map(x=>Math.max(1,Math.min(90,Math.floor(Number(x.value)||18))));
      s.party=s.names.length;s.food=s.party*40;s.started=true;gameId=crypto.randomUUID();
      setStory('The trail is calling.','Your wagon is loaded and your party is ready. Set out when you are ready.','INDEPENDENCE, MISSOURI');log('April 1, 1848 · The journey west begins.');campChoices();update();save();
    };update();
  }
  function endIfNeeded(){
    // Failure takes precedence over arrival; no resurrecting a lost party at a stop.
    if(s.health<=0||s.party<=0){s.gameOver=true;setStory('The trail was too much.','Your journey ends on the trail. Try a different pace or ration plan.','JOURNEY ENDED');}
    else if(s.miles>=content.totalMiles&&s.landmark>=content.stops.length){s.miles=content.totalMiles;s.gameOver=true;setStory('You made it to Oregon!','Your party reaches Oregon City with a new life ahead.','JOURNEY COMPLETE');}
    if(s.gameOver){pendingStop=null;view='end';buttons([{label:'Start a new journey ↗',run:setup}]);update();return true;}return false;
  }
  function reachedStop(){
    const stop=content.stops[s.landmark];
    if(!stop||s.miles<stop.mile)return false;
    s.landmark++;pendingStop=stop.id;view='stop';
    if(s.landmark===content.stops.length){endIfNeeded();return true;}
    s.scene=stop.type==='river'?'river':'prairie';
    setStory(stop.type==='river'?stop.name+' lies ahead.':'You reached '+stop.name+'.',stop.type==='river'?'How will you get the wagon across?':'Your party pauses at this landmark. Choose an action or continue.','LANDMARK REACHED');log(stop.name+' · '+s.miles+' miles traveled.');stopChoices();update();return true;
  }
  function continueTrail(){if(endIfNeeded())return;if(reachedStop())return;travel();}
  function travel(){
    if(!s.started||s.gameOver)return;
    s.miles+=s.miles===0?rand(12,22):s.pace==='Grueling'?rand(28,40):s.pace==='Steady'?rand(20,30):rand(12,19);
    TrailRules.advanceDate(s,7);s.food=Math.max(0,s.food-Math.ceil(s.party*(s.rations==='Filling'?4.2:s.rations==='Meager'?2.5:1.2)));
    s.health=Math.max(0,Math.min(100,s.health+(s.food===0?-rand(8,17):2)));s.scene='prairie';
    const event=TrailRules.selectEvent(content.events);
    if(event){TrailRules.applyEffects(s,event.effects);setStory(event.title,event.message,'TRAIL EVENT');log(event.title);}
    else{setStory('The trail rolls on.','The oxen keep their pace as dust rises behind the wagon.');log(s.miles+' miles traveled · '+s.pace+' pace · '+s.rations.toLowerCase()+' rations.');}
    if(endIfNeeded())return;if(reachedStop())return;
    if(s.food===0&&s.health<35)setStory('Supplies are running low.','Consider hunting before traveling farther.','SUPPLIES LOW');
    campChoices();update();
  }
  function executeAction(action){
    const success=Math.random()*100<(action.successPercent??100);
    TrailRules.advanceDate(s,TrailRules.sample(action.days??0));
    TrailRules.applyEffects(s,success?action.effects:action.failureEffects);
    setStory(action.label,success?(action.message||'Your party finishes the action.'):(action.failureMessage||'The attempt was unsuccessful.'),'ACTION RESULT');
    log((success?'Completed: ':'Unsuccessful: ')+action.label);
    if(!endIfNeeded()){if(pendingStop)stopChoices();else campChoices();update();}
  }
  function customChoices(actions){return actions.map(a=>({label:a.label,run:()=>executeAction(a)}));}
  function campChoices(){view='camp';buttons([{label:'Continue down the trail →',run:continueTrail},{label:'Hunt for food',run:hunt},{label:'Change pace · '+s.pace,run:pace},{label:'Change rations · '+s.rations,run:rations},...customChoices(content.actions)]);}
  function pace(){view='pace';buttons(['Steady','Grueling','Leisurely'].map(p=>({label:p+' · '+({Steady:'20–30',Grueling:'28–40',Leisurely:'12–19'}[p])+' mi / week',run:()=>{s.pace=p;log(p+' pace set.');campChoices();update();}})),'CHOOSE YOUR PACE');}
  function rations(){view='rations';buttons(['Filling','Meager','Bare bones'].map(r=>({label:r,run:()=>{s.rations=r;log(r+' rations set.');campChoices();update();}})),'SET RATIONS');}
  function hunt(){
    if(s.food>350){log('Your food stores are full. Keep moving.');campChoices();return;}
    TrailRules.advanceDate(s,content.hunting.days);
    if(Math.random()*100<content.hunting.failurePercent){setStory('No luck hunting today.','You return to camp empty-handed.');log('Hunting trip · No game found.');}
    else{const food=TrailRules.sample(content.hunting.food);s.food+=food;setStory('A good hunt!','Your party has fresh food for the days ahead.');log('Hunting success · '+food+' lb of food added.');}
    campChoices();update();
  }
  function stopChoices(){
    view='stop';const stop=content.stops.find(x=>x.id===pendingStop);
    const choices=stop.type==='river'?content.crossings.map(c=>({label:c.label+' · '+c.successPercent+'% safe',disabled:s.food<(c.costFood||0),run:()=>cross(c,stop)})):[{label:'Leave '+stop.name+' →',run:()=>{pendingStop=null;s.scene='prairie';campChoices();update();}}];
    buttons([...choices,...customChoices(stop.actions||[])],stop.name.toUpperCase());
  }
  function cross(c,stop){
    s.food-=c.costFood||0;TrailRules.advanceDate(s,c.days);
    if(Math.random()*100<c.successPercent){setStory('Safely across the water.','Your party is together and the trail continues west.','RIVER CROSSED');log(stop.name+' · Crossed safely.');}
    else{
      s.food=Math.max(0,s.food-TrailRules.sample(content.crossingFailure.foodLoss));s.miles=Math.max(0,s.miles-TrailRules.sample(content.crossingFailure.milesLost));
      let lost=false;if(Math.random()*100<content.crossingFailure.travelerLossPercent){s.names.pop();s.ages.pop();s.party=s.names.length;lost=true;}
      setStory('The crossing turns dangerous.',lost?'You lose supplies, time, and one traveler.':'You recover the wagon but lose supplies and ground.','DANGER AT THE RIVER');log(stop.name+' · '+(lost?'A traveler was lost.':'Supplies were lost.'));
    }
    pendingStop=null;s.scene='prairie';if(!endIfNeeded()){campChoices();update();}
  }
  function renderView(){
    if(s.gameOver){view='end';buttons([{label:'Start a new journey ↗',run:setup}]);}
    else if(view==='stop')stopChoices();else if(view==='pace')pace();else if(view==='rations')rations();else campChoices();
  }
  $('reset-button').onclick=()=>{setup();}; // setup saves the current journey before creating another.
  $('sound-toggle').onclick=()=>{soundEnabled=!soundEnabled;$('sound-toggle').textContent=soundEnabled?'♫ Sound on':'♪ Sound off';tone(880,.13);};
  window.addEventListener('pagehide',save);
  document.addEventListener('visibilitychange',()=>{if(document.visibilityState==='hidden')save();});
  window.addEventListener('storage',e=>{if(e.key===TrailSaves.KEY)renderSavedGames();});
  try{
    const response=await fetch('game-content.json',{cache:'no-cache',signal:AbortSignal.timeout(8000)});if(!response.ok)throw Error('Content request failed');
    latestContent=TrailRules.validate(await response.json());content=structuredClone(latestContent);
  }catch{
    $('save-status').textContent='Game content could not be loaded. Connect and reload to start a new journey.';
    renderSavedGames(); // Saved journeys include their rules and can still resume.
    try{const records=Object.values(TrailSaves.read(localStorage).games).filter(r=>{try{TrailSaves.valid(r,TrailRules);return !r.state.gameOver;}catch{return false;}}).sort((a,b)=>String(b.updatedAt).localeCompare(String(a.updatedAt)));if(records.length)loadGame(records[0].id);}catch{}
    $('reset-button').disabled=true;return;
  }
  renderSavedGames();
  try{
    const records=Object.values(TrailSaves.read(localStorage).games).filter(r=>{try{TrailSaves.valid(r,TrailRules);return !r.state.gameOver;}catch{return false;}}).sort((a,b)=>String(b.updatedAt).localeCompare(String(a.updatedAt)));
    if(records.length)loadGame(records[0].id);else setup();
  }catch{setup();}
  if('serviceWorker' in navigator)navigator.serviceWorker.register('sw.js').then(()=>{$('offline-status').textContent='Preparing offline play…';return navigator.serviceWorker.ready;}).then(()=>{$('offline-status').textContent='Ready for offline play on this device.';}).catch(()=>{$('offline-status').textContent='Offline reload unavailable. Progress still saves on this device.';});
})();
