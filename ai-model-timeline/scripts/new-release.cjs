#!/usr/bin/env node
/**
 * Publish a project version without needlessly creating a new graphic.
 * node scripts/new-release.cjs --version 1.0.3 --reason "Improve table filters" --changes "..."
 * node scripts/new-release.cjs --version 1.1.0 --reason "New AI models" --changes "..."
 * --date YYYY-MM-DD overrides creation date (America/New_York by default).
 *
 * If rendered SVG bytes match the last archived SVG, release only project changes.
 * If any visible SVG content or renderer output differs, create a new immutable
 * graphic and matching data snapshot using the NEW project version.
 */
'use strict';
const {fs,path,root,readJSON,sha256,gitBlobSha,compareVersion,validVersion,baseline,validateModels,graphicSVG}=require('./release-utils.cjs');
const args=process.argv.slice(2);
const flag=name=>{const i=args.indexOf('--'+name);return i<0?null:args[i+1];};
const version=flag('version'),reason=flag('reason'),changes=flag('changes');
const date=flag('date')||new Intl.DateTimeFormat('en-CA',{timeZone:'America/New_York',year:'numeric',month:'2-digit',day:'2-digit'}).format(new Date());
if(!validVersion(version))throw Error('Specify --version X.Y.Z');
if(!/^\d{4}-\d{2}-\d{2}$/.test(date)||new Date(date+'T00:00:00Z').toISOString().slice(0,10)!==date)throw Error('Invalid --date YYYY-MM-DD');
if(!reason||reason.trim().length<8)throw Error('Provide a meaningful --reason');
if(!changes||!changes.trim())throw Error('Specify --changes for the changelog');
const dataPath=path.join(root,'data/models.json'),manifestPath=path.join(root,'archive/manifest.json'),logPath=path.join(root,'CHANGELOG.md');
const data=readJSON(dataPath),manifest=readJSON(manifestPath);
if(!validVersion(data.project_version)||compareVersion(version,data.project_version)<=0)throw Error('Project version must increase from '+data.project_version);
validateModels(data.models);
const {release,archive,snapshot}=baseline(manifest,data);
const existing=fs.readFileSync(archive,'utf8');
const oldData=readJSON(snapshot);
if(release.graphic_git_blob_sha&&gitBlobSha(existing)!==release.graphic_git_blob_sha)throw Error('Archived graphic Git blob checksum mismatch');
if(release.graphic_sha256&&sha256(existing)!==release.graphic_sha256)throw Error('Archived graphic SHA-256 mismatch');
const candidate=graphicSVG(data.models,release.version,release.created);
const visualChanged=sha256(candidate)!==sha256(existing);
if(visualChanged&&manifest.releases.some(r=>r.version===version))throw Error('Graphic version already exists');
const target=path.join(root,'archive','v'+version);
if(visualChanged&&fs.existsSync(target))throw Error('Refusing to overwrite existing graphic directory '+target);
const priorModels=JSON.stringify(data.models)!==JSON.stringify(oldData.models);
data.project_version=version;
data.updated=date;
if(priorModels)data.models_updated=date;
let newGraphic=null;
if(visualChanged){
 data.graphic_version=version;data.graphic_created=date;
 const svgName='ai-model-timeline-v'+version+'-'+date+'.svg',jsonName='models-v'+version+'-'+date+'.json';
 const svg=graphicSVG(data.models,version,date);
 newGraphic={version,created:date,reason,changes,models:data.models.length,
  graphic:'archive/v'+version+'/'+svgName,
  dataset:'archive/v'+version+'/'+jsonName,
  graphic_sha256:sha256(svg),
  png_export_filename:'ai-model-timeline-v'+version+'-'+date+'.png',png_committed:false};
 manifest.latest=version;
 manifest.latest_graphic_version=version;
 manifest.releases.unshift(newGraphic);
} else {
 data.graphic_version=release.version;
 data.graphic_created=release.created;
 manifest.latest_graphic_version=release.version;
}
manifest.project_version=version;
const header='## ['+version+'] — '+date+(visualChanged?' (graphic release)':' (project-only; graphic unchanged)')+'\n';
const detail='\n**Why:** '+reason+'\n\n**Changes:** '+changes+'\n\n**Graphic:** '+(visualChanged?'New archive `'+newGraphic.graphic+'`':'Reused `'+release.graphic+'`; no new graphic generated')+'\n\n';
const log=fs.readFileSync(logPath,'utf8').replace(/^(# [^\n]+\n\n)/,'$1'+header+detail);
// Render and validate everything before writing files.
const dataText=JSON.stringify(data,null,2)+'\n',manifestText=JSON.stringify(manifest,null,2)+'\n';
if(visualChanged){
 const svg=graphicSVG(data.models,version,date);
 fs.mkdirSync(target,{recursive:false});
 fs.writeFileSync(path.join(root,newGraphic.graphic),svg,'utf8');
 fs.writeFileSync(path.join(root,newGraphic.dataset),dataText,'utf8');
}
fs.writeFileSync(dataPath,dataText,'utf8');
fs.writeFileSync(manifestPath,manifestText,'utf8');
fs.writeFileSync(logPath,log,'utf8');
console.log(visualChanged?'NEW GRAPHIC '+newGraphic.graphic:'NO GRAPHIC CHANGE; kept '+release.graphic);
console.log('Project v'+version+'; graphic v'+data.graphic_version+'; model records '+data.models.length);
