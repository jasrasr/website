// Filename: collide-viewer-polish.js
// Revision : 1.0.0
// Description : Collide-only viewer text cleanup for the public scoreboard.
// Author : Jason Lamb (with help from ChatGPT)
// Created Date : 2026-09-20
// Modified Date : 2026-09-20
// Changelog :
// 1.0.0 Shorten the walk-up song cue to "Tap for song" on the public viewer

(function () {
  if (window.__collideViewerPolishLoaded) return;
  window.__collideViewerPolishLoaded = true;

  function polishSongCue() {
    if (document.body.dataset.pageType !== 'viewer') return;

    document.querySelectorAll('.viewer-card .collide-song-hint').forEach((hint) => {
      if (hint.textContent.trim() !== 'Tap for song') {
        hint.textContent = 'Tap for song';
      }
    });
  }

  const observer = new MutationObserver(polishSongCue);
  observer.observe(document.querySelector('#app') || document.body, {
    childList: true,
    subtree: true
  });

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', polishSongCue, { once: true });
  } else {
    polishSongCue();
  }
}());
