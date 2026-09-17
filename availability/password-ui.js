/* Revision 1.2.0 | 2026-09-17 | Optional admin and event password handling. */
'use strict';
(() => {
  const nativeFetch = window.fetch.bind(window);
  const passwordIds = {admin: 'admin-password', event: 'event-password'};
  const confirmIds = {admin: 'admin-password-confirm', event: 'event-password-confirm'};

  function eventIdFrom(input, body) {
    if (body?.id) return body.id;
    try { return new URL(typeof input === 'string' ? input : input.url, location.href).searchParams.get('id') || new URLSearchParams(location.search).get('event'); }
    catch { return new URLSearchParams(location.search).get('event'); }
  }
  function secretKey(id) { return `availability-passwords:${id}`; }
  function loadSecrets(id) {
    if (!id) return {};
    try { return JSON.parse(sessionStorage.getItem(secretKey(id)) || '{}') || {}; }
    catch { return {}; }
  }
  function saveSecrets(id, secrets) {
    if (!id) return;
    try { sessionStorage.setItem(secretKey(id), JSON.stringify(secrets)); } catch {}
  }
  function fieldValue(id) { return document.getElementById(id)?.value || ''; }
  function creationPasswords() {
    return {adminPassword: fieldValue(passwordIds.admin), eventPassword: fieldValue(passwordIds.event)};
  }
  function installConfirmation(kind) {
    const first = document.getElementById(passwordIds[kind]);
    const confirm = document.getElementById(confirmIds[kind]);
    if (!first || !confirm) return;
    const validate = () => confirm.setCustomValidity(confirm.value && confirm.value !== first.value ? 'Passwords do not match.' : '');
    first.addEventListener('input', validate);
    confirm.addEventListener('input', validate);
  }

  document.addEventListener('DOMContentLoaded', () => {
    installConfirmation('admin');
    installConfirmation('event');
  });

  function buildJsonInit(init, body) {
    return {...(init || {}), method: 'POST', headers: {...(init?.headers || {}), 'Content-Type': 'application/json'}, body: JSON.stringify(body)};
  }

  window.fetch = async (input, init) => {
    const url = new URL(typeof input === 'string' ? input : input.url, location.href);
    if (!url.pathname.endsWith('/api.php') && !url.pathname.endsWith('api.php')) return nativeFetch(input, init);

    let body = null;
    if (init?.body && typeof init.body === 'string') {
      try { body = JSON.parse(init.body); } catch {}
    }
    let id = eventIdFrom(input, body);
    let secrets = loadSecrets(id);
    let requestInput = input;
    let requestInit = init;

    if (body?.action === 'create') {
      const creation = creationPasswords();
      body = {...body, ...creation};
      requestInit = buildJsonInit(init, body);
    } else if (body) {
      if (body.adminToken && secrets.adminPassword) body.adminPassword = secrets.adminPassword;
      else if (!body.adminToken && secrets.eventPassword) body.eventPassword = secrets.eventPassword;
      requestInit = buildJsonInit(init, body);
    } else if ((init?.method || 'GET').toUpperCase() === 'GET' && secrets.eventPassword && id) {
      body = {action: 'view', id, eventPassword: secrets.eventPassword};
      requestInput = 'api.php';
      requestInit = buildJsonInit(init, body);
    }

    let response = await nativeFetch(requestInput, requestInit);

    if (body?.action === 'create' && response.ok) {
      try {
        const data = await response.clone().json();
        id = data.event?.id || id;
        const creation = creationPasswords();
        const next = {};
        if (creation.adminPassword) next.adminPassword = creation.adminPassword;
        if (creation.eventPassword) next.eventPassword = creation.eventPassword;
        saveSecrets(id, next);
      } catch {}
      return response;
    }

    if (response.status !== 401) return response;
    let error;
    try { error = await response.clone().json(); } catch { return response; }
    const kind = error.passwordRequired;
    if (kind !== 'admin' && kind !== 'event') return response;

    const entered = window.prompt(kind === 'admin' ? 'Enter the admin password for this event:' : 'Enter the password to view this event:');
    if (entered === null) return response;
    if (entered.length < 4) return response;

    secrets = loadSecrets(id);
    secrets[`${kind}Password`] = entered;
    saveSecrets(id, secrets);

    if (body) {
      body = {...body, [`${kind}Password`]: entered};
      return nativeFetch(requestInput, buildJsonInit(init, body));
    }

    if (id) {
      const retryBody = {action: 'view', id, [`${kind}Password`]: entered};
      return nativeFetch('api.php', buildJsonInit(init, retryBody));
    }
    return response;
  };
})();
