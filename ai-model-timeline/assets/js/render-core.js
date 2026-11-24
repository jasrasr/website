const renderTimelineSvg=function renderSvg(models, meta={version:'1.0.0',created:'2026-11-23'}, collapsedYears=new Set(), measureLabel){
  const palette={"OpenAI":"#12c889","Anthropic":"#ff9852","Google DeepMind":"#4e9cff","xAI":"#bc63ff","Meta":"#42bcff","Mistral":"#ffba47","DeepSeek":"#6795ff","Microsoft AI / MAI":"#bd78f8","Moonshot AI / Kimi":"#42dfdb"};
  const esc=s=>String(s??"").replace(/[&<>"']/g,c=>({"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&apos;"}[c]));
  const labs=Object.keys(palette);
  const lastYear=Math.max(2026,...models.map(m=>Number(m.release_date.slice(0,4))));
  const ys=Array.from({length:lastYear-2023+1},(_,i)=>2023+i).filter(year=>!collapsedYears.has(year));
  const qKeys=ys.flatMap(y=>[1,2,3,4].map(q=>`${y}-Q${q}`));
  const quarter=m=>`${m.release_date.slice(0,4)}-Q${Math.floor((Number(m.release_date.slice(5,7))-1)/3)+1}`;
  const groups=Object.fromEntries(labs.map(lab=>[lab,Object.fromEntries(qKeys.map(q=>[
    q,models.filter(m=>m.platform===lab&&quarter(m)===q).sort((a,b)=>a.release_date.localeCompare(b.release_date)||a.model.localeCompare(b.model))
  ]))]));
  const slots={};
  const widths={};
  for(const q of qKeys){
    const count=Math.max(1,...labs.map(lab=>groups[lab][q].length));
    if(!measureLabel){
      widths[q]=count*163+20;
      continue;
    }
    let offset=5;
    slots[q]=Array.from({length:count},(_,index)=>{
      const width=Math.ceil(Math.max(78,...labs.map(lab=>measureLabel(groups[lab][q][index]?.model??'')))+12);
      const slot={offset,width};
      offset+=width+6;
      return slot;
    });
    widths[q]=offset;
  }
  const starts={};let xx=208;for(const q of qKeys){starts[q]=xx;xx+=widths[q];}
  const W=xx+34,rowH=100,top=150,H=top+labs.length*rowH+86;
  const line=[];
  line.push(`<svg xmlns="http://www.w3.org/2000/svg" width="${W}" height="${H}" viewBox="0 0 ${W} ${H}" role="img" aria-label="AI model release timeline, 2023 through ${lastYear}"><rect width="${W}" height="${H}" fill="#091321"/><style>text{font-family:Arial,Helvetica,sans-serif;fill:#f5f8fd}.h{font-size:31px;font-weight:700}.sub{font-size:16px;fill:#a5b7cd}.lab{font-size:17px;font-weight:700}.yr{font-size:20px;font-weight:700}.qt{font-size:13px;fill:#eaf4ff}.model{font-size:12.5px;font-weight:700}.date{font-size:12px;fill:#c6d2df}.leg{font-size:12.5px;fill:#cbd9e8}</style>`);
  line.push(`<text x="24" y="43" class="h">Frontier AI Model Release Timeline</text><text x="25" y="69" class="sub">Single-source verified model inventory • 2023–${lastYear} • v${esc(meta.version)} • ${esc(meta.created)}</text>`);
  const yearColors={"2023":"#1364c7","2024":"#067d63","2025":"#b76b20","2026":"#5935ae"};
  for(const y of ys){const xs=starts[`${y}-Q1`], total=[1,2,3,4].reduce((a,q)=>a+widths[`${y}-Q${q}`],0);line.push(`<rect x="${xs}" y="86" width="${total}" height="34" fill="${yearColors[y]||'#245b76'}"/><text class="yr" x="${xs+total/2}" y="109" text-anchor="middle">${y}</text>`);}
  for(const q of qKeys){const xs=starts[q];line.push(`<line x1="${xs}" y1="120" x2="${xs}" y2="${H-45}" stroke="#294158" stroke-width="1"/><text x="${xs+widths[q]/2}" y="138" text-anchor="middle" class="qt">${q.slice(-2)}</text>`);}
  line.push(`<line x1="${W-34}" y1="120" x2="${W-34}" y2="${H-45}" stroke="#294158"/>`);
  labs.forEach((lab,i)=>{const y=top+i*rowH, c=palette[lab];line.push(`<text x="14" y="${y+27}" class="lab">${esc(lab)}</text><line x1="205" y1="${y+40}" x2="${W-34}" y2="${y+40}" stroke="${c}" opacity=".6" stroke-width="2"/>`);
    for(const q of qKeys){
      groups[lab][q].forEach((m,j)=>{const slot=slots[q]?.[j],x=starts[q]+(slot?.offset??11+j*163),pillW=slot?.width??148,py=y+13; const dash=m.access==="restricted"||m.access==="mixed"?'stroke-dasharray="5 3"':'';
        line.push(`<rect x="${x}" y="${py}" rx="9" width="${pillW}" height="39" fill="${c}" fill-opacity=".2" stroke="${c}" stroke-width="1.7" ${dash}/><text x="${x+pillW/2}" y="${py+24}" text-anchor="middle" class="model">${esc(m.model)}</text><text x="${x+pillW/2}" y="${py+68}" text-anchor="middle" class="date">${esc(m.release_date)}</text>`);
      });
    }
  });
  if(measureLabel){
    line.push(`<text x="24" y="${H-35}" class="leg">Solid = documented public release    Dashed = restricted or mixed access<tspan x="24" dy="16">Month-only dates are intentionally not assigned an invented day.</tspan></text></svg>`);
  }else{
    line.push(`<text x="24" y="${H-19}" class="leg">Solid = documented public release    Dashed = restricted or mixed access    Month-only dates are intentionally not assigned an invented day.</text></svg>`);
  }
  return line.join("");
};
if(typeof module!=='undefined'&&module.exports){module.exports=renderTimelineSvg;}
else if(typeof window!=='undefined'){window.renderTimelineSvg=renderTimelineSvg;}
