/*
 * Ticketing - app.js
 * File Revision: 0.2.0
 * Modified: 2026-09-14
 *
 * Revision History:
 * 0.2.0 - Added requester/agent portals, sortable agent table, public/private replies.
 * 0.1.0 - Initial dashboard, ticket list, create/edit flow.
 */

const state = {
  portal: 'requester',
  requesterView: 'requesterDashboard',
  agentView: 'agentDashboard',
  tickets: [],
  requesterEmail: localStorage.getItem('ticketingRequesterEmail') || '',
  project: {},
  sort: { key: 'updatedAt', direction: 'desc' },
};

const $ = id => document.getElementById(id);
const dialog = $('ticketDialog');
const form = $('ticketForm');

async function api(method = 'GET', body = null, query = '') {
  const response = await fetch(`index.php?api=1${query}`, {
    method,
    headers: body ? { 'Content-Type': 'application/json' } : {},
    body: body ? JSON.stringify(body) : null,
  });
  const data = await response.json();
  if (!response.ok) throw new Error(data.error || 'Request failed.');
  return data;
}

function escapeHtml(value = '') {
  return String(value)
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#039;');
}

function ticketNumber(ticket) {
  return `#${String(ticket.number || 0).padStart(5, '0')}`;
}

function prettyDate(value) {
  if (!value) return '';
  return new Date(value).toLocaleString([], {
    month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit'
  });
}

function badge(value, type = '') {
  const css = String(value).toLowerCase().replace(/[^a-z0-9]+/g, '-');
  return `<span class="badge ${type} ${css}">${escapeHtml(value)}</span>`;
}

function renderProjectMetadata() {
  $('projectRevision').textContent = `Project rev ${state.project.revision || '--'}`;
  $('projectModified').textContent = `Modified ${state.project.modified || '--'}`;
}

function statHtml(tickets) {
  const counts = { Open: 0, Pending: 0, Resolved: 0, Closed: 0 };
  tickets.forEach(ticket => {
    if (Object.hasOwn(counts, ticket.status)) counts[ticket.status]++;
  });
  return [
    ['Open', counts.Open], ['Pending', counts.Pending], ['Resolved', counts.Resolved],
    ['Closed', counts.Closed], ['Total', tickets.length],
  ].map(([label, count]) => `<div class="stat-card"><span>${label}</span><strong>${count}</strong></div>`).join('');
}

function basicTable(tickets, requesterMode = false) {
  if (!tickets.length) return '<div class="empty-state">No tickets match this view.</div>';
  return `<div class="table-wrap"><table>
    <thead><tr><th>Ticket</th>${requesterMode ? '' : '<th>Requester</th>'}<th>Status</th><th>Priority</th><th>Agent</th><th>Updated</th></tr></thead>
    <tbody>${tickets.map(ticket => `
      <tr class="ticket-row" data-id="${escapeHtml(ticket.id)}">
        <td><strong>${ticketNumber(ticket)}</strong><div class="ticket-subject">${escapeHtml(ticket.subject)}</div><div class="muted small">${escapeHtml(ticket.category || 'General')}</div></td>
        ${requesterMode ? '' : `<td>${escapeHtml(ticket.requester)}<div class="muted small">${escapeHtml(ticket.email || '')}</div></td>`}
        <td>${badge(ticket.status || 'Open', 'status')}</td>
        <td>${badge(ticket.priority || 'Medium', 'priority')}</td>
        <td>${escapeHtml(ticket.assignedTo || 'Unassigned')}</td>
        <td>${prettyDate(ticket.updatedAt)}</td>
      </tr>`).join('')}</tbody>
  </table></div>`;
}

function sortValue(ticket, key) {
  if (key === 'number') return Number(ticket.number || 0);
  if (key === 'createdAt' || key === 'updatedAt') return new Date(ticket[key] || 0).getTime();
  return String(ticket[key] || '').toLowerCase();
}

function sortedAgentTickets(tickets) {
  const { key, direction } = state.sort;
  return [...tickets].sort((a, b) => {
    const av = sortValue(a, key);
    const bv = sortValue(b, key);
    const result = av < bv ? -1 : av > bv ? 1 : 0;
    return direction === 'asc' ? result : -result;
  });
}

function sortIndicator(key) {
  if (state.sort.key !== key) return '';
  return state.sort.direction === 'asc' ? ' ▲' : ' ▼';
}

function agentTable(tickets) {
  if (!tickets.length) return '<div class="empty-state">No tickets match this view.</div>';
  const rows = sortedAgentTickets(tickets);
  const heading = (label, key) => `<button class="sort-button" data-sort="${key}">${label}${sortIndicator(key)}</button>`;
  return `<div class="table-wrap"><table class="agent-table">
    <thead><tr>
      <th>${heading('Ticket', 'number')}</th>
      <th>${heading('Requester', 'requester')}</th>
      <th>${heading('Status', 'status')}</th>
      <th>${heading('Priority', 'priority')}</th>
      <th>${heading('Agent', 'assignedTo')}</th>
      <th>${heading('Created', 'createdAt')}</th>
      <th>${heading('Updated', 'updatedAt')}</th>
    </tr></thead>
    <tbody>${rows.map(ticket => `
      <tr class="ticket-row" data-id="${escapeHtml(ticket.id)}">
        <td><strong>${ticketNumber(ticket)}</strong><div class="ticket-subject">${escapeHtml(ticket.subject)}</div><div class="muted small">${escapeHtml(ticket.category || 'General')}</div></td>
        <td>${escapeHtml(ticket.requester)}<div class="muted small">${escapeHtml(ticket.email || '')}</div></td>
        <td>${badge(ticket.status || 'Open', 'status')}</td>
        <td>${badge(ticket.priority || 'Medium', 'priority')}</td>
        <td>${escapeHtml(ticket.assignedTo || 'Unassigned')}</td>
        <td>${prettyDate(ticket.createdAt)}</td>
        <td>${prettyDate(ticket.updatedAt)}</td>
      </tr>`).join('')}</tbody>
  </table></div>`;
}

function bindRows(container) {
  container.querySelectorAll('.ticket-row').forEach(row => {
    row.addEventListener('click', () => openTicket(row.dataset.id));
  });
}

function bindSortButtons() {
  document.querySelectorAll('.sort-button').forEach(button => {
    button.addEventListener('click', event => {
      event.stopPropagation();
      const key = button.dataset.sort;
      if (state.sort.key === key) state.sort.direction = state.sort.direction === 'asc' ? 'desc' : 'asc';
      else state.sort = { key, direction: 'asc' };
      renderAgentTickets();
    });
  });
}

function renderRequesterDashboard() {
  $('requesterStats').innerHTML = statHtml(state.tickets);
  const recent = [...state.tickets].sort((a, b) => new Date(b.updatedAt) - new Date(a.updatedAt)).slice(0, 8);
  $('requesterRecentTickets').innerHTML = basicTable(recent, true);
  bindRows($('requesterRecentTickets'));
}

function renderRequesterTickets() {
  const query = $('requesterSearchInput').value.trim().toLowerCase();
  const status = $('requesterStatusFilter').value;
  const tickets = state.tickets.filter(ticket => {
    const haystack = [ticket.number, ticket.subject, ticket.description, ticket.category, ticket.assignedTo].join(' ').toLowerCase();
    return (!status || ticket.status === status) && (!query || haystack.includes(query));
  });
  $('myTicketList').innerHTML = basicTable(tickets, true);
  bindRows($('myTicketList'));
}

function renderAgentDashboard() {
  $('agentStats').innerHTML = statHtml(state.tickets);
  const recent = [...state.tickets].sort((a, b) => new Date(b.updatedAt) - new Date(a.updatedAt)).slice(0, 8);
  $('agentRecentTickets').innerHTML = basicTable(recent, false);
  bindRows($('agentRecentTickets'));
}

function renderAgentTickets() {
  const query = $('agentSearchInput').value.trim().toLowerCase();
  const status = $('agentStatusFilter').value;
  const priority = $('agentPriorityFilter').value;
  const agent = $('agentFilter').value.trim().toLowerCase();
  const tickets = state.tickets.filter(ticket => {
    const haystack = [ticket.number, ticket.subject, ticket.description, ticket.requester, ticket.email, ticket.category, ticket.assignedTo, ticket.source].join(' ').toLowerCase();
    return (!status || ticket.status === status)
      && (!priority || ticket.priority === priority)
      && (!agent || String(ticket.assignedTo || '').toLowerCase().includes(agent))
      && (!query || haystack.includes(query));
  });
  $('agentTicketList').innerHTML = agentTable(tickets);
  bindRows($('agentTicketList'));
  bindSortButtons();
}

function updateVisibleView() {
  const requester = state.portal === 'requester';
  $('requesterIdentity').classList.toggle('hidden', !requester);
  $('requesterNav').classList.toggle('hidden', !requester);
  $('agentNav').classList.toggle('hidden', requester);

  const requesterDashboard = requester && state.requesterView === 'requesterDashboard';
  const myTickets = requester && state.requesterView === 'myTickets';
  const agentDashboard = !requester && state.agentView === 'agentDashboard';
  const allTickets = !requester && state.agentView === 'allTickets';

  $('requesterDashboardView').classList.toggle('hidden', !requesterDashboard);
  $('myTicketsView').classList.toggle('hidden', !myTickets);
  $('agentDashboardView').classList.toggle('hidden', !agentDashboard);
  $('allTicketsView').classList.toggle('hidden', !allTickets);

  if (requesterDashboard) {
    $('pageTitle').textContent = 'My Dashboard';
    $('pageSubtitle').textContent = 'Track the tickets you submitted.';
    renderRequesterDashboard();
  } else if (myTickets) {
    $('pageTitle').textContent = 'My Tickets';
    $('pageSubtitle').textContent = 'Tickets submitted with your requester email.';
    renderRequesterTickets();
  } else if (agentDashboard) {
    $('pageTitle').textContent = 'Agent Dashboard';
    $('pageSubtitle').textContent = 'Queue overview across all requesters and agents.';
    renderAgentDashboard();
  } else {
    $('pageTitle').textContent = 'All Tickets';
    $('pageSubtitle').textContent = 'Search, filter, sort, assign, and update every ticket.';
    renderAgentTickets();
  }
}

function clearForm() {
  form.reset();
  $('ticketId').value = '';
  $('priority').value = 'Medium';
  $('status').value = 'Open';
  $('category').value = 'General';
  $('source').value = 'Portal';
  $('comment').value = '';
  $('replySection').classList.add('hidden');
  $('commentHistory').classList.add('hidden');
  $('commentHistory').innerHTML = '';
  document.querySelector('input[name="replyVisibility"][value="public"]').checked = true;
}

function setFieldAccess(isNew = false) {
  const requesterMode = state.portal === 'requester';
  document.querySelectorAll('.agent-field').forEach(el => el.classList.toggle('hidden', requesterMode));
  ['subject', 'requester', 'email', 'priority', 'category', 'description'].forEach(id => {
    $(id).disabled = requesterMode && !isNew;
  });
  $('status').disabled = requesterMode;
  $('assignedTo').disabled = requesterMode;
  $('source').disabled = requesterMode;
  $('visibilityControl').classList.toggle('hidden', requesterMode);
  $('replyHint').textContent = requesterMode
    ? 'Your reply will be public and visible to agents.'
    : 'Public replies are visible to the requester. Private notes are agent-only.';
}

function openNewTicket() {
  clearForm();
  setFieldAccess(true);
  if (state.portal === 'requester' && state.requesterEmail) $('email').value = state.requesterEmail;
  $('ticketEyebrow').textContent = 'New ticket';
  $('dialogTitle').textContent = state.portal === 'requester' ? 'Submit Ticket' : 'Create Ticket';
  $('saveButton').textContent = $('dialogTitle').textContent;
  dialog.showModal();
}

function renderComments(ticket) {
  const comments = ticket.comments || [];
  if (!comments.length) return;
  $('commentHistory').classList.remove('hidden');
  $('commentHistory').innerHTML = `<h3>Conversation & Activity</h3>${[...comments].reverse().map(comment => {
    const visibility = comment.visibility || 'public';
    const visibilityLabel = visibility === 'private' ? '<span class="visibility private">Private note</span>' : '<span class="visibility public">Public</span>';
    return `<div class="comment ${visibility}">
      <div><strong>${escapeHtml(comment.author || 'Agent')}</strong><span>${prettyDate(comment.createdAt)}</span></div>
      <div class="comment-meta">${visibilityLabel}</div>
      <p>${escapeHtml(comment.body)}</p>
    </div>`;
  }).join('')}`;
}

function openTicket(id) {
  const ticket = state.tickets.find(item => item.id === id);
  if (!ticket) return;

  clearForm();
  setFieldAccess(false);
  $('ticketId').value = ticket.id;
  $('subject').value = ticket.subject || '';
  $('requester').value = ticket.requester || '';
  $('email').value = ticket.email || '';
  $('priority').value = ticket.priority || 'Medium';
  $('status').value = ticket.status || 'Open';
  $('category').value = ticket.category || 'General';
  $('assignedTo').value = ticket.assignedTo || '';
  $('source').value = ticket.source || 'Portal';
  $('description').value = ticket.description || '';
  $('ticketEyebrow').textContent = ticketNumber(ticket);
  $('dialogTitle').textContent = state.portal === 'agent' ? 'Agent Ticket Details' : 'Ticket Details';
  $('saveButton').textContent = state.portal === 'agent' ? 'Save Changes' : 'Send Reply';
  $('replySection').classList.remove('hidden');
  renderComments(ticket);
  dialog.showModal();
}

async function loadTickets() {
  try {
    const requesterMode = state.portal === 'requester';
    const query = requesterMode
      ? `&portal=requester&email=${encodeURIComponent(state.requesterEmail)}`
      : '&portal=agent';
    const data = await api('GET', null, query);
    state.tickets = data.tickets || [];
    state.project = data.project || state.project;
    renderProjectMetadata();
    updateVisibleView();
  } catch (error) {
    alert(error.message);
  }
}

async function switchPortal(portal) {
  state.portal = portal;
  document.querySelectorAll('.portal-button').forEach(button => button.classList.toggle('active', button.dataset.portal === portal));
  await loadTickets();
}

form.addEventListener('submit', async event => {
  event.preventDefault();
  const id = $('ticketId').value;
  const requesterMode = state.portal === 'requester';

  const body = id ? {
    id,
    actorRole: requesterMode ? 'requester' : 'agent',
    requesterEmail: state.requesterEmail,
    subject: $('subject').value.trim(),
    requester: $('requester').value.trim(),
    email: $('email').value.trim(),
    priority: $('priority').value,
    status: $('status').value,
    category: $('category').value.trim() || 'General',
    assignedTo: $('assignedTo').value.trim(),
    source: $('source').value.trim() || 'Portal',
    description: $('description').value.trim(),
    comment: $('comment').value.trim(),
    visibility: requesterMode ? 'public' : document.querySelector('input[name="replyVisibility"]:checked').value,
    commentAuthor: requesterMode ? ($('requester').value.trim() || 'Requester') : 'Agent',
  } : {
    subject: $('subject').value.trim(),
    requester: $('requester').value.trim(),
    email: $('email').value.trim(),
    priority: $('priority').value,
    category: $('category').value.trim() || 'General',
    description: $('description').value.trim(),
    source: 'Portal',
  };

  if (id && requesterMode && !$('comment').value.trim()) {
    alert('Enter a reply before submitting.');
    return;
  }

  try {
    await api(id ? 'PUT' : 'POST', body);
    if (!id && requesterMode) {
      state.requesterEmail = body.email;
      localStorage.setItem('ticketingRequesterEmail', body.email);
      $('requesterEmailLookup').value = body.email;
    }
    dialog.close();
    await loadTickets();
    if (!id && requesterMode) state.requesterView = 'myTickets';
    if (!id && !requesterMode) state.agentView = 'allTickets';
    updateVisibleView();
  } catch (error) {
    alert(error.message);
  }
});

document.querySelectorAll('.portal-button').forEach(button => button.addEventListener('click', () => switchPortal(button.dataset.portal)));
document.querySelectorAll('[data-requester-view]').forEach(button => button.addEventListener('click', () => {
  state.requesterView = button.dataset.requesterView;
  document.querySelectorAll('[data-requester-view]').forEach(item => item.classList.toggle('active', item === button));
  updateVisibleView();
}));
document.querySelectorAll('[data-agent-view]').forEach(button => button.addEventListener('click', () => {
  state.agentView = button.dataset.agentView;
  document.querySelectorAll('[data-agent-view]').forEach(item => item.classList.toggle('active', item === button));
  updateVisibleView();
}));

$('requesterNewTicketButton').addEventListener('click', openNewTicket);
$('agentNewTicketButton').addEventListener('click', openNewTicket);
$('refreshButton').addEventListener('click', loadTickets);
$('closeDialogButton').addEventListener('click', () => dialog.close());
$('cancelButton').addEventListener('click', () => dialog.close());
$('requesterSearchInput').addEventListener('input', renderRequesterTickets);
$('requesterStatusFilter').addEventListener('change', renderRequesterTickets);
$('agentSearchInput').addEventListener('input', renderAgentTickets);
$('agentStatusFilter').addEventListener('change', renderAgentTickets);
$('agentPriorityFilter').addEventListener('change', renderAgentTickets);
$('agentFilter').addEventListener('input', renderAgentTickets);
$('loadMyTicketsButton').addEventListener('click', async () => {
  state.requesterEmail = $('requesterEmailLookup').value.trim();
  localStorage.setItem('ticketingRequesterEmail', state.requesterEmail);
  await loadTickets();
});
$('requesterEmailLookup').addEventListener('keydown', event => {
  if (event.key === 'Enter') $('loadMyTicketsButton').click();
});

$('requesterEmailLookup').value = state.requesterEmail;
loadTickets();
