// Filename: app.js
// Revision : 1.33.0
// Description : Frontend logic for CVC Scoreboard. Handles score display,
//               admin controls, polling, team/title renaming, and dynamic grid layout.
//               Shared across all scoreboard instances (root, collide, youth, frontlines).
// Author : Jason Lamb (with help from Claude Code)
// Created Date : 2026-03-24
// Modified Date : 2026-06-17
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
// 1.19.0 Add optional Frontlines roster links to viewer/admin pages
// 1.20.0 Rename per-team reset button to "Reset Score to Zero" for clarity
// 1.21.0 Add View Scoreboard + Quick Score links to admin top banner; rename footer "Open Viewer Page" to "View Scoreboard"
// 1.22.0 Add per-card Remove Team button and Add Team form on full admin
// 1.23.0 Rename default fallback title to Live Scoreboard
// 1.24.0 Add visible labels and Enter-submit helper text to the Add Team form
// 1.25.0 Custom amount input now accepts a minus sign on mobile (text input with numeric inputmode and signed pattern)
// 1.26.0 Add green "+ Add Score" / red "− Subtract Score" toggle above custom-amount input; input shows live -N when Subtract mode is active so iOS users can subtract without typing a minus sign
// 1.27.0 Move per-card mode toggle above the +1/+10/+100/+1000 quick buttons and flip those buttons to red -1/-10/-100/-1000 when Subtract mode is active (matches quick-entry behavior)
// 1.28.0 Render Enter Categories and Edit Categories footer links when the corresponding URL data attributes are present (Frontlines-only categories feature)
// 1.29.0 Viewer page can hide scores (and rank badges) for the bottom half of teams when body has data-hide-bottom-scores="true" (Frontlines opt-in to protect losing-team morale)
// 1.30.0 Replaced hide-bottom-scores with hide-bottom-teams: viewer slices the team list to top ceil(n/2) when opt-in is on, and the grid recalculates cols/rows from the visible count so the remaining cards fill the screen
// 1.30.1 Tie-aware boundary: if teams beyond the halfway mark are tied with the lowest visible score, include them too (so the bottom of a tie group is never split). Hidden-count note is only shown when teams are actually hidden.
// 1.31.0 Added Reset Score to Zero confirm dialog with current score in the prompt; strengthened Remove Team to a two-step confirm with PERMANENTLY DELETE wording. Viewer hidden-count note clarified to "Showing X of Y teams — top half by score".
// 1.32.0 renderAdmin and renderViewer now preserve window.scrollY across re-renders, so the 10s admin poll and 2s viewer poll no longer scroll the user back to the top (noticeable on mobile while reading the Recent Activity panel). Update mechanism (poll interval, data refresh) is unchanged.
// 1.33.0 sortTeamsByScore tiebreaker now uses team.score_changed_at (older = ranked higher) before falling back to alphabetical. Added two-step confirm on Reset All Teams.

const quickValues = [1, 10, 100, 1000];
const viewerPollIntervalMs = 2000;
const adminPollIntervalMs = 10000;
let currentData = null;
let activityLogOpen = false;
const customScoreModes = new Map();

function getCustomMode(teamId) {
  return customScoreModes.get(teamId) === 'remove' ? 'remove' : 'add';
}

function syncSignedInput(input, mode) {
  if (!input) return;
  const digits = (input.value || '').replace(/[^0-9]/g, '');
  input.value = digits === '' ? '' : (mode === 'remove' ? '-' + digits : digits);
}

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

    // First tiebreaker: team that reached this score earlier ranks higher
    // (older score_changed_at first). Missing timestamp sorts above any real
    // timestamp, so existing teams without the field stay where they are
    // until their score next changes.
    const aTimestamp = String(a.score_changed_at || '');
    const bTimestamp = String(b.score_changed_at || '');
    if (aTimestamp !== bTimestamp) {
      return aTimestamp.localeCompare(bTimestamp);
    }

    // Final fallback: alphabetical (e.g., after a Reset All applied at the
    // same instant to every team).
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

function createQuickButtons(teamId, mode) {
  return quickValues
    .map((value) => {
      const signed = mode === 'remove' ? -value : value;
      const cls = signed > 0 ? 'positive' : 'negative';
      const display = signed > 0 ? `+${signed}` : String(signed);
      return `
      <button class="${cls}" type="button" data-action="adjust" data-team-id="${teamId}" data-amount="${signed}">${display}</button>
    `;
    })
    .join('');
}

function createAdminCard(team, rank) {
  const mode = getCustomMode(team.id);
  const addActive = mode === 'add' ? ' is-active' : '';
  const removeActive = mode === 'remove' ? ' is-active' : '';
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
      <div class="custom-mode-row">
        <button type="button" class="positive custom-mode-button${addActive}" data-action="set-custom-mode" data-team-id="${team.id}" data-mode="add">+ Add Score</button>
        <button type="button" class="negative custom-mode-button${removeActive}" data-action="set-custom-mode" data-team-id="${team.id}" data-mode="remove">&minus; Subtract Score</button>
      </div>
      <div class="button-grid">
        ${createQuickButtons(team.id, mode)}
      </div>
      <form class="custom-controls" data-action="custom-form" data-team-id="${team.id}">
        <div class="custom-input-row">
          <input name="customAmount" type="text" inputmode="numeric" pattern="-?[0-9]*" placeholder="Amount" aria-label="Custom amount for ${team.name}" />
          <button class="secondary" type="submit">Apply</button>
        </div>
      </form>
      <form class="custom-controls" data-action="rename-form" data-team-id="${team.id}">
        <input name="teamName" type="text" value="${team.name}" aria-label="Rename ${team.name}" />
        <button class="secondary" type="submit">Rename</button>
      </form>
      <div class="card-footer">
        <button class="warning" type="button" data-action="reset-team" data-team-id="${team.id}">Reset Score to Zero</button>
        <button class="negative" type="button" data-action="remove-team" data-team-id="${team.id}">Remove Team</button>
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
  const title = data.title || 'Live Scoreboard';
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
  const previousScrollY = window.scrollY || window.pageYOffset || 0;
  const role       = document.body.dataset.role || '';
  const logoutUrl  = document.body.dataset.logoutUrl || './logout.php';
  const adminUrl   = document.body.dataset.adminUrl || './admin-users.php';
  const passwordUrl = document.body.dataset.passwordUrl || './change-password.php';
  const changelogUrl = document.body.dataset.changelogUrl || './changelog.php';
  const scoreboardsUrl = document.body.dataset.scoreboardsUrl || './scoreboards.php';
  const rosterUrl = document.body.dataset.rosterUrl || '';
  const editRosterUrl = document.body.dataset.editRosterUrl || '';
  const categoryEntryUrl = document.body.dataset.categoryEntryUrl || '';
  const editCategoriesUrl = document.body.dataset.editCategoriesUrl || '';

  app.innerHTML = `
    <div class="page-shell">
      <header class="page-header">
        ${renderSharedHeader(data, 'admin')}
        <div class="header-actions">
          <a class="au-btn" href="index.php" target="_blank" rel="noopener">View Scoreboard</a>
          <a class="au-btn" href="enter-scores-quick.php">Quick Score</a>
        </div>
      </header>
      <p class="status-text" id="status-text">Scores save to JSON automatically after each change.</p>
      <p class="sort-note">Teams are sorted A-Z by name.</p>
      <main class="team-grid">
        ${(() => {
          const ranks = computeRanks(data.teams);
          return sortTeamsByName(data.teams).map((team) => createAdminCard(team, ranks.get(team.id))).join('');
        })()}
      </main>
      <section class="admin-add-team" aria-label="Add a team">
        <form class="add-team-form" data-action="add-team-form">
          <label class="form-field">
            <span>New Team Name</span>
            <input name="newTeamName" type="text" placeholder="Team name" required />
          </label>
          <label class="form-field color-field">
            <span>Color</span>
            <input name="newTeamColor" type="color" value="#64748b" />
          </label>
          <button class="positive add-team-button" type="submit">
            <span>Add Team</span>
            <small>or press Enter</small>
          </button>
        </form>
      </section>
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
        ${categoryEntryUrl ? `<a class="au-btn" href="${categoryEntryUrl}">Enter Categories</a>` : ''}
        <button class="secondary" id="open-viewer-button" type="button">View Scoreboard</button>
        ${rosterUrl ? `<a class="au-btn" href="${rosterUrl}">Roster</a>` : ''}
        ${editRosterUrl ? `<a class="au-btn" href="${editRosterUrl}">Edit Roster</a>` : ''}
        ${editCategoriesUrl ? `<a class="au-btn" href="${editCategoriesUrl}">Edit Categories</a>` : ''}
        <button class="warning" id="reset-all-button" type="button">Reset All Teams</button>
        ${role === 'admin' ? `<a class="au-btn" href="${adminUrl}">Manage Users</a>` : ''}
        <a class="au-btn" href="${scoreboardsUrl}">Scoreboards</a>
        <a class="au-btn" href="${changelogUrl}">Changelog</a>
        <a class="au-btn" href="${passwordUrl}">Change Password</a>
        <a class="au-btn" href="${logoutUrl}">Sign Out</a>
      </section>
    </div>
  `;
  if (previousScrollY > 0) {
    // Restore scroll position so the 10s admin poll does not yank the user
    // back to the top of the page (especially noticeable on mobile while
    // reading the Recent Activity panel).
    window.scrollTo(0, previousScrollY);
  }
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
  const previousScrollY = window.scrollY || window.pageYOffset || 0;
  const rosterUrl = document.body.dataset.rosterUrl || '';
  const hideBottomTeams = document.body.dataset.hideBottomTeams === 'true';
  const ranks = computeRanks(data.teams);
  const sorted = sortTeamsByScore(data.teams);
  let visibleTeams = sorted;
  if (hideBottomTeams && sorted.length > 0) {
    // Take the top ceil(n/2) teams, then extend the cut to include any teams
    // tied with the lowest visible score so we never split a tie group.
    const halfwayIdx = Math.ceil(sorted.length / 2) - 1;
    const cutoffScore = Number(sorted[halfwayIdx]?.score ?? 0);
    visibleTeams = sorted.filter((t) => Number(t.score ?? 0) >= cutoffScore);
  }
  const visibleCount = visibleTeams.length;
  const cols = visibleCount <= 4 ? 2 : visibleCount <= 6 ? 3 : 4;
  const rows = Math.ceil(visibleCount / cols);
  const gridStyle = `--viewer-cols: ${cols}; --viewer-rows: ${rows};`;
  const hiddenNote = (hideBottomTeams && visibleCount < sorted.length)
    ? `<div class="updated-at">Showing ${visibleCount} of ${sorted.length} teams — top half by score</div>`
    : '';

  app.innerHTML = `
    <div class="viewer-page">
      <header class="page-header viewer-admin-link" id="viewer-admin-header" role="button" tabindex="0" title="Open score entry">
        ${renderSharedHeader(data, 'viewer')}
        <div class="header-actions">
          <div class="updated-at">Auto-refresh every ${viewerPollIntervalMs / 1000} seconds</div>
          <div class="updated-at">Teams sorted by score (1st, 2nd, 3rd...)</div>
          ${hiddenNote}
          ${rosterUrl ? `<a class="au-btn" href="${rosterUrl}">Roster</a>` : ''}
        </div>
      </header>
      <main class="viewer-grid" style="${gridStyle}">
        ${visibleTeams.map((team) => createViewerCard(team, ranks.get(team.id))).join('')}
      </main>
    </div>
  `;
  if (previousScrollY > 0) {
    window.scrollTo(0, previousScrollY);
  }
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

      if (action === 'set-custom-mode') {
        const newMode = actionButton.dataset.mode === 'remove' ? 'remove' : 'add';
        customScoreModes.set(teamId, newMode);
        const card = actionButton.closest('.team-card');
        if (card) {
          card.querySelectorAll('.custom-mode-button').forEach((b) => {
            b.classList.toggle('is-active', b.dataset.mode === newMode);
          });
          card.querySelectorAll('.button-grid button[data-action="adjust"]').forEach((b) => {
            const magnitude = Math.abs(Number(b.dataset.amount) || 0);
            const signed = newMode === 'remove' ? -magnitude : magnitude;
            b.dataset.amount = String(signed);
            b.textContent = signed > 0 ? `+${signed}` : String(signed);
            b.classList.toggle('positive', signed > 0);
            b.classList.toggle('negative', signed < 0);
          });
          const modeInput = card.querySelector('input[name="customAmount"]');
          syncSignedInput(modeInput, newMode);
          if (modeInput) modeInput.focus();
        }
        return;
      }

      if (action === 'adjust') {
        await postJson(`api.php?action=update&team=${teamId}`, {
          amount: Number(actionButton.dataset.amount)
        });
      }

      if (action === 'reset-team') {
        const resetTeam = currentData?.teams?.find((t) => t.id === teamId);
        const resetTeamName = resetTeam?.name || 'this team';
        const resetTeamScore = resetTeam?.score ?? 0;
        if (!window.confirm(`Reset ${resetTeamName} score from ${resetTeamScore} to 0?`)) {
          return;
        }
        await postJson(`api.php?action=reset-team&team=${teamId}`);
      }

      if (action === 'remove-team') {
        const team = currentData?.teams?.find((t) => t.id === teamId);
        const teamName = team?.name || 'this team';
        if (!window.confirm(`PERMANENTLY DELETE the "${teamName}" team and ALL of its score data?\n\nThis cannot be undone.`)) {
          return;
        }
        if (!window.confirm(`Are you absolutely sure you want to delete "${teamName}"?`)) {
          return;
        }
        await postJson(`api.php?action=remove-team&team=${teamId}`);
        renderAdmin(currentData);
        setStatus(`${teamName} removed at ${formatUpdatedAt(currentData.updatedAt)}`);
        return;
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
      if (!window.confirm('RESET EVERY TEAM\'s score to 0?\n\nThis affects all teams on this scoreboard. The current scores are backed up to data/scores.previous.json on the server so a mistaken reset can be recovered.')) {
        return;
      }
      if (!window.confirm('Are you absolutely sure? This reset cannot be undone from the UI.')) {
        return;
      }
      await postJson('api.php?action=reset-all');
      renderAdmin(currentData);
      setStatus(`All teams reset at ${formatUpdatedAt(currentData.updatedAt)}`);
      return;
    }

    if (form && event.type === 'submit') {
      event.preventDefault();
      const teamId = form.dataset.teamId;
      const formData = new FormData(form);

      if (form.dataset.action === 'add-team-form') {
        const name  = String(formData.get('newTeamName') || '').trim();
        const color = String(formData.get('newTeamColor') || '#64748b');
        if (!name) {
          setStatus('New team name cannot be empty.');
          return;
        }
        await postJson('api.php?action=add-team', { name, color });
        renderAdmin(currentData);
        setStatus(`Team "${name}" added at ${formatUpdatedAt(currentData.updatedAt)}`);
        return;
      }

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

      const rawAmount = String(formData.get('customAmount') || '');
      const digits = rawAmount.replace(/[^0-9]/g, '');
      const magnitude = digits === '' ? 0 : parseInt(digits, 10);
      if (!Number.isFinite(magnitude) || magnitude === 0) {
        setStatus('Enter a number greater than 0 before clicking Apply.');
        return;
      }
      const mode = getCustomMode(teamId);
      const amount = mode === 'remove' ? -magnitude : magnitude;

      await postJson(`api.php?action=update&team=${teamId}`, { amount });
      renderAdmin(currentData);
      setStatus(`${mode === 'remove' ? 'Subtracted' : 'Added'} ${magnitude} at ${formatUpdatedAt(currentData.updatedAt)}`);
    }
  } catch (error) {
    setStatus(error.message);
  }
}

function handleViewerAction(event) {
  if (event.target.closest('a')) {
    return;
  }

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
    document.addEventListener('input', (event) => {
      const input = event.target.closest('input[name="customAmount"]');
      if (!input) return;
      const form = input.closest('form[data-action="custom-form"]');
      if (!form) return;
      syncSignedInput(input, getCustomMode(form.dataset.teamId));
    });
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
