// Filename: quick-entry.js
// Revision : 1.2.0
// Description : Compact score-entry behavior for CVC Youth Scoreboard quick entry page.
// Author : Jason Lamb (with help from Codex CLI)
// Created Date : 2026-05-26
// Modified Date : 2026-05-26
// Changelog :
// 1.0.0 initial release
// 1.1.0 Move navigation links to footer and keep team selection compact on mobile
// 1.2.0 Add signed-in user change-password footer link

const quickEntryValues = [1, 3, 5, 10];
const quickEntryPollIntervalMs = 10000;

let quickData = null;
let selectedTeamId = null;

function formatQuickUpdatedAt(updatedAt) {
  if (!updatedAt) {
    return 'No score updates yet';
  }

  return new Intl.DateTimeFormat(undefined, {
    dateStyle: 'short',
    timeStyle: 'short'
  }).format(new Date(updatedAt));
}

function getSelectedTeam() {
  if (!quickData?.teams?.length) {
    return null;
  }

  return quickData.teams.find((team) => team.id === selectedTeamId) || quickData.teams[0];
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

function renderTeamButton(team, selectedTeam) {
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

  const teamGrid = makeElement('div', { className: 'quick-team-grid' });
  quickData.teams.forEach((team) => {
    teamGrid.appendChild(renderTeamButton(team, selectedTeam));
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
  selectedTitle.appendChild(makeElement('h2', { text: selectedTeam.name }));
  selectedHeader.appendChild(selectedTitle);
  selectedHeader.appendChild(makeElement('div', {
    className: 'quick-selected-score',
    text: String(selectedTeam.score)
  }));

  const scoreGrid = makeElement('div', { className: 'quick-score-grid' });
  quickEntryValues.forEach((value) => scoreGrid.appendChild(renderScoreButton(value, selectedTeam)));
  quickEntryValues.forEach((value) => scoreGrid.appendChild(renderScoreButton(-value, selectedTeam)));

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

  actionPanel.append(selectedHeader, scoreGrid, manualForm);

  const statusRow = makeElement('div', { className: 'quick-status-row' });
  statusRow.appendChild(makeElement('p', {
    id: 'quick-status',
    className: 'status-text',
    text: `Last updated: ${formatQuickUpdatedAt(quickData.updatedAt)}`
  }));
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

  app.append(header, teamPanel, actionPanel, statusRow, links);
}

async function applyScoreChange(teamId, amount) {
  await postQuickScore(teamId, amount);
  renderQuickEntry();
  const updatedTeam = getSelectedTeam();
  setQuickStatus(`${updatedTeam.name}: ${amount > 0 ? '+' : ''}${amount} saved. New score ${updatedTeam.score}.`);
}

async function handleQuickClick(event) {
  const actionElement = event.target.closest('[data-action], #quick-refresh');
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

    const action = actionElement.dataset.action;

    if (action === 'select-team') {
      selectedTeamId = actionElement.dataset.teamId;
      renderQuickEntry();
      return;
    }

    if (action === 'adjust-score') {
      await applyScoreChange(actionElement.dataset.teamId, Number(actionElement.dataset.amount));
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
