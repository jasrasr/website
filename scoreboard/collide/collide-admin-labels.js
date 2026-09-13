// Filename: collide-admin-labels.js
// Revision : 1.0.0
// Description : Collide-only admin label overrides for clearer score-reset wording.
// Author : Jason Lamb (with help from ChatGPT)
// Created Date : 2026-09-13
// Modified Date : 2026-09-13
// Changelog :
// 1.0.0 Rename reset labels so they clearly refer to scores, not team names or metadata

function collideRelabelResetButtons() {
  document.querySelectorAll('button[data-action="reset-team"]').forEach((button) => {
    button.textContent = 'Reset Team Scores';
    button.setAttribute('aria-label', 'Reset this team score to zero');
  });

  const resetAllButton = document.querySelector('#reset-all-button');
  if (resetAllButton) {
    resetAllButton.textContent = 'Reset All Team Scores';
    resetAllButton.setAttribute('aria-label', 'Reset all team scores to zero');
  }
}

const collideLabelObserver = new MutationObserver(collideRelabelResetButtons);
collideLabelObserver.observe(document.querySelector('#app') || document.body, {
  childList: true,
  subtree: true
});

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', collideRelabelResetButtons, { once: true });
} else {
  collideRelabelResetButtons();
}
