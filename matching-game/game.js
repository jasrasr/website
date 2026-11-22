(() => {
  'use strict';

  const colors = [
    ['red', '#ff2828'], ['orange', '#ff8b20'], ['yellow', '#ffdb00'], ['green', '#3dbd45'],
    ['teal', '#13bdb4'], ['blue', '#247cff'], ['purple', '#9649db'], ['pink', '#f34fa5'],
    ['brown', '#a86d3c'], ['lime', '#a3d42d'], ['navy', '#344a9f'], ['coral', '#ff6a63'],
    ['mint', '#63c994'], ['violet', '#b774eb'], ['gold', '#d7a820'], ['sky blue', '#5dbde8'],
    ['magenta', '#c92784'], ['gray', '#8496a5'],
  ];
  const modes = {
    colors: { label: 'Colors', cards: colors.map(([name, color]) => ({ name, color, text: '' })) },
    letters: { label: 'Letters', cards: Array.from('ABCDEFGHIJKLMNOPQRSTUVWXYZ', text => ({ name: `Letter ${text}`, text })) },
    numbers: { label: 'Numbers', cards: Array.from({ length: 18 }, (_, index) => {
      const text = String(index + 1);
      return { name: `Number ${text}`, text };
    }) },
    objects: { label: 'Objects', cards: [
      ['Apple', '🍎'], ['Banana', '🍌'], ['Ball', '⚽'], ['Car', '🚗'],
      ['Train', '🚂'], ['Airplane', '✈️'], ['Teddy bear', '🧸'], ['Book', '📚'],
      ['Pencil', '✏️'], ['Cup', '🥤'], ['Key', '🔑'], ['Clock', '⏰'],
      ['Star', '⭐'], ['Flower', '🌻'], ['Tree', '🌳'], ['Dog', '🐶'],
      ['Cat', '🐱'], ['House', '🏠'],
    ].map(([name, text]) => ({ name, text })) },
  };
  const $ = id => document.getElementById(id);
  const root = document.querySelector('.game');
  const canSaveHistory = root.dataset.saveHistory === 'true';
  const storageKey = 'matching-game-session-v1';
  let rows = 4, columns = 4, pairs = 0, attempts = 0;
  let mode = 'colors';
  let first = null, second = null, locked = false;
  let hideTimer = null, tickTimer = null, startedAt = null, elapsedMs = 0;
  let session = readSession();

  function readSession() {
    try {
      const value = JSON.parse(sessionStorage.getItem(storageKey) || 'null');
      if (value && Number.isInteger(value.games) && Number.isInteger(value.pairs) && value.best && typeof value.best === 'object') return value;
    } catch (_) { /* Storage can be unavailable in private browsing. */ }
    return { games: 0, pairs: 0, best: {} };
  }

  function formatTime(ms, completed = false) {
    const seconds = completed ? Math.max(1, Math.ceil(ms / 1000)) : Math.floor(ms / 1000);
    return `${Math.floor(seconds / 60)}:${String(seconds % 60).padStart(2, '0')}`;
  }

  function updateSession() {
    $('session-totals').textContent = `${session.games} ${session.games === 1 ? 'game' : 'games'} · ${session.pairs} ${session.pairs === 1 ? 'pair' : 'pairs'}`;
    const best = session.best[bestKey()];
    $('session-best').textContent = `Best · ${modes[mode].label} ${rows}×${columns}: ${Number.isFinite(best) ? formatTime(best, true) : '—'}`;
  }

  function bestKey() {
    const size = `${rows}x${columns}`;
    return mode === 'colors' ? size : `${mode}:${size}`;
  }

  function promptForMatch() {
    $('message').textContent = `Find two ${mode} that match!`;
  }

  function randomIndex(max) {
    if (window.crypto?.getRandomValues) {
      const value = new Uint32Array(1);
      const limit = Math.floor(0x100000000 / max) * max;
      do { window.crypto.getRandomValues(value); } while (value[0] >= limit);
      return value[0] % max;
    }
    return Math.floor(Math.random() * max);
  }

  function shuffledCards() {
    const selected = modes[mode].cards.slice(0, rows * columns / 2);
    const cards = [...selected, ...selected];
    for (let i = cards.length - 1; i > 0; i--) {
      const j = randomIndex(i + 1);
      [cards[i], cards[j]] = [cards[j], cards[i]];
    }
    return cards;
  }

  function tick() {
    if (startedAt !== null) elapsedMs = Date.now() - startedAt;
    $('timer').textContent = formatTime(elapsedMs);
  }

  function reveal(tile) {
    tile.classList.add('revealed');
    tile.textContent = tile.dataset.symbol;
    tile.setAttribute('aria-label', tile.dataset.card);
    tile.setAttribute('aria-pressed', 'true');
  }

  function hide(tile) {
    tile.classList.remove('revealed');
    tile.textContent = '?';
    tile.setAttribute('aria-label', `Hidden block ${Number(tile.dataset.index) + 1}`);
    tile.setAttribute('aria-pressed', 'false');
  }

  function finishGame() {
    tick();
    window.clearInterval(tickTimer);
    tickTimer = null;
    startedAt = null;
    $('timer').textContent = formatTime(elapsedMs, true);
    session.games++;
    session.pairs += pairs;
    const size = bestKey();
    session.best[size] = Math.min(session.best[size] ?? Infinity, elapsedMs);
    try { sessionStorage.setItem(storageKey, JSON.stringify(session)); } catch (_) { /* Keep in memory. */ }
    updateSession();
    $('message').textContent = `You found them all in ${formatTime(elapsedMs, true)}! 🎉`;
    if (canSaveHistory) recordHistory({ mode, rows, columns, elapsedMs, attempts });
  }

  function choose(tile) {
    if (locked || tile.classList.contains('matched') || tile === first) return;
    if (startedAt === null && pairs === 0 && attempts === 0) {
      startedAt = Date.now();
      tickTimer = window.setInterval(tick, 250);
    }
    reveal(tile);
    if (!first) { first = tile; $('message').textContent = 'Find its match!'; return; }
    second = tile;
    attempts++;
    $('attempts').textContent = String(attempts);
    if (first.dataset.card === second.dataset.card) {
      first.classList.add('matched');
      second.classList.add('matched');
      first.disabled = second.disabled = true;
      pairs++;
      $('progress').textContent = `Pairs ${pairs} of ${rows * columns / 2}`;
      first = second = null;
      if (pairs === rows * columns / 2) finishGame();
      else $('message').textContent = 'Great match! ⭐';
      return;
    }
    locked = true;
    $('message').textContent = 'Try again!';
    hideTimer = window.setTimeout(() => {
      hide(first);
      hide(second);
      first = second = null;
      locked = false;
      hideTimer = null;
      promptForMatch();
    }, 1400);
  }

  function requestedSize() {
    const nextRows = Number($('rows').value), nextColumns = Number($('columns').value);
    if (!Number.isInteger(nextRows) || !Number.isInteger(nextColumns) || nextRows < 2 || nextRows > 6
      || nextColumns < 2 || nextColumns > 6 || nextRows * nextColumns % 2 !== 0) return null;
    return [nextRows, nextColumns];
  }

  function newGame() {
    const size = requestedSize();
    $('size-error').hidden = !!size;
    if (!size) return;
    [rows, columns] = size;
    mode = $('match-mode').value;
    window.clearTimeout(hideTimer);
    window.clearInterval(tickTimer);
    hideTimer = tickTimer = startedAt = null;
    elapsedMs = pairs = attempts = 0;
    first = second = null;
    locked = false;
    $('timer').textContent = '0:00';
    $('attempts').textContent = '0';
    $('progress').textContent = `Pairs 0 of ${rows * columns / 2}`;
    promptForMatch();
    const board = $('board');
    board.dataset.mode = mode;
    board.setAttribute('aria-label', `${modes[mode].label} matching board`);
    board.style.setProperty('--rows', rows);
    board.style.setProperty('--columns', columns);
    board.replaceChildren();
    shuffledCards().forEach(({ name, color, text }, index) => {
      const tile = document.createElement('button');
      tile.type = 'button';
      tile.className = 'tile';
      tile.textContent = '?';
      tile.dataset.card = name;
      tile.dataset.symbol = text;
      tile.dataset.index = String(index);
      if (color) tile.style.setProperty('--tile-color', color);
      tile.setAttribute('aria-label', `Hidden block ${index + 1}`);
      tile.setAttribute('aria-pressed', 'false');
      tile.addEventListener('click', () => choose(tile));
      board.append(tile);
    });
    updateSession();
  }

  function renderHistory(record) {
    $('history-summary').textContent = `${record.games} saved ${record.games === 1 ? 'game' : 'games'}`;
    const list = $('history-list');
    list.replaceChildren();
    for (const entry of record.recent || []) {
      const item = document.createElement('li');
      const label = modes[entry.mode ?? 'colors'].label;
      item.textContent = `${label} · ${entry.rows}×${entry.columns} · ${formatTime(entry.elapsedMs, true)} · ${entry.attempts} tries · ${new Date(entry.playedAt).toLocaleDateString()}`;
      list.append(item);
    }
    if (!list.children.length) {
      const item = document.createElement('li');
      item.textContent = 'No saved games yet.';
      list.append(item);
    }
  }

  async function loadHistory() {
    try {
      const response = await fetch('api.php', { credentials: 'same-origin', cache: 'no-store' });
      if (!response.ok) throw new Error('History unavailable');
      const result = await response.json();
      if (result.data?.record) renderHistory(result.data.record);
    } catch (_) { $('history-summary').textContent = 'Saved games are temporarily unavailable.'; }
  }

  async function recordHistory(game) {
    const status = $('save-status');
    status.textContent = 'Saving this game…';
    const gameId = window.crypto?.randomUUID?.() || `${Date.now()}-${Math.random().toString(36).slice(2)}`;
    try {
      const response = await fetch('api.php', {
        method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ csrf: root.dataset.csrf, gameId, ...game }),
      });
      if (!response.ok) throw new Error('Save failed');
      const result = await response.json();
      status.textContent = 'Game saved to your account.';
      if (result.data?.record) renderHistory(result.data.record);
    } catch (_) { status.textContent = 'Could not save this game. Your session score is still here.'; }
  }

  $('new-game').addEventListener('click', newGame);
  if (canSaveHistory) {
    const toggle = $('history-toggle'), panel = $('history-panel');
    toggle.addEventListener('click', () => {
      panel.hidden = !panel.hidden;
      toggle.setAttribute('aria-expanded', String(!panel.hidden));
      toggle.textContent = panel.hidden ? 'View saved games' : 'Hide saved games';
      if (!panel.hidden) loadHistory();
    });
  }
  newGame();
})();
