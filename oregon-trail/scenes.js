/* Deterministic canvas artwork; rendering never consumes gameplay randomness. */
(function(root){
  'use strict';
  const kinds=['prairie','settlement','fort','chimney','rock','pass','mountains','river','gorge','town'];
  const known={'kansas-river':'river','fort-kearney':'fort','chimney-rock':'chimney','fort-laramie':'fort','independence-rock':'rock','south-pass':'pass','fort-bridger':'fort','fort-hall':'fort','blue-mountains':'mountains','the-dalles':'gorge','oregon-city':'town'};
  function stopKind(stop){return stop.scene||known[stop.id]||(stop.type==='river'?'river':/^Fort\b/i.test(stop.name)?'fort':'prairie');}
  function resolve(s,content,pending){
    const stop=content.stops.find(x=>x.id===pending);
    if(stop)return {kind:stopKind(stop),id:stop.id,name:stop.name,crossing:stop.type==='river'};
    if(s.gameOver&&s.health>0&&s.party>0&&s.miles>=content.totalMiles)return {kind:'town',id:'oregon-city',name:content.stops.at(-1).name};
    if(!s.miles)return {kind:'settlement',id:'independence',name:'Independence, Missouri'};
    const kind=s.miles>=1700?'mountains':s.miles>=830?'pass':'prairie';
    const next=content.stops[s.landmark]||content.stops.at(-1);
    return {kind,id:'trail',name:'Trail toward '+next.name};
  }
  function wagonX(scene,miles){return scene.crossing||['river','gorge'].includes(scene.kind)?555:492-Math.min(miles/2040*90,90);}
  // Exposed geometry is shared by the renderer and its orientation tests.
  function riverBanks(y){return {left:400-Math.floor((y-100)*.6),right:430+Math.floor((y-100)*.08)};}
  function background(c,scene){
    const rect=(x,y,w,h,color)=>{c.fillStyle=color;c.fillRect(x,y,w,h);};
    const dry=['chimney','rock','pass'].includes(scene.kind);
    rect(0,0,800,260,'#101b20');rect(0,60,800,44,'#253434');
    rect(650,28,30,30,'#e5b072');rect(656,22,18,6,'#e5b072');rect(656,58,18,6,'#e5b072');
    for(let i=0;i<32;i++)rect(i*27,99-(i*17%11),30,15,'#34493b');
    rect(0,104,800,156,dry?'#776b43':'#3a6141');rect(0,109,800,5,dry?'#9b8953':'#50744b');
    for(let i=0;i<180;i++)rect((i*71+11)%800,116+(i*37)%139,3,2,dry?(i%3?'#8c7b4c':'#635b3c'):(i%3?'#567d4d':'#2b4b36'));
    // West is left: the oxen lead the wagon along this horizontal road.
    rect(0,181,800,19,'#8b7049');rect(0,184,800,4,'#b09260');rect(0,197,800,3,'#665538');
    function pine(x,y,size){
      rect(x+size*.4,y-size*.15,size*.15,size*.35,'#58472f');
      for(let i=0;i<4;i++)rect(x+i*size*.1,y-size+i*size*.18,size*(1-i*.2),size*.22,i%2?'#203e32':'#2b4b39');
    }
    function house(x,y,w,color,roof){
      rect(x,y,w,40,color);rect(x-5,y-7,w+10,8,roof);rect(x+7,y-13,w-14,7,roof);
      rect(x+w*.42,y+16,12,24,'#242b22');
      for(const px of [x+8,x+w-18]){rect(px,y+12,8,10,'#d1ae68');rect(px+3,y+12,2,10,'#57442e');}
      rect(x+w-15,y-24,7,15,'#4b4235');
    }
    if(scene.kind==='prairie'){
      for(const x of [45,122,710,755])pine(x,104,32);
      rect(90,132,12,7,'#504733');rect(87,137,7,8,'#504733');rect(101,136,5,9,'#504733');
    }
    if(scene.kind==='settlement'){
      house(65,83,72,'#9e895d','#514a36');house(174,72,100,'#b4a179','#654c32');house(310,89,65,'#89734c','#4a432f');
      rect(208,107,32,4,'#463f2c');pine(728,111,39);
    }
    if(scene.kind==='fort'){
      const adobe=scene.id==='fort-laramie',w=scene.id==='fort-hall'?290:330,x=48;
      const wall=adobe?'#b39a6a':'#806540',dark=adobe?'#826847':'#4c402b';
      rect(x,78,w,69,wall);
      for(let i=0;i<w;i+=12){rect(x+i,72,8,9,wall);rect(x+i+8,80,2,62,dark);}
      rect(x-7,55,38,100,wall);rect(x+w-31,55,38,100,wall);
      rect(x-10,50,44,8,dark);rect(x+w-34,50,44,8,dark);
      rect(x+w/2-25,106,50,45,'#242c24');rect(x+w/2-28,101,56,8,dark);
      rect(x+60,96,22,21,'#273127');rect(x+w-88,96,22,21,'#273127');
      rect(x+w/2+65,23,3,59,'#cfba86');rect(x+w/2+68,25,30,17,scene.id==='fort-bridger'?'#c08b57':'#c6bc95');
      rect(x+w/2+68,31,30,3,'#775d42');
      if(scene.id==='fort-bridger')house(96,79,80,'#96754b','#4b432e');
      if(scene.id==='fort-hall'){pine(8,135,43);pine(385,132,46);}
    }
    if(scene.kind==='chimney'){
      rect(160,115,195,30,'#8c6944');rect(185,98,145,20,'#b58c57');rect(208,78,98,24,'#c39a62');
      rect(246,30,25,55,'#c5a06e');rect(252,19,14,18,'#dbc08d');rect(239,71,42,14,'#b28b59');
      for(let y=88;y<139;y+=12)rect(202-(y-88)/2,y,114+(y-88),3,'#9b774d');
    }
    if(scene.kind==='rock'){
      rect(59,117,350,40,'#847b67');rect(78,93,311,30,'#a4997c');rect(114,77,245,25,'#b7ac8e');rect(162,65,153,15,'#c8bc9a');
      rect(91,120,267,5,'#6c6c5b');rect(129,93,155,3,'#ddd0a9');rect(183,80,4,15,'#958b73');
    }
    if(['pass','mountains','gorge'].includes(scene.kind)){
      const wooded=scene.kind==='mountains';
      const peaks=scene.kind==='pass'?[[0,45,240],[195,78,170],[459,37,270],[675,64,165]]:[[0,63,165],[105,31,230],[305,51,210],[484,23,245],[687,56,170]];
      for(const [x,top,w] of peaks){for(let y=top;y<115;y+=7){const half=(y-top+14)*w/150;rect(x+w/2-half,y,half*2,8,wooded?'#3e5360':'#5c665e');}rect(x+w/2-9,top+7,18,7,'#c6cec0');}
      if(wooded)for(let i=0;i<17;i++)pine(i*49,137,30+i%4*8);
      if(scene.kind==='pass'){rect(341,112,121,5,'#a59c75');rect(355,106,84,6,'#c1b38a');}
    }
    if(scene.kind==='town'){
      house(38,81,76,'#c2ab7c','#5b4b36');house(143,65,83,'#a78a62','#5c4231');house(256,77,99,'#b9a079','#4c4835');
      rect(190,32,12,29,'#b7b49b');rect(194,21,4,15,'#d6ccb0');rect(188,26,16,4,'#d6ccb0');
      rect(62,145,300,8,'#8d7551');for(let x=62;x<370;x+=26)rect(x,134,4,32,'#c8b68a');
    }
    if(scene.crossing||['river','gorge'].includes(scene.kind)){
      if(scene.kind==='gorge'){rect(0,106,204,60,'#3f4e47');rect(19,90,150,24,'#536355');rect(648,89,152,73,'#4c584e');rect(680,70,120,29,'#63715e');}
      // River runs from the horizon toward the viewer, cutting ACROSS the road.
      for(let y=102;y<260;y+=4){
        const bank=riverBanks(y);rect(bank.left-7,y,bank.right-bank.left+14,4,'#b1a47d');rect(bank.left,y,bank.right-bank.left,4,y%12?'#3c7680':'#4c8991');
        if(y%16===6)rect(bank.left+11,y,Math.max(8,(bank.right-bank.left)*.45),2,'#92bbb2');
      }
      // The trail ends at the near bank; the wagon waits on dry land to the right.
      const bank=riverBanks(180);rect(bank.right+10,181,35,4,'#ceb680');
    }
    return scene;
  }
  const api={kinds,resolve,stopKind,wagonX,riverBanks,background};
  if(typeof module!=='undefined'&&module.exports)module.exports=api;else root.TrailScenes=api;
})(typeof globalThis!=='undefined'?globalThis:this);
