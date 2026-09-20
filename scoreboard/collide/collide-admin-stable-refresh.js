// Filename: collide-admin-stable-refresh.js
// Revision : 1.0.0
// Description : Collide-only admin refresh guard that prevents polling re-renders while editing.
// Author : Jason Lamb (with help from ChatGPT)
// Created Date : 2026-09-20
// Modified Date : 2026-09-20
// Changelog :
// 1.0.0 Pause admin auto-refresh while fields are focused or forms have unsaved edits

(function () {
  if (window.__collideAdminStableRefreshLoaded) return;
  window.__collideAdminStableRefreshLoaded = true;

  const originalRefreshPage = window.refreshPage;
  if (typeof originalRefreshPage !== 'function') return;

  let dirtySince = 0;
  let saveInProgress = false;

  function now() {
    return Date.now ? Date.now() : new Date().getTime();
  }

  function isAdminPage() {
    return document.body?.dataset?.pageType === 'admin';
  }

  function focusedEditor() {
    const active = document.activeElement;
    if (!active || active === document.body) return false;

    return Boolean(active.closest('input, textarea, select, [contenteditable="true"]'));
  }

  function markDirty(event) {
    if (!isAdminPage()) return;
    if (!event.target.closest('.team-card, .admin-title-form, .add-team-form, .collide-meta-form')) return;
    dirtySince = now();
  }

  function markSaveStart(event) {
    if (!isAdminPage()) return;
    if (!event.target.closest('form')) return;
    saveInProgress = true;
    window.setTimeout(() => {
      saveInProgress = false;
      dirtySince = 0;
    }, 2500);
  }

  function recentlyDirty() {
    return dirtySince > 0 && now() - dirtySince < 120000;
  }

  function shouldPauseAdminRefresh() {
    return isAdminPage() && (focusedEditor() || recentlyDirty() || saveInProgress);
  }

  async function collideAdminStableRefreshPage(pageType) {
    if (pageType === 'admin' && shouldPauseAdminRefresh()) {
      const statusText = document.querySelector('#status-text');
      if (statusText) {
        statusText.textContent = 'Auto-refresh paused while editing. Save or leave the field to resume.';
      }
      return;
    }

    return originalRefreshPage(pageType);
  }

  document.addEventListener('input', markDirty, true);
  document.addEventListener('change', markDirty, true);
  document.addEventListener('submit', markSaveStart, true);

  window.refreshPage = collideAdminStableRefreshPage;
  try {
    refreshPage = collideAdminStableRefreshPage;
  } catch {
    // Some browsers may not allow assignment to the global binding.
  }
}());
