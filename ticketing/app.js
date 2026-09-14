/*
 * Ticketing - app.js
 * File Revision: 1.0.0
 * Modified: 2026-09-14
 *
 * Revision History:
 * 1.0.0 - Added login flows, persistent requester/agent lists, add-new dropdown actions, and session-aware ticket access.
 * Initial build - Requester/agent portals, sortable tables, and public/private replies.
 */

const state = {
  user: null,
  project: {},
  tickets: [],
  directory: [],
  requesterView: 'requesterDashboard',
  agentView: 'agentDashboard',
  sort: { key: 'updatedAt', direction: 'desc' },
  loginRole: 'requester',
  loginMode: 'login',
  agentBootstrapAvailable: false,
};

const $ = id => document.getElementById(id);
const ticketDialog = $('ticketDialog');
const personDialog = $('personDialog');
const ticketForm = $('ticketForm');
const personForm = $('personForm');

async function api(method = 'GET', body = null, action = 'tickets') {
  const response = await fetch(`index.php?api=1&action=${encodeURIComponent(action)}`, {
    method,
    headers: body ? { 'Content-Type': 'application/json' } : {},
    body: body ? JSON.stringify(body) : null,
  });
  const data = await response.json();
  if (!response.ok) throw new Error(data.error || 'Request failed.');
  return data;
}

function escapeHtml(value = '') {
  return String(value).replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;').replaceAll('"', '&quot;').replaceAll("'", '&#039;');
}
function ticketNumber(ticket) { return `#${String(ticket.number || 0).padStart(5, '0')}`; }
function prettyDate(value) { return value ? new Date(value).toLocaleString([], { month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit' }) : ''; }
function badge(value, type = '') { const css = String(value).toLowerCase().replace(/[^a-z0-9]+/g, '-'); return `<span class="badge ${type} ${css}">${escapeHtml(value)}</span>`; }
function isAgent() { return state.user?.role === 'agent'; }

function statHtml(tickets) {
  const counts = { Open: 0, Pending: 0, Resolved: 0, Closed: 0 };
  tickets.forEach(ticket => { if (Object.hasOwn(counts, ticket.status)) counts[ticket.status]++; });
  return [['Open', counts.Open], ['Pending', counts.Pending], ['Resolved', counts.Resolved], ['Closed', counts.Closed], ['Total', tickets.length]]
    .map(([label, count]) => `<div class="stat-card"><span>${label}</span><strong>${count}</strong></div>`).join('');
}

function basicTable(tickets, requesterMode = false) {
  if (!tickets.length) return '<div class="empty-state">No tickets match this view.</div>';
  return `<div class="table-wrap"><table><thead><tr><th>Ticket</th>${requesterMode ? '' : '<th>Requester</th>'}<th>Status</th><th>Priority</th><th>Agent</th><th>Updated</th></tr></thead><tbody>${tickets.map(ticket => `
    <tr class="ticket-row" data-id="${escapeHtml(ticket.id)}"><td><strong>${ticketNumber(ticket)}</strong><div class="ticket-subject">${escapeHtml(ticket.subject)}</div><div class="muted small">${escapeHtml(ticket.category || 'General')}</div></td>${requesterMode ? '' : `<td>${escapeHtml(ticket.requester)}<div class="muted small">${escapeHtml(ticket.email || '')}</div></td>`}<td>${badge(ticket.status || 'Open')}</td><td>${badge(ticket.priority || 'Medium')}</td><td>${escapeHtml(ticket.assignedTo || 'Unassigned')}</td><td>${prettyDate(ticket.updatedAt)}</td></tr>`).join('')}</tbody></table></div>`;
}

function sortValue(ticket, key) {
  if (key === 'number') return Number(ticket.number || 0);
  if (key === 'createdAt' || key === 'updatedAt') return new Date(ticket[key] || 0).getTime();
  return String(ticket[key] || '').toLowerCase();
}
function sortedAgentTickets(tickets) {
  const { key, direction } = state.sort;
  return [...tickets].sort((a, b) => { const av = sortValue(a, key), bv = sortValue(b, key); const r = av < bv ? -1 : av > bv ? 1 : 0; return direction === 'asc' ? r : -r; });
}
function agentTable(tickets) {
  if (!tickets.length) return '<div class="empty-state">No tickets match this view.</div>';
  const heading = (label, key) => `<button class="sort-button" data-sort="${key}">${label}${state.sort.key === key ? (state.sort.direction === 'asc' ? ' ▲' : ' ▼') : ''}</button>`;
  return `<div class="table-wrap"><table class="agent-table"><thead><tr><th>${heading('Ticket','number')}</th><th>${heading('Requester','requester')}</th><th>${heading('Status','status')}</th><th>${heading('Priority','priority')}</th><th>${heading('Agent','assignedTo')}</th><th>${heading('Created','createdAt')}</th><th>${heading('Updated','updatedAt')}</th></tr></thead><tbody>${sortedAgentTickets(tickets).map(ticket => `
    <tr class="ticket-row" data-id="${escapeHtml(ticket.id)}"><td><strong>${ticketNumber(ticket)}</strong><div class="ticket-subject">${escapeHtml(ticket.subject)}</div></td><td>${escapeHtml(ticket.requester)}<div class="muted small">${escapeHtml(ticket.email || '')}</div></td><td>${badge(ticket.status || 'Open')}</td><td>${badge(ticket.priority || 'Medium')}</td><td>${escapeHtml(ticket.assignedTo || 'Unassigned')}</td><td>${prettyDate(ticket.createdAt)}</td><td>${prettyDate(ticket.updatedAt)}</td></tr>`).join('')}</tbody></table></div>`;
}

function bindRows(container) { container.querySelectorAll('.ticket-row').forEach(row => row.addEventListener('click', () => openTicket(row.dataset.id))); }
function bindSortButtons() { document.querySelectorAll('.sort-button').forEach(button => button.addEventListener('click', event => { event.stopPropagation(); const key = button.dataset.sort; state.sort = state.sort.key === key ? { key, direction: state.sort.direction === 'asc' ? 'desc' : 'asc' } : { key, direction: 'asc' }; renderAgentTickets(); })); }

function requesterEntries() { return state.directory.filter(x => x.role === 'requester' && x.active !== false).sort((a,b) => a.name.localeCompare(b.name)); }
function agentEntries() { return state.directory.filter(x => x.role === 'agent' && x.active !== false).sort((a,b) => a.name.localeCompare(b.name)); }

function fillDirectorySelects(selectedRequesterEmail = '', selectedAgent = '') {
  const requesterSelect = $('requesterSelect');
  requesterSelect.innerHTML = '<option value="">Select requester...</option>' + requesterEntries().map(x => `<option value="${escapeHtml(x.email)}">${escapeHtml(x.name)} — ${escapeHtml(x.email)}</option>`).join('') + '<option value="__new__">+ Add new requester...</option>';
  requesterSelect.value = selectedRequesterEmail && [...requesterSelect.options].some(o => o.value === selectedRequesterEmail) ? selectedRequesterEmail : '';

  const assigned = $('assignedTo');
  assigned.innerHTML = '<option value="">Unassigned</option>' + agentEntries().map(x => `<option value="${escapeHtml(x.name)}">${escapeHtml(x.name)}</option>`).join('') + '<option value="__new__">+ Add new agent...</option>';
  assigned.value = selectedAgent && [...assigned.options].some(o => o.value === selectedAgent) ? selectedAgent : '';

  const filter = $('agentFilter');
  const current = filter.value;
  filter.innerHTML = '<option value="">All agents</option>' + agentEntries().map(x => `<option value="${escapeHtml(x.name)}">${escapeHtml(x.name)}</option>`).join('');
  if ([...filter.options].some(o => o.value === current)) filter.value = current;
}

function renderProjectMetadata() { $('projectRevision').textContent = `Project rev ${state.project.revision || '--'}`; $('projectModified').textContent = `Modified ${state.project.modified || '--'}`; }
function renderRequesterDashboard() { $('requesterStats').innerHTML = statHtml(state.tickets); const recent = [...state.tickets].sort((a,b) => new Date(b.updatedAt)-new Date(a.updatedAt)).slice(0,8); $('requesterRecentTickets').innerHTML = basicTable(recent, true); bindRows($('requesterRecentTickets')); }
function renderRequesterTickets() { const q = $('requesterSearchInput').value.trim().toLowerCase(), status = $('requesterStatusFilter').value; const tickets = state.tickets.filter(t => (!status || t.status === status) && (!q || [t.number,t.subject,t.description,t.category,t.assignedTo].join(' ').toLowerCase().includes(q))); $('myTicketList').innerHTML = basicTable(tickets, true); bindRows($('myTicketList')); }
function renderAgentDashboard() { $('agentStats').innerHTML = statHtml(state.tickets); const recent = [...state.tickets].sort((a,b) => new Date(b.updatedAt)-new Date(a.updatedAt)).slice(0,8); $('agentRecentTickets').innerHTML = basicTable(recent); bindRows($('agentRecentTickets')); }
function renderAgentTickets() { const q = $('agentSearchInput').value.trim().toLowerCase(), status = $('agentStatusFilter').value, priority = $('agentPriorityFilter').value, agent = $('agentFilter').value; const tickets = state.tickets.filter(t => (!status || t.status === status) && (!priority || t.priority === priority) && (!agent || t.assignedTo === agent) && (!q || [t.number,t.subject,t.description,t.requester,t.email,t.category,t.assignedTo,t.source].join(' ').toLowerCase().includes(q))); $('agentTicketList').innerHTML = agentTable(tickets); bindRows($('agentTicketList')); bindSortButtons(); }

function updateVisibleView() {
  const agent = isAgent();
  $('requesterNav').classList.toggle('hidden', agent);
  $('agentNav').classList.toggle('hidden', !agent);
  $('requesterDashboardView').classList.toggle('hidden', agent || state.requesterView !== 'requesterDashboard');
  $('myTicketsView').classList.toggle('hidden', agent || state.requesterView !== 'myTickets');
  $('agentDashboardView').classList.toggle('hidden', !agent || state.agentView !== 'agentDashboard');
  $('allTicketsView').classList.toggle('hidden', !agent || state.agentView !== 'allTickets');
  if (agent) {
    if (state.agentView === 'agentDashboard') { $('pageTitle').textContent = 'Agent Dashboard'; $('pageSubtitle').textContent = 'Queue overview across all requesters and agents.'; renderAgentDashboard(); }
    else { $('pageTitle').textContent = 'All Tickets'; $('pageSubtitle').textContent = 'Search, sort, assign, and update every ticket.'; renderAgentTickets(); }
  } else {
    if (state.requesterView === 'requesterDashboard') { $('pageTitle').textContent = 'My Dashboard'; $('pageSubtitle').textContent = 'Track the tickets you submitted.'; renderRequesterDashboard(); }
    else { $('pageTitle').textContent = 'My Tickets'; $('pageSubtitle').textContent = 'Your submitted requests and public replies.'; renderRequesterTickets(); }
  }
}

function clearTicketForm() {
  ticketForm.reset(); $('ticketId').value = ''; $('priority').value = 'Medium'; $('status').value = 'Open'; $('category').value = 'General'; $('source').value = 'Portal'; $('comment').value = ''; $('replySection').classList.add('hidden'); $('commentHistory').classList.add('hidden'); $('commentHistory').innerHTML = ''; document.querySelector('input[name="replyVisibility"][value="public"]').checked = true; fillDirectorySelects();
}
function setFieldAccess(isNew) {
  document.querySelectorAll('.agent-field').forEach(el => el.classList.toggle('hidden', !isAgent()));
  ['subject','priority','category','description'].forEach(id => $(id).disabled = !isAgent() && !isNew);
  ['status','requesterSelect','email','assignedTo','source'].forEach(id => $(id).disabled = !isAgent());
  $('visibilityControl').classList.toggle('hidden', !isAgent());
  $('replyHint').textContent = isAgent() ? 'Public replies are visible to the requester. Private notes are agent-only.' : 'Your reply is public and visible to agents.';
}
function openNewTicket() { clearTicketForm(); setFieldAccess(true); $('ticketEyebrow').textContent = 'New ticket'; $('dialogTitle').textContent = isAgent() ? 'Create Ticket' : 'Submit Ticket'; $('saveButton').textContent = $('dialogTitle').textContent; ticketDialog.showModal(); }
function renderComments(ticket) { const comments = ticket.comments || []; if (!comments.length) return; $('commentHistory').classList.remove('hidden'); $('commentHistory').innerHTML = `<h3>Conversation & Activity</h3>${[...comments].reverse().map(c => `<div class="comment ${c.visibility || 'public'}"><div><strong>${escapeHtml(c.author || 'User')}</strong><span>${prettyDate(c.createdAt)}</span></div><div class="comment-meta"><span class="visibility ${c.visibility || 'public'}">${c.visibility === 'private' ? 'Private note' : 'Public'}</span></div><p>${escapeHtml(c.body)}</p></div>`).join('')}`; }
function openTicket(id) { const ticket = state.tickets.find(x => x.id === id); if (!ticket) return; clearTicketForm(); setFieldAccess(false); $('ticketId').value = ticket.id; $('subject').value = ticket.subject || ''; $('priority').value = ticket.priority || 'Medium'; $('status').value = ticket.status || 'Open'; $('category').value = ticket.category || 'General'; $('source').value = ticket.source || 'Portal'; $('description').value = ticket.description || ''; $('email').value = ticket.email || ''; fillDirectorySelects(ticket.email || '', ticket.assignedTo || ''); $('ticketEyebrow').textContent = ticketNumber(ticket); $('dialogTitle').textContent = isAgent() ? 'Agent Ticket Details' : 'Ticket Details'; $('saveButton').textContent = isAgent() ? 'Save Changes' : 'Send Reply'; $('replySection').classList.remove('hidden'); renderComments(ticket); ticketDialog.showModal(); }

async function loadSession() {
  const data = await api('GET', null, 'session');
  state.user = data.user || null;
  state.project = data.project || {};
  state.agentBootstrapAvailable = !!data.agentBootstrapAvailable;
  renderProjectMetadata();
  if (state.user) await enterApp(); else showLogin();
}
async function loadData() {
  const [ticketData, directoryData] = await Promise.all([api('GET', null, 'tickets'), api('GET', null, 'directory')]);
  state.tickets = ticketData.tickets || [];
  state.project = ticketData.project || state.project;
  state.directory = directoryData.directory || [];
  renderProjectMetadata(); fillDirectorySelects(); updateVisibleView();
}
async function enterApp() { $('loginScreen').classList.add('hidden'); $('appShell').classList.remove('hidden'); $('signedInName').textContent = state.user.name || state.user.email; $('signedInRole').textContent = state.user.role; await loadData(); }
function showLogin() { $('appShell').classList.add('hidden'); $('loginScreen').classList.remove('hidden'); setLoginMode('login'); }

function setLoginMode(mode) {
  state.loginMode = mode;
  const needsName = mode !== 'login';
  $('loginNameWrap').classList.toggle('hidden', !needsName);
  $('loginSubmitButton').textContent = mode === 'login' ? 'Sign in' : mode === 'registerRequester' ? 'Create requester account' : 'Create first agent account';
  $('registerRequesterButton').classList.toggle('hidden', mode !== 'login' || state.loginRole !== 'requester');
  $('bootstrapAgentButton').classList.toggle('hidden', mode !== 'login' || state.loginRole !== 'agent' || !state.agentBootstrapAvailable);
  $('backToLoginButton').classList.toggle('hidden', mode === 'login');
  $('loginMessage').textContent = '';
}

function openPersonDialog(role) { $('personRole').value = role; $('personDialogTitle').textContent = role === 'agent' ? 'Add Agent' : 'Add Requester'; personForm.reset(); $('personRole').value = role; personDialog.showModal(); }

$('loginForm').addEventListener('submit', async event => {
  event.preventDefault();
  const body = { role: state.loginRole, name: $('loginName').value.trim(), email: $('loginEmail').value.trim(), password: $('loginPassword').value };
  try {
    const action = state.loginMode === 'registerRequester' ? 'register-requester' : state.loginMode === 'bootstrapAgent' ? 'bootstrap-agent' : 'login';
    const data = await api('POST', body, action);
    state.user = data.user;
    await enterApp();
  } catch (error) { $('loginMessage').textContent = error.message; }
});

document.querySelectorAll('[data-login-role]').forEach(button => button.addEventListener('click', () => { state.loginRole = button.dataset.loginRole; document.querySelectorAll('[data-login-role]').forEach(b => b.classList.toggle('active', b === button)); setLoginMode('login'); }));
$('registerRequesterButton').addEventListener('click', () => setLoginMode('registerRequester'));
$('bootstrapAgentButton').addEventListener('click', () => setLoginMode('bootstrapAgent'));
$('backToLoginButton').addEventListener('click', () => setLoginMode('login'));
$('logoutButton').addEventListener('click', async () => { await api('POST', {}, 'logout'); state.user = null; state.tickets = []; state.directory = []; await loadSession(); });

$('requesterSelect').addEventListener('change', () => { if ($('requesterSelect').value === '__new__') { $('requesterSelect').value = ''; openPersonDialog('requester'); return; } const entry = requesterEntries().find(x => x.email === $('requesterSelect').value); $('email').value = entry?.email || ''; });
$('assignedTo').addEventListener('change', () => { if ($('assignedTo').value === '__new__') { $('assignedTo').value = ''; openPersonDialog('agent'); } });

personForm.addEventListener('submit', async event => {
  event.preventDefault();
  try {
    const body = { role: $('personRole').value, name: $('personName').value.trim(), email: $('personEmail').value.trim(), password: $('personPassword').value };
    const data = await api('POST', body, 'directory');
    personDialog.close();
    const directoryData = await api('GET', null, 'directory');
    state.directory = directoryData.directory || [];
    fillDirectorySelects(body.role === 'requester' ? data.entry.email : '', body.role === 'agent' ? data.entry.name : '');
  } catch (error) { alert(error.message); }
});

 ticketForm.addEventListener('submit', async event => {
  event.preventDefault();
  const id = $('ticketId').value;
  const requester = requesterEntries().find(x => x.email === $('requesterSelect').value);
  const body = id ? {
    id, subject: $('subject').value.trim(), requester: requester?.name || '', email: $('email').value.trim(), priority: $('priority').value, status: $('status').value, category: $('category').value.trim() || 'General', assignedTo: $('assignedTo').value, source: $('source').value.trim() || 'Portal', description: $('description').value.trim(), comment: $('comment').value.trim(), visibility: isAgent() ? document.querySelector('input[name="replyVisibility"]:checked').value : 'public'
  } : {
    subject: $('subject').value.trim(), requester: requester?.name || '', email: $('email').value.trim(), priority: $('priority').value, category: $('category').value.trim() || 'General', assignedTo: $('assignedTo').value, source: $('source').value.trim() || 'Portal', description: $('description').value.trim()
  };
  if (id && !isAgent() && !$('comment').value.trim()) { alert('Enter a reply before submitting.'); return; }
  try { await api(id ? 'PUT' : 'POST', body, 'tickets'); ticketDialog.close(); await loadData(); if (!id) { if (isAgent()) state.agentView = 'allTickets'; else state.requesterView = 'myTickets'; updateVisibleView(); } } catch (error) { alert(error.message); }
});

document.querySelectorAll('[data-requester-view]').forEach(button => button.addEventListener('click', () => { state.requesterView = button.dataset.requesterView; document.querySelectorAll('[data-requester-view]').forEach(x => x.classList.toggle('active', x === button)); updateVisibleView(); }));
document.querySelectorAll('[data-agent-view]').forEach(button => button.addEventListener('click', () => { state.agentView = button.dataset.agentView; document.querySelectorAll('[data-agent-view]').forEach(x => x.classList.toggle('active', x === button)); updateVisibleView(); }));
$('requesterNewTicketButton').addEventListener('click', openNewTicket);
$('agentNewTicketButton').addEventListener('click', openNewTicket);
$('refreshButton').addEventListener('click', loadData);
$('closeDialogButton').addEventListener('click', () => ticketDialog.close());
$('cancelButton').addEventListener('click', () => ticketDialog.close());
$('closePersonDialogButton').addEventListener('click', () => personDialog.close());
$('cancelPersonButton').addEventListener('click', () => personDialog.close());
$('requesterSearchInput').addEventListener('input', renderRequesterTickets);
$('requesterStatusFilter').addEventListener('change', renderRequesterTickets);
$('agentSearchInput').addEventListener('input', renderAgentTickets);
$('agentStatusFilter').addEventListener('change', renderAgentTickets);
$('agentPriorityFilter').addEventListener('change', renderAgentTickets);
$('agentFilter').addEventListener('change', renderAgentTickets);

loadSession().catch(error => { $('loginMessage').textContent = error.message; showLogin(); });
