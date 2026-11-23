/*
File Name: app.js
Revision: 2.0.2
Description: Responsive fake AI frontend, public PHP response API and server-backed anonymous history.
Author: Jason Lamb (with help from ChatGPT)
Created Date: 2026-11-20
Modified Date: 2026-11-22
Changelog: 2.0.2 - Navigate recent submitted questions with Up and Down arrows. 2.0.1 - Clarify anonymous history is hidden from the visitor view while retained for admins. 2.0.0 - Display matched rule reasons, persist questions via API and support history deletion.
*/
'use strict';
(() => {
  const log = document.getElementById('messages');
  const form = document.getElementById('chat-form');
  const input = document.getElementById('message');
  const submit = document.getElementById('send');
  const clearButton = document.getElementById('clear');
  const forgetButton = document.getElementById('forget');
  const status = document.getElementById('status');
  const limit = 100;
  let historyQuestions = [];
  let historyPosition = -1;
  let unsentDraft = '';
  function bubble(role, content, detail = '') {
    const wrapper = document.createElement('article');
    wrapper.className = 'message ' + (role === 'You' ? 'user' : 'bot');
    const label = document.createElement('span');
    label.className = 'speaker';
    label.textContent = role;
    const body = document.createElement('p');
    body.textContent = String(content);
    wrapper.append(label, body);
    if (detail) {
      const foot = document.createElement('small');
      foot.className = 'match-info';
      foot.textContent = detail;
      wrapper.append(foot);
    }
    log.append(wrapper);
    while (log.children.length > limit) log.firstElementChild.remove();
    log.scrollTop = log.scrollHeight;
  }
  function reset() {
    log.replaceChildren();
    bubble('Fake bot', "Welcome to Very Fake AI. I have the confidence of an expert and the database of a potato. Try 'potato salad', 'Mr. Potato Head', or 'printer'.");
    input.value = '';
    historyPosition = -1;
    unsentDraft = '';
  }
  async function request(action, question = '') {
    const data = new URLSearchParams({action, question});
    const response = await fetch('api.php', {
      method:'POST', body:data, credentials:'same-origin',
      headers:{'X-Requested-With':'XMLHttpRequest'}
    });
    let result;
    try { result = await response.json(); } catch { throw new Error('The server returned something even the fake brain did not expect.'); }
    if (!response.ok) throw new Error(result.error || 'Request unsuccessful.');
    return result;
  }
  async function loadHistory() {
    try {
      const response = await fetch('api.php?action=history', {credentials:'same-origin',cache:'no-store'});
      if (!response.ok) throw new Error('Unable to read history.');
      const result = await response.json();
      reset();
      const saved = Array.isArray(result.history) ? result.history : [];
      historyQuestions = saved.map(entry => entry.question).filter(question => typeof question === 'string' && question.trim() !== '');
      const entries = [...saved].reverse();
      for (const entry of entries.slice(-50)) {
        bubble('You', entry.question);
        bubble('Fake bot', entry.answer, entry.ruleId ? 'Past match · rule: ' + entry.ruleId : 'Past match · no rule');
      }
      status.textContent = result.linked ? 'FAKE AI · Account-linked history' : 'FAKE AI · Anonymous question history';
      submit.disabled = false;
      input.focus();
    } catch {
      reset();
      historyQuestions = [];
      status.textContent = 'History unavailable; retry after server setup.';
      // A failed history endpoint likely means writes will fail too. Let the API report it.
      submit.disabled = false;
    }
  }
  input.addEventListener('keydown', event => {
    if (event.isComposing || event.altKey || event.ctrlKey || event.metaKey || event.shiftKey) return;
    if (event.key === 'ArrowUp') {
      if (historyQuestions.length === 0) return;
      if (historyPosition === -1) unsentDraft = input.value;
      historyPosition = Math.min(historyPosition + 1, historyQuestions.length - 1);
    } else if (event.key === 'ArrowDown' && historyPosition !== -1) {
      historyPosition -= 1;
    } else return;
    event.preventDefault();
    input.value = historyPosition === -1 ? unsentDraft : historyQuestions[historyPosition];
    input.setSelectionRange(input.value.length, input.value.length);
  });
  input.addEventListener('input', () => {
    if (historyPosition !== -1) {
      historyPosition = -1;
      unsentDraft = input.value;
    }
  });
  form.addEventListener('submit', async event => {
    event.preventDefault();
    const question = input.value.trim();
    if (!question || submit.disabled) return;
    bubble('You', question);
    input.value = '';
    submit.disabled = true;
    status.textContent = 'Consulting extremely fictional neurons...';
    try {
      const answer = await request('chat', question);
      bubble('Fake bot', answer.answer, answer.rule ?
        'Matched "' + answer.rule + '" · ' + answer.matchType + ' match · score ' + answer.score :
        'No matching rule · professionally improvised fallback');
      historyQuestions.unshift(question);
      if (historyQuestions.length > 100) historyQuestions.pop();
      historyPosition = -1;
      unsentDraft = '';
      status.textContent = answer.linked ? 'Fake intelligence · Saved to account' : 'Fake intelligence · Stored anonymously';
    } catch (error) {
      bubble('Fake bot', error.message);
      status.textContent = 'Fake brain experiencing authentic technical difficulties';
    } finally {
      submit.disabled = false;
      input.focus();
    }
  });
  clearButton.addEventListener('click', () => {reset(); input.focus();});
  forgetButton.addEventListener('click', async () => {
    if (!window.confirm('Clear anonymous questions from this browser\'s history? Administrators can still review them until they expire after 90 days. Account-linked questions are not affected.')) return;
    forgetButton.disabled = true;
    try {
      await request('clear_guest_history');
      await loadHistory();
      status.textContent = 'Anonymous history cleared from this browser';
    } catch (error) {
      status.textContent = error.message;
    } finally {
      forgetButton.disabled = false;
    }
  });
  loadHistory();
})();
