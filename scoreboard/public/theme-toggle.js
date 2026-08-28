// Filename: theme-toggle.js
// Revision : 1.0.0
// Description : Adds a persisted light/dark theme toggle for CVC Scoreboard pages.
// Author : Jason Lamb (with help from Codex CLI)
// Created Date : 2026-06-21
// Modified Date : 2026-06-21
// Changelog :
// 1.0.0 Initial release; dark remains the default theme

(function () {
  const storageKey = 'cvc-scoreboard-theme';
  const root = document.documentElement;

  function getStoredTheme() {
    try {
      return window.localStorage.getItem(storageKey) === 'light' ? 'light' : 'dark';
    } catch (error) {
      return 'dark';
    }
  }

  function storeTheme(theme) {
    try {
      window.localStorage.setItem(storageKey, theme);
    } catch (error) {
      // Storage can be blocked in private browsing; the page-level theme still works.
    }
  }

  function applyTheme(theme) {
    if (theme === 'light') {
      root.setAttribute('data-theme', 'light');
    } else {
      root.removeAttribute('data-theme');
    }
  }

  function updateButton(button, theme) {
    const nextTheme = theme === 'light' ? 'dark' : 'light';
    button.textContent = theme === 'light' ? 'Dark mode' : 'Light mode';
    button.setAttribute('aria-label', `Switch to ${nextTheme} mode`);
    button.setAttribute('title', `Switch to ${nextTheme} mode`);
    button.setAttribute('aria-pressed', theme === 'light' ? 'true' : 'false');
  }

  function initThemeToggle() {
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'theme-toggle';
    document.body.appendChild(button);

    let theme = getStoredTheme();
    applyTheme(theme);
    updateButton(button, theme);

    button.addEventListener('click', () => {
      theme = theme === 'light' ? 'dark' : 'light';
      applyTheme(theme);
      storeTheme(theme);
      updateButton(button, theme);
    });
  }

  applyTheme(getStoredTheme());

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initThemeToggle);
  } else {
    initThemeToggle();
  }
})();
