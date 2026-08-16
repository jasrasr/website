async function api(action, data = {}, method = 'POST') {
  const options = { method, headers: { 'Content-Type': 'application/json' } };
  if (method !== 'GET') options.body = JSON.stringify(data);
  const response = await fetch(`api.php?action=${encodeURIComponent(action)}`, options);
  const result = await response.json();
  if (!result.ok) throw new Error(result.error || 'Request failed.');
  return result;
}
function escapeHtml(value){return String(value ?? '').replace(/[&<>'"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[c]));}
function renderLeaderboard(players, limit = 10) {
  if (!players.length) return '<p class="muted">No teams have joined yet.</p>';
  return `<ol class="leaderboard">${players.slice(0,limit).map((p,i)=>`<li><span class="rank">${i+1}</span><span><i class="swatch" style="background:${p.color}"></i>${escapeHtml(p.team_name)}</span><span class="points">${p.score.toLocaleString()}</span></li>`).join('')}</ol>`;
}
function remainingSeconds(game) {
  if (!game.ends_at) return 0;
  const offset = game.server_time - (Date.now()/1000);
  return Math.max(0, game.ends_at - ((Date.now()/1000)+offset));
}
function formatExpiry(seconds){
  seconds=Math.max(0,Number(seconds)||0);
  const h=Math.floor(seconds/3600),m=Math.floor((seconds%3600)/60);
  if(h>0)return `${h}h ${m}m`;
  return `${Math.max(1,m)}m`;
}
async function runKhootishMaintenance(){
  try{await fetch('maintenance.php',{cache:'no-store'});}catch(e){}
}
async function refreshActiveGames(){
  const box=document.getElementById('activeGamesList');
  if(!box)return;
  try{
    const r=await fetch('active-games.php',{cache:'no-store'});
    const j=await r.json();
    if(!j.ok)throw new Error(j.error||'Unable to load active games.');
    const games=j.games||[];
    box.innerHTML=games.length?games.map(g=>{
      const status=g.phase==='lobby'?'Lobby':g.phase==='question'?'Question active':g.phase==='results'?'Showing results':g.phase;
      const q=g.question_number?`Question ${g.question_number} of ${g.question_count}`:`Not started`;
      const answers=g.phase==='question'?` • ${g.answered_count}/${g.team_count} answered`:'';
      return `<article class="active-game"><div><div class="row"><strong>${escapeHtml(g.title)}</strong><span class="status-chip">${escapeHtml(status)}</span></div><div class="active-game-code">${escapeHtml(g.code)}</div><p class="muted">${g.team_count} team${g.team_count===1?'':'s'} • ${q}${answers} • Expires in ${formatExpiry(g.expires_in_seconds)}</p></div><div class="row"><a class="button" href="host.php?code=${encodeURIComponent(g.code)}">Manage</a><a class="button secondary" target="_blank" href="display.php?code=${encodeURIComponent(g.code)}">Display</a></div></article>`;
    }).join(''):'<p class="muted">No active games. Launch a quiz below to create one.</p>';
    const count=document.getElementById('activeGamesCount');if(count)count.textContent=`${games.length} active`;
  }catch(e){box.innerHTML=`<p class="muted">${escapeHtml(e.message)}</p>`;}
}
function installActiveGamesDashboard(){
  const admin=document.getElementById('adminArea');
  if(!admin||document.getElementById('activeGamesPanel'))return;
  const panel=document.createElement('section');
  panel.className='card';panel.id='activeGamesPanel';
  panel.innerHTML='<div class="topbar"><div><h2>Active Games</h2><p class="muted">All launched games remain here until finished or automatically expired after 24 hours.</p></div><span class="pill light-pill" id="activeGamesCount">0 active</span></div><div id="activeGamesList"><p class="muted">Loading active games…</p></div>';
  admin.insertBefore(panel,admin.firstChild);
  refreshActiveGames();
  setInterval(refreshActiveGames,3000);
}
document.addEventListener('DOMContentLoaded',()=>{runKhootishMaintenance();installActiveGamesDashboard();setInterval(runKhootishMaintenance,60000);});
