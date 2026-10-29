/*
 * File Name: app.js
 * Author: Jason Lamb (with help from ChatGPT)
 * Description: Accessible charts, per-lot history, sale simulation and private ledger controls.
 * Revision: 1.0.0
 * Created Date: 2026-10-29
 * Modified Date: 2026-10-29
 * Changelog: 1.0.0 - Initial mobile dashboard, split-aware tax lot charts and CSV review.
 */
'use strict';
const $ = s => document.querySelector(s);
let state={events:[],analysis:{holdings:{},series:{},totals:{},missing_prices:[]},csrf:''};
let selectedTicker='', selectedLot='',csvPreviewKey='';
const svgNS='http://www.w3.org/2000/svg';
function el(tag,cls,text) {const n=document.createElement(tag);if(cls)n.className=cls;if(text!==undefined)n.textContent=String(text);return n;}
function cell(tr,value,cls) {const td=el('td',cls,value);tr.append(td);return td;}
function money(n) {return n===null || n===undefined || !Number.isFinite(Number(n))?'—':new Intl.NumberFormat('en-US',{style:'currency',currency:'USD',maximumFractionDigits:2}).format(Number(n));}
function qty(n) {return Number(n).toLocaleString('en-US',{maximumFractionDigits:8});}
function percent(n) {return n===null || n===undefined || !Number.isFinite(Number(n))?'—':Number(n).toFixed(2)+'%';}
function day(d) {return new Date(d+'T12:00:00').toLocaleDateString('en-US',{month:'short',day:'numeric',year:'numeric'});}
function colorize(n) {return n===null||n===undefined?'':Number(n)>=0?'positive':'negative';}
function notice(message,error=false) {const node=$('#notice');node.textContent=message;node.className='notice'+(error?' error':'');}
function formBody(action) {const fd=new FormData();fd.set('csrf',state.csrf);fd.set('action',action);return fd;}
async function api(body) {
 const response=await fetch('?api=1',body?{method:'POST',credentials:'same-origin',body}:{credentials:'same-origin'});
 let json;try{json=await response.json();}catch(_){throw new Error('Server did not return a valid JSON response.');}
 if(!response.ok||!json.success)throw new Error(json.message||'Request failed.');
 return json.data;
}
function svgEl(tag,attrs,text) {
 const node=document.createElementNS(svgNS,tag);
 for(const [k,v] of Object.entries(attrs||{}))node.setAttribute(k,String(v));
 if(text!==undefined)node.textContent=String(text);
 return node;
}
function lineChart(selector,points,keys,labels) {
 const host=$(selector);host.replaceChildren();
 if(!points.length){host.append(el('p','empty','Add historical price observations to display a growth chart.'));return;}
 const w=720,h=260,left=66,right=18,top=18,bottom=42;
 const values=points.flatMap(p=>keys.map(k=>p[k])).filter(n=>typeof n==='number'&&Number.isFinite(n));
 if(!values.length){host.append(el('p','empty','No valued lots are available for this period.'));return;}
 let min=Math.min(0,...values),max=Math.max(0,...values);
 if(max-min<0.000001){max+=1;min-=1;}
 const sx=i=>left+(w-left-right)*(points.length===1?.5:i/(points.length-1));
 const sy=v=>top+(max-v)/(max-min)*(h-top-bottom);
 const svg=svgEl('svg',{viewBox:'0 0 '+w+' '+h,role:'img','aria-label':labels.join(' and ')+' across recorded market price dates'});
 for(let i=0;i<=4;i++){const y=top+i*(h-top-bottom)/4;svg.append(svgEl('line',{x1:left,y1:y,x2:w-right,y2:y,class:'gridline'}));const v=max-i*(max-min)/4;svg.append(svgEl('text',{x:left-9,y:y+4,'text-anchor':'end',class:'axistext'},money(v)));}
 const start=points[0].date,end=points[points.length-1].date;
 svg.append(svgEl('text',{x:left,y:h-12,class:'axistext'},day(start)));
 if(end!==start)svg.append(svgEl('text',{x:w-right,y:h-12,'text-anchor':'end',class:'axistext'},day(end)));
 keys.forEach((key,k)=>{
  const fill=k===0?'#30b39b':'#8d9bd0';
  const path=points.map((p,i)=>(i===0?'M':'L')+sx(i).toFixed(2)+','+sy(p[key]).toFixed(2)).join(' ');
  svg.append(svgEl('path',{d:path,fill:'none',stroke:fill,'stroke-width':3,'stroke-linejoin':'round','stroke-linecap':'round'}));
  points.forEach((p,i)=>{if(i===0||i===points.length-1||points.length<13){
   const dot=svgEl('circle',{cx:sx(i),cy:sy(p[key]),r:4,fill});
   dot.append(svgEl('title',{},p.date+' · '+labels[k]+': '+money(p[key])));
   svg.append(dot);
  }});
 });
 host.append(svg);
 const legend=el('div','legend');
 labels.forEach((label,i)=>{const x=el('span',i===0?'legendA':'legendB',label);legend.append(x);});host.append(legend);
}
function barChart(ticker) {
 const host=$('#lotChart');host.replaceChildren();
 const lots=(state.analysis.holdings[ticker]?.lots||[]).filter(l=>l.shares>0.000000001&&l.unrealized!==null);
 if(!lots.length){host.append(el('p','empty','No open priced lots. Add a market price for this stock.'));return;}
 const sorted=[...lots].sort((a,b)=>a.date.localeCompare(b.date));
 const max=Math.max(1,...sorted.map(l=>Math.abs(l.unrealized)));
 const container=el('div','bars');
 sorted.forEach(l=>{const row=el('button','barrow');row.type='button';row.title='Inspect lot from '+day(l.date);
  row.addEventListener('click',()=>chooseLot(l.id));
  row.append(el('span','barlabel',day(l.date)));
  const track=el('span','bartrack');
  const mid=el('span','barmid');track.append(mid);
  const bar=el('span','bar '+colorize(l.unrealized));
  const width=Math.min(49,49*Math.abs(l.unrealized)/max);
  bar.style.width=width+'%';
  bar.style.left=l.unrealized>=0?'50%':(50-width)+'%';
  track.append(bar);row.append(track);row.append(el('b','baramount',money(l.unrealized)));
  container.append(row);
 });host.append(container);
}
function selectOptions(node,arr,label,value,emptyLabel) {
 const prior=node.value;node.replaceChildren();
 if(emptyLabel){const o=el('option',null,emptyLabel);o.value='';node.append(o);}
 for(const a of arr){const o=el('option',null,label(a));o.value=value(a);node.append(o);}
 if(arr.some(a=>value(a)===prior))node.value=prior;
}
function openLots() {return Object.values(state.analysis.holdings).flatMap(h=>h.lots).filter(l=>l.shares>0.000000001);}
function renderLots(ticker) {
 const lots=openLots().filter(l=>l.ticker===ticker).sort((a,b)=>a.date.localeCompare(b.date));
 const tbody=$('#lotsBody');tbody.replaceChildren();
 $('#lotSummary').textContent=lots.length+' open purchase lot'+(lots.length===1?'':'s');
 if(!lots.length){const tr=el('tr');const td=cell(tr,'No open tax lots for the selected stock.');td.colSpan=8;tbody.append(tr);}
 lots.forEach(l=>{
  const tr=el('tr',selectedLot===l.id?'selected':'');
  cell(tr,day(l.date));
  cell(tr,l.source==='reinvest'?'Reinvested dividend':'Purchase');
  cell(tr,qty(l.shares),'numeric');
  cell(tr,money(l.remaining_cost/l.shares),'numeric');
  cell(tr,money(l.market_value),'numeric');
  cell(tr,money(l.unrealized),'numeric '+colorize(l.unrealized));
  cell(tr,percent(l.roi_pct),'numeric '+colorize(l.roi_pct));
  const td=cell(tr,'');const button=el('button','smallbtn','Explore');button.type='button';button.addEventListener('click',()=>chooseLot(l.id));td.append(button);
  tbody.append(tr);
 });
 const manualLots=openLots().filter(l=>l.ticker===$('#addEvent [name=ticker]').value.trim().toUpperCase());
 selectOptions($('#manualLot'),manualLots,l=>day(l.date)+' · '+qty(l.shares)+' shares · '+l.id.slice(0,6),l=>l.id,'Choose a purchase lot');
 selectOptions($('#saleLot'),lots,l=>day(l.date)+' · '+qty(l.shares)+' shares · '+money(l.remaining_cost),l=>l.id,'Choose a purchase lot');
}
function chooseLot(id) {
 const lot=openLots().find(l=>l.id===id);if(!lot)return;
 selectedLot=id;selectedTicker=lot.ticker;$('#tickerSelect').value=lot.ticker;
 render();
 $('#selectedLot').hidden=false;
 $('#selectedName').textContent=lot.ticker+' · '+day(lot.date)+' · '+qty(lot.shares)+' shares open · '+percent(lot.roi_pct)+' total return';
 const points=(state.analysis.series[lot.ticker]||[]).filter(p=>p.date>=lot.date).map(p=>{
  const x=p.lots.find(x=>x.id===lot.id);
  return x?{date:p.date,value:x.value,cost:x.cost}:null;
 }).filter(Boolean);
 lineChart('#lotHistory',points,['value','cost'],['Lot market value','Remaining lot basis']);
 $('#saleLot').value=lot.id;$('#salePrice').value=state.analysis.holdings[lot.ticker]?.price||'';
 $('#selectedLot').scrollIntoView({behavior:'smooth',block:'nearest'});
}
function renderHoldings() {
 const host=$('#holdings');host.replaceChildren();
 Object.values(state.analysis.holdings).forEach(h=>{
  const card=el('div','holding');const heading=el('div','holdingTop');heading.append(el('b',null,h.ticker),el('strong',colorize(h.unrealized),money(h.value)));card.append(heading);
  card.append(el('p','subtle',qty(h.shares)+' shares · basis '+money(h.basis)+' · '+(h.price===null?'MISSING PRICE':'$'+qty(h.price)+'/share as of '+day(h.price_date))));
  const stats=el('div','holdingStats');stats.append(el('span',colorize(h.unrealized),'Unrealized '+money(h.unrealized)),el('span',colorize(h.realized),'Realized '+money(h.realized)));
  card.append(stats);card.append(el('p','subtle','Lifetime '+money(h.lifetime_profit)+' · Cash dividends '+money(h.dividends_cash)+' · Reinvested '+money(h.dividends_reinvested)+' · XIRR '+percent(h.xirr_pct)));
  host.append(card);
 });
 if(!host.childElementCount)host.append(el('p','empty','No transactions recorded yet.'));
}
function renderEvents() {
 const body=$('#eventsBody');body.replaceChildren();
 const rows=[...state.events].reverse().slice(0,100);
 $('#recordCount').textContent=state.events.filter(e=>!e.voided_at).length+' active events · newest 100 shown';
 rows.forEach(e=>{
  const tr=el('tr',e.voided_at?'voided':'');
  cell(tr,e.date);cell(tr,e.ticker);cell(tr,e.type+(e.voided_at?' (void)':''));
  cell(tr,['buy','reinvest','sell'].includes(e.type)?qty(e.quantity):e.type==='split'?(e.numerator+':'+e.denominator):'—','numeric');
  cell(tr,e.type==='price'?money(e.price):e.type==='split'?'—':money(e.amount),'numeric');
  cell(tr,e.note||'—');
  const td=cell(tr,'');
  if(!e.voided_at){
   const b=el('button','smallbtn danger','Void');b.type='button';
   b.addEventListener('click',async()=>{if(!confirm('Void this event? The original will be retained and all calculations recomputed.'))return;
    const fd=formBody('void');fd.set('id',e.id);
    try{await api(fd);await load();notice('Event voided and ledger recalculated.');}catch(err){notice(err.message,true);}
   });td.append(b);
  }
  body.append(tr);
 });
}
function render() {
 const t=state.analysis.totals;
 for(const [id,v] of [['marketValue',t.market_value],['lifetimeProfit',t.lifetime_profit],['unrealized',t.unrealized]]) {
  const node=$('#'+id);node.textContent=money(v);node.className=colorize(v);
 }
 $('#xirr').textContent=percent(t.xirr_pct);
 const tickers=Object.keys(state.analysis.holdings);
 if(!tickers.includes(selectedTicker))selectedTicker=tickers[0]||'';
 const selector=$('#tickerSelect');
 selectOptions(selector,tickers,x=>x,x=>x,'No stock');
 if(selectedTicker)selector.value=selectedTicker;
 const series=state.analysis.series[selectedTicker]||[];
 lineChart('#valueChart',series,['value','cost'],['Position market value','Remaining basis']);
 barChart(selectedTicker);renderLots(selectedTicker);renderHoldings();renderEvents();
 if(selectedLot && !openLots().some(l=>l.id===selectedLot)){selectedLot='';$('#selectedLot').hidden=true;}
}
async function load() {
 const d=await api();state={...state,events:d.events,analysis:d.analysis,csrf:d.csrf};render();
 const missing=d.analysis.missing_prices||[];
 if(missing.length)notice('Missing current price observations for '+missing.join(', ')+'. Portfolio totals and XIRR are withheld until priced.');
 else notice('Ledger loaded. Market values use the last manually recorded price per stock; check its observation date.');
}
function setEventFields() {
 const type=$('#eventType').value;
 document.querySelectorAll('[data-fields]').forEach(label=>label.hidden=!label.dataset.fields.split(' ').includes(type));
}
function updateManualLots() {
 const ticker=$('#addEvent [name=ticker]').value.trim().toUpperCase();
 selectOptions($('#manualLot'),openLots().filter(l=>l.ticker===ticker),
  l=>day(l.date)+' · '+qty(l.shares)+' shares',l=>l.id,'Choose a purchase lot');
}
function salePreview(event) {
 event.preventDefault();const lot=openLots().find(l=>l.id===$('#saleLot').value);
 const q=Number($('#saleQty').value),price=Number($('#salePrice').value);
 if(!lot||!Number.isFinite(q)||q<=0||q>lot.shares+1e-9||!Number.isFinite(price)||price<=0) {
  $('#saleResult').textContent='Choose a valid lot, share quantity (no more than shares remaining), and sale price.';return;
 }
 const cost=lot.remaining_cost*(q/lot.shares),sale=q*price,gain=sale-cost;
 const months=lot.days_held>365?'Potentially long-term':'Potentially short-term';
 $('#saleResult').replaceChildren(el('b',colorize(gain),'Estimated '+money(gain)+' '+(gain>=0?'gain':'loss')),
  el('p',null,'Proceeds '+money(sale)+' · allocated basis '+money(cost)+' · return '+percent(cost>0?100*gain/cost:null)),
  el('p','subtle',months+' · held '+lot.days_held+' days · no order placed. Assumes no additional selling fees.'));
}
function fileKey(f) {return f?f.name+':'+f.size+':'+f.lastModified:'';}
async function csvAction(action) {
 const file=$('#csvFile').files[0];
 if(!file){notice('Choose a normalized CSV file.',true);return;}
 if(action==='import_csv' && fileKey(file)!==csvPreviewKey){notice('Preview this file before importing.',true);return;}
 const fd=formBody(action);fd.set('file',file);
 const button=action==='preview_csv'?$('#previewCsv'):$('#commitCsv');
 button.disabled=true;
 try{
  const d=await api(fd);
  if(action==='preview_csv') {
   csvPreviewKey=fileKey(file);$('#commitCsv').disabled=false;
   $('#csvPreview').textContent=d.rows+' rows validated · '+d.tickers.join(', ')+'. This has not been saved. Review the source before importing.';
   notice('CSV preview succeeded; no records were saved.');
  }else{
   csvPreviewKey='';$('#commitCsv').disabled=true;$('#csvPreview').textContent='Import complete. All '+d.count+' ledger records retained.';
   await load();notice('CSV imported. Verify market prices and each lot before making sell decisions.');
  }
 }catch(err){
  csvPreviewKey='';$('#commitCsv').disabled=true;$('#csvPreview').textContent=err.message;notice(err.message,true);
 }finally {button.disabled=false;}
}
document.addEventListener('DOMContentLoaded',()=>{
 $('#eventDate').value=new Date().toLocaleDateString('en-CA');
 if(!/^\d{4}-\d{2}-\d{2}$/.test($('#eventDate').value))$('#eventDate').value=new Date().toISOString().slice(0,10);
 $('#eventDate').max=new Date().toISOString().slice(0,10);
 $('#tickerSelect').addEventListener('change',e=>{selectedTicker=e.target.value;selectedLot='';$('#selectedLot').hidden=true;render();});
 $('#eventType').addEventListener('change',setEventFields);setEventFields();
 $('#addEvent [name=ticker]').addEventListener('input',updateManualLots);
 $('#saleSimulator').addEventListener('submit',salePreview);
 $('#saleLot').addEventListener('change',()=>{$('#salePrice').value=state.analysis.holdings[openLots().find(l=>l.id===$('#saleLot').value)?.ticker]?.price||'';});
 $('#hideLot').addEventListener('click',()=>{selectedLot='';$('#selectedLot').hidden=true;render();});
 $('#addEvent').addEventListener('submit',async event=>{
  event.preventDefault();const form=event.currentTarget;const data=Object.fromEntries(new FormData(form).entries());
  for(const key of ['quantity','amount','price','numerator','denominator'])data[key]=data[key]===''?'0':data[key];
  const fd=formBody('add');fd.set('event',JSON.stringify(data));
  try {await api(fd);await load();notice('Event saved. Purchase lots and performance recalculated.');form.reset();$('#eventDate').value=new Date().toISOString().slice(0,10);setEventFields();}
  catch(err){notice(err.message,true);}
 });
 $('#csvFile').addEventListener('change',()=>{csvPreviewKey='';$('#commitCsv').disabled=true;$('#csvPreview').textContent='File selected. Validate and preview before import.';});
 $('#previewCsv').addEventListener('click',()=>csvAction('preview_csv'));
 $('#commitCsv').addEventListener('click',()=>csvAction('import_csv'));
 load().catch(err=>notice(err.message,true));
});
