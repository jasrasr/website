// Filename: collide-extras.js
// Revision : 1.2.0
// Description : Collide-only UI layer for team mottos, walk-up songs, and hurray overlays.
// Author : Jason Lamb (with help from ChatGPT)
// Created Date : 2026-09-13
// Modified Date : 2026-09-13
// Changelog :
// 1.0.0 Add per-team motto display, walk-up song playback, and admin upload controls
// 1.0.1 Clear only the uploaded file field after save so saved motto text remains visible
// 1.1.0 Make public team cards clickable for audio and add per-team placeholder jingles
// 1.2.0 Add separate admin-triggered full-screen hurray overlay

const collidePlaceholderSongByTeamId = {
  'sixth-boys': 'blue-burst',
  'sixth-girls': 'pink-spark',
  'seventh-boys': 'teal-rise',
  'seventh-girls': 'purple-pop',
  'eighth-boys': 'orange-charge',
  'eighth-girls': 'green-run'
};

const collidePlaceholderSongs = {
  'blue-burst': {
    label: 'Blue Burst',
    notes: [[392, 0.11], [494, 0.11], [587, 0.17]]
  },
  'pink-spark': {
    label: 'Pink Spark',
    notes: [[523, 0.1], [659, 0.1], [784, 0.16]]
  },
  'teal-rise': {
    label: 'Teal Rise',
    notes: [[330, 0.12], [392, 0.1], [523, 0.18]]
  },
  'purple-pop': {
    label: 'Purple Pop',
    notes: [[466, 0.09], [622, 0.12], [698, 0.16]]
  },
  'orange-charge': {
    label: 'Orange Charge',
    notes: [[294, 0.11], [440, 0.11], [587, 0.19]]
  },
  'green-run': {
    label: 'Green Run',
    notes: [[349, 0.09], [440, 0.09], [523, 0.09], [659, 0.15]]
  },
  'default-chime': {
    label: 'Default Chime',
    notes: [[440, 0.12], [554, 0.12], [659, 0.18]]
  }
};

const collideExtras = {
  decorateQueued: false,
  data: null,
  active: null,
  hurraySeenEventId: null,
  hurrayTimer: null
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

function collidePlaceholderSongForTeam(team) {
  const key = String(team?.placeholder_song || collidePlaceholderSongByTeamId[team?.id] || 'default-chime');
  return collidePlaceholderSongs[key] || collidePlaceholderSongs['default-chime'];
}

function collideTeamSignature(team, mode) {
  const song = team.walkup_song || {};
  return [
    mode,
    team.id || '',
    team.name || '',
    team.motto || '',
    team.placeholder_song || collidePlaceholderSongByTeamId[team.id] || 'default-chime',
    song.file || '',
    song.uploaded_at || ''
  ].join('|');
}

function collideSongLabel(team) {
  const song = team.walkup_song || null;
  if (song && song.file) return song.original_name || song.file;
  return `Placeholder quick song: ${collidePlaceholderSongForTeam(team).label}`;
}

function collideViewerExtraHtml(team) {
  const motto = String(team.motto || '').trim();
  const song = team.walkup_song || null;
  return `
    ${motto ? `<div class="collide-team-motto">${collideEscapeHtml(motto)}</div>` : ''}
    <div class="collide-song-hint">${song && song.url ? 'Tap team for walk-up song' : 'Tap team for placeholder song'}</div>
  `;
}

function collideAdminExtraHtml(team) {
  const motto = String(team.motto || '').trim();
  const song = team.walkup_song || null;
  const previewLabel = song && song.url ? 'Play Song' : 'Play Placeholder';
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
          <button class="secondary" type="button" data-collide-play="${collideEscapeHtml(team.id)}">${previewLabel}</button>
          <button class="positive" type="button" data-collide-hurray="${collideEscapeHtml(team.id)}">Hurray Screen</button>
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
      card.classList.toggle('collide-is-playing', collideExtras.active?.teamId === team.id);
      return;
    }

    card.dataset.collideTeamId = team.id;
    card.dataset.collideMetaSignature = signature;
    card.classList.add('collide-card-playable');
    card.classList.toggle('collide-is-playing', collideExtras.active?.teamId === team.id);
    card.setAttribute('role', 'button');
    card.setAttribute('tabindex', '0');
    card.setAttribute('aria-label', `${team.name || 'Team'}: play walk-up song`);
    card.querySelector('.collide-viewer-extra')?.remove();

    const wrapper = document.createElement('div');
    wrapper.className = 'collide-viewer-extra';
    wrapper.innerHTML = collideViewerExtraHtml(team);
    const title = card.querySelector('.team-title');
    if (title) {
      title.insertAdjacentElement('afterend', wrapper);
    } else {
      card.insertAdjacentElement('afterbegin', wrapper);
    }
  });
}

function collideEnsureHurrayOverlay() {
  let overlay = document.querySelector('#collide-hurray-overlay');
  if (overlay) return overlay;

  overlay = document.createElement('div');
  overlay.id = 'collide-hurray-overlay';
  overlay.className = 'collide-hurray-overlay hidden';
  overlay.setAttribute('aria-live', 'polite');
  overlay.innerHTML = `
    <div class="collide-hurray-card">
      <div class="collide-hurray-kicker">Team Celebration</div>
      <div class="collide-hurray-message">HURRAY!</div>
      <div class="collide-hurray-team"></div>
    </div>
  `;
  document.body.appendChild(overlay);
  return overlay;
}

function collideShowHurrayOverlay(event) {
  const overlay = collideEnsureHurrayOverlay();
  const message = String(event?.message || 'HURRAY!').trim() || 'HURRAY!';
  const teamName = String(event?.team_name || 'Team').trim() || 'Team';
  const teamColor = /^#[0-9a-fA-F]{6}$/.test(String(event?.team_color || '')) ? event.team_color : '#38bdf8';

  overlay.style.setProperty('--team-color', teamColor);
  overlay.querySelector('.collide-hurray-message').textContent = message;
  overlay.querySelector('.collide-hurray-team').textContent = teamName;

  clearTimeout(collideExtras.hurrayTimer);
  overlay.classList.remove('hidden', 'is-visible');
  void overlay.offsetWidth;
  overlay.classList.add('is-visible');

  collideExtras.hurrayTimer = window.setTimeout(() => {
    overlay.classList.remove('is-visible');
    window.setTimeout(() => overlay.classList.add('hidden'), 450);
  }, 3600);
}

function collideMaybeShowHurray(data) {
  if (document.body.dataset.pageType !== 'viewer') return;

  const event = data?.hurray_event || null;
  const eventId = String(event?.id || '');
  if (eventId === '' || collideExtras.hurraySeenEventId === eventId) return;

  const eventTime = Date.parse(String(event?.created_at || ''));
  if (Number.isFinite(eventTime) && Date.now() - eventTime > 20000) {
    collideExtras.hurraySeenEventId = eventId;
    return;
  }

  collideExtras.hurraySeenEventId = eventId;
  collideShowHurrayOverlay(event);
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
    collideMaybeShowHurray(data);
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

function collideMarkControl(control, playing) {
  if (!control) return;

  control.classList?.toggle('collide-is-playing', playing);

  if (control.tagName === 'BUTTON') {
    if (!control.dataset.originalLabel) {
      control.dataset.originalLabel = control.textContent.trim() || 'Play Song';
    }
    control.textContent = playing ? 'Playing...' : control.dataset.originalLabel;
  }
}

function collideStopActivePlayback() {
  const active = collideExtras.active;
  if (!active) return;

  if (active.audio) {
    active.audio.pause();
    active.audio.currentTime = 0;
  }

  if (active.timer) {
    clearTimeout(active.timer);
  }

  if (active.context) {
    active.context.close().catch(() => {});
  }

  collideMarkControl(active.control, false);
  document
    .querySelectorAll(`.viewer-card[data-collide-team-id="${CSS.escape(active.teamId)}"]`)
    .forEach((card) => card.classList.remove('collide-is-playing'));

  collideExtras.active = null;
}

async function collidePlayUploadedSong(teamId, song, control) {
  const audio = new Audio(song.url);
  audio.dataset.teamId = teamId;

  collideExtras.active = { type: 'upload', teamId, audio, control };
  collideMarkControl(control, true);

  audio.addEventListener('ended', () => {
    if (collideExtras.active?.audio === audio) {
      collideStopActivePlayback();
    }
  }, { once: true });

  try {
    await audio.play();
  } catch {
    collideStopActivePlayback();
    window.alert('Unable to play this audio file. The browser may not support the format.');
  }
}

async function collidePlayPlaceholderSong(teamId, team, control) {
  const AudioContextClass = window.AudioContext || window.webkitAudioContext;
  if (!AudioContextClass) {
    window.alert('This browser cannot play the placeholder song.');
    return;
  }

  const context = new AudioContextClass();
  const song = collidePlaceholderSongForTeam(team);
  const masterGain = context.createGain();
  masterGain.gain.setValueAtTime(0.08, context.currentTime);
  masterGain.connect(context.destination);

  let cursor = context.currentTime + 0.02;
  song.notes.forEach(([frequency, duration]) => {
    const oscillator = context.createOscillator();
    const noteGain = context.createGain();
    oscillator.type = 'triangle';
    oscillator.frequency.setValueAtTime(frequency, cursor);
    noteGain.gain.setValueAtTime(0.0001, cursor);
    noteGain.gain.exponentialRampToValueAtTime(0.24, cursor + 0.02);
    noteGain.gain.exponentialRampToValueAtTime(0.0001, cursor + duration);
    oscillator.connect(noteGain);
    noteGain.connect(masterGain);
    oscillator.start(cursor);
    oscillator.stop(cursor + duration + 0.03);
    cursor += duration + 0.035;
  });

  const totalMilliseconds = Math.max(250, Math.ceil((cursor - context.currentTime + 0.08) * 1000));
  collideExtras.active = {
    type: 'placeholder',
    teamId,
    context,
    control,
    timer: window.setTimeout(() => {
      if (collideExtras.active?.context === context) {
        collideStopActivePlayback();
      }
    }, totalMilliseconds)
  };

  collideMarkControl(control, true);
  await context.resume();
}

async function collidePlaySong(teamId, control) {
  const loadedData = collideExtras.data || await collideFetchScores();
  const team = collideFindTeam(teamId) || loadedData.teams?.find((candidate) => candidate.id === teamId);
  if (!team) return;

  if (collideExtras.active?.teamId === teamId) {
    collideStopActivePlayback();
    return;
  }

  collideStopActivePlayback();

  const song = team.walkup_song || null;
  if (song && song.url) {
    await collidePlayUploadedSong(teamId, song, control);
    return;
  }

  await collidePlayPlaceholderSong(teamId, team, control);
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
  const fileInput = form.querySelector('input[type="file"][name="walkup_audio"]');
  if (fileInput) fileInput.value = '';
  const card = form.closest('.team-card');
  if (card) delete card.dataset.collideMetaSignature;
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
    statusText.textContent = 'Walk-up song removed. Placeholder quick song is still available.';
  }
}

async function collideTriggerHurray(teamId) {
  const response = await fetch('team-meta.php?action=hurray', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ team_id: teamId })
  });

  const payload = await response.json().catch(() => ({}));
  if (!response.ok) {
    throw new Error(payload.error || 'Unable to trigger the hurray screen.');
  }

  collideExtras.data = payload;
  const event = payload.hurray_event || null;
  const statusText = document.querySelector('#status-text');
  if (statusText) {
    statusText.textContent = `${event?.team_name || 'Team'} hurray screen sent to the public scoreboard.`;
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

  const hurrayButton = event.target.closest('[data-collide-hurray]');
  if (hurrayButton) {
    event.preventDefault();
    event.stopPropagation();
    try {
      await collideTriggerHurray(hurrayButton.dataset.collideHurray);
    } catch (error) {
      const statusText = document.querySelector('#status-text');
      if (statusText) statusText.textContent = error.message;
    }
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
    return;
  }

  const viewerCard = event.target.closest('.viewer-card[data-collide-team-id]');
  if (document.body.dataset.pageType === 'viewer' && viewerCard && !event.target.closest('a, button, input, textarea, select, label')) {
    event.preventDefault();
    event.stopPropagation();
    await collidePlaySong(viewerCard.dataset.collideTeamId, viewerCard);
  }
});

document.addEventListener('keydown', async (event) => {
  if (document.body.dataset.pageType !== 'viewer') return;
  if (event.key !== 'Enter' && event.key !== ' ') return;

  const viewerCard = event.target.closest('.viewer-card[data-collide-team-id]');
  if (!viewerCard) return;

  event.preventDefault();
  await collidePlaySong(viewerCard.dataset.collideTeamId, viewerCard);
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
