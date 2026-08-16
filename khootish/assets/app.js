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
