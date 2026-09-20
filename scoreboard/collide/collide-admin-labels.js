// Filename: collide-admin-labels.js
// Revision : 1.0.1
// Description : Collide-only admin label overrides for clearer score-reset wording.
// Author : Jason Lamb (with help from ChatGPT)
// Created Date : 2026-09-13
// Modified Date : 2026-09-20
// Changelog :
// 1.0.1 Avoid MutationObserver relabel loops by only writing labels when text actually changes
// 1.0.0 Rename reset labels so they clearly refer to scores, not team names or metadata

function collideSetButtonText(button, text, ariaLabel) {
  if (!button) return;

  if (button.textContent !== text) {
    button.textContent = text;
  }

  if (button.getAttribute('aria-label') !== ariaLabel) {
    button.setAttribute('aria-label', ariaLabel);
  }
}

function collideRelabelResetButtons() {
  document.querySelectorAll('button[data-action="reset-team"]').forEach((button) => {
    collideSetButtonText(button, 'Reset Team Scores', 'Reset this team score to zero');
  });

  collideSetButtonText(
    document.querySelector('#reset-all-button'),
    'Reset All Team Scores',
    'Reset all team scores to zero'
  );
}

const collideLabelObserver = new MutationObserver(() => {
  window.requestAnimationFrame(collideRelabelResetButtons);
});

collideLabelObserver.observe(document.querySelector('#app') || document.body, {
  childList: true,
  subtree: true
});

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', collideRelabelResetButtons, { once: true });
} else {
  collideRelabelResetButtons();
}
