'use strict';
const $ = id => document.getElementById(id);
const params = new URLSearchParams(location.search);
const fragment = new URLSearchParams(location.hash.slice(1));
const room = params.get('room');
const role = params.get('view') || 'team';
const storeKey = `game-buzzer:${room}:`;
const randomToken = () => Array.from(crypto.getRandomValues(new Uint8Array(24)), n => n.toString(16).padStart(2, '0')).join('');
function stored(key, initial) { let value = localStorage.getItem(storeKey + key); if (!value) { value = initial || randomToken(); localStorage.setItem(storeKey + key, value); } return value; }
let host = room && role === 'host' ? stored('host', fragment.get('host') || '') : '';
let device = room && role === 'team' ? stored('device') : '';
let voter = room && role === 'survey' ? stored('voter') : '';
let teamKey = fragment.get('team') || (room ? localStorage.getItem(storeKey + 'teamKey') : '');
if (teamKey && room) localStorage.setItem(storeKey + 'teamKey', teamKey);
if (fragment.get('host') && room && role === 'host') { host = fragment.get('host'); localStorage.setItem(storeKey + 'host', host); }
if (location.hash) history.replaceState(null, '', location.pathname + location.search);
let state = null, offset = 0, rtt = 0, lastGood = 0, busy = false, lastChime = '', audio = null, rendered = '', generation = 0;
function notice(message) { $('notice').textContent = message; $('notice').hidden = !message; }
async function api(action, extra = {}) {
  const started = performance.now(), wall = Date.now();
  const res = await fetch('api.php', {method:'POST', headers:{'Content-Type':'application/json'}, cache:'no-store',
    body:JSON.stringify({action, room, ...(host ? {host} : {}), ...(device ? {device} : {}), ...(voter ? {voter} : {}), ...extra}), signal:AbortSignal.timeout(7000)});
  const out = await res.json();
  if (!res.ok) throw new Error(out.error || 'Request failed.');
  if (out.serverNow) { rtt = Math.round(performance.now() - started); offset = out.serverNow * 1000 - (wall + rtt / 2); lastGood = Date.now(); }
  return out;
}
const el = (tag, text, cls) => { const n = document.createElement(tag); if (text !== undefined) n.textContent = text; if (cls) n.className = cls; return n; };
function button(text, fn, cls = 'secondary') { const b = el('button', text, cls); b.type = 'button'; b.addEventListener('click', fn); return b; }
function url(view, hash = '') { const u = new URL(location.pathname, location.origin); u.searchParams.set('room', room); u.searchParams.set('view', view); u.hash = hash; return u.href; }
function linkRow(parent, name, value) {
  const row = el('div', undefined, 'linkrow'); const field = el('input'); field.value = value; field.readOnly = true; field.setAttribute('aria-label', name + ' link');
  row.append(el('span', name), field, button('Copy', async () => { try { await navigator.clipboard.writeText(value); notice('Link copied.'); } catch { field.select(); notice('Select and copy the link.'); } })); parent.append(row);
}
function links(s) {
  if ($('links').children.length) return;
  linkRow($('links'), 'Private host', url('host', `host=${host}`));
  linkRow($('links'), 'Projector', url('projector'));
  linkRow($('links'), 'Student survey', url('survey'));
  linkRow($('links'), 'Check-in kiosk', url('survey') + '&kiosk=1');
  s.teamLinks.forEach(t => linkRow($('teamLinks'), s.teams[t.team].name, url('team', `team=${t.key}`)));
}
async function command(action, extra = {}) {
  if (busy) return;
  generation++;
  busy = true;
  try { notice(''); state = await api(action, {operation:randomToken(), ...extra}); render(); }
  catch(e) { notice(e.message + ' Check the current screen before trying again.'); }
  finally { busy = false; }
}
function survey(s) {
  if (!$('surveyQuestions').children.length) s.questions.forEach((q,i) => {
    const f = el('fieldset'); f.append(el('legend', `${i+1}. ${q.prompt}`));
    ['a','b'].forEach(a => { const label = el('label'); const input = el('input'); input.type='radio'; input.name=`q${i}`; input.value=a; input.required=true; label.append(input, document.createTextNode(`${a.toUpperCase()}: ${q[a]}`)); f.append(label); });
    $('surveyQuestions').append(f);
  });
  $('ballot').hidden = s.surveySubmitted || !s.surveyOpen;
  $('thanks').hidden = !s.surveySubmitted;
  $('nextStudent').hidden = params.get('kiosk') !== '1' || !s.surveyOpen;
  if (!s.surveyOpen && !s.surveySubmitted) notice('The arrival survey has closed.');
}
function render() {
  const s = state; if (!s) return;
  $('gameTitle').textContent = s.title;
  $('roleLabel').textContent = role === 'team' && s.myTeam !== null ? s.teams[s.myTeam].name : {host:'HOST DESK',projector:'LIVE GAME',survey:'ARRIVAL SURVEY'}[role] || 'TEAM PHONE';
  if (role === 'host') {
    if (!s.teamLinks) { notice('Host access missing. Reopen your private host invitation.'); return; }
    links(s); $('surveyStatus').textContent = `${s.ballotCount} surveys submitted · ${s.surveyOpen ? 'Arrival survey open' : 'Answers locked'} · Phones per team: ${s.devices.join(' / ')}`;
    $('closeSurvey').disabled = !s.surveyOpen || !s.ballotCount;
    $('showQuestion').disabled = s.surveyOpen;
    $('reveal').disabled = s.question === null || s.revealed;
  }
  if (role === 'survey') { survey(s); return; }
  const signature = JSON.stringify([s.teams,s.ranking,s.question,s.revealed,s.results,s.predictionTeams,s.myPrediction,s.round]);
  if (signature !== rendered) {
    rendered = signature;
    $('scores').replaceChildren();
    [...s.teams].sort((a,b)=>b.score-a.score || a.id-b.id).forEach(t => {
      const row = el('div',undefined,'score'), right = el('div'); right.append(el('strong',String(t.score)));
      if (role === 'host') [-1,1].forEach(delta => { const b=button(delta===1?'+':'−',()=>command('adjust',{team:t.id,delta})); b.setAttribute('aria-label',`${delta===1?'Add':'Remove'} point for ${t.name}`); right.append(b); });
      row.append(el('span',t.name),right); $('scores').append(row);
    });
    $('ranking').replaceChildren(); $('emptyRanking').hidden = s.ranking.length > 0;
    s.ranking.forEach((entry,i) => {
      const row = el('li', s.teams[entry.team].name);
      row.append(el('small',`${entry.elapsedMs} ms after opening${i ? ` · +${entry.gapMs} ms behind first${entry.gapMs <= 150 ? ' · CLOSE FINISH' : ''}` : ' · FIRST'}`));
      if (role === 'host') row.append(button('Award +1',()=>command('award',{team:entry.team,round:s.round})));
      $('ranking').append(row);
    });
    $('questionOptions').replaceChildren(); $('resultBars').replaceChildren();
    if (s.question !== null) {
      const q=s.questions[s.question];
      ['a','b'].forEach(a=>{const choice=el('div',undefined,'choice');choice.append(el('strong',a.toUpperCase()+' · '),document.createTextNode(q[a]));$('questionOptions').append(choice);});
      if (s.results) {
        ['a','b'].forEach(a=>{const row=el('div',undefined,'result'),bar=el('progress');bar.max=s.ballotCount||1;bar.value=s.results[a];bar.setAttribute('aria-label',a.toUpperCase()+' votes');row.append(el('strong',a.toUpperCase()),bar,el('span',`${s.results[a]} votes`));$('resultBars').append(row);});
        $('resultBars').append(el('h3',s.results.majority==='tie'?'It’s a tie!':`${s.results.majority.toUpperCase()} wins the room!`));
      }
    }
  }
  const chimeKey = s.ranking.length ? `${s.round}:${s.ranking[0].team}` : '';
  if(chimeKey && chimeKey !== lastChime) { if(audio) {const osc=audio.createOscillator(),gain=audio.createGain();osc.connect(gain);gain.connect(audio.destination);osc.frequency.value=880;gain.gain.setValueAtTime(.15,audio.currentTime);gain.gain.exponentialRampToValueAtTime(.001,audio.currentTime+.35);osc.start();osc.stop(audio.currentTime+.35);} }
  lastChime=chimeKey;
  tick();
}
function tick() {
  if (!state || role === 'survey') return;
  const s=state, now=(Date.now()+offset)/1000, stale=Date.now()-lastGood>4000;
  $('connection').textContent = stale ? 'Connection lost — reconnecting…' : `Connected · ${rtt} ms round trip`;
  $('buzz').hidden=role!=='team' || s.question!==null;
  $('predictionButtons').hidden=role!=='team' || s.question===null || s.revealed;
  document.querySelectorAll('[data-answer]').forEach(b=>b.disabled=stale || busy || !s.predictionOpen || s.myPrediction!==null || s.myTeam===null);
  if (s.question!==null) {
    $('roundLabel').textContent=`MAJORITY RULES · ${s.question+1} / 10`;
    $('headline').textContent=s.questions[s.question].prompt;
    $('roundStatus').textContent=s.revealed?'Survey says…':s.myPrediction ? `Your team locked ${s.myPrediction.toUpperCase()} · ${s.predictionTeams.length}/6 teams ready` : `${s.predictionTeams.length}/6 teams locked in. Predict the whole room’s majority.`;
    $('timing').textContent=''; return;
  }
  const mine=s.ranking.findIndex(x=>x.team===s.myTeam);
  const open=s.openAt!==null && now>=s.openAt && now<s.closeAt;
  const countdown=s.openAt!==null && now<s.openAt && s.closeAt>s.openAt;
  $('roundLabel').textContent=`BUZZER · ROUND ${s.round}`;
  $('headline').textContent=countdown ? `Get ready… ${Math.ceil(s.openAt-now)}` : open ? 'Buzz in!' : s.round ? 'Round closed' : 'Get your team ready.';
  $('roundStatus').textContent=open ? `${Math.max(0,Math.ceil(s.closeAt-now))} seconds remaining` : countdown ? 'Wait for GO.' : 'The host controls the next round.';
  $('buzz').disabled=!open || stale || busy || mine>=0 || s.myTeam===null;
  $('buzz').textContent=mine>=0 ? `TEAM #${mine+1}` : stale ? 'OFFLINE' : open ? 'BUZZ!' : 'WAIT';
  $('timing').textContent=`Ranked by server acceptance, not phone clocks. ${rtt>300?'Connection is slow; close results may be affected.':'Close finishes may be affected by network delays.'}`;
}
async function poll() {
  try { if (!busy) {const requestedGeneration=generation;const next=await api('state');if(requestedGeneration===generation&&!busy){state=next;render();}} }
  catch(e) { $('connection').textContent='Connection lost — reconnecting…'; if(!state) notice(e.message); }
  finally { setTimeout(poll,document.hidden?2000:(role==='survey'?2000:700)); }
}
$('create').addEventListener('submit',async e=>{e.preventDefault();const b=e.submitter;b.disabled=true;try{
  const form=new FormData(e.target);const questions=$('questions').value.trim().split('\n').map(line=>{const p=line.split('|').map(s=>s.trim());if(p.length!==3)throw Error('Each question needs exactly three parts separated by |.');return {prompt:p[0],a:p[1],b:p[2]};});
  const out=await api('create',{title:form.get('title'),password:form.get('password'),questions});location.href=`?room=${out.id}&view=host#host=${out.host}`;
}catch(err){notice(err.message);}finally{b.disabled=false;}});
$('arm').onclick=()=>command('arm',{duration:Number($('duration').value)});
$('closeBuzz').onclick=()=>command('closeBuzz',{round:state.round});
$('closeSurvey').onclick=()=>{if(confirm('Lock all arrival answers and close the survey? This cannot be reopened.'))command('closeSurvey');};
$('showQuestion').onclick=()=>command('question',{question:Number($('questionSelect').value)});
$('reveal').onclick=()=>{if(state.predictionTeams.length<6&&!confirm('Not every team has predicted. Reveal and score anyway?'))return;command('reveal',{question:state.question});};
// Pointer-down sends immediately; keyboard activation remains accessible.
function buzz(){ if(!$('buzz').disabled) command('buzz',{round:state.round}); }
$('buzz').addEventListener('pointerdown',e=>{if(e.button===0){e.preventDefault();buzz();}});
$('buzz').addEventListener('click',e=>{if(e.detail===0)buzz();});
$('buzz').addEventListener('contextmenu',e=>e.preventDefault());
document.querySelectorAll('[data-answer]').forEach(b=>b.onclick=()=>command('predict',{question:state.question,answer:b.dataset.answer}));
$('ballot').addEventListener('submit',async e=>{e.preventDefault();const data=new FormData(e.target);await command('vote',{answers:Array.from({length:10},(_,i)=>data.get(`q${i}`))});});
$('nextStudent').onclick=()=>{if(!confirm('Ready for a different student? Each student should submit only once.'))return;voter=randomToken();localStorage.setItem(storeKey+'voter',voter);$('ballot').reset();state.surveySubmitted=false;render();};
$('fullscreen').onclick=()=>{if(document.fullscreenElement)document.exitFullscreen();else document.documentElement.requestFullscreen?.().catch(()=>notice('Full screen is not supported on this device.'));};
$('sound').onclick=async()=>{try{audio=audio||new(window.AudioContext||window.webkitAudioContext)();await audio.resume();$('sound').textContent='Chime enabled';}catch{notice('Audio unavailable on this device.');}};
async function start(){
 if(!room){try{const res=await fetch('questions.json');const qs=await res.json();$('questions').value=qs.map(q=>`${q.prompt} | ${q.a} | ${q.b}`).join('\n');}catch{notice('Unable to load questions. Reload the page.');}return;}
 $('welcome').hidden=true;$('game').hidden=false;$('host').hidden=role!=='host';$('survey').hidden=role!=='survey';$('playArea').hidden=role==='survey';document.body.classList.toggle('projector',role==='projector');
 for(let i=0;i<10;i++){const o=el('option',`Question ${i+1}`);o.value=i;$('questionSelect').append(o);}
 if(role==='team') {try{if(!teamKey)throw Error('Open the team invitation from your host.');state=await api('join',{key:teamKey});render();}catch(e){notice(e.message);return;}}
 poll();setInterval(tick,100);
}
start();
