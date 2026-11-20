/*
File Name: app.js
Author: Jason Lamb (with help from ChatGPT)
Description: Mobile-friendly chat interaction and safe JSON prompt loading.
Revision: 1.0.0
Created Date: 2026-11-20
Modified Date: 2026-11-20
Changelog: 1.0.0 - Load preset replies, manage session chat, and handle clear and errors.
*/
'use strict';
(function() {
  const log = document.getElementById('messages');
  const form = document.getElementById('chat-form');
  const input = document.getElementById('message');
  const submit = document.getElementById('send');
  const clearButton = document.getElementById('clear');
  const status = document.getElementById('status');
  let prompts = [];
  const maxBubbles = 80;

  function bubble(who, content) {
    const article = document.createElement('div');
    article.className = 'message ' + (who === 'You' ? 'user' : 'bot');
    const speaker = document.createElement('span');
    speaker.className = 'speaker';
    speaker.textContent = who;
    const line = document.createElement('p');
    line.textContent = content; // Never interpret user text or JSON replies as HTML.
    article.append(speaker, line);
    log.append(article);
    while (log.children.length > maxBubbles) log.firstElementChild.remove();
    log.scrollTop = log.scrollHeight;
  }
  function reset() {
    log.replaceChildren();
    bubble('Bot', "Hello, human. I only respond to predefined prompts. Try 'help' or 'what time is it?'.");
    input.value = '';
    input.focus();
  }
  function setReady(ready, message) {
    input.disabled = !ready;
    submit.disabled = !ready;
    status.textContent = message;
  }
  form.addEventListener('submit', function(event) {
    event.preventDefault();
    if (input.disabled) return;
    const message = input.value.trim();
    if (!message) return;
    if (message.toLocaleLowerCase() === '/clear') {reset();return;}
    bubble('You', message);
    try {
      bubble('Bot', FakeChatbotEngine.getReply(message, prompts).reply);
    } catch (error) {
      bubble('Bot', 'There was an error choosing a response. Please reload.');
      console.error('Fake chatbot response error', error);
    }
    input.value = '';
    input.focus();
  });
  clearButton.addEventListener('click', reset);

  async function loadPrompts() {
    try {
      const response = await fetch('./data/prompts.json', {cache:'no-cache'});
      if (!response.ok) throw new Error('Prompt request returned HTTP ' + response.status);
      const data = await response.json();
      if (!data || !Array.isArray(data.prompts) || data.prompts.length === 0 ||
          !data.prompts.every(entry => entry && Array.isArray(entry.keywords) &&
              entry.keywords.every(k => typeof k === 'string') && typeof entry.reply === 'string')) {
        throw new Error('Prompts JSON is invalid or empty.');
      }
      if (typeof FakeChatbotEngine === 'undefined') throw new Error('Chat engine is unavailable.');
      prompts = data.prompts;
      setReady(true, prompts.length + ' preset responses loaded · no AI connection');
      reset();
    } catch (error) {
      console.error('Unable to load fake chatbot prompts', error);
      log.replaceChildren();
      bubble('Bot', 'Could not load prompt definitions. Serve the project over HTTP(S) and verify data/prompts.json.');
      setReady(false, 'Responses unavailable. Check setup.');
    }
  }
  loadPrompts();
}());
