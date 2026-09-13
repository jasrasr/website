// Filename: collide-extras.js
// Revision : 1.0.0
// Description : Collide-only UI layer for team mottos and walk-up songs.
// Author : Jason Lamb (with help from ChatGPT)
// Created Date : 2026-09-13
// Modified Date : 2026-09-13
// Changelog :
// 1.0.0 Add per-team motto display, walk-up song playback, and admin upload controls

const collideExtras = {
  decorateQueued: false,
  data: null,
  activeAudio: null,
  activeButton: null
};

function collideEscapeHtml(value) {
  return String(value ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');
}

function collideSortTeamsByName(teams) {
  return [...teams].sort((a, b) => String(a.name || '').localeCompare(String(b.name || ''), undefined, {
    sensitivity: 'base',
    numeric: true
  }));
}

function collideSortTeamsByScore(teams) {
  return [...teams].sort((a, b) => {
    const scoreDifference = Number(b.score || 0) - Number(a.score || 0);
    if (scoreDifference !== 0) return scoreDifference;

    const aTimestamp = String(a.score_changed_at || '');
    const bTimestamp = String(b.score_changed_at || '');
    if (aTimestamp !== bTimestamp) return aTimestamp.localeCompare(bTimestamp);

    return String(a.name || '').localeCompare(String(b.name || ''), undefined, {
      sensitivity: 'base',
      numeric: true
    });
  });
}

async function collideFetchScores() {
  const response = await fetch('api.php?action=scores', { cache: 'no-store' });
  if (!response.ok) throw new Error('Unable to load Collide team metadata.');
  collideExtras.data = await response.json();
  return collideExtras.data;
}

function collideTeamSignature(team, mode) {
  const song = team.walkup_song || {};
  return [
    mode,
    team.id || '',
    team.name || '',
    team.motto || '',
    song.file || '',
    song.uploaded_at || ''
  ].join('|');
}

function collideSongLabel(team) {
  const song = team.walkup_song || null;
  if (!song || !song.file) return 'No walk-up song uploaded';
  return song.original_name || song.file;
}

function collideViewerExtraHtml(team) {
  const motto = String(team.motto || '').trim();
  const song = team.walkup_song || null;
  return `
    ${motto ? `<div class="collide-team-motto">${collideEscapeHtml(motto)}</div>` : ''}
    ${song && song.url ? `
      <button class="secondary collide-play-button" type="button" data-collide-play="${collideEscapeHtml(team.id)}">
        ▶ Walk-up song
      </button>
    ` : ''}
  `;
}

function collideAdminExtraHtml(team) {
  const motto = String(team.motto || '').trim();
  const song = team.walkup_song || null;
  return `
    <section class="collide-meta-panel" aria-label="Collide extras for ${collideEscapeHtml(team.name)}">
      <form class="collide-meta-form" enctype="multipart/form-data">
        <input type="hidden" name="team_id" value="${collideEscapeHtml(team.id)}" />
        <label class="collide-meta-field">
          <span>Subtitle / motto</span>
          <textarea name="motto" maxlength="160" rows="2" placeholder="Example: Built different, hopefully not from drywall.">${collideEscapeHtml(motto)}</textarea>
        </label>
        <label class="collide-meta-field">
          <span>Walk-up song</span>
          <input type="file" name="walkup_audio" accept="audio/mpeg,audio/mp4,audio/aac,audio/wav,audio/ogg,audio/webm,.mp3,.m4a,.aac,.wav,.ogg,.webm" />
        </label>
        <div class="collide-meta-actions">
          <button class="secondary" type="submit">Save Motto / Upload Song</button>
          ${song && song.url ? `<button class="secondary" type="button" data-collide-play="${collideEscapeHtml(team.id)}">Play</button>` : ''}
          ${song && song.file ? `<button class="warning" type="button" data-collide-delete-song="${collideEscapeHtml(team.id)}">Remove Song</button>` : ''}
        </div>
        <div class="collide-song-status">${collideEscapeHtml(collideSongLabel(team))}</div>
      </form>
    </section>
  `;
}

function collideDecorateAdmin(data) {
  const cards = Array.from(document.querySelectorAll('.team-grid .team-card'));
  const teams = collideSortTeamsByName(data.teams || []);

  cards.forEach((card, index) => {
    const team = teams[index];
    if (!team) return;

    const signature = collideTeamSignature(team, 'admin');
    if (card.dataset.collideMetaSignature === signature && card.querySelector('.collide-meta-panel')) {
      return;
    }

    card.dataset.collideTeamId = team.id;
    card.dataset.collideMetaSignature = signature;
    card.querySelector('.collide-meta-panel')?.remove();

    const footer = card.querySelector('.card-footer');
    if (footer) {
      footer.insertAdjacentHTML('beforebegin', collideAdminExtraHtml(team));
    } else {
      card.insertAdjacentHTML('beforeend', collideAdminExtraHtml(team));
    }
  });
}

function collideDecorateViewer(data) {
  const cards = Array.from(document.querySelectorAll('.viewer-grid .viewer-card'));
  const teams = collideSortTeamsByScore(data.teams || []);

  cards.forEach((card, index) => {
    const team = teams[index];
    if (!team) return;

    const signature = collideTeamSignature(team, 'viewer');
    if (card.dataset.collideMetaSignature === signature && card.querySelector('.collide-viewer-extra')) {
      return;
    }

    card.dataset.collideTeamId = team.id;
    card.dataset.collideMetaSignature = signature;
    card.querySelector('.collide-viewer-extra')?.remove();

    const wrapper = document.createElement('div');
    wrapper.className = 'collide-viewer-extra';
    wrapper.innerHTML = collideViewerExtraHtml(team);
    const scoreBox = card.querySelector('.score-box');
    if (scoreBox) {
      scoreBox.insertAdjacentElement('afterend', wrapper);
    } else {
      card.appendChild(wrapper);
    }
  });
}

async function collideDecorate() {
  const app = document.querySelector('#app');
  if (!app) return;

  const data = await collideFetchScores();
  const pageType = document.body.dataset.pageType;
  if (pageType === 'admin') {
    collideDecorateAdmin(data);
  } else if (pageType === 'viewer') {
    collideDecorateViewer(data);
  }
}

function collideScheduleDecorate() {
  if (collideExtras.decorateQueued) return;
  collideExtras.decorateQueued = true;
  window.requestAnimationFrame(async () => {
    collideExtras.decorateQueued = false;
    try {
      await collideDecorate();
    } catch {
      // The shared app already owns primary error rendering.
    }
  });
}

function collideFindTeam(teamId) {
  return (collideExtras.data?.teams || []).find((team) => team.id === teamId) || null;
}

async function collidePlaySong(teamId, button) {
  const team = collideFindTeam(teamId) || (await collideFetchScores()).teams?.find((candidate) => candidate.id === teamId);
  const song = team?.walkup_song || null;
  if (!song || !song.url) return;

  if (collideExtras.activeAudio && collideExtras.activeAudio.dataset?.teamId === teamId && !collideExtras.activeAudio.paused) {
    collideExtras.activeAudio.pause();
    button.textContent = '▶ Walk-up song';
    return;
  }

  if (collideExtras.activeAudio) {
    collideExtras.activeAudio.pause();
    collideExtras.activeAudio.currentTime = 0;
  }
  if (collideExtras.activeButton) {
    collideExtras.activeButton.textContent = collideExtras.activeButton.dataset.originalLabel || '▶ Walk-up song';
  }

  const audio = new Audio(song.url);
  audio.dataset.teamId = teamId;
  collideExtras.activeAudio = audio;
  collideExtras.activeButton = button;
  button.dataset.originalLabel = button.textContent.trim() || '▶ Walk-up song';
  button.textContent = '⏸ Playing';

  audio.addEventListener('ended', () => {
    button.textContent = button.dataset.originalLabel || '▶ Walk-up song';
  }, { once: true });

  try {
    await audio.play();
  } catch {
    button.textContent = button.dataset.originalLabel || '▶ Walk-up song';
    window.alert('Unable to play this audio file. The browser may not support the format.');
  }
}

async function collideSaveMeta(form) {
  const response = await fetch('team-meta.php?action=save', {
    method: 'POST',
    body: new FormData(form)
  });

  const payload = await response.json().catch(() => ({}));
  if (!response.ok) {
    throw new Error(payload.error || 'Unable to save Collide team metadata.');
  }

  collideExtras.data = payload;
  form.reset();
  collideDecorateAdmin(payload);
  const statusText = document.querySelector('#status-text');
  if (statusText) {
    statusText.textContent = `Collide team details saved at ${new Date().toLocaleTimeString()}.`;
  }
}

async function collideDeleteSong(teamId) {
  const response = await fetch('team-meta.php?action=delete-song', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ team_id: teamId })
  });

  const payload = await response.json().catch(() => ({}));
  if (!response.ok) {
    throw new Error(payload.error || 'Unable to remove walk-up song.');
  }

  collideExtras.data = payload;
  collideDecorateAdmin(payload);
  const statusText = document.querySelector('#status-text');
  if (statusText) {
    statusText.textContent = 'Walk-up song removed.';
  }
}

document.addEventListener('submit', async (event) => {
  const form = event.target.closest('.collide-meta-form');
  if (!form) return;
  event.preventDefault();

  try {
    await collideSaveMeta(form);
  } catch (error) {
    const statusText = document.querySelector('#status-text');
    if (statusText) statusText.textContent = error.message;
  }
});

document.addEventListener('click', async (event) => {
  const playButton = event.target.closest('[data-collide-play]');
  if (playButton) {
    event.preventDefault();
    event.stopPropagation();
    await collidePlaySong(playButton.dataset.collidePlay, playButton);
    return;
  }

  const deleteButton = event.target.closest('[data-collide-delete-song]');
  if (deleteButton) {
    event.preventDefault();
    event.stopPropagation();
    const teamId = deleteButton.dataset.collideDeleteSong;
    const team = collideFindTeam(teamId);
    const teamName = team?.name || 'this team';
    if (!window.confirm(`Remove the walk-up song for ${teamName}?`)) return;

    try {
      await collideDeleteSong(teamId);
    } catch (error) {
      const statusText = document.querySelector('#status-text');
      if (statusText) statusText.textContent = error.message;
    }
  }
});

const collideObserver = new MutationObserver(collideScheduleDecorate);
collideObserver.observe(document.querySelector('#app') || document.body, {
  childList: true,
  subtree: true
});

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', collideScheduleDecorate, { once: true });
} else {
  collideScheduleDecorate();
}
