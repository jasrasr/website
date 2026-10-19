/*
File Name     : tools/render-scenes.cjs
Author        : Jason Lamb (with help from ChatGPT)
Description   : Render actual game scenes into a PNG preview with native file metadata.
Revision      : 1.0.0
Created Date  : 2026-10-19
Modified Date : 2026-10-19
Changelog     :
  Rev 1.0.0 - Established independent file revision tracking; Render actual game scenes into a PNG preview with native file metadata.
*/
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
// PNG files support text chunks rather than source-code comments.
const imageHeader=[
  'File Name     : docs/scenery-preview.png',
  'Author        : Jason Lamb (with help from ChatGPT)',
  'Description   : Preview of all built-in Oregon Trail scenes.',
  'Revision      : 1.0.0',
  'Created Date  : 2026-10-19',
  'Modified Date : 2026-10-19',
  'Changelog     :',
  '  Rev 1.0.0 - Created scene preview and added PNG metadata.'
].join('\n');
function crc32(buffer){let crc=0xffffffff;for(const byte of buffer){crc^=byte;for(let i=0;i<8;i++)crc=(crc>>>1)^((crc&1)?0xedb88320:0);}return (crc^0xffffffff)>>>0;}
const png=sheet.toBuffer('image/png'),text=Buffer.from('Comment\0'+imageHeader,'latin1');
const type=Buffer.from('tEXt'),length=Buffer.alloc(4),checksum=Buffer.alloc(4);
length.writeUInt32BE(text.length);checksum.writeUInt32BE(crc32(Buffer.concat([type,text])));
fs.writeFileSync(path.join(root,'docs/scenery-preview.png'),Buffer.concat([png.subarray(0,33),length,type,text,checksum,png.subarray(33)]));
