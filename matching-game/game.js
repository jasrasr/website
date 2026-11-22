(() => {
  'use strict';

  const colors = [
    { name: 'red', value: '#ff2828' },
    { name: 'orange', value: '#ff8b20' },
    { name: 'yellow', value: '#ffdb00' },
    { name: 'green', value: '#3dbd45' },
    { name: 'teal', value: '#13bdb4' },
    { name: 'blue', value: '#247cff' },
    { name: 'purple', value: '#9649db' },
    { name: 'pink', value: '#f34fa5' },
  ];
  const board = document.getElementById('board');
  const progress = document.getElementById('progress');
  const message = document.getElementById('message');
  const newGameButton = document.getElementById('new-game');
  let first = null;
  let second = null;
  let pairs = 0;
  let locked = false;
  let hideTimer = null;

  function randomIndex(max) {
    if (window.crypto && window.crypto.getRandomValues) {
      const value = new Uint32Array(1);
      const limit = Math.floor(0x100000000 / max) * max;
      do { window.crypto.getRandomValues(value); } while (value[0] >= limit);
      return value[0] % max;
    }
    return Math.floor(Math.random() * max);
  }

  function shuffledCards() {
    const cards = [...colors, ...colors];
    for (let i = cards.length - 1; i > 0; i--) {
      const j = randomIndex(i + 1);
      [cards[i], cards[j]] = [cards[j], cards[i]];
    }
    return cards;
  }

  function reveal(tile) {
    tile.classList.add('revealed');
    tile.textContent = '';
    tile.setAttribute('aria-label', `${tile.dataset.color} block`);
    tile.setAttribute('aria-pressed', 'true');
  }

  function hide(tile) {
    tile.classList.remove('revealed');
    tile.textContent = '?';
    tile.setAttribute('aria-label', `Hidden block ${Number(tile.dataset.index) + 1}`);
    tile.setAttribute('aria-pressed', 'false');
  }

  function choose(tile) {
    if (locked || tile.classList.contains('matched') || tile === first) return;
    reveal(tile);
    if (!first) {
      first = tile;
      message.textContent = 'Find its match!';
      return;
    }
    second = tile;
    if (first.dataset.color === second.dataset.color) {
      first.classList.add('matched');
      second.classList.add('matched');
      first.disabled = true;
      second.disabled = true;
      pairs++;
      progress.textContent = `Pairs ${pairs} of 8`;
      message.textContent = pairs === 8 ? 'You found them all! 🎉' : 'Great match! ⭐';
      first = null;
      second = null;
      return;
    }
    locked = true;
    message.textContent = 'Try again!';
    hideTimer = window.setTimeout(() => {
      hide(first);
      hide(second);
      first = null;
      second = null;
      locked = false;
      hideTimer = null;
      message.textContent = 'Find two colors that match!';
    }, 1400);
  }

  function newGame() {
    if (hideTimer !== null) window.clearTimeout(hideTimer);
    hideTimer = null;
    first = null;
    second = null;
    pairs = 0;
    locked = false;
    progress.textContent = 'Pairs 0 of 8';
    message.textContent = 'Find two colors that match!';
    board.replaceChildren();
    shuffledCards().forEach((color, index) => {
      const tile = document.createElement('button');
      tile.type = 'button';
      tile.className = 'tile';
      tile.textContent = '?';
      tile.dataset.color = color.name;
      tile.dataset.index = String(index);
      tile.style.setProperty('--tile-color', color.value);
      tile.setAttribute('aria-label', `Hidden block ${index + 1}`);
      tile.setAttribute('aria-pressed', 'false');
      tile.addEventListener('click', () => choose(tile));
      board.append(tile);
    });
  }

  newGameButton.addEventListener('click', newGame);
  newGame();
})();
