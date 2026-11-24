'use strict';
const fs=require('fs');
const path=require('path');
const crypto=require('crypto');
const root=path.resolve(__dirname,'..');
const renderer=require('../assets/js/render-core.js');
const readJSON=file=>JSON.parse(fs.readFileSync(file,'utf8'));
const sha256=content=>crypto.createHash('sha256').update(content,'utf8').digest('hex');
const compareVersion=(a,b)=>{const x=a.split('.').map(Number),y=b.split('.').map(Number);for(let i=0;i<3;i++){if(x[i]!==y[i])return x[i]-y[i];}return 0;};
const validVersion=v=>/^\d+\.\d+\.\d+$/.test(v||'');
const baseline=(manifest,data)=>{
 const release=manifest.releases.find(r=>r.version===(data.graphic_version||manifest.latest));
 if(!release)throw Error('Current graphic release missing from manifest');
 const archive=path.join(root,release.graphic),snapshot=path.join(root,release.dataset);
 if(!fs.existsSync(archive)||!fs.existsSync(snapshot))throw Error('Current archive files missing');
 return {release,archive,snapshot};
};
function validateModels(models){
 if(!Array.isArray(models)||!models.length)throw Error('Cannot release an empty dataset');
 const ids=new Set(),pairs=new Set();
 for(const m of models){
  if(!m.id||!m.model||!m.platform||!m.source_url?.startsWith('https://'))throw Error('Incomplete model record: '+JSON.stringify(m));
  if(!/^\d{4}-\d{2}(-\d{2})?$/.test(m.release_date))throw Error('Invalid date format: '+m.model);
  if(+m.release_date.slice(5,7)<1||+m.release_date.slice(5,7)>12)throw Error('Invalid month: '+m.model);
  if(m.release_date.length===10 && new Date(m.release_date+'T00:00:00Z').toISOString().slice(0,10)!==m.release_date)throw Error('Invalid calendar date: '+m.model);
  const key=m.platform+'|'+m.model;
  if(ids.has(m.id)||pairs.has(key))throw Error('Duplicate ID or model: '+key);
  ids.add(m.id);pairs.add(key);
 }
}
const graphicSVG=(models,version,date)=>renderer(models,{version,created:date});
module.exports={fs,path,root,readJSON,sha256,compareVersion,validVersion,baseline,validateModels,graphicSVG};
