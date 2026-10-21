'use strict';
const $=id=>document.getElementById(id);
const labels={resolve_candidate:'Resolution candidate',follow_up:'Follow up',investigate:'Investigate',blocked:'Blocked',escalate:'Escalate'};
let token='',tickets=[],reviews=new Map(),selected=null,busy=false,stop=false,generation=0;
const loginKey='freshservice-review-login-v1',loginLifetime=7*24*60*60*1000;
function clearSavedLogin(){try{localStorage.removeItem(loginKey);}catch{}}
function saveLogin(value){try{localStorage.setItem(loginKey,JSON.stringify({token:value,expires:Date.now()+loginLifetime}));return true;}catch{return false;}}
function savedLogin(){try{const saved=JSON.parse(localStorage.getItem(loginKey));if(saved&&typeof saved.token==='string'&&saved.token.trim()&&Number.isFinite(saved.expires)&&saved.expires>Date.now()&&saved.expires<=Date.now()+loginLifetime)return saved.token;}catch{}clearSavedLogin();return '';}
const esc=v=>String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
function message(s){$('message').textContent=s;}
function ticketUrlsForOpening(queue){
 const urls=[],seen=new Set();
 for(const ticket of queue){
  try{
   const url=new URL(String(ticket?.url??''));
   if(url.protocol!=='https:'||!url.hostname.toLowerCase().endsWith('.freshservice.com')||seen.has(url.href))continue;
   seen.add(url.href);urls.push(url.href);
  }catch{}
 }
 return urls;
}
function openTicketTabs(queue,openTab=(url,target)=>window.open(url,target)){
 const urls=ticketUrlsForOpening(queue);let opened=0,blocked=0;
 for(const url of urls){
  const tab=openTab('about:blank','_blank');
  if(!tab){blocked++;continue;}
  try{tab.opener=null;tab.location.replace(url);opened++;}
  catch{try{tab.close();}catch{}blocked++;}
 }
 return {total:urls.length,opened,blocked};
}
function controls(){for(const id of ['refresh','analyze-all'])$(id).disabled=busy;$('open-all').disabled=busy||tickets.length===0;document.querySelectorAll('[data-analyze]').forEach(b=>b.disabled=busy);}
async function api(action,id){
 const started=Date.now(),context=`${action}${id?` for ticket #${id}`:''}`;
 let response;
 try{response=await fetch('api.php',{method:'POST',cache:'no-store',headers:{'Content-Type':'application/json','Authorization':'Bearer '+token},body:JSON.stringify({action,id})});}
 catch{throw Error(`Network request failed during ${context}. Check your connection and retry.`);}
 let data;
 try{data=await response.json();}
 catch{
  const elapsed=Math.round((Date.now()-started)/1000);
  const hint=[502,503,504].includes(response.status)?'The host or gateway may have timed out.':response.status===403?'The host may have blocked this request.':'Check the hosting PHP error log for this request.';
  throw Error(`Non-JSON response during ${context} (HTTP ${response.status}, ${elapsed}s). ${hint} Try opening this ticket first to test history retrieval separately.`);
 }
 if(!data||typeof data!=='object'||Array.isArray(data))throw Error(`Invalid JSON response during ${context} (HTTP ${response.status}).`);
 if(!response.ok||data.error){
  if(response.status===401)clearSavedLogin();
  const stage=typeof data.stage==='string'?` Stage: ${data.stage}.`:'';
  const ref=typeof data.request_id==='string'?` Reference: ${data.request_id}.`:'';
  throw Error(`${context}: ${data.error||'Request failed'} (HTTP ${response.status}).${stage}${ref}`);
 }
 return data;
}
function renderQueue(){const query=$('search').value.toLowerCase(),filter=$('filter').value,sort=$('sort').value;
 const shown=tickets.filter(t=>(`${t.id} ${t.subject}`).toLowerCase().includes(query)&&(!filter||(filter==='unreviewed'?!reviews.has(t.id):reviews.get(t.id)?.review?.recommendation===filter))).sort((a,b)=>(b[sort]??-1)-(a[sort]??-1)||a.id-b.id);
 $('count').textContent=`${tickets.length} unresolved · ${reviews.size} reviewed`;
 $('queue').innerHTML=shown.map(t=>`<button class="ticket ${t.id===selected?'active':''}" data-ticket="${t.id}"><small>#${t.id} · ${['Unknown','Low','Medium','High','Urgent'][t.priority]||'Unknown'}${t.overdue?' · OVERDUE':''}</small><strong>${esc(t.subject)}</strong><small>${t.age_days??'?'} days old · ${t.idle_days??'?'} days idle · Score ${t.score}</small><span class="badge">${labels[reviews.get(t.id)?.review?.recommendation]||'Awaiting review'}</span></button>`).join('')||'<p>No matching tickets.</p>';
}
function renderDetail(d){const t=d.ticket,r=d.review;selected=t.id;renderQueue();
 $('detail').innerHTML=`<p class="eyebrow">TICKET #${t.id}</p><h2>${esc(t.subject)}</h2><p class="muted">Snapshot: ${esc(d.fetched_at)} · ${d.conversations.length} conversations · ${d.attachment_count+d.conversations.reduce((n,c)=>n+c.attachment_count,0)} attachments (contents not read)</p><div class="row"><button data-analyze="${t.id}">${r?'Review again':'Analyze with AI'}</button><a href="${esc(t.url)}" target="_blank" rel="noopener noreferrer">Open in Freshservice ↗</a></div><p class="notice">Analyze sends this description and all fetched public and private notes to the configured OpenAI API. Check drafts before using them.</p>
 ${r?`<section class="section"><span class="badge">${labels[r.recommendation]}</span><p class="muted">AI confidence: ${esc(r.confidence)} · Human decision required</p><h2>${esc(r.summary)}</h2><p>${esc(r.reason)}</p><h3>Next step</h3><p>${esc(r.next_step)}</p><h3>Supporting evidence</h3>${r.evidence.map(e=>`<blockquote><small>${esc(e.source)}</small><br>${esc(e.quote)}</blockquote>`).join('')}<h3>Known blockers</h3><ul>${r.blockers.map(x=>`<li>${esc(x)}</li>`).join('')||'<li>No blockers identified in fetched text; verify the missing records below.</li>'}</ul><h3>Before resolving or closing</h3><ul>${[...r.closure_checks,...d.limitations].map(x=>`<li>${esc(x)}</li>`).join('')}</ul><label for="internal-draft">Internal note draft — agent-only</label><textarea id="internal-draft">${esc(r.internal_note_draft)}</textarea><button class="secondary" data-copy="internal-draft">Copy internal note</button><div class="section"><label for="reply-draft">Requester reply draft — review for internal information before sending</label><textarea id="reply-draft">${esc(r.requester_reply_draft)}</textarea><button class="secondary" data-copy="reply-draft">Copy requester reply</button></div></section>`:''}
 <section class="section"><h3>Description</h3><div class="body">${esc(d.description)}</div></section><section class="section"><details ${r?'':'open'}><summary>Full conversation history (${d.conversations.length})</summary>${d.conversations.map(c=>`<div class="conversation ${c.private?'private':''}"><small>${c.private?'INTERNAL NOTE':c.incoming?'INCOMING PUBLIC MESSAGE':'OUTGOING PUBLIC MESSAGE'} · ${esc(c.created_at)} · conversation:${c.id} · author ${esc(c.user_id)}</small><p class="body">${esc(c.body)}</p></div>`).join('')||'<p>No conversations returned.</p>'}</details></section>`;controls();
}
async function refresh(remember=false){busy=true;controls();const g=generation;message('Loading your unresolved queue…');try{const d=await api('queue');if(g!==generation)return;const loginSaved=remember===true?saveLogin(token):true;tickets=d.tickets;reviews.clear();selected=null;$('connect').hidden=true;$('workspace').hidden=false;$('freshness').textContent='Fetched '+new Date(d.fetched_at).toLocaleString();$('detail').innerHTML='<div class="empty"><h2>Select a ticket to start.</h2></div>';renderQueue();message(!loginSaved?'Queue loaded. Browser storage unavailable; login lasts only in this page.':d.ai_enabled?'Queue loaded.':'Queue loaded. AI requires server configuration.');}catch(e){if(g===generation)message(e.message);}finally{if(g===generation){busy=false;controls();}}}
async function analyze(id){const d=await api('analyze',id);return d;}
$('connect-form').addEventListener('submit',e=>{e.preventDefault();if(busy)return;token=$('token').value.trim();$('token').value='';clearSavedLogin();refresh(true);});
$('refresh').onclick=()=>refresh();
$('open-all').onclick=()=>{
 if(busy||tickets.length===0)return;
 const urls=ticketUrlsForOpening(tickets);
 if(urls.length===0){message('No valid Freshservice ticket links are available. Refresh the queue and try again.');return;}
 const noun=urls.length===1?'ticket':'tickets';
 if(!window.confirm(`Open all ${urls.length} unresolved ${noun} in separate tabs?\n\nTo use Chrome, open this review page in Chrome before continuing. Your browser may ask you to allow pop-ups for jasr.me.`))return;
 const result=openTicketTabs(tickets);
 message(result.blocked===0
  ? `${result.opened} ticket tabs opened in this browser.`
  : `${result.opened} of ${result.total} ticket tabs opened. Allow pop-ups and redirects for jasr.me, then try again for the blocked tabs.`);
};
$('lock').onclick=()=>{generation++;stop=true;clearSavedLogin();token='';tickets=[];reviews.clear();selected=null;busy=false;$('queue').replaceChildren();$('detail').replaceChildren();$('workspace').hidden=true;$('connect').hidden=false;$('stop').hidden=true;message('Locked. Ticket data cleared from this page.');controls();};
for(const id of ['search','filter','sort'])$(id).addEventListener('input',renderQueue);
$('queue').onclick=async e=>{const b=e.target.closest('[data-ticket]');if(!b||busy)return;const id=Number(b.dataset.ticket);const g=generation;busy=true;controls();message('Loading complete history…');try{const d=reviews.get(id)||await api('detail',id);if(g!==generation)return;renderDetail(d);message('');}catch(err){if(g===generation)message(err.message);}finally{if(g===generation){busy=false;controls();}}};
$('detail').onclick=async e=>{const b=e.target.closest('button');if(!b)return;if(b.dataset.copy){try{await navigator.clipboard.writeText($(b.dataset.copy).value);message('Draft copied. Review it in Freshservice before sending.');}catch{message('Select the draft text and copy it manually.');}return;}if(!b.dataset.analyze||busy)return;const g=generation;busy=true;controls();message('Reading full history and checking AI evidence…');try{const d=await analyze(Number(b.dataset.analyze));if(g!==generation)return;reviews.set(d.ticket.id,d);renderDetail(d);message('Review ready. Verify the suggested next step before acting.');}catch(err){if(g===generation)message(err.message);}finally{if(g===generation){busy=false;controls();}}};
$('stop').onclick=()=>{stop=true;message('Stopping after the current ticket.');};
$('analyze-all').onclick=async()=>{if(busy)return;busy=true;stop=false;const g=generation;controls();$('stop').hidden=false;let done=0;const failed=[];try{for(const t of tickets){if(stop||g!==generation)break;if(reviews.has(t.id))continue;message(`Reviewing #${t.id} · ${done} completed this run…`);let d;try{d=await analyze(t.id);}catch(err){if(g!==generation)return;failed.push({id:t.id,error:err.message});continue;}if(g!==generation)return;reviews.set(t.id,d);done++;renderQueue();if(selected===null||selected===t.id)renderDetail(d);}if(g===generation)message(`${done} reviews completed${stop?' · stopped':''}. ${failed.length?'Failed reviews: '+failed.map(f=>'#'+f.id+' — '+f.error).join(' | ')+'. Select these tickets for manual review or retry.':'Use the recommendation filter to find resolution candidates.'}`);}catch(e){if(g===generation)message(`Stopped: ${e.message} Completed reviews are retained in this tab.`);}finally{if(g===generation){busy=false;$('stop').hidden=true;controls();}}};

const rememberedToken=savedLogin();if(rememberedToken){token=rememberedToken;refresh();}
