#!/usr/bin/env node
'use strict';
const fs=require('fs'),path=require('path');
const root=path.resolve(__dirname,'..');
const d=JSON.parse(fs.readFileSync(path.join(root,'data/models.json'),'utf8'));
const manifest=JSON.parse(fs.readFileSync(path.join(root,'archive/manifest.json'),'utf8'));
const release=manifest.releases.find(x=>x.version===manifest.latest);
if(!release)throw Error('Latest version missing from manifest');
const snapshot=JSON.parse(fs.readFileSync(path.join(root,release.dataset),'utf8'));
const graphic=fs.readFileSync(path.join(root,release.graphic),'utf8');
const compare=JSON.stringify;
if(compare(snapshot)!==compare(d))throw Error('Live dataset does not match archived snapshot. Create a new release.');
const count=(graphic.match(/ rx="9" width="148"/g)||[]).length;
if(count!==d.models.length)throw Error('SVG capsule count differs from table data');
const ids=d.models.map(x=>x.id),pairs=d.models.map(x=>x.platform+'|'+x.model);
if(new Set(ids).size!==ids.length||new Set(pairs).size!==pairs.length)throw Error('Duplicate model or ID');
for(const m of d.models){if(!graphic.includes(m.model.replaceAll('&','&amp;')))throw Error('Missing SVG model: '+m.model);}
if(d.project_version!==release.version)throw Error('Version mismatch');
console.log('PASS: '+count+' models, identical live/archive JSON, unique IDs/models, matching release version.');
