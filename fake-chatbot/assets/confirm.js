/*
File Name: confirm.js
Revision: 2.0.0
Description: Confirm destructive admin and personal history actions.
Author: Jason Lamb (with help from ChatGPT)
Created Date: 2026-11-20
Modified Date: 2026-11-20
Changelog: 2.0.0 - Add deliberate confirmation for permanent deletes.
*/
'use strict';
document.querySelectorAll('[data-confirm]').forEach(button => {
  button.addEventListener('click', event => {
    if (!window.confirm(button.dataset.confirm)) event.preventDefault();
  });
});
