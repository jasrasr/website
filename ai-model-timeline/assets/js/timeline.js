const DATA='data/models.json';
let svgText='';
const esc=s=>String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
fetch(DATA).then(r=>r.json()).then(data=>render(data.models||[])).catch(e=>document.querySelector('#timeline').textContent='Unable to load model data.');

function render(models){
  const rows=document.querySelector('#modelRows');
  rows.innerHTML=models.map(m=>`<tr><td>${esc(m.release_date)}</td><td>${esc(m.platform)}</td><td>${esc(m.model)}</td><td>${esc(m.status)}</td><td>${esc(m.access||'')}</td><td>${m.source_url?`<a href="${esc(m.source_url)}" target="_blank" rel="noopener">source</a>`:''}</td></tr>`).join('');
  if(!models.length){ rows.innerHTML='<tr><td colspan="6" class="empty">Historical dataset audit in progress.</td></tr>'; document.querySelector('#timeline').innerHTML='<div class="empty">No canonical graphic generated until the initial dataset audit is complete.</div>'; return; }
  const sorted=[...models].sort((a,b)=>a.release_date.localeCompare(b.release_date)||a.platform.localeCompare(b.platform));
  const platforms=[...new Set(sorted.map(x=>x.platform))];
  const width=1500,rowH=78,left=180,top=55,right=40,height=top+platforms.length*rowH+50;
  const dates=sorted.map(x=>new Date(x.release_date+'T00:00:00Z').getTime()),min=Math.min(...dates),max=Math.max(...dates),span=Math.max(1,max-min);
  const x=t=>left+(t-min)/(span)*(width-left-right);
  let s=`<svg xmlns="http://www.w3.org/2000/svg" width="${width}" height="${height}" viewBox="0 0 ${width} ${height}"><rect width="100%" height="100%" fill="#11151a"/><style>text{font-family:system-ui,-apple-system,Segoe UI,sans-serif;fill:#f4f6f8}.p{font-weight:700}.m{font-size:13px}.d{font-size:11px;fill:#aab2bd}.line{stroke:#303a46;stroke-width:1}</style>`;
  platforms.forEach((p,i)=>{const y=top+i*rowH;s+=`<text class="p" x="14" y="${y+7}">${esc(p)}</text><line class="line" x1="${left}" y1="${y}" x2="${width-right}" y2="${y}"/>`;});
  sorted.forEach(m=>{const i=platforms.indexOf(m.platform),xx=x(new Date(m.release_date+'T00:00:00Z').getTime()),yy=top+i*rowH;const dash=m.status==='announced'||m.status==='pending'?' stroke-dasharray="4 3"':'';s+=`<circle cx="${xx}" cy="${yy}" r="6" fill="#7cb7ff" stroke="#11151a" stroke-width="2"${dash}/><text class="m" x="${xx+9}" y="${yy-8}">${esc(m.model)}</text><text class="d" x="${xx+9}" y="${yy+10}">${esc(m.release_date)}</text>`;});
  s+='</svg>'; svgText=s;document.querySelector('#timeline').innerHTML=s;
}
function filename(ext){return `ai-model-timeline-v${window.TIMELINE_VERSION}-${window.TIMELINE_DATE}.${ext}`;}
document.querySelector('#svgBtn').onclick=()=>{if(!svgText)return;download(new Blob([svgText],{type:'image/svg+xml'}),filename('svg'));};
document.querySelector('#pngBtn').onclick=()=>{if(!svgText)return;const img=new Image(),u=URL.createObjectURL(new Blob([svgText],{type:'image/svg+xml'}));img.onload=()=>{const c=document.createElement('canvas');c.width=img.width;c.height=img.height;c.getContext('2d').drawImage(img,0,0);c.toBlob(b=>download(b,filename('png')));URL.revokeObjectURL(u)};img.src=u;};
function download(blob,name){const a=document.createElement('a');a.href=URL.createObjectURL(blob);a.download=name;a.click();setTimeout(()=>URL.revokeObjectURL(a.href),1000);}
