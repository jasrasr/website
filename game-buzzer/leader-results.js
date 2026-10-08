'use strict';
const $ = id => document.getElementById(id);
// Read-only requests need a valid token but never save a ballot.
const leaderToken = '0'.repeat(48);
let questions = [];
function notice(message) { $('notice').textContent = message; $('notice').hidden = !message; }
async function api(kind, extra = {}) {
  const response = await fetch('api.php', {method:'POST', headers:{'Content-Type':'application/json'}, cache:'no-store',
    body:JSON.stringify({action:'leaderQuestions', leader:leaderToken, kind, ...extra})});
  const result = await response.json();
  if (!response.ok) throw new Error(result.error || 'Request failed.');
  return result;
}

function render() {
  const query = $('searchQuestions').value.trim().toLocaleLowerCase();
  const list = $('questionList'); list.replaceChildren();
  const ties = new Map();
  for (const question of questions) ties.set(question.count, (ties.get(question.count) || 0) + 1);
  let rank = 0, previousCount = null;
  questions.forEach((question, index) => {
    if (question.count !== previousCount) rank = index + 1;
    previousCount = question.count;
    if (!`${question.prompt} ${question.a} ${question.b}`.toLocaleLowerCase().includes(query)) return;
    const row = document.createElement('article'); row.className = 'question-choice';
    const questionLabel = `Question ${question.number ?? Number(question.id.slice(1))}`;
    const title = document.createElement('strong'); title.textContent = `${questionLabel} · ${question.prompt}`;
    const answers = document.createElement('p'); answers.className = 'question-answers'; answers.textContent = `A: ${question.a} · B: ${question.b}`;
    const count = document.createElement('small'); count.className = 'question-count'; count.textContent = `${question.count === 0 ? 'No picks yet' : `${ties.get(question.count) > 1 ? 'Tied rank' : 'Rank'} ${rank}`} · ${question.count} leader ${question.count === 1 ? 'pick' : 'picks'}`;
    row.append(title, answers, count); list.append(row);
  });
  if (!list.children.length) list.textContent = 'No matching questions.';
  const activity = window.pollActivity || [];
  const flagged = activity.filter(entry => entry.flagged).length;
  $('activitySummary').textContent = `${activity.length} anonymous browser fingerprints · ${flagged} flagged for review`;
  const activityList = $('activityList'); activityList.replaceChildren();
  for (const entry of activity) {
    const row = document.createElement('div'); row.className = `activity-row${entry.flagged ? ' activity-flagged' : ''}`;
    const id = document.createElement('strong'); id.textContent = entry.fingerprint;
    const details = document.createElement('span');
    details.textContent = `${entry.votes} pick saves · ${entry.suggestions} suggestions · ${entry.selected} currently selected`;
    row.append(id, details);
    if (entry.flagged) { const flag = document.createElement('small'); flag.textContent = 'Review activity'; row.append(flag); }
    activityList.append(row);
  }
}
async function load() {
  try {
    const result = await api('state'); questions = result.questions; window.pollActivity = result.activity;
    render(); notice(''); $('connection').textContent = 'Counts update automatically';
  } catch(error) { $('connection').textContent = 'Connection unavailable'; notice(error.message); }
}
$('searchQuestions').addEventListener('input', render);
$('copyTopTen').addEventListener('click', async () => {
  const text = questions.slice(0, 10).map(q => `${q.prompt} | ${q.a} | ${q.b}`).join('\n');
  try { await navigator.clipboard.writeText(text); notice('Top 10 copied. Paste them into the game setup question editor.'); }
  catch { notice('Clipboard access is unavailable in this browser.'); }
});

load();
setInterval(load, 10000);
