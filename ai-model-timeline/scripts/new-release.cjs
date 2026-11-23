#!/usr/bin/env node
/**
 * New-AIModelTimelineRelease equivalent for Node.js.
 * Usage:
 *   node scripts/new-release.cjs --version 1.0.1 --reason "Verified new model releases" --changes "Added model X"
 * Optional: --date YYYY-MM-DD (defaults to current local date, used ONLY for graphic creation date).
 * Archives are append-only. Never overwrite an existing release.
 */
'use strict';
const fs=require('fs'),path=require('path'),render=require('../assets/js/render-core.js');
const root=path.resolve(__dirname,'..');
const args=process.argv.slice(2);const flag=n=>{const i=args.indexOf('--'+n);return i>=0?args[i+1]:null;};
const version=flag('version'),reason=flag('reason'),changes=flag('changes')||'See source data diff.';
const date=flag('date')||new Date().toLocaleDateString('en-CA',{timeZone:'America/New_York'});
if(!/^\d+\.\d+\.\d+$/.test(version||''))throw Error('Specify --version X.Y.Z');
if(!/^\d{4}-\d{2}-\d{2}$/.test(date))throw Error('Invalid --date');
if(!reason||reason.trim().length<8)throw Error('A meaningful --reason is required');
const dir=path.join(root,'archive','v'+version);
if(fs.existsSync(dir))throw Error('Refusing to overwrite existing release '+version);
const dataPath=path.join(root,'data','models.json');
const manifestPath=path.join(root,'archive','manifest.json');
const changelogPath=path.join(root,'CHANGELOG.md');
const d=JSON.parse(fs.readFileSync(dataPath,'utf8'));const manifest=JSON.parse(fs.readFileSync(manifestPath,'utf8'));
if(manifest.releases.some(x=>x.version===version))throw Error('Version already in manifest');
const models=d.models||[],keys=new Set(),ids=new Set();
for(const m of models){
 if(!/^\d{4}-\d{2}(-\d{2})?$/.test(m.release_date)||Number(m.release_date.slice(5,7))>12||Number(m.release_date.slice(5,7))<1)throw Error('Invalid model date: '+m.model);
 if(m.release_date.length===10&&!Number.isFinite(Date.parse(m.release_date)))throw Error('Invalid calendar day: '+m.model);
 if(!m.model||!m.platform||!m.source_url?.startsWith('https://'))throw Error('Incomplete model: '+JSON.stringify(m));
 const key=m.platform+'|'+m.model;if(keys.has(key)||ids.has(m.id))throw Error('Duplicate model/id: '+key);keys.add(key);ids.add(m.id);
}
if(!models.length)throw Error('Refusing to release empty dataset');
d.project_version=version;d.updated=date;
const svg=render(models);
const capsuleCount=(svg.match(/ rx="9" width="148"/g)||[]).length;
if(capsuleCount!==models.length)throw Error('SVG capsule mismatch: '+capsuleCount+' vs '+models.length);
const svgName='ai-model-timeline-v'+version+'-'+date+'.svg';
const jsonName='models-v'+version+'-'+date+'.json';
manifest.latest=version;
manifest.releases.unshift({version,created:date,reason,changes,models:models.length,
 graphic:'archive/v'+version+'/'+svgName,
 dataset:'archive/v'+version+'/'+jsonName,
 png_export_filename:'ai-model-timeline-v'+version+'-'+date+'.png',png_committed:false});
const oldLog=fs.readFileSync(changelogPath,'utf8');
const entry='## ['+version+'] — '+date+'\n\n**Graphic:** `archive/v'+version+'/'+svgName+'`\n\n**Data snapshot:** `archive/v'+version+'/'+jsonName+'`\n\n**Why created:** '+reason+'\n\n**Changes:** '+changes+'\n\n**Models:** '+models.length+'\n\n';
const updatedLog=oldLog.replace(/^(# [^\n]+\n\n)/,'$1'+entry);
fs.mkdirSync(dir,{recursive:false});
fs.writeFileSync(path.join(dir,svgName),svg,'utf8');
fs.writeFileSync(path.join(dir,jsonName),JSON.stringify(d,null,2)+'\n','utf8');
fs.writeFileSync(dataPath,JSON.stringify(d,null,2)+'\n','utf8');
fs.writeFileSync(manifestPath,JSON.stringify(manifest,null,2)+'\n','utf8');
fs.writeFileSync(changelogPath,updatedLog,'utf8');
console.log('Created '+svgName+' and '+jsonName+' ('+models.length+' records).');
