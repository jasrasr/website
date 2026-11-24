/* AI Model Timeline: independent project and graphic versions */
const safe=(s)=>String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const dl=(blob,name)=>{const a=document.createElement('a'),u=URL.createObjectURL(blob);a.href=u;a.download=name;document.body.append(a);a.click();a.remove();setTimeout(()=>URL.revokeObjectURL(u),1500);};
(async()=>{try{
const r=await fetch('data/models.json',{cache:'no-store'});if(!r.ok)throw new Error('Data unavailable');const d=await r.json();const ms=[...d.models].sort((a,b)=>a.release_date.localeCompare(b.release_date)||a.platform.localeCompare(b.platform)||a.model.localeCompare(b.model));
const table=document.querySelector('#modelRows');
const tableEl=table.closest('table');
const collator=new Intl.Collator(undefined,{numeric:true,sensitivity:'base'});
let sort={key:'release_date',direction:'asc'};
const sortedRows=()=>[...ms].sort((a,b)=>{
 const av=String(a[sort.key]??''),bv=String(b[sort.key]??'');
 // ISO-style YYYY-MM and YYYY-MM-DD sort in proper chronological order.
 const primary=sort.key==='release_date'?av.localeCompare(bv):collator.compare(av,bv);
 const tie=a.release_date.localeCompare(b.release_date)||collator.compare(a.platform,b.platform)||collator.compare(a.model,b.model);
 return (sort.direction==='asc'?1:-1)*primary||tie;
});
const drawRows=()=>{
 table.innerHTML=sortedRows().map(m=>'<tr><td>'+safe(m.release_date)+'</td><td>'+safe(m.platform)+'</td><td>'+safe(m.model)+'</td><td>'+safe(m.status)+'</td><td>'+safe(m.access)+'</td><td><a target="_blank" rel="noopener noreferrer" href="'+safe(m.source_url)+'">Source</a></td></tr>').join('');
 tableEl.querySelectorAll('thead th').forEach(th=>{
  const btn=th.querySelector('.sort-header'),active=btn?.dataset.sort===sort.key;
  th.setAttribute('aria-sort',active?(sort.direction==='asc'?'ascending':'descending'):'none');
  const arrow=btn?.querySelector('.sort-arrow');if(arrow)arrow.textContent=active?(sort.direction==='asc'?'▲':'▼'):'↕';
 });
};
tableEl.querySelectorAll('.sort-header').forEach(btn=>btn.addEventListener('click',()=>{
 const key=btn.dataset.sort;
 sort={key,direction:sort.key===key&&sort.direction==='asc'?'desc':'asc'};
 drawRows();
}));
drawRows();
const graphicVersion=d.graphic_version||d.project_version;
const graphicDate=d.graphic_created||d.updated;
const timeline=document.querySelector('#timeline');
const yearControls=document.querySelector('#timelineYears');
const collapsedYears=new Set();
const graphicMeta={version:graphicVersion,created:graphicDate};
const drawTimeline=()=>{
 if(collapsedYears.size===yearControls.childElementCount && collapsedYears.size>0){
  timeline.innerHTML='<p class="empty">All years are collapsed. Select a year to expand it.</p>';
  return;
 }
 timeline.innerHTML=renderTimelineSvg(ms,graphicMeta,collapsedYears);
};
drawTimeline();
for(const label of timeline.querySelectorAll('.yr')){
 const year=Number(label.textContent);
 const button=document.createElement('button');
 button.type='button';
 button.textContent=String(year);
 button.setAttribute('aria-controls','timeline');
 button.setAttribute('aria-expanded','true');
 button.addEventListener('click',()=>{
  const expanded=collapsedYears.delete(year);
  if(!expanded)collapsedYears.add(year);
  button.setAttribute('aria-expanded',String(expanded));
  drawTimeline();
 });
 yearControls.append(button);
}
document.querySelector('#count').textContent=ms.length+' named model milestones';
const latestRelease=await fetch('archive/manifest.json',{cache:'no-store'}).then(r=>{if(!r.ok)throw Error('Archive manifest unavailable');return r.json()}).then(m=>m.releases.find(x=>x.version===graphicVersion));
if(!latestRelease)throw new Error('Current archived graphic not found');
document.querySelector('#svgBtn').onclick=()=>downloadArchived(latestRelease,'svg');
document.querySelector('#pngBtn').onclick=()=>downloadArchived(latestRelease,'png');
}catch(err){document.querySelector('#timeline').textContent='Unable to load release data: '+err.message;}})();
fetch('archive/manifest.json',{cache:'no-store'}).then(r=>r.json()).then(data=>{
const history=document.querySelector('#history');if(!history)return;
const safeText=t=>String(t??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
history.innerHTML=(data.releases||[]).map(r=>'<article class="history-item"><strong>Graphic v'+safeText(r.version)+'</strong> · '+safeText(r.created)+' · '+safeText(r.models)+' models<p>'+safeText(r.reason)+'</p><div class="history-actions"><button type="button" class="archive-download" data-version="'+safeText(r.version)+'" data-format="svg">Download SVG</button><button type="button" class="archive-download" data-version="'+safeText(r.version)+'" data-format="png">Download PNG</button><a href="'+encodeURI(r.dataset)+'">JSON snapshot</a></div></article>').join('');
history.addEventListener('click',event=>{const button=event.target.closest('.archive-download');if(!button)return;const release=(data.releases||[]).find(r=>r.version===button.dataset.version);if(release)downloadArchived(release,button.dataset.format,button);});
}).catch(()=>{});

/**
 * Download the immutable SVG bytes from archive/; PNG is converted from
 * those archived SVG bytes only. Neither operation writes to the repository.
 */
const getArchiveSvg=async release=>{
 if(!/^archive\/v\d+\.\d+\.\d+\/ai-model-timeline-v\d+\.\d+\.\d+-\d{4}-\d{2}-\d{2}\.svg$/.test(release.graphic))throw Error('Invalid archive path');
 const response=await fetch(release.graphic,{cache:'no-store'});
 if(!response.ok)throw Error('Archived graphic could not be loaded');
 return response.text();
};
async function downloadArchived(release,format,button){
 const target=button||document.querySelector(format==='svg'?'#svgBtn':'#pngBtn');
 const prior=target?.textContent;
 if(target){target.disabled=true;target.textContent='Preparing '+format.toUpperCase()+'…';}
 try{
  const source=await getArchiveSvg(release);
  const base='ai-model-timeline-v'+release.version+'-'+release.created;
  if(format==='svg'){
   dl(new Blob([source],{type:'image/svg+xml;charset=utf-8'}),base+'.svg');
  }else if(format==='png'){
   const url=URL.createObjectURL(new Blob([source],{type:'image/svg+xml;charset=utf-8'}));
   try{
    const image=await new Promise((resolve,reject)=>{const img=new Image();img.onload=()=>resolve(img);img.onerror=()=>reject(Error('Cannot render archived SVG'));img.src=url;});
    const canvas=document.createElement('canvas');canvas.width=image.naturalWidth;canvas.height=image.naturalHeight;
    const ctx=canvas.getContext('2d');if(!ctx)throw Error('PNG rendering unavailable');
    ctx.drawImage(image,0,0);
    const blob=await new Promise(resolve=>canvas.toBlob(resolve,'image/png'));
    if(!blob)throw Error('PNG conversion failed');
    dl(blob,base+'.png');
   }finally{URL.revokeObjectURL(url);}
  }else throw Error('Unsupported download format');
 }catch(error){alert('Download failed: '+error.message);}
 finally{if(target){target.disabled=false;target.textContent=prior;}}
}
