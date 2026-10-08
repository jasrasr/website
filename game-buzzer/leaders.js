'use strict';
const $ = id => document.getElementById(id);
const tokenKey = 'game-buzzer:leader-question-token';
const randomToken = () => Array.from(crypto.getRandomValues(new Uint8Array(24)), n => n.toString(16).padStart(2, '0')).join('');
let leaderToken = localStorage.getItem(tokenKey);
if (!leaderToken) { leaderToken = randomToken(); localStorage.setItem(tokenKey, leaderToken); }
// Assign random priorities once per page visit, independent of popularity.
// Keep the order stable during refreshes, searches, and saves.
const questionOrder = new Map();
function votingOrder(rows) {
  for (const row of rows) if (!questionOrder.has(row.id)) questionOrder.set(row.id, Math.random());
  return [...rows].sort((a, b) => questionOrder.get(a.id) - questionOrder.get(b.id) || a.id.localeCompare(b.id));
}
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
    const number = question.id.startsWith('q') ? `Question ${Number(question.id.slice(1))} · ` : `Leader suggestion${question.by ? ` · ${question.by}` : ''} · `;
    title.textContent = `${number}${question.prompt}`; prompt.append(title);
    label.append(checkbox, prompt); row.append(label);
    const answers = document.createElement('p'); answers.className = 'question-answers'; answers.textContent = `A: ${question.a}  ·  B: ${question.b}`;
    row.append(answers); list.append(row);
  }

}
async function load(preserveDraft = true) {
  try {
    const result = await api('state');
    questions = votingOrder(result.questions);
    if (!initialized || !preserveDraft) draft = new Set(result.selected);
    initialized = true; render();
    $('connection').textContent = 'Choose your favorites';
  } catch (error) { $('connection').textContent = 'Connection unavailable'; if (!initialized) notice(error.message); }
}
$('searchQuestions').addEventListener('input', render);
$('saveSelections').addEventListener('click', async () => {
  if (busy) return;
  busy = true; $('saveSelections').disabled = true;
  try {
    const result = await api('vote', {selected:[...draft]});
    questions = votingOrder(result.questions); draft = new Set(result.selected); render(); notice('Your picks are saved. Thanks for helping choose!');
  } catch (error) { notice(error.message); }
  finally { busy = false; $('saveSelections').disabled = false; }
});
$('suggestionForm').addEventListener('submit', async event => {
  event.preventDefault();
  if (busy) return;
  const formElement = event.currentTarget, submitButton = formElement.querySelector('button[type="submit"]');
  const form = new FormData(formElement);
  busy = true; submitButton.disabled = true;
  try {
    const result = await api('suggest', {prompt:form.get('prompt'),a:form.get('a'),b:form.get('b')});
    // Add only the new favorite so other unsaved checks/unchecks stay intact.
    if (result.suggestedId) draft.add(result.suggestedId);
    questions = votingOrder(result.questions); render(); formElement.reset(); notice('Your question was added and saved as one of your favorites. Save my picks to save any other changes.');
  } catch (error) { notice(error.message); }
  finally { busy = false; submitButton.disabled = false; }
});
load(false);
setInterval(() => { if (!busy) load(true); }, 10000);
