(() => {
  'use strict';
  const status = document.getElementById('tracker-status');
  let excluded = false;
  try { excluded = localStorage.getItem('jasr.webstats.exclude') === '1'; } catch {}
  status.textContent = navigator.doNotTrack === '1' || navigator.globalPrivacyControl
    ? 'Tracking is disabled by your browser privacy preference.'
    : excluded ? 'This browser is excluded from tracking on this site.'
    : window.JasrWebstats ? 'Tracker loaded. Verify delivery in the dashboard after each test.'
    : 'Tracker did not load. Check installation, JavaScript settings, or blockers.';
  document.querySelectorAll('button').forEach(button => {
    button.addEventListener('click', () => {
      document.getElementById('click-status').textContent = button.hasAttribute('data-analytics-ignore')
        ? 'Excluded button clicked. This should not create an event.'
        : `Clicked ${button.getAttribute('data-analytics-event')}. Refresh the dashboard to verify it arrived.`;
    });
  });
})();
