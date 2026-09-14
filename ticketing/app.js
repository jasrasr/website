const state = {
  tickets: [],
  currentView: 'dashboard',
};

const $ = (id) => document.getElementById(id);
const dashboardView = $('dashboardView');
const ticketsView = $('ticketsView');
const dialog = $('ticketDialog');
const form = $('ticketForm');

async function api(method = 'GET', body = null) {
  const response = await fetch('index.php?api=1', {
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
  const date = new Date(value);
  return date.toLocaleString([], {
    month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit'
  });
}

function badge(value, type = '') {
  const css = String(value).toLowerCase().replace(/[^a-z0-9]+/g, '-');
  return `<span class="badge ${type} ${css}">${escapeHtml(value)}</span>`;
}

function tableHtml(tickets) {
  if (!tickets.length) {
    return '<div class="empty-state">No tickets match this view.</div>';
  }

  return `<div class="table-wrap"><table>
    <thead><tr><th>Ticket</th><th>Requester</th><th>Status</th><th>Priority</th><th>Assigned</th><th>Updated</th></tr></thead>
    <tbody>${tickets.map(ticket => `
      <tr class="ticket-row" data-id="${escapeHtml(ticket.id)}">
        <td><strong>${ticketNumber(ticket)}</strong><div class="ticket-subject">${escapeHtml(ticket.subject)}</div><div class="muted small">${escapeHtml(ticket.category || 'General')}</div></td>
        <td>${escapeHtml(ticket.requester)}</td>
        <td>${badge(ticket.status || 'Open', 'status')}</td>
        <td>${badge(ticket.priority || 'Medium', 'priority')}</td>
        <td>${escapeHtml(ticket.assignedTo || 'Unassigned')}</td>
        <td>${prettyDate(ticket.updatedAt)}</td>
      </tr>`).join('')}</tbody>
  </table></div>`;
}

function renderStats() {
  const counts = {
    Open: 0,
    Pending: 0,
    Resolved: 0,
    Closed: 0,
  };

  state.tickets.forEach(ticket => {
    if (Object.hasOwn(counts, ticket.status)) counts[ticket.status] += 1;
  });

  $('stats').innerHTML = [
    ['Open', counts.Open],
    ['Pending', counts.Pending],
    ['Resolved', counts.Resolved],
    ['Closed', counts.Closed],
    ['Total', state.tickets.length],
  ].map(([label, count]) => `<div class="stat-card"><span>${label}</span><strong>${count}</strong></div>`).join('');
}

function renderDashboard() {
  renderStats();
  const recent = [...state.tickets]
    .sort((a, b) => new Date(b.updatedAt) - new Date(a.updatedAt))
    .slice(0, 8);
  $('recentTickets').innerHTML = tableHtml(recent);
  bindRows($('recentTickets'));
}

function renderTickets() {
  const query = $('searchInput').value.trim().toLowerCase();
  const status = $('statusFilter').value;
  const priority = $('priorityFilter').value;

  const filtered = [...state.tickets]
    .filter(ticket => !status || ticket.status === status)
    .filter(ticket => !priority || ticket.priority === priority)
    .filter(ticket => {
      if (!query) return true;
      const haystack = [ticket.number, ticket.subject, ticket.description, ticket.requester, ticket.email, ticket.category, ticket.assignedTo]
        .join(' ').toLowerCase();
      return haystack.includes(query);
    })
    .sort((a, b) => new Date(b.updatedAt) - new Date(a.updatedAt));

  $('ticketList').innerHTML = tableHtml(filtered);
  bindRows($('ticketList'));
}

function bindRows(container) {
  container.querySelectorAll('.ticket-row').forEach(row => {
    row.addEventListener('click', () => openTicket(row.dataset.id));
  });
}

function setView(view) {
  state.currentView = view;
  const isDashboard = view === 'dashboard';
  dashboardView.classList.toggle('hidden', !isDashboard);
  ticketsView.classList.toggle('hidden', isDashboard);
  $('pageTitle').textContent = isDashboard ? 'Dashboard' : 'Tickets';

  document.querySelectorAll('.nav-button[data-view]').forEach(button => {
    button.classList.toggle('active', button.dataset.view === view);
  });

  if (isDashboard) renderDashboard(); else renderTickets();
}

function clearForm() {
  form.reset();
  $('ticketId').value = '';
  $('priority').value = 'Medium';
  $('status').value = 'Open';
  $('category').value = 'General';
  $('comment').value = '';
  $('commentWrap').classList.add('hidden');
  $('commentHistory').classList.add('hidden');
  $('commentHistory').innerHTML = '';
}

function openNewTicket() {
  clearForm();
  $('ticketEyebrow').textContent = 'New ticket';
  $('dialogTitle').textContent = 'Create Ticket';
  $('saveButton').textContent = 'Create Ticket';
  dialog.showModal();
}

function openTicket(id) {
  const ticket = state.tickets.find(item => item.id === id);
  if (!ticket) return;

  clearForm();
  $('ticketId').value = ticket.id;
  $('subject').value = ticket.subject || '';
  $('requester').value = ticket.requester || '';
  $('email').value = ticket.email || '';
  $('priority').value = ticket.priority || 'Medium';
  $('status').value = ticket.status || 'Open';
  $('category').value = ticket.category || 'General';
  $('assignedTo').value = ticket.assignedTo || '';
  $('description').value = ticket.description || '';
  $('ticketEyebrow').textContent = ticketNumber(ticket);
  $('dialogTitle').textContent = 'Ticket Details';
  $('saveButton').textContent = 'Save Changes';
  $('commentWrap').classList.remove('hidden');

  const comments = ticket.comments || [];
  if (comments.length) {
    $('commentHistory').classList.remove('hidden');
    $('commentHistory').innerHTML = `<h3>Activity</h3>${[...comments].reverse().map(comment => `
      <div class="comment"><div><strong>${escapeHtml(comment.author || 'Agent')}</strong><span>${prettyDate(comment.createdAt)}</span></div><p>${escapeHtml(comment.body)}</p></div>
    `).join('')}`;
  }

  dialog.showModal();
}

async function loadTickets() {
  try {
    const data = await api();
    state.tickets = data.tickets || [];
    renderDashboard();
    renderTickets();
  } catch (error) {
    alert(error.message);
  }
}

form.addEventListener('submit', async event => {
  event.preventDefault();

  const id = $('ticketId').value;
  const body = {
    id,
    subject: $('subject').value.trim(),
    requester: $('requester').value.trim(),
    email: $('email').value.trim(),
    priority: $('priority').value,
    status: $('status').value,
    category: $('category').value.trim() || 'General',
    assignedTo: $('assignedTo').value.trim(),
    description: $('description').value.trim(),
    comment: $('comment').value.trim(),
    commentAuthor: 'Agent',
  };

  try {
    await api(id ? 'PUT' : 'POST', body);
    dialog.close();
    await loadTickets();
    setView(id ? state.currentView : 'tickets');
  } catch (error) {
    alert(error.message);
  }
});

document.querySelectorAll('.nav-button[data-view]').forEach(button => {
  button.addEventListener('click', () => setView(button.dataset.view));
});

$('newTicketButton').addEventListener('click', openNewTicket);
$('refreshButton').addEventListener('click', loadTickets);
$('closeDialogButton').addEventListener('click', () => dialog.close());
$('cancelButton').addEventListener('click', () => dialog.close());
$('searchInput').addEventListener('input', renderTickets);
$('statusFilter').addEventListener('change', renderTickets);
$('priorityFilter').addEventListener('change', renderTickets);

loadTickets();
