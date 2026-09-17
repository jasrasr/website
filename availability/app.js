/* Revision 1.1.0 | 2026-09-17 | Private contacts, household/food details, proposed times and expiry.
 * History: 1.0.0 — Event creation, voting, live rankings and response matrix. */
'use strict';
const $ = id => document.getElementById(id);
const params = new URLSearchParams(location.search);
let eventId = params.get('event'), event = null, editing = false, dirty = false, sequence = 0;
let chosenDates = [], answers = {}, formRevision = 0, savingVote = false;
const storageKey = () => `availability:${eventId}`;
let credentials = {};
function remember() {
  try { localStorage.setItem(storageKey(), JSON.stringify(credentials)); }
  catch { message('Browser storage is unavailable. Save your private link to return later.'); }
}
function message(text, error = false) { $('message').textContent = text; $('message').classList.toggle('error', error); }
function node(tag, text, className) { const element = document.createElement(tag); if (text !== undefined) element.textContent = text; if (className) element.className = className; return element; }
function dateLabel(date) {
  const [day, time] = date.split('T');
  // Format calendar components in UTC to avoid shifting an event option into the viewer's time zone.
  const label = new Intl.DateTimeFormat(undefined, {weekday: 'short', month: 'short', day: 'numeric', year: 'numeric', timeZone: 'UTC'}).format(new Date(`${day}T12:00:00Z`));
  return `${label} · ${time || 'Time undecided'}`;
}
const privateFields = {privateName: 'private-name', phone: 'phone', email: 'email'};
const partyFields = {adults: 'adults', kids: 'kids', foodType: 'food-type', foodNote: 'food-note'};
const zones = typeof Intl.supportedValuesOf === 'function' ? Intl.supportedValuesOf('timeZone') : ['America/New_York', 'America/Chicago', 'America/Denver', 'America/Los_Angeles', 'Europe/London'];
for (const zone of [...new Set(['America/New_York', 'UTC', ...zones])]) { const option = node('option', zone); option.value = zone; $('timezone').append(option); }
$('timezone').value = 'America/New_York';
function link(kind) {
  const url = new URL(location.pathname, location.origin);
  url.searchParams.set('event', eventId);
  const fragment = new URLSearchParams();
  if (kind === 'admin') fragment.set('admin', credentials.adminToken);
  if (kind === 'response') { fragment.set('response', credentials.responseId); fragment.set('key', credentials.responseToken); }
  url.hash = fragment.toString(); return url.href;
}
async function api(body) {
  if (!body && (credentials.adminToken || credentials.responseToken)) body = {action: 'view', id: eventId, ...credentials};
  else if (body && body.action !== 'create' && credentials.adminToken) body = {...body, adminToken: credentials.adminToken};
  const response = await fetch(body ? 'api.php' : `api.php?id=${encodeURIComponent(eventId)}`, body ? {method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify(body)} : {cache: 'no-store'});
  let data;
  try { data = await response.json(); } catch { throw new Error('The server did not return a valid response. Check that PHP is enabled.'); }
  if (!response.ok) throw new Error(data.error || 'The request failed.');
  return data;
}
async function copy(text) {
  try { await navigator.clipboard.writeText(text); message('Link copied.'); }
  catch { window.prompt('Copy this link:', text); }
}
function renderLinks() {
  $('admin-link-row').hidden = !credentials.adminToken;
  $('response-link-row').hidden = !credentials.responseToken;
  $('private-links').hidden = !credentials.adminToken && !credentials.responseToken;
  if (credentials.adminToken) $('admin-link').value = link('admin');
  if (credentials.responseToken) $('response-link').value = link('response');
  $('edit-event').hidden = !credentials.adminToken;
}
function renderChips() {
  $('date-chips').replaceChildren(...chosenDates.map(date => {
    const button = node('button', `${dateLabel(date)} ×`, 'date-chip'); button.type = 'button';
    button.setAttribute('aria-label', `Remove ${dateLabel(date)}`);
    button.onclick = () => { chosenDates = chosenDates.filter(item => item !== date); renderChips(); };
    return button;
  }));
}
$('add-date').onclick = () => {
  const input = $('date-input');
  if (!input.value || !input.checkValidity()) { input.reportValidity(); message('Choose a valid date first.', true); return; }
  if (chosenDates.length >= 60) { message('Choose up to 60 dates.', true); return; }
  const option = input.value + ($('time-input').value ? `T${$('time-input').value}` : '');
  if (!chosenDates.includes(option)) chosenDates.push(option);
  chosenDates.sort(); input.value = ''; renderChips();
};
$('date-input').onkeydown = e => { if (e.key === 'Enter') { e.preventDefault(); $('add-date').click(); } };
$('event-form').onsubmit = async e => {
  e.preventDefault();
  if (!chosenDates.length) { message('Add at least one date.', true); return; }
  const form = new FormData(e.target);
  const body = {action: editing ? 'update' : 'create', title: form.get('title'), adminName: form.get('adminName'), description: form.get('description'), dates: chosenDates,
    location: form.get('location'), timezone: $('timezone').value, expiresLocal: form.get('expiresLocal')};
  if (editing) Object.assign(body, {id: eventId, adminToken: credentials.adminToken, revision: formRevision, closed: form.get('closed') === 'on'});
  $('save-event').disabled = true; ++sequence;
  try {
    const data = await api(body);
    eventId = data.event.id;
    if (data.adminToken) credentials.adminToken = data.adminToken;
    remember(); history.replaceState(null, '', link());
    editing = false; $('setup').hidden = true; $('poll').hidden = false;
    applyEvent(data.event, data); renderLinks();
    message(data.adminToken ? 'Your event is ready. Save your private admin link, then share the invite link.' : 'Event updated.');
  } catch (err) { message(err.message, true); }
  finally { $('save-event').disabled = false; }
};
$('edit-event').onclick = () => {
  editing = true; formRevision = event.revision; chosenDates = [...event.dates];
  const form = $('event-form');
  for (const key of ['title', 'adminName', 'description', 'location', 'expiresLocal']) form.elements[key].value = event[key] || '';
  if (![...$('timezone').options].some(option => option.value === event.timezone) && event.timezone) { const option = node('option', event.timezone); option.value = event.timezone; $('timezone').append(option); }
  $('timezone').value = event.timezone || 'America/New_York'; $('timezone').disabled = event.responses.length > 0;
  form.elements.closed.checked = event.closed;
  $('setup').hidden = false; $('closed-label').hidden = false; $('edit-note').hidden = false; $('cancel-edit').hidden = false;
  $('setup').querySelector('h2').textContent = 'Edit your event'; $('save-event').textContent = 'Save event changes';
  renderChips(); $('setup').scrollIntoView({behavior: 'smooth'});
};
$('cancel-edit').onclick = () => { editing = false; $('setup').hidden = true; };
$('copy-public').onclick = () => copy(link());
$('copy-admin').onclick = () => copy(link('admin'));
$('copy-response').onclick = () => copy(link('response'));
function summary() {
  const yes = Object.values(answers).filter(value => value === 'yes').length;
  const no = Object.values(answers).filter(value => value === 'no').length;
  $('answer-summary').textContent = `${yes} can attend · ${no} can’t attend · ${event.dates.length - yes - no} unanswered`;
  $('vote-state').textContent = event.expired ? 'Voting has expired. Results remain visible.' : event.closed ? 'This poll is closed. Results remain visible.' : dirty ? 'You have unsaved changes.' : credentials.responseId ? 'Your saved response is loaded.' : 'Choose your dates, then save your response.';
}
function renderVote() {
  $('vote-dates').replaceChildren(...event.dates.map(date => {
    const row = node('label', undefined, 'vote-date'); row.append(node('span', dateLabel(date)));
    const select = node('select'); select.setAttribute('aria-label', `Availability on ${dateLabel(date)}`);
    for (const [value, label] of [['', '— Not answered'], ['yes', '✓ Can attend'], ['no', '✕ Can’t attend']]) {
      const option = node('option', label); option.value = value; select.append(option);
    }
    select.value = answers[date] || ''; select.className = select.value;
    select.onchange = () => { if (select.value) answers[date] = select.value; else delete answers[date]; select.className = select.value; dirty = true; summary(); };
    row.append(select); return row;
  }));
  for (const control of $('vote-form').elements) control.disabled = event.closed || event.expired || savingVote;
  summary();
}
for (const button of document.querySelectorAll('[data-fill]')) button.onclick = () => {
  answers = button.dataset.fill ? Object.fromEntries(event.dates.map(date => [date, button.dataset.fill])) : {};
  dirty = true; renderVote();
};
$('voter-name').oninput = () => { dirty = true; summary(); };
for (const id of [...Object.values(privateFields), ...Object.values(partyFields)]) $(id).oninput = () => { dirty = true; summary(); };
$('vote-form').onsubmit = async e => {
  e.preventDefault(); savingVote = true; $('save-vote').disabled = true; ++sequence;
  // Freeze the submitted form until the server confirms it so edits cannot be overwritten.
  for (const control of $('vote-form').elements) control.disabled = true;
  try {
    const details = Object.fromEntries(Object.entries({...privateFields, ...partyFields}).map(([key, id]) => [key, ['adults', 'kids'].includes(key) ? ($(id).value === '' ? null : Number($(id).value)) : $(id).value]));
    const data = await api({action: 'vote', id: eventId, revision: event.revision, name: $('voter-name').value, ...details, answers, responseId: credentials.responseId || '', responseToken: credentials.responseToken || ''});
    Object.assign(credentials, {responseId: data.responseId, responseToken: data.responseToken}); remember(); dirty = false;
    applyEvent(data.event, data); renderLinks(); message('Your availability is saved. The group results are up to date.');
  } catch (err) { message(err.message, true); }
  finally { savingVote = false; renderVote(); }
};
function applyEvent(next, access = {}) {
  const changedDates = event && event.revision !== next.revision;
  const changedStatus = event && (event.expired !== next.expired || event.closed !== next.closed);
  const first = !event; event = next;
  document.title = `${event.title} · Availability`;
  $('title').textContent = event.title; $('description').textContent = event.description || 'Find the date that works for your group.';
  $('event-meta').textContent = `Organized by ${event.adminName} · ${event.dates.length} proposed options · ${event.expired ? 'Voting expired' : event.closed ? 'Closed' : 'Open for responses'}`;
  const deadline = event.expiresAt ? new Intl.DateTimeFormat(undefined, {year: 'numeric', month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit', timeZoneName: 'short', timeZone: event.timezone || 'America/New_York'}).format(new Date(event.expiresAt)) : 'No deadline';
  $('schedule-note').textContent = `Date and time are not confirmed. ${event.location ? `Location: ${event.location}. ` : 'Location: still deciding. '}All times: ${event.timezone || 'America/New_York'}. Voting expiration: ${deadline}.`;
  const mine = access.myResponse || event.responses.find(response => response.id === credentials.responseId);
  if (!dirty && mine) {
    answers = {...mine.answers}; $('voter-name').value = mine.name;
    for (const [key, id] of Object.entries(partyFields)) $(id).value = mine[key] ?? '';
    if (access.myResponse) for (const [key, id] of Object.entries(privateFields)) $(id).value = mine[key] || '';
  }
  answers = Object.fromEntries(Object.entries(answers).filter(([date]) => event.dates.includes(date)));
  if (changedDates) message('The organizer updated this event. Review the current dates before saving.');
  if (first || changedDates || changedStatus || !dirty) renderVote();
  renderResults();
  renderAdmin(access.adminResponses);
}
function renderAdmin(responses) {
  $('admin-contacts').hidden = !responses;
  if (!responses) { $('admin-contact-table').replaceChildren(); return; }
  const table = node('table'), head = node('thead'), heading = node('tr');
  for (const title of ['Public name', 'Private name', 'Phone', 'Email', 'Adults', 'Kids', 'Food']) { const cell = node('th', title); cell.scope = 'col'; heading.append(cell); }
  head.append(heading); table.append(head);
  const body = node('tbody');
  for (const response of responses) {
    const row = node('tr');
    for (const value of [response.name, response.privateName, response.phone, response.email, response.adults, response.kids, [response.foodType, response.foodNote].filter(Boolean).join(' — ')]) row.append(node('td', value ?? '—'));
    body.append(row);
  }
  table.append(body); $('admin-contact-table').replaceChildren(responses.length ? table : node('p', 'No attendee details yet.', 'muted'));
}
function renderResults() {
  const total = event.responses.length;
  $('response-count').textContent = `${total} response${total === 1 ? '' : 's'}`;
  const ranked = event.dates.map(date => {
    const available = event.responses.filter(r => r.answers[date] === 'yes');
    return {date, yes: available.length, no: event.responses.filter(r => r.answers[date] === 'no').length,
      adults: available.reduce((sum, r) => sum + (r.adults ?? 0), 0), kids: available.reduce((sum, r) => sum + (r.kids ?? 0), 0),
      unspecified: available.filter(r => r.adults == null || r.kids == null).length};
  }).sort((a, b) => b.yes - a.yes || a.date.localeCompare(b.date));
  const top = ranked[0].yes, winners = ranked.filter(row => row.yes === top);
  $('best-summary').textContent = !total ? 'Share your invite link to get the first response.' : !top ? 'No date has a “can attend” response yet.' : `${winners.length > 1 ? `${winners.length} dates tied for best` : `Best date: ${dateLabel(winners[0].date)}`} · ${top} of ${total} can attend`;
  $('results').replaceChildren(...ranked.map(row => {
    const item = node('div', undefined, 'result');
    const heading = node('div', undefined, 'result-heading'); heading.append(node('strong', dateLabel(row.date)));
    if (row.yes === top && top > 0) heading.append(node('span', 'Best fit', 'best-tag'));
    const meter = node('meter'); meter.min = 0; meter.max = total || 1; meter.value = row.yes;
    meter.setAttribute('aria-label', `${dateLabel(row.date)}: ${row.yes} of ${total} can attend`);
    item.append(heading, meter, node('p', `${row.yes} responses can attend · ${row.no} can’t · ${total - row.yes - row.no} unanswered`, 'muted'),
      node('p', `Reported headcount: ${row.adults} adults + ${row.kids} kids${row.unspecified ? ` · ${row.unspecified} available response(s) with incomplete counts` : ''}`, 'muted')); return item;
  }));
  const table = node('table'), head = node('thead'), header = node('tr');
  const nameHeader = node('th', 'Name'); nameHeader.scope = 'col'; header.append(nameHeader);
  for (const date of event.dates) { const th = node('th', dateLabel(date)); th.scope = 'col'; header.append(th); }
  for (const title of ['Adults', 'Kids', 'Food to share']) { const th = node('th', title); th.scope = 'col'; header.append(th); }
  head.append(header); table.append(head);
  const body = node('tbody');
  for (const response of event.responses) {
    const row = node('tr'), name = node('th', response.name); name.scope = 'row'; row.append(name);
    for (const date of event.dates) {
      const answer = response.answers[date]; const cell = node('td', answer === 'yes' ? '✓ Yes' : answer === 'no' ? '✕ No' : '—', answer === 'yes' ? 'yes-text' : answer === 'no' ? 'no-text' : 'muted');
      cell.setAttribute('aria-label', answer === 'yes' ? 'Can attend' : answer === 'no' ? 'Cannot attend' : 'Not answered'); row.append(cell);
    }
    for (const value of [response.adults ?? '—', response.kids ?? '—', [response.foodType, response.foodNote].filter(Boolean).join(' — ') || 'Not decided']) row.append(node('td', value));
    body.append(row);
  }
  table.append(body); $('response-table').replaceChildren(total ? table : node('p', 'No responses yet. Yours can be the first.', 'muted'));
}
async function refresh() {
  if (!eventId || document.hidden) return;
  const request = ++sequence;
  try {
    const data = await api(); if (request !== sequence) return;
    $('setup').hidden = !editing; $('poll').hidden = false;
    applyEvent(data.event, data); renderLinks();
    $('live-state').textContent = `Live · Updated ${new Date().toLocaleTimeString()} · Refreshes every 5 seconds`;
  } catch (err) { if (request !== sequence) return; $('live-state').textContent = 'Connection interrupted. Retrying…'; message(err.message, true); }
}
if (eventId) {
  $('setup').hidden = true;
  try { credentials = JSON.parse(localStorage.getItem(storageKey()) || '{}') || {}; } catch { credentials = {}; }
  const fragment = new URLSearchParams(location.hash.slice(1));
  if (fragment.get('admin')) credentials.adminToken = fragment.get('admin');
  if (fragment.get('response') && fragment.get('key')) { credentials.responseId = fragment.get('response'); credentials.responseToken = fragment.get('key'); }
  // Tokens stay out of server URLs, access logs, referrers and the address bar.
  if (location.hash) { remember(); history.replaceState(null, '', link()); }
  refresh();
}
setInterval(() => { if (!editing && !savingVote) refresh(); }, 5000);
window.addEventListener('beforeunload', e => { if (dirty) { e.preventDefault(); e.returnValue = ''; } });
