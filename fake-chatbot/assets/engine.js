/*
File Name: engine.js
Author: Jason Lamb (with help from ChatGPT)
Description: Deterministic, side-effect-free response selector shared by browser and Node tests.
Revision: 1.0.0
Created Date: 2026-11-20
Modified Date: 2026-11-20
Changelog: 1.0.0 - Match whole keywords and expand only safe date/time placeholders.
*/
(function(root, factory) {
  'use strict';
  const engine = factory();
  if (typeof module === 'object' && module.exports) {
    module.exports = engine;
  } else {
    root.FakeChatbotEngine = engine;
  }
}(typeof globalThis !== 'undefined' ? globalThis : this, function() {
  'use strict';
  function normalize(value) {
    return String(value ?? '').normalize('NFKC').toLocaleLowerCase()
      .replace(/[^\p{L}\p{N}]+/gu, ' ').trim().replace(/\s+/g, ' ');
  }
  function isMatch(message, keyword) {
    const needle = normalize(keyword);
    return needle.length > 0 && (' ' + message + ' ').includes(' ' + needle + ' ');
  }
  function expandTokens(reply, now) {
    const date = now instanceof Date && !Number.isNaN(now.valueOf()) ? now : new Date();
    return reply.replace(/\{\{(time|date)\}\}/g, (_, token) =>
      token === 'time'
        ? new Intl.DateTimeFormat(undefined, {hour:'numeric', minute:'2-digit'}).format(date)
        : new Intl.DateTimeFormat(undefined, {year:'numeric', month:'long', day:'numeric'}).format(date)
    );
  }
  function getReply(message, prompts, now = new Date()) {
    const normalizedMessage = normalize(message);
    if (!normalizedMessage) return {matched:false, reply:'Please type something so I can respond.'};
    if (Array.isArray(prompts)) {
      for (const prompt of prompts) {
        if (!prompt || !Array.isArray(prompt.keywords) || typeof prompt.reply !== 'string') continue;
        if (prompt.keywords.some(keyword => isMatch(normalizedMessage, keyword))) {
          return {matched:true, reply:expandTokens(prompt.reply, now)};
        }
      }
    }
    return {matched:false, reply:"You said: '" + String(message).slice(0,200) + "' ... and I have no clue what that means yet."};
  }
  return {getReply, normalize};
}));
