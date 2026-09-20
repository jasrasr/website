/* JASR Webstats 1.0.0 — no third-party dependencies. */
(() => {
  'use strict';
  if (window.JasrWebstats || navigator.doNotTrack === '1' || navigator.globalPrivacyControl) return;
  const script = document.currentScript;
  if (!script) return;
  const endpoint = new URL('../../collect.php', script.src).href;
  const randomId = () => Array.from(crypto.getRandomValues(new Uint8Array(16)), b => b.toString(16).padStart(2, '0')).join('');
  const cleanUrl = value => {
    try {
      const u = new URL(value, location.href);
      return /^https?:$/.test(u.protocol) ? u.origin + u.pathname : '';
    } catch { return ''; }
  };
  let excluded = false;
  let session = randomId();
  try {
    excluded = localStorage.getItem('jasr.webstats.exclude') === '1';
    session = sessionStorage.getItem('jasr.webstats.session') || session;
    sessionStorage.setItem('jasr.webstats.session', session);
  } catch { /* Tracking still works without storage; session becomes per-page. */ }
  const track = (kind, target = '', label = '') => {
    if (excluded || document.documentElement.hasAttribute('data-analytics-ignore')) return;
    const body = JSON.stringify({id: randomId(), kind, page: cleanUrl(location.href),
      target: target ? cleanUrl(target) : '', label: /^[a-zA-Z0-9_.:-]{0,80}$/.test(label) ? label : '',
      referrer: document.referrer ? new URL(document.referrer).origin : '', session});
    // text/plain avoids a cross-origin preflight. Omit credentials across every origin.
    fetch(endpoint, {method: 'POST', mode: 'cors', credentials: 'omit', keepalive: true,
      headers: {'Content-Type': 'text/plain;charset=UTF-8'}, body}).catch(() => {});
  };
  window.JasrWebstats = {
    pageview: () => track('pageview'),
    event: name => track('click', '', name),
    exclude: (value = true) => {
      excluded = Boolean(value);
      try { localStorage.setItem('jasr.webstats.exclude', excluded ? '1' : '0'); } catch {}
    }
  };
  track('pageview');
  document.addEventListener('click', event => {
    if (!(event.target instanceof Element)) return;
    const element = event.target.closest('a,button,input[type="submit"],input[type="button"],[data-analytics-event]');
    if (!element || element.closest('[data-analytics-ignore]')) return;
    // Never read button text, field values, DOM ids, or mailto/tel addresses.
    track('click', element.matches('a') ? element.href : '',
      element.getAttribute('data-analytics-event') || element.tagName.toLowerCase());
  }, {capture: true, passive: true});
})();
