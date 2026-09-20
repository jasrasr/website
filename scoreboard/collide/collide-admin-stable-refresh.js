// Filename: collide-admin-stable-refresh.js
// Revision : 1.1.0
// Description : Collide-only admin refresh guard that prevents unnecessary polling re-renders.
// Author : Jason Lamb (with help from ChatGPT)
// Created Date : 2026-09-20
// Modified Date : 2026-09-20
// Changelog :
// 1.0.0 Pause admin auto-refresh while fields are focused or forms have unsaved edits
// 1.1.0 Skip unchanged admin re-renders entirely so background polling no longer flashes the page

(function () {
  if (window.__collideAdminStableRefreshLoaded) return;
  window.__collideAdminStableRefreshLoaded = true;

  const originalRefreshPage = window.refreshPage;
  const originalFetchScores = window.fetchScores;
  const originalRenderAdmin = window.renderAdmin;

  if (
    typeof originalRefreshPage !== 'function' ||
    typeof originalFetchScores !== 'function' ||
    typeof originalRenderAdmin !== 'function'
  ) {
    return;
  }

  let dirtySince = 0;
  let saveInProgress = false;
  let lastAdminSignature = '';

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

  function normalizedSong(team) {
    const song = team?.walkup_song || null;
    if (!song) return '';

    return [
      song.file || '',
      song.original_name || '',
      song.uploaded_at || '',
      song.size_bytes || 0
    ].join('|');
  }

  function teamSignature(team) {
    return [
      team?.id || '',
      team?.name || '',
      Number(team?.score || 0),
      team?.color || '',
      team?.score_changed_at || '',
      team?.motto || '',
      team?.placeholder_song || '',
      normalizedSong(team)
    ].join('~');
  }

  function adminSignature(data) {
    const teams = Array.isArray(data?.teams)
      ? data.teams.map(teamSignature).join('||')
      : '';

    return [
      data?.title || '',
      data?.updatedAt || '',
      data?.hasPreviousSnapshot ? '1' : '0',
      teams
    ].join('##');
  }

  async function seedAdminSignature() {
    if (!isAdminPage()) return;

    try {
      const data = await originalFetchScores();
      lastAdminSignature = adminSignature(data);
    } catch {
      // Initial app rendering owns user-facing error handling.
    }
  }

  async function collideAdminStableRefreshPage(pageType) {
    if (pageType !== 'admin') {
      return originalRefreshPage(pageType);
    }

    if (shouldPauseAdminRefresh()) {
      return;
    }

    const data = await originalFetchScores();
    const nextSignature = adminSignature(data);
    const app = document.querySelector('#app');
    const hasRenderedAdmin = Boolean(app?.querySelector('.team-grid'));

    if (hasRenderedAdmin && lastAdminSignature !== '' && nextSignature === lastAdminSignature) {
      return;
    }

    lastAdminSignature = nextSignature;
    return originalRenderAdmin(data);
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

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
      window.setTimeout(seedAdminSignature, 500);
    }, { once: true });
  } else {
    window.setTimeout(seedAdminSignature, 500);
  }
}());
