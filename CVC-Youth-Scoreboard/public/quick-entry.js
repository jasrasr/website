// Filename: quick-entry.js
// Revision : 1.8.0
// Description : Compact score-entry behavior for CVC Youth Scoreboard quick entry page.
// Author : Jason Lamb (with help from Codex CLI)
// Created Date : 2026-05-26
// Modified Date : 2026-06-12
// Changelog :
// 1.0.0 initial release
// 1.1.0 Move navigation links to footer and keep team selection compact on mobile
// 1.2.0 Add signed-in user change-password footer link
// 1.3.0 Use +1/+10/+100/+1000 buttons and manual negative-score note
// 1.4.0 Show 1st/2nd/3rd place rank badge on each team button and selected team header
// 1.5.0 Sort team buttons A-Z; show revision next to last-updated; drop comma in date/time; add sort note
// 1.6.0 Add Scoreboards footer link (reads data-scoreboards-url)
// 1.7.0 Add optional Frontlines roster links
// 1.8.0 Add Reset-to-0 button for the selected team and a collapsible audit log section

const QUICK_ENTRY_REVISION = '1.8.0';
const quickEntryValues = [1, 10, 100, 1000];
const quickEntryPollIntervalMs = 10000;

let quickData = null;
let selectedTeamId = null;
let quickActivityLogOpen = false;

function formatQuickUpdatedAt(updatedAt) {
  if (!updatedAt) {
    return 'No score updates yet';
  }

  const d = new Date(updatedAt);
  const dateStr = new Intl.DateTimeFormat(undefined, { dateStyle: 'short' }).format(d);
  const timeStr = new Intl.DateTimeFormat(undefined, { timeStyle: 'short' }).format(d);
  return `${dateStr} ${timeStr}`;
}

function getSelectedTeam() {
  if (!quickData?.teams?.length) {
    return null;
  }

  return quickData.teams.find((team) => team.id === selectedTeamId) || quickData.teams[0];
}

function quickOrdinalSuffix(n) {
  const abs = Math.abs(n);
  const mod100 = abs % 100;
  if (mod100 >= 11 && mod100 <= 13) return `${n}th`;
  switch (abs % 10) {
    case 1: return `${n}st`;
    case 2: return `${n}nd`;
    case 3: return `${n}rd`;
    default: return `${n}th`;
  }
}

// Standard competition ranking (tied scores share rank: 1, 1, 3). Returns Map<teamId, rank>.
function computeQuickRanks(teams) {
  const sorted = [...teams].sort((a, b) => {
    const diff = Number(b.score || 0) - Number(a.score || 0);
    if (diff !== 0) return diff;
    return String(a.name || '').localeCompare(String(b.name || ''), undefined, {
      sensitivity: 'base',
      numeric: true
    });
  });
  const ranks = new Map();
  let lastScore = null;
  let lastRank = 0;
  sorted.forEach((team, index) => {
    const score = Number(team.score || 0);
    const rank = score === lastScore ? lastRank : index + 1;
    ranks.set(team.id, rank);
    lastScore = score;
    lastRank = rank;
  });
  return ranks;
}

function rankBadgeClass(rank) {
  if (rank === 1) return 'rank-gold';
  if (rank === 2) return 'rank-silver';
  if (rank === 3) return 'rank-bronze';
  return 'rank-plain';
}

async function fetchQuickScores() {
  const response = await fetch('api.php?action=scores');
  if (!response.ok) {
    throw new Error('Unable to load scores.');
  }

  quickData = await response.json();
  if (!selectedTeamId && quickData.teams?.length) {
    selectedTeamId = quickData.teams[0].id;
  }

  return quickData;
}

async function postQuickScore(teamId, amount) {
  const response = await fetch(`api.php?action=update&team=${encodeURIComponent(teamId)}`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json'
    },
    body: JSON.stringify({ amount })
  });

  if (!response.ok) {
    const { error } = await response.json().catch(() => ({ error: 'Update failed.' }));
    throw new Error(error || 'Update failed.');
  }

  quickData = await response.json();
  return quickData;
}

async function postQuickReset(teamId) {
  const response = await fetch(`api.php?action=reset-team&team=${encodeURIComponent(teamId)}`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json'
    },
    body: JSON.stringify({})
  });

  if (!response.ok) {
    const { error } = await response.json().catch(() => ({ error: 'Reset failed.' }));
    throw new Error(error || 'Reset failed.');
  }

  quickData = await response.json();
  return quickData;
}

async function loadQuickActivityLog() {
  const log = document.querySelector('#quick-activity-log');
  if (!log) return;
  try {
    const response = await fetch('api.php?action=audit');
    if (!response.ok) throw new Error('Failed to load.');
    const { entries } = await response.json();
    if (!entries || entries.length === 0) {
      log.innerHTML = '<p class="status-text">No activity recorded yet.</p>';
      return;
    }
    log.innerHTML = `
      <div class="au-table-wrap">
        <table class="au-table au-audit">
          <thead>
            <tr>
              <th>Time (UTC)</th><th>User</th><th>Action</th>
              <th>Team</th><th>Change</th><th>Score</th><th>IP</th>
            </tr>
          </thead>
          <tbody>
            ${entries.map((e) => `
              <tr>
                <td>${(e.timestamp || '').slice(0, 19)}</td>
                <td>${e.username || ''}</td>
                <td>${e.action || ''}</td>
                <td>${e.team_name || '—'}</td>
                <td>${e.amount != null ? (e.amount > 0 ? '+' : '') + e.amount : '—'}</td>
                <td>${e.new_score != null ? e.new_score : '—'}</td>
                <td>${e.ip || ''}</td>
              </tr>
            `).join('')}
          </tbody>
        </table>
      </div>
    `;
  } catch {
    log.innerHTML = '<p class="status-text">Unable to load activity log.</p>';
  }
}

function syncQuickActivityLog() {
  const log = document.querySelector('#quick-activity-log');
  const btn = document.querySelector('#quick-activity-toggle');
  if (!log || !btn) return Promise.resolve();
  if (quickActivityLogOpen) {
    log.classList.remove('hidden');
    btn.textContent = 'Hide Recent Activity';
    return loadQuickActivityLog();
  }
  log.classList.add('hidden');
  btn.textContent = 'Show Recent Activity';
  return Promise.resolve();
}

function setQuickStatus(message) {
  const status = document.querySelector('#quick-status');
  if (status) {
    status.textContent = message;
  }
}

function makeElement(tagName, options = {}) {
  const element = document.createElement(tagName);

  if (options.className) {
    element.className = options.className;
  }

  if (options.text !== undefined) {
    element.textContent = options.text;
  }

  if (options.attributes) {
    Object.entries(options.attributes).forEach(([name, value]) => {
      element.setAttribute(name, value);
    });
  }

  if (options.dataset) {
    Object.entries(options.dataset).forEach(([name, value]) => {
      element.dataset[name] = value;
    });
  }

  return element;
}

function renderTeamButton(team, selectedTeam, rank) {
  const button = makeElement('button', {
    className: 'quick-team-button',
    attributes: {
      type: 'button',
      'aria-pressed': team.id === selectedTeam.id ? 'true' : 'false',
      style: `--team-color: ${team.color}`
    },
    dataset: {
      action: 'select-team',
      teamId: team.id
    }
  });

  if (rank) {
    button.appendChild(makeElement('span', {
      className: `rank-badge ${rankBadgeClass(rank)}`,
      text: quickOrdinalSuffix(rank),
      attributes: { 'aria-label': `Rank ${quickOrdinalSuffix(rank)}` }
    }));
  }

  button.appendChild(makeElement('span', {
    className: 'quick-team-name',
    text: team.name
  }));
  button.appendChild(makeElement('span', {
    className: 'quick-team-score',
    text: String(team.score)
  }));

  return button;
}

function renderScoreButton(value, selectedTeam) {
  const button = makeElement('button', {
    className: value > 0 ? 'positive' : 'negative',
    text: value > 0 ? `+${value}` : String(value),
    attributes: {
      type: 'button'
    },
    dataset: {
      action: 'adjust-score',
      teamId: selectedTeam.id,
      amount: String(value)
    }
  });

  return button;
}

function renderQuickEntry() {
  const app = document.querySelector('#quick-entry-app');
  const selectedTeam = getSelectedTeam();
  const logoutUrl = document.body.dataset.logoutUrl || './logout.php';
  const passwordUrl = document.body.dataset.passwordUrl || './change-password.php';
  const scoreboardsUrl = document.body.dataset.scoreboardsUrl || './scoreboards.php';
  const rosterUrl = document.body.dataset.rosterUrl || '';
  const editRosterUrl = document.body.dataset.editRosterUrl || '';
  const username = document.body.dataset.username || '';

  if (!app || !quickData || !selectedTeam) {
    return;
  }

  app.replaceChildren();

  const header = makeElement('header', { className: 'quick-header' });
  const title = makeElement('div', { className: 'quick-title' });
  title.appendChild(makeElement('h1', { text: quickData.title || 'CVC Youth Scoreboard' }));
  title.appendChild(makeElement('p', {
    className: 'updated-at',
    text: username ? `Quick entry - ${username}` : 'Quick entry'
  }));

  header.appendChild(title);

  const teamPanel = makeElement('section', { className: 'quick-panel' });
  teamPanel.appendChild(makeElement('p', {
    className: 'quick-section-label',
    text: 'Team'
  }));
  teamPanel.appendChild(makeElement('p', {
    className: 'sort-note',
    text: 'Teams are sorted A-Z by name.'
  }));

  const ranks = computeQuickRanks(quickData.teams);
  const sortedTeams = [...quickData.teams].sort((a, b) =>
    String(a.name || '').localeCompare(String(b.name || ''), undefined, {
      sensitivity: 'base',
      numeric: true
    })
  );
  const teamGrid = makeElement('div', { className: 'quick-team-grid' });
  sortedTeams.forEach((team) => {
    teamGrid.appendChild(renderTeamButton(team, selectedTeam, ranks.get(team.id)));
  });
  teamPanel.appendChild(teamGrid);

  const actionPanel = makeElement('section', {
    className: 'quick-panel quick-action-panel',
    attributes: {
      style: `--selected-team-color: ${selectedTeam.color}`
    }
  });

  const selectedHeader = makeElement('div', { className: 'quick-selected' });
  const selectedTitle = makeElement('div');
  selectedTitle.appendChild(makeElement('p', {
    className: 'quick-section-label',
    text: 'Selected'
  }));
  const selectedRank = ranks.get(selectedTeam.id);
  const selectedNameRow = makeElement('div', { className: 'quick-selected-name-row' });
  if (selectedRank) {
    selectedNameRow.appendChild(makeElement('span', {
      className: `rank-badge ${rankBadgeClass(selectedRank)}`,
      text: quickOrdinalSuffix(selectedRank),
      attributes: { 'aria-label': `Rank ${quickOrdinalSuffix(selectedRank)}` }
    }));
  }
  selectedNameRow.appendChild(makeElement('h2', { text: selectedTeam.name }));
  selectedTitle.appendChild(selectedNameRow);
  selectedHeader.appendChild(selectedTitle);
  selectedHeader.appendChild(makeElement('div', {
    className: 'quick-selected-score',
    text: String(selectedTeam.score)
  }));

  const scoreGrid = makeElement('div', { className: 'quick-score-grid' });
  quickEntryValues.forEach((value) => scoreGrid.appendChild(renderScoreButton(value, selectedTeam)));

  const manualForm = makeElement('form', {
    className: 'quick-manual-form',
    dataset: { action: 'manual-score', teamId: selectedTeam.id }
  });
  manualForm.appendChild(makeElement('input', {
    attributes: {
      name: 'manualAmount',
      type: 'number',
      inputmode: 'numeric',
      step: '1',
      placeholder: 'Manual +/- amount',
      'aria-label': `Manual score change for ${selectedTeam.name}`
    }
  }));
  manualForm.appendChild(makeElement('button', {
    className: 'secondary',
    text: 'Apply',
    attributes: { type: 'submit' }
  }));
  const manualNote = makeElement('p', {
    className: 'quick-manual-note',
    text: 'Enter -1, -10, or another negative number for minus scoring.'
  });

  const resetButton = makeElement('button', {
    className: 'warning quick-reset-button',
    text: 'Reset Score to Zero',
    attributes: { type: 'button' },
    dataset: { action: 'reset-selected', teamId: selectedTeam.id }
  });

  actionPanel.append(selectedHeader, scoreGrid, manualForm, manualNote, resetButton);

  const statusRow = makeElement('div', { className: 'quick-status-row' });
  const statusBlock = makeElement('div', { className: 'quick-status-block' });
  statusBlock.appendChild(makeElement('p', {
    id: 'quick-status',
    className: 'status-text',
    text: `Last updated: ${formatQuickUpdatedAt(quickData.updatedAt)}`
  }));
  statusBlock.appendChild(makeElement('p', {
    className: 'quick-revision',
    text: `v${QUICK_ENTRY_REVISION}`
  }));
  statusRow.appendChild(statusBlock);
  statusRow.appendChild(makeElement('a', {
    className: 'au-btn',
    text: 'Refresh',
    attributes: { href: '#', id: 'quick-refresh' }
  }));

  const links = makeElement('div', { className: 'quick-links' });
  links.appendChild(makeElement('a', {
    className: 'au-btn',
    text: 'Full Admin',
    attributes: { href: './enter-scores.php' }
  }));
  links.appendChild(makeElement('a', {
    className: 'au-btn',
    text: 'Viewer',
    attributes: { href: './index.php', target: '_blank', rel: 'noopener' }
  }));
  if (rosterUrl) {
    links.appendChild(makeElement('a', {
      className: 'au-btn',
      text: 'Roster',
      attributes: { href: rosterUrl }
    }));
  }
  if (editRosterUrl) {
    links.appendChild(makeElement('a', {
      className: 'au-btn',
      text: 'Edit Roster',
      attributes: { href: editRosterUrl }
    }));
  }
  links.appendChild(makeElement('a', {
    className: 'au-btn',
    text: 'Scoreboards',
    attributes: { href: scoreboardsUrl }
  }));
  links.appendChild(makeElement('a', {
    className: 'au-btn',
    text: 'Change Password',
    attributes: { href: passwordUrl }
  }));
  links.appendChild(makeElement('a', {
    className: 'au-btn',
    text: 'Sign Out',
    attributes: { href: logoutUrl }
  }));

  const activitySection = makeElement('section', {
    className: 'quick-activity-section',
    attributes: { 'aria-label': 'Recent activity' }
  });
  activitySection.appendChild(makeElement('button', {
    className: 'secondary',
    text: 'Show Recent Activity',
    attributes: { type: 'button', id: 'quick-activity-toggle' }
  }));
  activitySection.appendChild(makeElement('div', {
    className: 'hidden',
    attributes: { id: 'quick-activity-log' }
  }));

  app.append(header, teamPanel, actionPanel, statusRow, activitySection, links);
  syncQuickActivityLog();
}

async function applyScoreChange(teamId, amount) {
  await postQuickScore(teamId, amount);
  renderQuickEntry();
  const updatedTeam = getSelectedTeam();
  setQuickStatus(`${updatedTeam.name}: ${amount > 0 ? '+' : ''}${amount} saved. New score ${updatedTeam.score}.`);
}

async function handleQuickClick(event) {
  const actionElement = event.target.closest('[data-action], #quick-refresh, #quick-activity-toggle');
  if (!actionElement) {
    return;
  }

  try {
    if (actionElement.id === 'quick-refresh') {
      event.preventDefault();
      await fetchQuickScores();
      renderQuickEntry();
      setQuickStatus(`Refreshed: ${formatQuickUpdatedAt(quickData.updatedAt)}`);
      return;
    }

    if (actionElement.id === 'quick-activity-toggle') {
      quickActivityLogOpen = !quickActivityLogOpen;
      await syncQuickActivityLog();
      return;
    }

    const action = actionElement.dataset.action;

    if (action === 'select-team') {
      selectedTeamId = actionElement.dataset.teamId;
      renderQuickEntry();
      return;
    }

    if (action === 'adjust-score') {
      await applyScoreChange(actionElement.dataset.teamId, Number(actionElement.dataset.amount));
      return;
    }

    if (action === 'reset-selected') {
      const teamId = actionElement.dataset.teamId;
      const team = quickData?.teams?.find((t) => t.id === teamId);
      const teamName = team?.name || 'team';
      if (!window.confirm(`Reset ${teamName} score to 0?`)) {
        return;
      }
      await postQuickReset(teamId);
      renderQuickEntry();
      setQuickStatus(`${teamName} score reset to 0.`);
    }
  } catch (error) {
    setQuickStatus(error.message);
  }
}

async function handleQuickSubmit(event) {
  const form = event.target.closest('form[data-action="manual-score"]');
  if (!form) {
    return;
  }

  event.preventDefault();

  const formData = new FormData(form);
  const amount = Number(formData.get('manualAmount'));

  if (!Number.isFinite(amount) || amount === 0) {
    setQuickStatus('Enter a positive or negative number before clicking Apply.');
    return;
  }

  try {
    await applyScoreChange(form.dataset.teamId, amount);
  } catch (error) {
    setQuickStatus(error.message);
  }
}

async function initQuickEntry() {
  await fetchQuickScores();
  renderQuickEntry();

  document.addEventListener('click', handleQuickClick);
  document.addEventListener('submit', handleQuickSubmit);

  setInterval(async () => {
    const activeTag = document.activeElement?.tagName;
    if (activeTag === 'INPUT' || activeTag === 'TEXTAREA') {
      return;
    }

    try {
      await fetchQuickScores();
      renderQuickEntry();
    } catch {
      setQuickStatus('Unable to refresh scores right now.');
    }
  }, quickEntryPollIntervalMs);
}

initQuickEntry().catch((error) => {
  const app = document.querySelector('#quick-entry-app');
  if (app) {
    app.textContent = error.message;
  }
});
