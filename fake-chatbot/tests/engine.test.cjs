/*
File Name: engine.test.cjs
Author: Jason Lamb (with help from ChatGPT)
Description: Node built-in tests for deterministic fake chatbot reply selection.
Revision: 1.0.0
Created Date: 2026-11-20
Modified Date: 2026-11-20
Changelog: 1.0.0 - Test prompt matching, safe tokens, and unknown messages.
*/
'use strict';
const test = require('node:test');
const assert = require('node:assert/strict');
const {getReply} = require('../assets/engine.js');
const {prompts} = require('../data/prompts.json');

test('responds to a known greeting regardless of case', () => {
  assert.equal(getReply('HELLO!', prompts).reply, 'Hello there, human. Nice to see you.');
});
test('matches whole words rather than fragments', () => {
  assert.equal(getReply('This is my question', prompts).matched, false);
  assert.equal(getReply('Can you tell me the TIME?', prompts).matched, true);
});
test('supports multi-word keywords', () => {
  assert.match(getReply('How are you?', prompts).reply, /100 percent operational/);
});
test('expands supported time/date placeholders', () => {
  const fixedDate = new Date('2026-11-20T18:05:00Z');
  assert.doesNotMatch(getReply('time', prompts, fixedDate).reply, /\{\{/);
  assert.doesNotMatch(getReply('date', prompts, fixedDate).reply, /\{\{/);
});
test('does not execute code from reply text', () => {
  const message = getReply('execute', [{keywords:['execute'], reply:'$(Get-Date) <script>alert(1)</script>'}]);
  assert.equal(message.reply, '$(Get-Date) <script>alert(1)</script>');
});
test('handles empty and invalid prompts', () => {
  assert.match(getReply('', prompts).reply, /Please type/);
  assert.equal(getReply('unknown', []).matched, false);
});
