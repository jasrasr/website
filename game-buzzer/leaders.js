'use strict';
const $ = id => document.getElementById(id);
const tokenKey = 'game-buzzer:leader-question-token';
const randomToken = () => Array.from(crypto.getRandomValues(new Uint8Array(24)), n => n.toString(16).padStart(2, '0')).join('');
let leaderToken = localStorage.getItem(tokenKey);
if (!leaderToken) { leaderToken = randomToken(); localStorage.setItem(tokenKey, leaderToken); }
let questions = [], draft = new Set(), initialized = false, busy = false;
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
  $('selectionCount').textContent = `${draft.size} selected`;
  const list = $('questionList');
  list.replaceChildren();
  const filtered = questions.filter(q => `${q.prompt} ${q.a} ${q.b}`.toLocaleLowerCase().includes(query));
  if (!filtered.length) { list.append(document.createTextNode('No matching questions.')); return; }
  for (const question of filtered) {
    const row = document.createElement('article'); row.className = 'question-choice';
    const label = document.createElement('label');
    const checkbox = document.createElement('input'); checkbox.type = 'checkbox'; checkbox.checked = draft.has(question.id);
    checkbox.setAttribute('aria-label', `Select question: ${question.prompt}`);
    checkbox.addEventListener('change', () => {
      if (checkbox.checked) draft.add(question.id); else draft.delete(question.id);
      notice(''); $('selectionCount').textContent = `${draft.size} selected`;
    });
    const prompt = document.createElement('span'), title = document.createElement('strong');
    const number = question.id.startsWith('q') ? `#${Number(question.id.slice(1))} · ` : `Leader suggestion${question.by ? ` · ${question.by}` : ''} · `;
    title.textContent = `${number}${question.prompt}`; prompt.append(title);
    label.append(checkbox, prompt); row.append(label);
    const answers = document.createElement('p'); answers.className = 'question-answers'; answers.textContent = `A: ${question.a}  ·  B: ${question.b}`;
    const count = document.createElement('small'); count.className = 'question-count'; count.textContent = `${question.count} leader ${question.count === 1 ? 'pick' : 'picks'}`;
    row.append(answers, count); list.append(row);
  }
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
async function load(preserveDraft = true) {
  try {
    const result = await api('state');
    questions = result.questions; window.pollActivity = result.activity;
    if (!initialized || !preserveDraft) draft = new Set(result.selected);
    initialized = true; render();
    $('connection').textContent = 'Counts update automatically';
  } catch (error) { $('connection').textContent = 'Connection unavailable'; if (!initialized) notice(error.message); }
}
$('searchQuestions').addEventListener('input', render);
$('saveSelections').addEventListener('click', async () => {
  if (busy) return;
  busy = true; $('saveSelections').disabled = true;
  try {
    const result = await api('vote', {selected:[...draft]});
    questions = result.questions; window.pollActivity = result.activity; draft = new Set(result.selected); render(); notice('Your picks are saved. Thanks for helping choose!');
  } catch (error) { notice(error.message); }
  finally { busy = false; $('saveSelections').disabled = false; }
});
$('copyTopTen').addEventListener('click', async () => {
  const text = questions.slice(0, 10).map(q => `${q.prompt} | ${q.a} | ${q.b}`).join('\n');
  try { await navigator.clipboard.writeText(text); notice('Top 10 copied. Paste them into the game setup question editor.'); }
  catch { notice('Clipboard access is unavailable in this browser.'); }
});
$('suggestionForm').addEventListener('submit', async event => {
  event.preventDefault();
  if (busy) return;
  const formElement = event.currentTarget, submitButton = formElement.querySelector('button[type="submit"]');
  const form = new FormData(formElement);
  busy = true; submitButton.disabled = true;
  try {
    const result = await api('suggest', {prompt:form.get('prompt'),a:form.get('a'),b:form.get('b')});
    questions = result.questions; window.pollActivity = result.activity; render(); formElement.reset(); notice('Your question was added to the shared list. You can select it above.');
  } catch (error) { notice(error.message); }
  finally { busy = false; submitButton.disabled = false; }
});
load(false);
setInterval(() => { if (!busy) load(true); }, 10000);
