// Filename: collide-stable-refresh.js
// Revision : 1.0.0
// Description : Collide-only viewer polling patch that avoids rebuilding the public scoreboard when data has not changed.
// Author : Jason Lamb (with help from ChatGPT)
// Created Date : 2026-09-20
// Modified Date : 2026-09-20
// Changelog :
// 1.0.0 Skip unchanged viewer re-renders so the scoreboard does not flicker every two seconds

(function () {
  if (window.__collideStableRefreshLoaded) return;
  window.__collideStableRefreshLoaded = true;

  const originalRefreshPage = window.refreshPage;
  const originalFetchScores = window.fetchScores;
  const originalRenderViewer = window.renderViewer;

  if (
    typeof originalRefreshPage !== 'function' ||
    typeof originalFetchScores !== 'function' ||
    typeof originalRenderViewer !== 'function'
  ) {
    return;
  }

  let lastViewerSignature = '';

  function normalizeSong(team) {
    const song = team?.walkup_song || null;
    if (!song) return '';
    return [
      song.file || '',
      song.url || '',
      song.original_name || '',
      song.uploaded_at || ''
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
      normalizeSong(team)
    ].join('~');
  }

  function viewerSignature(data) {
    const teams = Array.isArray(data?.teams)
      ? data.teams.map(teamSignature).join('||')
      : '';

    return [
      data?.title || '',
      data?.updatedAt || '',
      data?.hurray_event?.id || '',
      teams
    ].join('##');
  }

  async function collideStableRefreshPage(pageType) {
    if (pageType !== 'viewer') {
      return originalRefreshPage(pageType);
    }

    const data = await originalFetchScores();
    const app = document.querySelector('#app');
    const hasRenderedViewer = Boolean(app?.querySelector('.viewer-grid'));
    const nextSignature = viewerSignature(data);

    if (hasRenderedViewer && nextSignature === lastViewerSignature) {
      window.dispatchEvent(new CustomEvent('scoreboard:viewer-data-unchanged', {
        detail: { data }
      }));
      return;
    }

    lastViewerSignature = nextSignature;
    originalRenderViewer(data);
    window.dispatchEvent(new CustomEvent('scoreboard:viewer-rendered', {
      detail: { data }
    }));
  }

  window.refreshPage = collideStableRefreshPage;
  try {
    refreshPage = collideStableRefreshPage;
  } catch {
    // Some browsers may not allow assignment to the global binding.
    // The window property is still updated above.
  }
}());
