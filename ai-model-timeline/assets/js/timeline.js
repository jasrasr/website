/* AI Model Timeline v1.0.0: renderTimelineSvg is loaded from render-core.js */
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
const svg=renderTimelineSvg(ms);document.querySelector('#timeline').innerHTML=svg;
document.querySelector('#count').textContent=ms.length+' named model milestones';
document.querySelector('#svgBtn').onclick=()=>dl(new Blob([svg],{type:'image/svg+xml;charset=utf-8'}),'ai-model-timeline-v'+d.project_version+'-'+d.updated+'.svg');
document.querySelector('#pngBtn').onclick=()=>{const img=new Image(),u=URL.createObjectURL(new Blob([svg],{type:'image/svg+xml'}));img.onload=()=>{const cn=document.createElement('canvas');cn.width=img.naturalWidth;cn.height=img.naturalHeight;cn.getContext('2d').drawImage(img,0,0);cn.toBlob(b=>{if(b)dl(b,'ai-model-timeline-v'+d.project_version+'-'+d.updated+'.png');URL.revokeObjectURL(u);},'image/png');};img.src=u;};
}catch(err){document.querySelector('#timeline').textContent='Unable to load release data: '+err.message;}})();
fetch('archive/manifest.json',{cache:'no-store'}).then(r=>r.json()).then(data=>{
const history=document.querySelector('#history');if(!history)return;
const safeText=t=>String(t??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
history.innerHTML=(data.releases||[]).map(r=>'<article class="history-item"><strong>v'+safeText(r.version)+'</strong> · '+safeText(r.created)+' · '+safeText(r.models)+' models<p>'+safeText(r.reason)+'</p><a href="'+encodeURI(r.graphic)+'">SVG graphic</a> · <a href="'+encodeURI(r.dataset)+'">JSON snapshot</a></article>').join('');
}).catch(()=>{});
