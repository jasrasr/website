// Filename: category-entry.js
// Revision : 1.2.0
// Description : Scorer + admin UI for awarding Frontlines goal categories. Pick a team,
//               then one-tap a goal button. Server enforces maxAwardsPerTeam; client
//               visually disables buttons whose cap is reached using audit-log counts.
// Author : Jason Lamb (with help from Claude Code)
// Created Date : 2026-06-17
// Modified Date : 2026-06-21
// Changelog :
// 1.0.0 initial release
// 1.1.0 Add ranked categories with manual 12000-to-1000 award values
// 1.2.0 Sort categories by custom sortOrder before name

const CATEGORY_ENTRY_REVISION = '1.2.0';
const RANKED_CATEGORY_POINTS = [12000, 11000, 10000, 9000, 8000, 7000, 6000, 5000, 4000, 3000, 2000, 1000];
const categoryPollIntervalMs = 10000;
const auditLookbackLimit = 1000;

let scoresData = null;
let categoriesData = null;
let auditEntries = [];
let selectedTeamId = null;
let statusMessage = '';
let activityLogOpen = false;

function apiUrl() {
  return document.body.dataset.apiUrl || './api.php';
}

async function fetchJson(path) {
  const url = path.startsWith('?') ? `${apiUrl()}${path}` : `${apiUrl()}?${path}`;
  const response = await fetch(url, { credentials: 'same-origin' });
  if (!response.ok) {
    let message = `Request failed (${response.status}).`;
    try {
      const body = await response.json();
      if (body && body.error) message = body.error;
    } catch { /* keep default */ }
    throw new Error(message);
  }
  return response.json();
}

async function postAction(path) {
  const url = path.startsWith('?') ? `${apiUrl()}${path}` : `${apiUrl()}?${path}`;
  const response = await fetch(url, { method: 'POST', credentials: 'same-origin' });
  let data = null;
  try { data = await response.json(); } catch { data = null; }
  if (!response.ok) {
    const message = (data && data.error) || `Request failed (${response.status}).`;
    throw new Error(message);
  }
  return data;
}

async function loadAll() {
  const [scores, cats, audit] = await Promise.all([
    fetchJson('action=scores'),
    fetchJson('action=list-categories'),
    fetchJson('action=audit')
  ]);
  scoresData = scores;
  categoriesData = cats;
  auditEntries = Array.isArray(audit?.entries) ? audit.entries : [];
}

function el(tag, options = {}) {
  const element = document.createElement(tag);
  if (options.className) element.className = options.className;
  if (options.text !== undefined) element.textContent = options.text;
  if (options.html !== undefined) element.innerHTML = options.html;
  if (options.attributes) {
    Object.entries(options.attributes).forEach(([k, v]) => element.setAttribute(k, v));
  }
  if (options.dataset) {
    Object.entries(options.dataset).forEach(([k, v]) => { element.dataset[k] = v; });
  }
  return element;
}

function ordinalSuffix(n) {
  const v = n % 100;
  if (v >= 11 && v <= 13) return `${n}th`;
  switch (n % 10) {
    case 1: return `${n}st`;
    case 2: return `${n}nd`;
    case 3: return `${n}rd`;
    default: return `${n}th`;
  }
}

function rankBadgeClass(rank) {
  return rank === 1 ? 'rank-gold' : rank === 2 ? 'rank-silver' : rank === 3 ? 'rank-bronze' : 'rank-plain';
}

function computeRanks(teams) {
  const sorted = [...teams].sort((a, b) => Number(b.score || 0) - Number(a.score || 0));
  const ranks = new Map();
  let lastScore = null;
  let lastRank = 0;
  sorted.forEach((team, idx) => {
    const score = Number(team.score || 0);
    const rank = score === lastScore ? lastRank : idx + 1;
    ranks.set(team.id, rank);
    lastScore = score;
    lastRank = rank;
  });
  return ranks;
}

function countAwards(teamId, categoryId) {
  let count = 0;
  for (const entry of auditEntries) {
    if (!entry || typeof entry !== 'object') continue;
    if (entry.action !== 'award-category') continue;
    if (entry.team_id !== teamId) continue;
    if (entry.category_id !== categoryId) continue;
    count++;
  }
  return count;
}

function isRankedCategory(category) {
  return category?.scoringMode === 'ranked';
}

function sortCategories(categories) {
  return [...categories].sort((a, b) => {
    const orderA = Number.isFinite(Number(a.sortOrder)) ? Number(a.sortOrder) : Number.MAX_SAFE_INTEGER;
    const orderB = Number.isFinite(Number(b.sortOrder)) ? Number(b.sortOrder) : Number.MAX_SAFE_INTEGER;
    if (orderA !== orderB) return orderA - orderB;
    return String(a.name || '').localeCompare(String(b.name || ''), undefined, { sensitivity: 'base' });
  });
}

function setStatus(message) {
  statusMessage = message;
  const status = document.querySelector('#category-status');
  if (status) status.textContent = message;
}

function formatUpdatedAt(updatedAt) {
  if (!updatedAt) return 'Waiting for first update';
  const date = new Date(updatedAt);
  if (Number.isNaN(date.getTime())) return 'Waiting for first update';
  return date.toLocaleString(undefined, {
    month: 'short', day: 'numeric',
    hour: 'numeric', minute: '2-digit', hour12: true
  });
}

function getSelectedTeam() {
  if (!scoresData || !Array.isArray(scoresData.teams)) return null;
  return scoresData.teams.find((t) => t.id === selectedTeamId) || null;
}

function renderTeamButton(team, ranks) {
  const isSelected = team.id === selectedTeamId;
  const button = el('button', {
    className: 'quick-team-button',
    attributes: {
      type: 'button',
      'aria-pressed': isSelected ? 'true' : 'false',
      style: `--team-color: ${team.color}`
    },
    dataset: { action: 'select-team', teamId: team.id }
  });
  const rank = ranks.get(team.id);
  if (rank) {
    button.append(el('span', {
      className: `rank-badge ${rankBadgeClass(rank)}`,
      text: ordinalSuffix(rank),
      attributes: { 'aria-label': `Rank ${ordinalSuffix(rank)}` }
    }));
  }
  button.append(el('span', { className: 'quick-team-name', text: team.name }));
  button.append(el('span', { className: 'quick-team-score', text: String(team.score) }));
  return button;
}

function renderCategoryButton(category, selectedTeam) {
  if (isRankedCategory(category)) {
    return renderRankedCategory(category, selectedTeam);
  }

  const points = Number(category.points || 0);
  const sign = points > 0 ? '+' : '';
  const isPositive = points > 0;
  const cap = (category.maxAwardsPerTeam == null) ? null : Number(category.maxAwardsPerTeam);
  const awarded = countAwards(selectedTeam.id, category.id);
  const atCap = cap !== null && awarded >= cap;
  const inactive = !category.active;

  const button = el('button', {
    className: `category-button ${isPositive ? 'positive' : 'negative'}${atCap || inactive ? ' is-disabled' : ''}`,
    attributes: { type: 'button' },
    dataset: { action: 'award-category', categoryId: category.id, teamId: selectedTeam.id }
  });
  if (atCap || inactive) {
    button.setAttribute('disabled', 'disabled');
    button.setAttribute('aria-disabled', 'true');
  }

  button.append(el('span', { className: 'category-button-name', text: category.name }));
  button.append(el('span', {
    className: 'category-button-points',
    text: `${sign}${points}`
  }));

  if (cap !== null) {
    const remaining = Math.max(0, cap - awarded);
    button.append(el('span', {
      className: 'category-button-cap',
      text: atCap ? `Max reached (${cap})` : `${remaining} of ${cap} left`
    }));
  } else if (inactive) {
    button.append(el('span', { className: 'category-button-cap', text: 'Inactive' }));
  }

  return button;
}

function renderRankedCategory(category, selectedTeam) {
  const awarded = countAwards(selectedTeam.id, category.id);
  if (awarded > 0) {
    return null;
  }

  const inactive = !category.active;
  const wrapper = el('section', {
    className: `category-ranked ${inactive ? ' is-disabled' : ''}`
  });
  wrapper.append(el('h3', { className: 'category-ranked-title', text: category.name }));
  if (inactive) {
    wrapper.append(el('p', { className: 'category-button-cap', text: 'Inactive' }));
  }

  const grid = el('div', { className: 'category-ranked-value-grid' });
  RANKED_CATEGORY_POINTS.forEach((points) => {
    const button = el('button', {
      className: 'category-button positive category-ranked-value',
      attributes: { type: 'button' },
      dataset: {
        action: 'award-category',
        categoryId: category.id,
        teamId: selectedTeam.id,
        awardPoints: String(points)
      }
    });
    if (inactive) {
      button.setAttribute('disabled', 'disabled');
      button.setAttribute('aria-disabled', 'true');
    }
    button.append(el('span', { className: 'category-button-name', text: category.name }));
    button.append(el('span', { className: 'category-button-points', text: `+${points}` }));
    grid.append(button);
  });
  wrapper.append(grid);
  return wrapper;
}

function renderHeader() {
  const username = document.body.dataset.username || '';
  const header = el('header', { className: 'quick-header' });
  const title = el('div', { className: 'quick-title' });
  title.append(el('h1', { text: scoresData?.title || 'Frontlines Scoreboard' }));
  title.append(el('p', { className: 'updated-at', text: username ? `Category entry - ${username}` : 'Category entry' }));
  header.append(title);
  return header;
}

function renderTeamPanel(ranks) {
  const panel = el('section', { className: 'quick-panel' });
  panel.append(el('p', { className: 'quick-section-label', text: 'Team' }));
  panel.append(el('p', { className: 'sort-note', text: 'Teams are sorted A-Z by name.' }));

  const grid = el('div', { className: 'quick-team-grid' });
  const sorted = [...(scoresData?.teams || [])].sort((a, b) =>
    String(a.name || '').localeCompare(String(b.name || ''), undefined, { sensitivity: 'base' })
  );
  sorted.forEach((team) => grid.append(renderTeamButton(team, ranks)));
  panel.append(grid);
  return panel;
}

function renderActionPanel(ranks) {
  const panel = el('section', { className: 'quick-action-panel quick-panel' });
  const selectedTeam = getSelectedTeam();

  if (!selectedTeam) {
    panel.append(el('p', { className: 'status-text', text: 'Select a team above to view goal buttons.' }));
    return panel;
  }

  const headerRow = el('div', { className: 'quick-selected' });
  const titleBlock = el('div');
  titleBlock.append(el('p', { className: 'quick-section-label', text: 'Selected' }));
  const nameRow = el('div', { className: 'quick-selected-name-row' });
  const rank = ranks.get(selectedTeam.id);
  if (rank) {
    nameRow.append(el('span', {
      className: `rank-badge ${rankBadgeClass(rank)}`,
      text: ordinalSuffix(rank),
      attributes: { 'aria-label': `Rank ${ordinalSuffix(rank)}` }
    }));
  }
  nameRow.append(el('h2', { text: selectedTeam.name }));
  titleBlock.append(nameRow);
  headerRow.append(titleBlock);
  headerRow.append(el('div', {
    className: 'quick-selected-score',
    text: String(selectedTeam.score)
  }));
  panel.append(headerRow);

  panel.append(el('p', { className: 'sort-note', text: 'Goals are sorted A-Z. Tap a button to award.' }));

  const categories = Array.isArray(categoriesData?.categories)
    ? sortCategories(categoriesData.categories)
    : [];

  if (categories.length === 0) {
    panel.append(el('p', { className: 'status-text', text: 'No goals defined yet. Ask an admin to add some on Edit Categories.' }));
    return panel;
  }

  const grid = el('div', { className: 'category-grid' });
  categories.forEach((cat) => {
    const button = renderCategoryButton(cat, selectedTeam);
    if (button) grid.append(button);
  });
  panel.append(grid);

  return panel;
}

function renderStatusRow() {
  const row = el('div', { className: 'quick-status-row' });
  const block = el('div', { className: 'quick-status-block' });
  block.append(el('p', {
    className: 'status-text',
    attributes: { id: 'category-status' },
    text: statusMessage || `Last updated: ${formatUpdatedAt(scoresData?.updatedAt)}`
  }));
  block.append(el('p', { className: 'quick-revision', text: `v${CATEGORY_ENTRY_REVISION}` }));
  row.append(block);

  const refresh = el('button', {
    className: 'au-btn',
    text: 'Refresh',
    attributes: { type: 'button', id: 'category-refresh' }
  });
  row.append(refresh);
  return row;
}

function renderActivitySection() {
  const section = el('section', { className: 'quick-activity' });
  const toggle = el('button', {
    className: 'secondary',
    text: activityLogOpen ? 'Hide Recent Activity' : 'Show Recent Activity',
    attributes: { type: 'button', id: 'category-activity-toggle' }
  });
  section.append(toggle);

  if (activityLogOpen) {
    const log = el('div', { className: 'activity-log', attributes: { id: 'category-activity-log' } });
    const recent = auditEntries.slice(0, 20);
    if (recent.length === 0) {
      log.append(el('p', { className: 'updated-at', text: 'No recent activity yet.' }));
    } else {
      const table = el('table', { className: 'activity-table' });
      const thead = el('thead');
      const headRow = el('tr');
      ['When', 'Who', 'Action', 'Team', 'Detail'].forEach((label) => headRow.append(el('th', { text: label })));
      thead.append(headRow);
      table.append(thead);

      const tbody = el('tbody');
      recent.forEach((entry) => {
        const tr = el('tr');
        tr.append(el('td', { text: formatUpdatedAt(entry.timestamp) }));
        tr.append(el('td', { text: entry.username || '' }));
        tr.append(el('td', { text: entry.action || '' }));
        tr.append(el('td', { text: entry.team_name || '' }));
        let detail = '';
        if (entry.action === 'award-category') {
          const pts = Number(entry.amount || 0);
          const sign = pts > 0 ? '+' : '';
          detail = `${entry.category_name || ''} ${sign}${pts} (score ${entry.new_score})`;
        } else if (entry.amount != null) {
          const pts = Number(entry.amount);
          const sign = pts > 0 ? '+' : '';
          detail = `${sign}${pts} (score ${entry.new_score ?? '—'})`;
        }
        tr.append(el('td', { text: detail }));
        tbody.append(tr);
      });
      table.append(tbody);
      log.append(table);
    }
    section.append(log);
  }

  return section;
}

function renderFooterLinks() {
  const links = el('section', { className: 'quick-links' });
  const quickUrl = document.body.dataset.quickEntryUrl || './enter-scores-quick.php';
  const fullUrl = document.body.dataset.fullAdminUrl || './enter-scores.php';
  const editCatsUrl = document.body.dataset.editCategoriesUrl || '';
  const rosterUrl = document.body.dataset.rosterUrl || '';
  const editRosterUrl = document.body.dataset.editRosterUrl || '';
  const scoreboardsUrl = document.body.dataset.scoreboardsUrl || '../scoreboards.php';
  const passwordUrl = document.body.dataset.passwordUrl || '../change-password.php';
  const logoutUrl = document.body.dataset.logoutUrl || '../logout.php';

  links.append(el('a', { className: 'au-btn', text: 'Quick Entry', attributes: { href: quickUrl } }));
  links.append(el('a', { className: 'au-btn', text: 'Full Admin', attributes: { href: fullUrl } }));
  if (editCatsUrl) {
    links.append(el('a', { className: 'au-btn', text: 'Edit Categories', attributes: { href: editCatsUrl } }));
  }
  if (rosterUrl) {
    links.append(el('a', { className: 'au-btn', text: 'Team Roster', attributes: { href: rosterUrl } }));
  }
  if (editRosterUrl) {
    links.append(el('a', { className: 'au-btn', text: 'Edit Roster', attributes: { href: editRosterUrl } }));
  }
  links.append(el('a', { className: 'au-btn', text: 'All Scoreboards', attributes: { href: scoreboardsUrl } }));
  links.append(el('a', { className: 'au-btn', text: 'Change Password', attributes: { href: passwordUrl } }));
  links.append(el('a', { className: 'au-btn negative', text: 'Sign out', attributes: { href: logoutUrl } }));
  return links;
}

function render() {
  const app = document.querySelector('#category-entry-app');
  if (!app || !scoresData) return;

  app.replaceChildren();
  const ranks = computeRanks(scoresData.teams || []);

  app.append(renderHeader());
  app.append(renderTeamPanel(ranks));
  app.append(renderActionPanel(ranks));
  app.append(renderStatusRow());
  app.append(renderActivitySection());
  app.append(renderFooterLinks());
}

async function refresh() {
  try {
    await loadAll();
    render();
  } catch (error) {
    setStatus(error.message);
  }
}

async function handleClick(event) {
  if (event.target.closest('#category-refresh')) {
    event.preventDefault();
    await refresh();
    setStatus(`Refreshed: ${formatUpdatedAt(scoresData?.updatedAt)}`);
    return;
  }

  if (event.target.closest('#category-activity-toggle')) {
    activityLogOpen = !activityLogOpen;
    render();
    return;
  }

  const actionEl = event.target.closest('button[data-action]');
  if (!actionEl) return;

  try {
    if (actionEl.dataset.action === 'select-team') {
      selectedTeamId = actionEl.dataset.teamId;
      render();
      return;
    }

    if (actionEl.dataset.action === 'award-category') {
      if (actionEl.hasAttribute('disabled')) return;
      const teamId = actionEl.dataset.teamId;
      const categoryId = actionEl.dataset.categoryId;
      const awardPoints = actionEl.dataset.awardPoints || '';
      actionEl.setAttribute('disabled', 'disabled');
      try {
        const awardQuery = awardPoints === '' ? '' : `&awardPoints=${encodeURIComponent(awardPoints)}`;
        const saved = await postAction(`action=award-category&team=${encodeURIComponent(teamId)}&category=${encodeURIComponent(categoryId)}${awardQuery}`);
        scoresData = saved;
        // Re-fetch audit so cap counts stay accurate.
        try {
          const audit = await fetchJson('action=audit');
          auditEntries = Array.isArray(audit?.entries) ? audit.entries : [];
        } catch { /* non-fatal */ }
        const team = (saved.teams || []).find((t) => t.id === teamId);
        const cat = (categoriesData?.categories || []).find((c) => c.id === categoryId);
        const pts = awardPoints === '' ? Number(cat?.points || 0) : Number(awardPoints);
        const sign = pts > 0 ? '+' : '';
        setStatus(`${team?.name}: ${cat?.name} ${sign}${pts} saved. New score ${team?.score}.`);
        render();
      } catch (error) {
        setStatus(error.message);
        actionEl.removeAttribute('disabled');
      }
    }
  } catch (error) {
    setStatus(error.message);
  }
}

async function init() {
  try {
    await loadAll();
    render();
  } catch (error) {
    const app = document.querySelector('#category-entry-app');
    if (app) {
      app.innerHTML = '';
      app.append(el('p', { className: 'status-text', text: error.message }));
    }
    return;
  }

  document.addEventListener('click', handleClick);

  setInterval(async () => {
    const activeTag = document.activeElement?.tagName;
    if (activeTag === 'INPUT' || activeTag === 'TEXTAREA') return;
    await refresh();
  }, categoryPollIntervalMs);
}

init();
