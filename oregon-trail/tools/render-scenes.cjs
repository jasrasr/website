// Development-only preview; the game itself has no canvas package dependency.
const fs=require('node:fs'),vm=require('node:vm'),path=require('node:path'),{createCanvas}=require('@napi-rs/canvas');
const root=path.resolve(__dirname,'..'),scenes=require('../scenes.js'),content=require('../game-content.json');
const code=fs.readFileSync(path.join(root,'game.js'),'utf8');
const draw=code.slice(code.indexOf('  function draw(){'),code.indexOf('  function setStory'));
const panels=[{id:null,name:'Independence, Missouri',mile:0},...content.stops];
const sheet=createCanvas(1280,Math.ceil(panels.length/2)*250),sc=sheet.getContext('2d');
sc.fillStyle='#121811';sc.fillRect(0,0,sheet.width,sheet.height);
for(let i=0;i<panels.length;i++){
  const stop=panels[i],canvas=createCanvas(800,260),ctx=canvas.getContext('2d');canvas.setAttribute=()=>{};
  const s={miles:stop.mile,landmark:Math.max(0,content.stops.findIndex(x=>x.id===stop.id)+1),health:100,party:5,gameOver:false};
  vm.runInNewContext('function pixelRect(x,y,w,h,c){ctx.fillStyle=c;ctx.fillRect(x,y,w,h)}\n'+draw+'\ndraw();',{ctx,canvas,s,content,pendingStop:stop.id,TrailScenes:scenes,Math});
  const x=i%2*640,y=Math.floor(i/2)*250;sc.imageSmoothingEnabled=false;sc.drawImage(canvas,x+15,y+12,610,198);
  sc.fillStyle='#e5ddc3';sc.font='15px monospace';sc.fillText(stop.name,x+16,y+236);
}
fs.mkdirSync(path.join(root,'docs'),{recursive:true});
fs.writeFileSync(path.join(root,'docs/scenery-preview.png'),sheet.toBuffer('image/png'));
