// Filename: app.js
// Revision : 1.18.0
// Description : Frontend logic for CVC Scoreboard. Handles score display,
//               admin controls, polling, team/title renaming, and dynamic grid layout.
//               Shared across all scoreboard instances (root, collide, youth, frontlines).
// Author : Jason Lamb (with help from Claude Code)
// Created Date : 2026-03-24
// Modified Date : 2026-06-08
// Changelog :
// 1.0.0 Initial PHP release, converted from Node.js/Express
// 1.1.0 Fixed API URL paths to use relative query params instead of REST-style paths
// 1.2.0 Added rename team and update scoreboard title features
// 1.3.0 Increased admin poll to 10s; skip re-render when an input is focused
// 1.4.0 Added dynamic viewer grid columns to support variable team counts
// 1.5.0 Fixed Safari mobile scrolling; score font now scales by viewport height
// 1.6.0 Score font scaling now kicks in at 3 digits (was 4) to prevent overflow on large scores
// 1.7.0 Added font size reduction for 9-digit (2.5vw) and 10+ digit (2vw) scores to prevent box overflow
// 1.8.0 Use CSS custom property --viewer-cols so mobile media query can override column count
// 1.9.0 Show logged-in user, logout/manage-users buttons, and Recent Activity section in admin
// 1.10.0 Persist activity log open state across auto-refreshes
// 1.11.0 Move admin menu controls to page bottom; add quick-entry link and clickable viewer header
// 1.12.0 Add signed-in user change-password footer link
// 1.13.0 Add changelog footer link and admin-only all-scoreboards navigation link
// 1.14.0 Sort admin teams A-Z and viewer teams by score for GitHub issues 4 and 6
// 1.15.0 Match full-admin quick buttons to quick-entry positive scoring buttons
// 1.16.0 Show 1st/2nd/3rd place rank badge on viewer and admin team cards
// 1.17.0 Add sort-order note to admin (A-Z) and viewer (by score) pages; expose --viewer-rows custom property so responsive breakpoints can override grid row sizing
// 1.18.0 Show Scoreboards footer button to all signed-in users (was admin-only)

const quickValues = [1, 10, 100, 1000];
const viewerPollIntervalMs = 2000;
const adminPollIntervalMs = 10000;
let currentData = null;
let activityLogOpen = false;

async function fetchScores() {
  const response = await fetch('api.php?action=scores');
  if (!response.ok) {
    throw new Error('Unable to load scores.');
  }
  currentData = await response.json();
  return currentData;
}

async function postJson(url, payload = {}) {
  const response = await fetch(url, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json'
    },
    body: JSON.stringify(payload)
  });

  if (!response.ok) {
    const { error } = await response.json().catch(() => ({ error: 'Update failed.' }));
    throw new Error(error || 'Update failed.');
  }

  currentData = await response.json();
  return currentData;
}

function formatUpdatedAt(updatedAt) {
  if (!updatedAt) {
    return 'Waiting for first score update';
  }

  return new Intl.DateTimeFormat(undefined, {
    dateStyle: 'medium',
    timeStyle: 'medium'
  }).format(new Date(updatedAt));
}

function scoreFontStyle(score) {
  const len = String(Math.abs(score)).length;
  if (len <= 2) return '';
  const vw = len === 3 ? 9 : len === 4 ? 7 : len === 5 ? 5 : len <= 8 ? 4 : len === 9 ? 2.5 : 2;
  return `style="font-size: clamp(1.5rem, min(${vw}vw, ${vw}vh), 7rem);"`;
}

function sortTeamsByName(teams) {
  return [...teams].sort((a, b) => String(a.name || '').localeCompare(String(b.name || ''), undefined, {
    sensitivity: 'base',
    numeric: true
  }));
}

function sortTeamsByScore(teams) {
  return [...teams].sort((a, b) => {
    const scoreDifference = Number(b.score || 0) - Number(a.score || 0);
    if (scoreDifference !== 0) {
      return scoreDifference;
    }

    return String(a.name || '').localeCompare(String(b.name || ''), undefined, {
      sensitivity: 'base',
      numeric: true
    });
  });
}

function ordinalSuffix(n) {
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

// Returns Map<teamId, rank> using standard competition ranking (tied scores share rank: 1, 1, 3).
function computeRanks(teams) {
  const sorted = sortTeamsByScore(teams);
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

function rankBadgeHtml(rank) {
  const medalClass = rank === 1 ? 'rank-gold' : rank === 2 ? 'rank-silver' : rank === 3 ? 'rank-bronze' : 'rank-plain';
  return `<div class="rank-badge ${medalClass}" aria-label="Rank ${ordinalSuffix(rank)}">${ordinalSuffix(rank)}</div>`;
}

function createQuickButtons(teamId) {
  return quickValues
    .map((value) => `
      <button class="positive" type="button" data-action="adjust" data-team-id="${teamId}" data-amount="${value}">+${value}</button>
    `)
    .join('');
}

function createAdminCard(team, rank) {
  return `
    <section class="team-card" style="--team-color: ${team.color};">
      ${rankBadgeHtml(rank)}
      <div class="team-title">
        <div class="team-name">${team.name}</div>
        <div class="updated-at">Tap buttons to update</div>
      </div>
      <div class="score-box">
        <div class="score-value" ${scoreFontStyle(team.score)}>${team.score}</div>
      </div>
      <div class="button-grid">
        ${createQuickButtons(team.id)}
      </div>
      <div class="updated-at score-note">Use custom amount for negative scoring.</div>
      <form class="custom-controls" data-action="custom-form" data-team-id="${team.id}">
        <input name="customAmount" type="number" inputmode="numeric" step="1" placeholder="Custom +/- amount" aria-label="Custom amount for ${team.name}" />
        <button class="secondary" type="submit">Apply</button>
      </form>
      <form class="custom-controls" data-action="rename-form" data-team-id="${team.id}">
        <input name="teamName" type="text" value="${team.name}" aria-label="Rename ${team.name}" />
        <button class="secondary" type="submit">Rename</button>
      </form>
      <div class="card-footer">
        <button class="warning" type="button" data-action="reset-team" data-team-id="${team.id}">Reset Team</button>
      </div>
    </section>
  `;
}

function createViewerCard(team, rank) {
  return `
    <section class="team-card viewer-card" style="--team-color: ${team.color};">
      ${rankBadgeHtml(rank)}
      <div class="team-title">
        <div class="team-name">${team.name}</div>
      </div>
      <div class="score-box">
        <div class="score-value" ${scoreFontStyle(team.score)}>${team.score}</div>
      </div>
    </section>
  `;
}

function renderSharedHeader(data, pageType) {
  const title = data.title || 'CVC Youth Scoreboard';
  const updatedLabel = formatUpdatedAt(data.updatedAt);
  const updateMode = pageType === 'admin' ? 'Admin controls' : 'Viewer mode';
  const username = pageType === 'admin' ? document.body.dataset.username : null;

  return `
    <div>
      <p>${updateMode}${username ? ` &mdash; ${username}` : ''}</p>
      <h1>${title}</h1>
      <p class="updated-at">Last updated: ${updatedLabel}</p>
    </div>
  `;
}

async function renderAdmin(data) {
  const app = document.querySelector('#app');
  const role       = document.body.dataset.role || '';
  const logoutUrl  = document.body.dataset.logoutUrl || './logout.php';
  const adminUrl   = document.body.dataset.adminUrl || './admin-users.php';
  const passwordUrl = document.body.dataset.passwordUrl || './change-password.php';
  const changelogUrl = document.body.dataset.changelogUrl || './changelog.php';
  const scoreboardsUrl = document.body.dataset.scoreboardsUrl || './scoreboards.php';

  app.innerHTML = `
    <div class="page-shell">
      <header class="page-header">
        ${renderSharedHeader(data, 'admin')}
      </header>
      <p class="status-text" id="status-text">Scores save to JSON automatically after each change.</p>
      <p class="sort-note">Teams are sorted A-Z by name.</p>
      <main class="team-grid">
        ${(() => {
          const ranks = computeRanks(data.teams);
          return sortTeamsByName(data.teams).map((team) => createAdminCard(team, ranks.get(team.id))).join('');
        })()}
      </main>
      <section id="activity-section" style="margin-top:1.5rem">
        <button class="secondary" id="activity-toggle" type="button">Show Recent Activity</button>
        <div id="activity-log" class="hidden" style="margin-top:1rem"></div>
      </section>
      <section class="admin-footer-actions" aria-label="Scoreboard admin actions">
        <form class="admin-title-form" data-action="title-form">
          <input name="pageTitle" type="text" value="${data.title}" aria-label="Scoreboard title" />
          <button class="secondary" type="submit">Update Title</button>
        </form>
        <a class="au-btn" href="enter-scores-quick.php">Quick Entry</a>
        <button class="secondary" id="open-viewer-button" type="button">Open Viewer Page</button>
        <button class="warning" id="reset-all-button" type="button">Reset All Teams</button>
        ${role === 'admin' ? `<a class="au-btn" href="${adminUrl}">Manage Users</a>` : ''}
        <a class="au-btn" href="${scoreboardsUrl}">Scoreboards</a>
        <a class="au-btn" href="${changelogUrl}">Changelog</a>
        <a class="au-btn" href="${passwordUrl}">Change Password</a>
        <a class="au-btn" href="${logoutUrl}">Sign Out</a>
      </section>
    </div>
  `;
  await syncActivityLog();
}

async function syncActivityLog() {
  const log = document.querySelector('#activity-log');
  const btn = document.querySelector('#activity-toggle');
  if (!log || !btn) return;
  if (activityLogOpen) {
    log.classList.remove('hidden');
    btn.textContent = 'Hide Recent Activity';
    await loadActivityLog();
  } else {
    log.classList.add('hidden');
    btn.textContent = 'Show Recent Activity';
  }
}

async function loadActivityLog() {
  const log = document.querySelector('#activity-log');
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
            ${entries.map(e => `
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

function renderViewer(data) {
  const app = document.querySelector('#app');
  const teamCount = data.teams.length;
  const cols = teamCount <= 4 ? 2 : teamCount <= 6 ? 3 : 4;
  const rows = Math.ceil(teamCount / cols);
  const gridStyle = `--viewer-cols: ${cols}; --viewer-rows: ${rows};`;

  app.innerHTML = `
    <div class="viewer-page">
      <header class="page-header viewer-admin-link" id="viewer-admin-header" role="button" tabindex="0" title="Open score entry">
        ${renderSharedHeader(data, 'viewer')}
        <div class="header-actions">
          <div class="updated-at">Auto-refresh every ${viewerPollIntervalMs / 1000} seconds</div>
          <div class="updated-at">Teams sorted by score (1st, 2nd, 3rd...)</div>
        </div>
      </header>
      <main class="viewer-grid" style="${gridStyle}">
        ${(() => {
          const ranks = computeRanks(data.teams);
          return sortTeamsByScore(data.teams).map((team) => createViewerCard(team, ranks.get(team.id))).join('');
        })()}
      </main>
    </div>
  `;
}

function setStatus(message) {
  const statusText = document.querySelector('#status-text');
  if (statusText) {
    statusText.textContent = message;
  }
}

async function refreshPage(pageType) {
  const data = await fetchScores();
  if (pageType === 'viewer') {
    renderViewer(data);
  } else {
    renderAdmin(data);
  }
}

async function handleAdminAction(event) {
  const actionButton = event.target.closest('button[data-action]');
  const form = event.target.closest('form[data-action]');

  try {
    if (actionButton) {
      const teamId = actionButton.dataset.teamId;
      const action = actionButton.dataset.action;

      if (action === 'adjust') {
        await postJson(`api.php?action=update&team=${teamId}`, {
          amount: Number(actionButton.dataset.amount)
        });
      }

      if (action === 'reset-team') {
        await postJson(`api.php?action=reset-team&team=${teamId}`);
      }

      renderAdmin(currentData);
      setStatus(`Saved at ${formatUpdatedAt(currentData.updatedAt)}`);
      return;
    }

    if (event.target.id === 'open-viewer-button') {
      window.open('index.php', '_blank', 'noopener');
      return;
    }

    if (event.target.id === 'activity-toggle') {
      activityLogOpen = !activityLogOpen;
      await syncActivityLog();
      return;
    }

    if (event.target.id === 'reset-all-button') {
      await postJson('api.php?action=reset-all');
      renderAdmin(currentData);
      setStatus(`All teams reset at ${formatUpdatedAt(currentData.updatedAt)}`);
      return;
    }

    if (form && event.type === 'submit') {
      event.preventDefault();
      const teamId = form.dataset.teamId;
      const formData = new FormData(form);

      if (form.dataset.action === 'title-form') {
        const title = formData.get('pageTitle').trim();
        if (!title) {
          setStatus('Title cannot be empty.');
          return;
        }
        await postJson('api.php?action=rename-title', { title });
        renderAdmin(currentData);
        setStatus(`Title updated at ${formatUpdatedAt(currentData.updatedAt)}`);
        return;
      }

      if (form.dataset.action === 'rename-form') {
        const name = formData.get('teamName').trim();
        if (!name) {
          setStatus('Team name cannot be empty.');
          return;
        }
        await postJson(`api.php?action=rename-team&team=${teamId}`, { name });
        renderAdmin(currentData);
        setStatus(`Team renamed at ${formatUpdatedAt(currentData.updatedAt)}`);
        return;
      }

      const amount = Number(formData.get('customAmount'));
      if (!Number.isFinite(amount) || amount === 0) {
        setStatus('Enter a positive or negative number before clicking Apply.');
        return;
      }

      await postJson(`api.php?action=update&team=${teamId}`, { amount });
      renderAdmin(currentData);
      setStatus(`Custom score saved at ${formatUpdatedAt(currentData.updatedAt)}`);
    }
  } catch (error) {
    setStatus(error.message);
  }
}

function handleViewerAction(event) {
  const header = event.target.closest('#viewer-admin-header');
  if (!header) {
    return;
  }

  if (event.type === 'keydown' && event.key !== 'Enter' && event.key !== ' ') {
    return;
  }

  event.preventDefault();
  window.open('enter-scores.php', '_blank', 'noopener');
}

async function init() {
  const pageType = document.body.dataset.pageType;
  await refreshPage(pageType);

  if (pageType === 'admin') {
    document.addEventListener('click', handleAdminAction);
    document.addEventListener('submit', handleAdminAction);
  } else {
    document.addEventListener('click', handleViewerAction);
    document.addEventListener('keydown', handleViewerAction);
  }

  const pollIntervalMs = pageType === 'viewer' ? viewerPollIntervalMs : adminPollIntervalMs;
  setInterval(async () => {
    try {
      const activeTag = document.activeElement?.tagName;
      const inputFocused = activeTag === 'INPUT' || activeTag === 'TEXTAREA';

      if (pageType === 'admin' && inputFocused) {
        return;
      }

      await refreshPage(pageType);
      if (pageType === 'admin') {
        setStatus(`Synced at ${formatUpdatedAt(currentData.updatedAt)}`);
      }
    } catch {
      if (pageType === 'admin') {
        setStatus('Unable to refresh scores right now.');
      }
    }
  }, pollIntervalMs);
}

init().catch((error) => {
  const app = document.querySelector('#app');
  app.innerHTML = `<div class="page-shell"><p>${error.message}</p></div>`;
});
