/*
File Name     : tests/metadata.test.cjs
Author        : Jason Lamb (with help from ChatGPT)
Description   : Audit native file headers, project revision display, and offline version assets.
Revision      : 1.0.2
Created Date  : 2026-10-19
Modified Date : 2026-10-19
Changelog     :
  Rev 1.0.2 - Validated hash-comment headers and versioned changelog links.
  Rev 1.0.1 - Allow future independent file and project revisions in metadata checks.
  Rev 1.0.0 - Established independent file revision tracking; Audit native file headers, project revision display, and offline version assets.
*/
const test=require('node:test'),assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path');
const root=path.resolve(__dirname,'..');
const labels=['File Name','Author','Description','Revision','Created Date','Modified Date','Changelog'];
function walk(dir){return fs.readdirSync(dir,{withFileTypes:true}).flatMap(entry=>entry.isDirectory()?walk(path.join(dir,entry.name)):[path.join(dir,entry.name)]);}
function pngComment(buffer){let i=8;while(i<buffer.length){const n=buffer.readUInt32BE(i),type=buffer.toString('ascii',i+4,i+8),data=buffer.subarray(i+8,i+8+n);if(type==='tEXt'&&data.toString('latin1').startsWith('Comment\0'))return data.subarray(8).toString('latin1');i+=12+n;}throw Error('PNG header comment missing');}
test('Every project file has its native header and complete revision metadata',()=>{
  for(const full of walk(root)){
    const relative=path.relative(root,full).split(path.sep).join('/'),ext=path.extname(full);
    let header;
    if(ext==='.png')header=pngComment(fs.readFileSync(full));
    else if(ext==='.json'){const data=JSON.parse(fs.readFileSync(full,'utf8'));assert.equal(Object.keys(data)[0],'_header');header=JSON.stringify(data._header);assert.equal(data._header['File Name'],relative);}
    else if(relative==='.htaccess'){const source=fs.readFileSync(full,'utf8');assert.ok(source.startsWith('# File Name'));header=source.split('\n').filter(line=>line.startsWith('#')).join('\n');}
    else{
      const source=fs.readFileSync(full,'utf8');const opener=['.md','.html'].includes(ext)?'<!--':'/*',closer=opener==='<!--'?'-->':'*/';
      assert.ok(source.startsWith(opener),relative+' needs native comment header');header=source.slice(0,source.indexOf(closer));
    }
    for(const label of labels)assert.ok(header.includes(label),relative+' missing '+label);
    assert.ok(header.includes(relative),relative+' missing file path');
    assert.ok(header.includes('Jason Lamb (with help from ChatGPT)'),relative+' missing author');
    assert.match(header,/Revision[\s" :]+[1-9]\d*\.\d+\.\d+/);assert.match(header,/\d{4}-\d{2}-\d{2}/);
  }
});
test('Project revision is displayed from the shared version and cached for offline play',()=>{
  const version=require('../version.js');assert.match(version.revision,/^[1-9]\d*\.\d+\.\d+$/);assert.match(version.updated,/^\d{4}-\d{2}-\d{2}$/);
  const html=fs.readFileSync(path.join(root,'index.html'),'utf8'),game=fs.readFileSync(path.join(root,'game.js'),'utf8'),worker=fs.readFileSync(path.join(root,'sw.js'),'utf8');
  assert.ok(html.includes('id="project-version"'));assert.ok(html.includes('href="CHANGELOG.md?v='+version.revision+'"'));
  assert.ok(game.includes('TrailVersion.revision'));assert.ok(game.includes('TrailVersion.updated'));
  assert.ok(worker.includes("'version.js'"));assert.ok(worker.includes("'CHANGELOG.md'"));
});
