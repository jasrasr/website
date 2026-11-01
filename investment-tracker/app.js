/*
 * File Name: app.js
 * Author: Jason Lamb (with help from ChatGPT)
 * Description: Accessible charts, per-lot history, sale simulation and private ledger controls.
 * Revision: 1.5.0
 * Created Date: 2026-10-29
 * Modified Date: 2026-10-30
 * Changelog: 1.5.0 - Preview and import all eligible Robinhood symbols in one confirmed batch; show exceptions separately. 1.4.2 - Show unknown receipt basis and prevent false partial-sale tax calculations. 1.4.0 - Allow provisional Robinhood activity import without tax-lot attestation; display basis gaps prominently. 1.3.3 - Provide noncommitting FIFO allocation suggestions with explicit broker-verification warning. 1.3.2 - Show export end date and clearly mark execution prices as not live. 1.3.1 - Distinguish fees and stock-lending income in holding summaries. 1.3.0 - Interactive per-ticker Robinhood draft review, share verification and atomic commit. 1.2.1 - Detect raw Robinhood CSV in either upload field and route it to preview without enabling import. 1.2.0 - Safe Robinhood CSV preview and exception dashboard. 1.1.0 - Original and adjusted quantities, tax term and isolated NVIDIA demo. 1.0.0 - Initial mobile dashboard, split-aware tax lot charts and CSV review.
 */
'use strict';
const $ = s => document.querySelector(s);
let state={events:[],analysis:{holdings:{},series:{},totals:{},missing_prices:[]},csrf:''};
let selectedTicker='', selectedLot='',csvPreviewKey='';
let rhStage=null,rhSelected=null,rhBatchPreview=null;
let eventPage=0;
const eventPageSize=100;
const demoMode=document.body?.dataset.demo==='1';
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
 const endpoint=demoMode?'?api=1&demo=1':'?api=1';
 const response=await fetch(endpoint,body?{method:'POST',credentials:'same-origin',body}:{credentials:'same-origin'});
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
 const dates=points.map(p=>Date.parse(p.date+'T00:00:00Z'));
 const span=dates[dates.length-1]-dates[0];
 const sx=i=>left+(w-left-right)*(span===0?0.5:(dates[i]-dates[0])/span);
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
 if(!lots.length){const tr=el('tr');const td=cell(tr,'No open tax lots for the selected stock.');td.colSpan=13;tbody.append(tr);}
 lots.forEach(l=>{
  const tr=el('tr',selectedLot===l.id?'selected':'');
  cell(tr,day(l.date));
  cell(tr,l.source==='reinvest'?'Reinvested dividend':l.source==='receipt'?'Share credit · basis unknown':'Purchase');
  cell(tr,qty(l.original_shares),'numeric');
  cell(tr,money(l.original_unit_cost),'numeric');
  cell(tr,'×'+qty(l.split_factor),'numeric');
  cell(tr,qty(l.shares),'numeric');
  cell(tr,money(l.remaining_unit_cost),'numeric');
  cell(tr,money(l.basis_unknown?null:l.remaining_cost),'numeric');
  cell(tr,money(l.market_value),'numeric');
  cell(tr,money(l.unrealized),'numeric '+colorize(l.unrealized));
  cell(tr,percent(l.roi_pct),'numeric '+colorize(l.roi_pct));
  const age=cell(tr,'');
  age.append(el('span','heldFor',l.held_for),el('span','term '+(l.tax_term==='long-term'?'longTerm':'shortTerm'),l.tax_term==='unknown'?'Basis / term unknown':l.tax_term==='long-term'?'Long-term':'Short-term'));
  if(l.tax_term==='short-term')age.append(el('small','subtle',l.days_to_long_term+' days until long-term'));
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
 $('#selectedName').textContent=lot.ticker+' · purchased '+day(lot.date)+' · '+qty(lot.shares)+' shares currently held';
 const facts=$('#lotFacts');facts.replaceChildren();
 const fields=[
  ['Original purchase',qty(lot.original_shares)+' shares × '+money(lot.original_unit_cost)],
  ['Original total cost',money(lot.original_cost)],
  ['Stock split factor','×'+qty(lot.split_factor)+' · '+qty(lot.adjusted_acquired_shares)+' total adjusted shares before sales'],
  ['Shares currently held',qty(lot.shares)],
  ['Adjusted unit basis',money(lot.remaining_unit_cost)+'/share · '+money(lot.remaining_cost)+' remaining total basis'],
  ['Last observed share price',money(state.analysis.holdings[lot.ticker]?.price)+' · '+(state.analysis.holdings[lot.ticker]?.price_date||'not available')],
  ['Market value / unrealized gain',money(lot.market_value)+' / '+money(lot.unrealized)+' ('+percent(lot.roi_pct)+')'],
  ['Original holding duration',lot.held_for+' · '+lot.days_held+' days'],
  ['Holding-period classification',lot.tax_term==='long-term'?'Long-term':('Short-term · '+lot.days_to_long_term+' days remaining')],
  ['First potentially long-term sale date',lot.long_term_on?day(lot.long_term_on):'Unknown']
 ];
 fields.forEach(([name,val])=>{const c=el('div','fact');c.append(el('span','subtle',name),el('strong',null,val));facts.append(c);});
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
  card.append(el('p','subtle',qty(h.shares)+' shares · basis '+(h.basis_unknown?'UNKNOWN (received shares)':money(h.basis))+' · '+(h.price===null?'MISSING PRICE':'$'+qty(h.price)+'/share as of '+day(h.price_date))));
  const stats=el('div','holdingStats');stats.append(el('span',colorize(h.unrealized),'Unrealized '+money(h.unrealized)),el('span',colorize(h.realized),'Realized '+money(h.realized)));
  if(h.allocation_estimated || h.basis_unknown)card.append(el('p','rhWarning',h.basis_unknown?'UNKNOWN BASIS: share-credit tax cost and holding period cannot be calculated.':'ESTIMATED FIFO: stock sale lots have not been verified against the broker.'));
  card.append(stats);card.append(el('p','subtle','Lifetime '+money(h.lifetime_profit)+' · Cash dividends '+money(h.dividends_cash)+' · Reinvested '+money(h.dividends_reinvested)+' · Stock-lending/other income '+money(h.other_income||0)+' · Fees '+money(h.fees||0)+' · XIRR '+percent(h.xirr_pct)));
  host.append(card);
 });
 if(!host.childElementCount)host.append(el('p','empty','No transactions recorded yet.'));
}
function renderEvents() {
 const body=$('#eventsBody');body.replaceChildren();
 const pageCount=Math.max(1,Math.ceil(state.events.length/eventPageSize));
 eventPage=Math.min(eventPage,pageCount-1);
 const first=eventPage*eventPageSize;
 const rows=[...state.events].reverse().slice(first,first+eventPageSize);
 $('#recordCount').textContent=state.events.filter(e=>!e.voided_at).length+' active events';
 $('#eventPage').textContent=state.events.length
  ?'Showing '+(first+1)+'–'+Math.min(first+eventPageSize,state.events.length)+' of '+state.events.length+' records · page '+(eventPage+1)+' of '+pageCount
  :'No records';
 $('#newerEvents').disabled=eventPage===0;
 $('#olderEvents').disabled=eventPage>=pageCount-1;
 rows.forEach(e=>{
  const tr=el('tr',e.voided_at?'voided':'');
  cell(tr,e.date);cell(tr,e.ticker);cell(tr,e.type+(e.voided_at?' (void)':''));
  cell(tr,['buy','reinvest','sell'].includes(e.type)?qty(e.quantity):e.type==='split'?(e.numerator+':'+e.denominator):'—','numeric');
  cell(tr,e.type==='price'?money(e.price):e.type==='split'?'—':money(e.amount),'numeric');
  cell(tr,e.note||'—');
  const td=cell(tr,'');
  if(!e.voided_at && !demoMode){
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
 selectedLot='';$('#selectedLot').hidden=true;
 const d=await api();state={...state,events:d.events,analysis:d.analysis,csrf:d.csrf};render();
 const missing=d.analysis.missing_prices||[];
 if(demoMode)notice('Read-only NVDA demonstration: full activity history reconciles 1.736881 shares including DRIPs; historical sale tax-lot attribution is provisional, and the latest example price is stale (Oct 9).');
 else if(missing.length)notice('Missing current price observations for '+missing.join(', ')+'. Portfolio totals and XIRR are withheld until priced.');
 else notice('Ledger loaded. Prices are last recorded observations (manual or brokerage execution), NOT live market quotes; check their dates before decisions.');
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
 if(lot?.basis_unknown){$('#saleResult').textContent='This share credit has unknown cost basis and acquisition date. Its taxable gain cannot be simulated without verified basis.';return;}
 if(!lot||!Number.isFinite(q)||q<=0||q>lot.shares+1e-9||!Number.isFinite(price)||price<=0) {
  $('#saleResult').textContent='Choose a valid lot, share quantity (no more than shares remaining), and sale price.';return;
 }
 const cost=lot.remaining_cost*(q/lot.shares),sale=q*price,gain=sale-cost;
 const months=lot.tax_term==='long-term'?'Potentially long-term':'Potentially short-term';
 $('#saleResult').replaceChildren(el('b',colorize(gain),'Estimated '+money(gain)+' '+(gain>=0?'gain':'loss')),
  el('p',null,'Proceeds '+money(sale)+' · allocated basis '+money(cost)+' · return '+percent(cost>0?100*gain/cost:null)),
  el('p','subtle',months+' · held '+lot.held_for+' ('+lot.days_held+' days) · first long-term date '+lot.long_term_on+' · no order placed. Assumes no additional selling fees.'));
}
function describeType(code) {
 const names={buy:'Cash purchases',sell:'Sales (tax lots unresolved)',reinvest:'Dividend reinvestments',dividend:'Dividend credits',split_credit:'Split share credits (not ratios)',stock_lending:'Stock lending',cash_movement:'Deposits/transfers',fee_or_tax:'Fees and taxes',interest:'Interest',unsupported:'Other corporate actions / cancellations'};
 return names[code]||code;
}
async function previewRobinhood(event,fileOverride=null) {
 if(event)event.preventDefault();
 const file=fileOverride||$('#rhFile').files[0];if(!file){notice('Select a Robinhood activity CSV.',true);return;}
 const button=$('#rhForm button[type=submit]');button.disabled=true;
 try{
  const fd=formBody('preview_robinhood');fd.set('file',file);
  const report=await api(fd);
  const host=$('#rhPreview');host.replaceChildren();
  host.append(el('strong',null,report.records.toLocaleString()+' account events · '+report.ticker_count+' tickers · '+report.first_date+' through '+report.last_date));
  host.append(el('p','subtle',report.matched_dividend_reinvestments+' reinvestment dividend credits matched to buys; unmatched credits remain in their original category.'));
  const counts=el('div','rhSummary');
  for(const [type,count] of Object.entries(report.counts||{})) {
   const card=el('div','rhCount');card.append(el('b',null,count.toLocaleString()),el('span',null,describeType(type)));counts.append(card);
  }
  host.append(counts);
  host.append(el('p','rhWarning','No data imported or saved. Activity CSV does not identify selected tax lots for sales, nor split ratios for split-credit rows.'));
  const target=$('#rhTickers');target.replaceChildren();
  const heading=el('h3',null,'Ticker reconciliation');
  target.append(heading);
  const controls=el('label','rhFilter','Filter stock ');
  const input=el('input');input.placeholder='NVDA, AAPL, TSLA…';input.maxLength=16;controls.append(input);target.append(controls);
  const list=el('div','rhTickerList');target.append(list);
  function draw(){
   list.replaceChildren();
   const query=input.value.trim().toUpperCase();
   let filtered=report.tickers.filter(x=>!query||x.ticker.includes(query));
   if(!query)filtered=filtered.slice(0,25);
   if(!filtered.length){list.append(el('p','subtle','No matching tickers.'));return;}
   for(const ticker of filtered){
    const details=el('details','rhTicker');const summary=el('summary');
    summary.append(el('strong',null,ticker.ticker));
    summary.append(el('span','subtle',Object.entries(ticker.counts).map(([type,n])=>n+' '+type).join(' · ')));
    summary.append(el('b','term shortTerm',ticker.review_required?'Needs review':'No explicit blockers'));
    details.append(summary);
    if(ticker.issues.length){
      const ul=el('ul');
      ticker.issues.forEach(x=>ul.append(el('li',null,x.count+' × '+x.issue)));
      details.append(ul);
    }else details.append(el('p','subtle','No directly detected blockers. Still reconcile opening balances and holdings before final import.'));
    list.append(details);
   }
   if(!query&&report.tickers.length>filtered.length)list.append(el('p','hint','Showing first '+filtered.length+' tickers; search by symbol for others.'));
  }
  input.addEventListener('input',draw);draw();
  notice('Robinhood report analyzed. No transactions were imported or saved.');
 } catch(error){$('#rhPreview').textContent=error.message;$('#rhTickers').replaceChildren();notice(error.message,true);}
 finally{button.disabled=false;}
}

async function isOriginalRobinhoodCsv(file) {
 // Read only the header on the user's device; do not upload it twice or alter the file.
 const header=(await file.slice(0,2048).text()).replace(/^\uFEFF/,'').split(/\r?\n/,1)[0].trim();
 return header.split(',').map(part=>part.trim().replace(/^"|"$/g,'')).join(',')===
  'Activity Date,Process Date,Settle Date,Instrument,Description,Trans Code,Quantity,Price,Amount';
}
// The editable review is intentionally separate from the brokerage preview.
// Staging is server-private and does not touch the user's permanent ledger.
async function rhDraftApi(ticker='') {
 const uri='?api=1&reconciliation=1'+(ticker?'&ticker='+encodeURIComponent(ticker):'');
 const res=await fetch(uri,{credentials:'same-origin'});
 const json=await res.json();
 if(!res.ok||!json.success)throw new Error(json.message||'Unable to read private reconciliation draft.');
 return json.data;
}
function rhField(label,value,type='text') {
 const n=el('label','rhControl');n.append(el('span',null,label));
 const i=el('input');i.type=type;i.value=value??'';
 if(type==='number'){i.step='any';i.min='0';i.inputMode='decimal';}
 n.append(i);return {outer:n,input:i};
}
function rhAllocation(host,available,lotId='',value='') {
 const line=el('div','rhAllocation');
 const select=el('select');select.className='rhLotRef';
 const blank=el('option',null,'Choose purchase lot');blank.value='';select.append(blank);
 available.forEach(row=>{
  const o=el('option',null,row.date+' · '+row.kind+' · '+qty(row.quantity)+' shares · '+money(Math.abs(row.amount))+' · '+row.id.slice(0,7));
  o.value=row.id;select.append(o);
 });
 select.value=lotId;
 const q=rhField('Shares from this lot',value,'number');
 q.outer.classList.add('rhAllocationQty');
 const remove=el('button','ghost','Remove');remove.type='button';remove.addEventListener('click',()=>line.remove());
 line.append(select,q.outer,remove);host.append(line);
}
function rhDrawStock(data) {
 rhSelected=data;
 const host=$('#rhReviewLines');host.replaceChildren();
 $('#rhExpectedShares').value=data.review.expected_shares??'';
 $('#rhConfirm').checked=!!data.review.source_confirmed;
 $('#rhSaveReview').disabled=!data.rows.length||!!data.completed;
 $('#rhSuggestFifo').disabled=!!data.completed || !data.rows.some(x=>x.kind==='sell');
 $('#rhAutoImport').disabled=!!data.completed;
 $('#rhCommit').disabled=!data.validation?.ready||!!data.completed;
 const heading=el('p','subtle',data.rows.length+' brokerage activity lines for '+data.ticker+'. Purchase lot IDs are shown so each historical sale can be assigned precisely.');
 host.append(heading);
 if(data.completed){
  host.append(el('p','notice','This ticker was already imported ('+data.completed.events+' events). Review is read-only.'));return;
 }
 const buys=data.rows.filter(r=>r.kind==='buy'||r.kind==='reinvest');
 if(buys.length){
  const listing=el('details','rhLotsLegend');listing.append(el('summary',null,'Show '+buys.length+' source purchase lots and original share quantities'));
  const list=el('div','rhLotsGrid');
  buys.forEach(row=>list.append(el('p','subtle',row.id.slice(0,9)+' · '+day(row.date)+' · '+qty(row.quantity)+' shares · '+money(Math.abs(row.amount))+(row.kind==='reinvest'?' (DRIP)':''))));
  listing.append(list);host.append(listing);
 }
 const issues=data.rows.filter(r=>['sell','split_credit','unsupported','receipt'].includes(r.kind)||r.pair_issue);
 if(!issues.length)host.append(el('p','notice','No explicit sale or split corrections found. Verify the current broker share balance before saving.'));
 for(const row of issues) {
  const card=el('section','rhIssue');
  const title=el('h4',null,row.kind==='sell'?'Allocate stock sale':row.kind==='split_credit'?'Verify stock split':row.kind==='unsupported'?'Unsupported corporate action':row.kind==='receipt'?'Shares received — basis unknown':'Verify reinvested dividend');
  card.append(title);
  card.append(el('p','subtle',day(row.date)+' · '+row.code+' · source line '+row.line+' · '+(row.description||'')));
  if(row.kind==='sell') {
   card.dataset.saleId=row.id;
   card.append(el('p',null,'Sold '+qty(row.quantity)+' shares for '+money(row.amount)+'. Allocate all sold shares across original purchase lots. Do not rely on FIFO unless verified with the broker.'));
   const choices=buys.filter(b=>b.date<=row.date);
   if(!choices.length){card.append(el('p','negative','No eligible earlier purchase lots in this export. This sale cannot be imported without earlier basis records.'));}
   else {
    const allocations=el('div','rhAllocations');card.append(allocations);
    const previous=data.review.sales?.[row.id]||{};
    const old=Object.entries(previous);
    if(!old.length)rhAllocation(allocations,choices,'','');
    else old.forEach(([id,q])=>rhAllocation(allocations,choices,id,q));
    const button=el('button','smallbtn','+ Allocate another purchase lot');button.type='button';
    button.addEventListener('click',()=>rhAllocation(allocations,choices));
    card.append(button);
   }
  }else if(row.kind==='split_credit') {
   card.dataset.splitId=row.id;
   card.append(el('p',null,'Robinhood credited '+qty(row.credit)+' additional shares. Confirm the actual split ratio from the corporate action. Do not enter the credited-share quantity as a ratio.'));
   const saved=data.review.ratios?.[row.id];
   const ratio=el('div','rhRatio');
   const n=rhField('New shares (e.g. 10)',saved?.n??'','number');
   const d=rhField('Old shares (e.g. 1)',saved?.d??'','number');
   n.input.className='rhRatioNum';d.input.className='rhRatioDen';
   ratio.append(n.outer,d.outer);card.append(ratio);
  }else if(row.kind==='unsupported') {
   card.append(el('p','negative','Unrecognized corporate action. This symbol cannot be automatically imported without a defined conversion. Other stocks can still be imported.'));
  }else if(row.kind==='receipt') {
   card.append(el('p',null,qty(row.quantity)+' received shares will be recorded with an UNKNOWN cost basis. Share count is tracked, but estimated taxable gain/loss is hidden until basis can be established.'));
  }else if(row.pair_issue){
   card.append(el('p','negative','Dividend reinvestment issue: '+row.pair_issue+'. The credit must be reconciled first.'));
  }
  host.append(card);
 }
 rhRenderValidation(data.validation);
}
function rhRenderValidation(result) {
 const box=$('#rhValidation');box.replaceChildren();
 if(!result) {box.append(el('p',null,'Save corrections to validate this stock. No ledger records have been imported.'));return;}
 box.append(el('strong',result.ready?'Validation passed — awaiting explicit import':'Review incomplete — nothing imported'));
 box.append(el('p',null,'Computed shares '+qty(result.computed_shares)+' · broker shares '+qty(result.expected_shares)));
 if(result.issues?.length){
  const ul=el('ul');result.issues.slice(0,25).forEach(issue=>ul.append(el('li',null,issue)));
  box.append(ul);
  if(result.issues.length>25)box.append(el('p','subtle',(result.issues.length-25)+' additional issues. Review the source report before continuing.'));
 }
 box.append(el('p','subtle',result.notice));
}
async function rhRefresh(ticker='') {
 const data=await rhDraftApi(ticker);
 if(!data?.exists) {
  $('#rhDraftStatus').textContent='No private draft yet';rhStage=null;rhBatchPreview=null;$('#rhPreviewAll').disabled=true;$('#rhCommitAll').disabled=true;return;
 }
 rhStage=data;
 $('#rhPreviewAll').disabled=false;
 $('#rhDraftStatus').textContent=data.source_records+' stock activity rows staged · '+data.ticker_count+' tickers · through '+(data.report_last_date||'report end unknown');
 const select=$('#rhDraftTicker');
 const prior=ticker||select.value;
 selectOptions(select,data.tickers,x=>x.ticker+' — '+x.status,x=>x.ticker,'Select a stock');
 if(data.tickers.some(x=>x.ticker===prior))select.value=prior;
 if(ticker&&data.rows)rhDrawStock(data);
}

function rhBatchDisplay(report,wasImported=false) {
 const box=$('#rhBatchStatus');box.replaceChildren();
 if(wasImported) {
  box.append(el('strong',null,report.stocks+' stocks and '+report.events+' activity events imported to your private portfolio as ESTIMATED.'));
  box.append(el('p','subtle',report.blocked.length+' stocks need special handling; '+report.skipped.length+' already imported or skipped.'));
 } else {
  box.append(el('strong',null,report.eligible_count+' stocks eligible · '+report.blocked_count+' need review · '+report.skipped_count+' already in portfolio'));
  box.append(el('p',null,report.import_count+' source-derived events ready. Nothing has been saved to your permanent portfolio. Past sale-lot attribution is provisional FIFO.'));
  if(report.error)box.append(el('p','negative',report.error));
 }
 const blocked=report.blocked||[];
 if(blocked.length) {
  const details=el('details','rhBatchExceptions');
  details.append(el('summary',null,'Show '+blocked.length+' stocks requiring special handling'));
  const ul=el('ul');
  blocked.forEach(item=>ul.append(el('li',null,item.ticker+' · '+item.reason)));
  details.append(ul);box.append(details);
 }
}
async function rhPreviewAll() {
 if(!rhStage){notice('Stage the CSV first.',true);return;}
 const b=$('#rhPreviewAll');b.disabled=true;
 try {
  const result=await api(formBody('preview_auto_import_all'));
  rhBatchPreview=result;
  rhBatchDisplay(result);
  $('#rhCommitAll').disabled=!result.ready;
  notice('Full Robinhood import preview complete. No personal ledger records changed.');
 }catch(err){rhBatchPreview=null;$('#rhCommitAll').disabled=true;notice(err.message,true);}
 finally{b.disabled=false;}
}
async function rhCommitAll() {
 if(!rhBatchPreview?.ready)return;
 const n=rhBatchPreview.eligible_count,e=rhBatchPreview.import_count;
 if(!confirm('Import '+n+' eligible stocks ('+e+' events) to your private portfolio using provisional FIFO for sales? Tax lots are NOT broker-verified, unknown-basis shares will remain unvalued for taxable gains, and the '+rhBatchPreview.blocked_count+' blocked stocks will remain untouched.'))return;
 const b=$('#rhCommitAll');b.disabled=true;
 try {
  const fd=formBody('commit_auto_import_all');fd.set('fingerprint',rhBatchPreview.fingerprint);
  const result=await api(fd);
  rhBatchPreview=null;
  await load();await rhRefresh();
  rhBatchDisplay(result,true);
  notice(result.stocks+' stocks successfully imported as estimated history. '+result.blocked.length+' stocks remain in the exception queue.');
 }catch(err){notice(err.message,true);}
}
async function rhStageUpload(){
 const file=$('#rhFile').files[0];if(!file){notice('Select your original Robinhood CSV first.',true);return;}
 if(rhStage && !confirm('Replace the current private reconciliation draft? Saved but unimported corrections in it will be lost. Existing personal holdings will NOT be deleted.'))return;
 const b=$('#stageRh');b.disabled=true;
 try{
  const fd=formBody('stage_robinhood');fd.set('file',file);
  await api(fd);
  rhSelected=null;rhBatchPreview=null;$('#rhCommitAll').disabled=true;$('#rhReviewLines').replaceChildren();
  $('#rhCommit').disabled=true;$('#rhSaveReview').disabled=true;
  await rhRefresh();
  $('#reconciliation').scrollIntoView({behavior:'smooth',block:'start'});
  notice('Report staged privately. Select a ticker, correct flagged events, validate and explicitly import.');
 }catch(err){notice(err.message,true);}
 finally{b.disabled=false;}
}
function rhSuggestFifo() {
 if(!rhSelected?.rows)return;
 const lots=[],allSuggestions={};
 const rows=rhSelected.rows;
 for(const event of rows){
  if(event.kind==='split_credit') {
   const card=Array.from(document.querySelectorAll('#rhReviewLines [data-split-id]')).find(x=>x.dataset.splitId===event.id);
   const n=Number(card?.querySelector('.rhRatioNum')?.value),d=Number(card?.querySelector('.rhRatioDen')?.value);
   if(!Number.isInteger(n)||!Number.isInteger(d)||n<=0||d<=0||n===d){
    notice('Enter and verify all stock split ratios before suggesting FIFO allocations.',true);return;
   }
   lots.forEach(lot=>lot.remaining*=n/d);
  }else if(event.kind==='buy'||event.kind==='reinvest'){
   lots.push({id:event.id,date:event.date,remaining:Number(event.quantity)});
  }else if(event.kind==='sell'){
   let needed=Number(event.quantity);const allocations={};
   for(const lot of lots){
    if(needed<=0.0000001)break;
    const used=Math.min(needed,lot.remaining);
    if(used<=0)continue;
    allocations[lot.id]=used;
    lot.remaining=Math.max(0,lot.remaining-used);
    needed-=used;
   }
   if(needed>0.0000001){notice('FIFO could not allocate '+event.ticker+' sale on '+event.date+'. Earlier purchase history or a corporate action may be missing.',true);return;}
   allSuggestions[event.id]=allocations;
  }else if(event.kind==='unsupported'){
   // Still show useful proposals for sales; these cannot unlock import.
  }
 }
 const purchases=rows.filter(x=>x.kind==='buy'||x.kind==='reinvest');
 for(const card of document.querySelectorAll('#rhReviewLines [data-sale-id]')){
  const allocation=allSuggestions[card.dataset.saleId];
  if(!allocation)continue;
  const target=card.querySelector('.rhAllocations');target.replaceChildren();
  const eligible=purchases.filter(x=>x.date<=rows.find(r=>r.id===card.dataset.saleId)?.date);
  Object.entries(allocation).forEach(([id,shares])=>rhAllocation(target,eligible,id,Number(shares.toFixed(8))));
 }
 $('#rhConfirm').checked=false;
 $('#rhCommit').disabled=true;
 notice('Provisional FIFO selections filled in. Verify each with Robinhood tax-lot records; FIFO is NOT assumed to be the actual broker election. Save corrections only after review.');
}
async function rhSave(){
 if(!rhSelected || !rhStage)return;
 const review={
  ticker:rhSelected.ticker,revision:rhStage.revision,
  expected_shares:$('#rhExpectedShares').value,
  source_confirmed:$('#rhConfirm').checked,
  ratios:{},sales:{}
 };
 document.querySelectorAll('#rhReviewLines [data-split-id]').forEach(card=>{
  const n=card.querySelector('.rhRatioNum').value,d=card.querySelector('.rhRatioDen').value;
  if(n!==''||d!=='')review.ratios[card.dataset.splitId]={n:Number(n),d:Number(d)};
 });
 document.querySelectorAll('#rhReviewLines [data-sale-id]').forEach(card=>{
  const alloc={};
  card.querySelectorAll('.rhAllocation').forEach(row=>{
   const id=row.querySelector('.rhLotRef').value,q=row.querySelector('input').value;
   if(id&&q!=='')alloc[id]=(alloc[id]||0)+Number(q);
  });
  if(Object.keys(alloc).length)review.sales[card.dataset.saleId]=alloc;
 });
 const b=$('#rhSaveReview');b.disabled=true;
 try {
  const fd=formBody('save_reconciliation');fd.set('review',JSON.stringify(review));
  const result=await api(fd);rhStage=result;rhDrawStock(result);
  $('#rhCommit').disabled=!result.validation?.ready;
  notice(result.validation?.ready?'Validation passed. Import is a separate action.':'Corrections saved. Complete remaining review issues before import.');
 }catch(err){notice(err.message,true);}
 finally{b.disabled=false;}
}
async function rhAutoImport() {
 if(!rhSelected || !rhStage)return;
 const ticker=rhSelected.ticker;
 if(!confirm('Import '+ticker+' from the Robinhood activity CSV as an ESTIMATED history? Sales use assumed FIFO, splits may be inferred from credited shares, and received shares have UNKNOWN cost basis. No brokerage tax-lot election will be claimed.'))return;
 const button=$('#rhAutoImport');button.disabled=true;
 try {
  const fd=formBody('auto_import_stock');fd.set('ticker',ticker);fd.set('revision',rhStage.revision);
  const result=await api(fd);
  await load();await rhRefresh(ticker);
  const report=(result.warnings||[]).slice(0,5).join(' ');
  notice(ticker+' imported provisionally. '+result.imported+' activity events. '+report);
 }catch(err){notice(err.message,true);button.disabled=false;}
}

async function rhCommitTicker(){
 if(!rhSelected||!rhStage || !rhSelected.validation?.ready)return;
 const ticker=rhSelected.ticker;
 if(!confirm('Import all validated '+ticker+' transactions into YOUR private portfolio? This does not place trades. The sale-lot choices must match brokerage records.'))return;
 const b=$('#rhCommit');b.disabled=true;
 try {
  const fd=formBody('commit_reconciliation');fd.set('ticker',ticker);fd.set('revision',rhStage.revision);
  const result=await api(fd);
  await load();
  await rhRefresh(ticker);
  notice(result.imported+' '+ticker+' events imported into your private ledger. Add a recent price observation to calculate current gains.');
 }catch(err){notice(err.message,true);b.disabled=false;}
}

function fileKey(f) {return f?f.name+':'+f.size+':'+f.lastModified:'';}
async function csvAction(action) {
 const file=$('#csvFile').files[0];
 if(!file){notice('Choose a normalized CSV file.',true);return;}
 if(action==='preview_csv' && await isOriginalRobinhoodCsv(file)) {
  csvPreviewKey='';
  $('#commitCsv').disabled=true;
  $('#csvPreview').textContent='Original Robinhood activity CSV detected. Redirected to brokerage analysis. No records imported.';
  await previewRobinhood(null,file);
  $('#robinhood-upload').scrollIntoView({behavior:'smooth',block:'start'});
  return;
 }
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
 $('#newerEvents').addEventListener('click',()=>{if(eventPage>0){eventPage--;renderEvents();}});
 $('#olderEvents').addEventListener('click',()=>{if((eventPage+1)*eventPageSize<state.events.length){eventPage++;renderEvents();}});
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
 $('#rhForm').addEventListener('submit',previewRobinhood);
 $('#stageRh').addEventListener('click',rhStageUpload);
 $('#rhDraftTicker').addEventListener('change',e=>{
  const ticker=e.target.value;
  rhSelected=null;$('#rhReviewLines').replaceChildren();
  $('#rhSaveReview').disabled=true;$('#rhCommit').disabled=true;$('#rhAutoImport').disabled=true;$('#rhSuggestFifo').disabled=true;
  if(ticker)rhRefresh(ticker).catch(err=>notice(err.message,true));
 });
 $('#rhSuggestFifo').addEventListener('click',rhSuggestFifo);
 $('#rhSaveReview').addEventListener('click',rhSave);
 $('#rhAutoImport').addEventListener('click',rhAutoImport);
 $('#rhCommit').addEventListener('click',rhCommitTicker);
 $('#rhPreviewAll').addEventListener('click',rhPreviewAll);
 $('#rhCommitAll').addEventListener('click',rhCommitAll);
 if(!demoMode)rhRefresh().catch(err=>notice(err.message,true));
 $('#previewCsv').addEventListener('click',()=>csvAction('preview_csv'));
 $('#commitCsv').addEventListener('click',()=>csvAction('import_csv'));
 load().catch(err=>notice(err.message,true));
});
