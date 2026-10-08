(() => {
  const $ = (id) => document.getElementById(id);
  const canvas = $('scene');
  const ctx = canvas.getContext('2d');
  const landmarks = [{name:'Kansas River',mile:102},{name:'Fort Kearney',mile:304},{name:'Chimney Rock',mile:554},{name:'Fort Laramie',mile:640},{name:'Independence Rock',mile:830},{name:'South Pass',mile:932},{name:'Fort Bridger',mile:1120},{name:'Fort Hall',mile:1330},{name:'Blue Mountains',mile:1700},{name:'The Dalles',mile:1900},{name:'Oregon City',mile:2040}];
  const initial = () => ({started:false,miles:0,day:1,month:4,year:1848,food:200,health:100,party:5,pace:'Steady',rations:'Filling',landmark:0,scene:'prairie',gameOver:false,names:['You','Sarah','James','Mary','Thomas'],ages:[35,32,14,11,8]});
  let s = initial();
  const names = ['You','Sarah','James','Mary','Thomas'];
  let soundEnabled=false;
  let autoMode=false;
  let autoTimer;
  let audioContext;
  const monthNames=['January','February','March','April','May','June','July','August','September','October','November','December'];
  const rand=(a,b)=>Math.floor(Math.random()*(b-a+1))+a;
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
    const shift=Math.min(s.miles/2040*430,400);const wx=492-(shift%240);const ox=wx-93;
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
      pixelRect(105,72,590,3,s.miles>=2040?'#a4bd72':'#d28c59');
      ctx.textAlign='center';ctx.font='700 22px "DM Sans",sans-serif';ctx.fillStyle=s.miles>=2040?'#d9e8ae':'#f0c19b';ctx.fillText(s.miles>=2040?'JOURNEY COMPLETE':'JOURNEY ENDED',400,111);
      ctx.font='11px "DM Mono",monospace';ctx.fillStyle='#d8d8c8';ctx.fillText(s.miles>=2040?'OREGON CITY IS IN SIGHT':'THE TRAIL HAS CLAIMED YOUR PARTY',400,139);ctx.fillStyle='#989d8c';ctx.fillText('Choose an option below to continue',400,159);ctx.textAlign='start';
    }
  }
  function setStory(title,message,phase='THE JOURNEY BEGINS'){ $('headline').textContent=title;$('message').textContent=message;$('phase-label').textContent=phase; }
  function update(){
    $('date').textContent=`${monthNames[s.month-1].toUpperCase()} ${s.day}, ${s.year}`;$('miles').textContent=s.miles;$('food').textContent=Math.max(0,s.food);$('party').textContent=s.party;
    $('health').textContent=s.health>75?'Good':s.health>45?'Fair':s.health>20?'Poor':'Critical';$('health-meter').style.width=`${s.health}%`;$('health-meter').style.background=s.health>45?'#a6bd79':'#d28c59';$('food-meter').style.width=`${Math.min(s.food/200*100,100)}%`;$('food-meter').style.background=s.food>50?'#a6bd79':'#d28c59';$('party-names').textContent=s.names.map((name,i)=>`${name} · ${s.ages[i]}`).join('  ');
    const lm=landmarks[s.landmark]||landmarks.at(-1);$('landmark').textContent=lm.name;$('to-landmark').textContent=Math.max(0,lm.mile-s.miles);$('distance-fill').style.width=`${Math.min(s.miles/2040*100,100)}%`;$('scene-place').textContent=s.miles===0?'INDEPENDENCE, MISSOURI':lm.name.toUpperCase();draw();
  }
  function log(t){$('log-text').textContent=t;}
  function tone(frequency=660,duration=.08,type='square'){if(!soundEnabled)return;try{audioContext??=new(window.AudioContext||window.webkitAudioContext)();const oscillator=audioContext.createOscillator(),gain=audioContext.createGain();oscillator.type=type;oscillator.frequency.value=frequency;gain.gain.setValueAtTime(.035,audioContext.currentTime);gain.gain.exponentialRampToValueAtTime(.001,audioContext.currentTime+duration);oscillator.connect(gain);gain.connect(audioContext.destination);oscillator.start();oscillator.stop(audioContext.currentTime+duration)}catch{}}
  function buttons(items,label='WHAT WILL YOU DO?'){$('actions').innerHTML=`<p class="action-label">${label}</p><div class="button-row">${items.map((x,i)=>`<button class="game-button ${i===0?'primary':''}" data-action="${i}">${x.label}</button>`).join('')}</div>`;$('actions').querySelectorAll('button').forEach((b,i)=>b.onclick=()=>{tone(520+i*90);items[i].run()});if(autoMode&&!s.gameOver){clearTimeout(autoTimer);autoTimer=setTimeout(()=>{const first=$('actions').querySelector('button');if(first)first.click()},650)}}
  function setup(){s=initial();setStory('Who is heading west?','Build your party before you leave Independence. You can bring up to five travelers, each with a name and age.','PARTY SETUP');log('Choose your family, then pack the wagon.');const render=()=>{const count=Number($('family-count').value);$('family-fields').innerHTML=Array.from({length:count},(_,i)=>`<label class="traveler-field"><span>TRAVELER ${i+1}</span><input class="traveler-name" value="${s.names[i]||''}" maxlength="16" placeholder="Name"><input class="traveler-age" type="number" min="1" max="90" value="${s.ages[i]||18}" aria-label="Age"></label>`).join('')};$('actions').innerHTML='<p class="action-label">YOUR WAGON PARTY</p><div class="setup-row"><label class="count-label">TRAVELERS <select id="family-count"><option>1</option><option>2</option><option>3</option><option selected>5</option></select></label><div id="family-fields" class="family-fields"></div></div><button class="game-button primary" id="begin-journey">Pack the wagon and begin →</button>';$('family-count').onchange=render;render();$('begin-journey').onclick=()=>{const names=[...document.querySelectorAll('.traveler-name')].map((x,i)=>x.value.trim()||`Traveler ${i+1}`);const ages=[...document.querySelectorAll('.traveler-age')].map(x=>Math.max(1,Number(x.value)||18));s.party=names.length;s.names=names;s.ages=ages;s.started=true;s.food=names.length*40;setStory('The trail is calling.','Your wagon is loaded and your party is ready. Set out when you are ready.','INDEPENDENCE, MISSOURI');log('April 1, 1848 · The journey west begins.');buttons([{label:'Set out on the trail →',run:travel},{label:'Check the supplies',run:()=>{setStory('Packed for the long road.',`You have ${s.food} pounds of food and ${s.party} hopeful travelers. There are 2,040 miles between you and Oregon.`,'AT THE TRAILHEAD');log('The oxen are hitched. Independence is behind you.');buttons([{label:'Let’s go →',run:travel}])}}]);update()};update()}
  function travel(){if(!s.started){start();return}if(s.gameOver)return;
    if(s.miles===0){s.miles=rand(12,22);s.day+=7}else{s.miles+=s.pace==='Grueling'?rand(28,40):s.pace==='Steady'?rand(20,30):rand(12,19);s.day+=7}
    const eat=s.rations==='Filling'?Math.ceil(s.party*4.2):s.rations==='Meager'?Math.ceil(s.party*2.5):Math.ceil(s.party*1.2);s.food=Math.max(0,s.food-eat);
    if(s.food===0){s.health-=rand(8,17);log('Your food is gone. The party grows weaker with each day.')}else{s.health=Math.min(100,s.health+2)}
    s.day+=0;while(s.day>30){s.day-=30;s.month++;if(s.month>12){s.month=1;s.year++}}
    s.scene='prairie';const event=Math.random();
    if(event<.12){const lost=rand(10,34);s.miles=Math.max(0,s.miles-lost);setStory('A wheel breaks on the rough trail.','The wagon jolts to a stop. The repair takes time and costs you ground.');log(`Wagon trouble · You lost ${lost} miles to repairs.`)}
    else if(event<.21){const hurt=rand(9,24);s.health=Math.max(0,s.health-hurt);setStory('Someone has taken ill.','A long day in the dust catches up with your party. You make camp early and tend to the sick.');log(`Illness on the trail · Party health fell by ${hurt} points.`)}
    else if(event<.31){s.food+=rand(12,36);setStory('A kind afternoon for hunting.','You spot game in the tall grass and bring back enough to fill the pot.');log('Hunting success · Fresh food added to your supplies.')}
    else{setStory(s.miles>=2040?'Oregon at last!':'The trail rolls on.',s.miles>=2040?'After months on the trail, you reach Oregon City. Your party made it west together.':'The oxen keep their steady pace. Dust rises behind the wagon as the miles pass beneath you.','ON THE TRAIL')}
    while(s.landmark<landmarks.length-1&&s.miles>=landmarks[s.landmark].mile){const name=landmarks[s.landmark].name;s.landmark++;if(name==='Kansas River'||name==='The Dalles'){s.scene='river';river(name);return}setStory(`You reached ${name}.`,`The landmark rises into view. Your party pauses to rest and take in how far west you have come.`,'LANDMARK REACHED');log(`${name} · ${s.miles} miles traveled.`)}
    if(s.miles>=2040){s.miles=2040;s.gameOver=true;setStory('You made it to Oregon!','Your party reaches Oregon City with the wagon, and a new life ahead. What a journey.','JOURNEY COMPLETE');log(`Journey complete · ${s.day} ${monthNames[s.month-1]}, ${s.year}`);buttons([{label:'Play again ↗',run:start}]);update();return}
    if(s.health<=0||s.party<=0){s.gameOver=true;setStory('The trail was too much.','Your journey ends on the trail. You can try again with a different pace and ration plan.','JOURNEY ENDED');buttons([{label:'Try again ↗',run:start}]);update();return}
    if(s.food<=0&&s.health<35){setStory('Supplies are running low.','Your party is hungry and tired. Consider hunting before traveling farther.','SUPPLIES LOW')}
    log(`You traveled ${s.miles} miles · ${s.pace} pace · ${s.rations.toLowerCase()} rations.`);update();campChoices();
  }
  function campChoices(){buttons([{label:'Continue down the trail →',run:travel},{label:'Hunt for food',run:hunt},{label:`Change pace · ${s.pace}`,run:pace},{label:`Change rations · ${s.rations}`,run:rations}])}
  function pace(){buttons([{label:'Steady · 20–30 mi / week',run:()=>{s.pace='Steady';log('You settle into a steady pace.');update();campChoices()}},{label:'Grueling · 28–40 mi / week',run:()=>{s.pace='Grueling';s.health=Math.max(0,s.health-5);log('Grueling pace set. The party will travel faster, but tire more quickly.');update();campChoices()}},{label:'Leisurely · 12–19 mi / week',run:()=>{s.pace='Leisurely';s.health=Math.min(100,s.health+5);log('Leisurely pace set. The party can recover as you go.');update();campChoices()} }],'CHOOSE YOUR PACE')}
  function rations(){buttons([{label:'Filling · 4 lb / person',run:()=>{s.rations='Filling';update();campChoices()}},{label:'Meager · 2.5 lb / person',run:()=>{s.rations='Meager';update();campChoices()}},{label:'Bare bones · 1 lb / person',run:()=>{s.rations='Bare bones';update();campChoices()}}],'SET DAILY RATIONS')}
  function hunt(){if(s.food>350){log('Your food stores are full. Save the powder and keep moving.');campChoices();return}if(Math.random()<.2){setStory('No luck hunting today.','The game is scarce here. You return to camp with empty hands.','A DAY ON THE TRAIL');log('Hunting trip · No game found.');s.day+=1}else{const found=rand(35,85);s.food+=found;setStory('A good hunt!','You spot deer at the edge of the prairie. The party has fresh food for the days ahead.','A DAY ON THE TRAIL');log(`Hunting success · ${found} pounds of food added.`);s.day+=1}update();campChoices()}
  function river(name){setStory(`${name} lies ahead.`,`The water is high and the current is strong. How will you get the wagon across?`,'RIVER CROSSING');log('A river crossing · Choose carefully.');buttons([{label:'Ford the river · 40% safe',run:()=>cross('ford',name)},{label:'Caulk the wagon · 65% safe',run:()=>cross('caulk',name)},{label:'Pay for a ferry · $5',run:()=>cross('ferry',name)}]);update()}
  function cross(method,name){s.scene='prairie';s.day+=2;let chance=method==='ford'?.4:method==='caulk'?.65:.93;let fee=method==='ferry'?5:0;if(method==='ferry')s.food=Math.max(0,s.food-3);const success=Math.random()<chance;if(success){setStory('Safely across the water.','The wagon creaks up the far bank. Your party is together and the trail continues west.','RIVER CROSSED');log(`${name} · You made it across ${method==='ferry'?'by ferry':method==='caulk'?'with a caulked wagon':'by fording'}.`)}else{const lost=rand(20,70);s.food=Math.max(0,s.food-rand(20,70));s.miles=Math.max(0,s.miles-lost);if(Math.random()<.25){s.party--;setStory('The crossing turns dangerous.','The current sweeps through the wagon. You lose supplies, precious time, and one member of your party.','DANGER AT THE RIVER');log(`${name} · A traveler was lost in the crossing.`)}else{setStory('The wagon takes on water.','The current pushes you downstream. You recover the wagon, but lose food and precious miles.','DANGER AT THE RIVER');log(`${name} · Supplies lost; the wagon drifted ${lost} miles downstream.`)}}update();if(s.party<=0){s.gameOver=true;setStory('The trail was too much.','Your journey ends at the river.','JOURNEY ENDED');buttons([{label:'Try again ↗',run:start}])}else campChoices()}
  $('actions').addEventListener('click',e=>{const b=e.target.closest('button');if(b)b.blur()});$('reset-button').onclick=()=>{clearTimeout(autoTimer);autoMode=false;if(s.started&&!s.gameOver&&!confirm('Start over and leave this journey behind?'))return;setup()};$('sound-toggle').onclick=()=>{soundEnabled=!soundEnabled;const el=$('sound-toggle');el.innerHTML=soundEnabled?'♫ <span>Sound on</span>':'♪ <span>Sound off</span>';tone(880,.13)};$('auto-button').onclick=()=>{autoMode=!autoMode;const el=$('auto-button');el.textContent=autoMode?'■ Stop auto-play':'▶ Auto-play';if(autoMode&&!s.started){s.names=['You','Martha','Elias','Ruth','Samuel'];s.ages=[35,33,16,12,7];s.party=5;s.food=200;s.started=true;setStory('Auto-play journey underway.','The wagon will make its own decisions and continue until it reaches Oregon or the trail ends.','AUTO MODE');log('Auto-play is driving the wagon.');buttons([{label:'Set out on the trail →',run:travel}]);update()}else if(autoMode){buttons([{label:'Continue down the trail →',run:travel}])}else{clearTimeout(autoTimer);log('Auto-play paused. Choose each action yourself.')}};
  setup();
})();
