/*
  Project: Just Jason Jamboree Junction
  File: assets/js/script.js
  Revision: 1.2.0
  Updated: 2026-06-02
  Change: Generates only visible Jason text, with infinite append on scroll.
*/
(function () {
  'use strict';

  const wall = document.getElementById('jasonWall');
  const batchSize = 240;
  let batchCount = 0;

  function getTilt(index) {
    const pattern = [-2.2, 1.4, -0.8, 2.5, -1.5, 0.7, 1.9, -2.8];
    return pattern[index % pattern.length];
  }

  function addJasonBatch() {
    const fragment = document.createDocumentFragment();
    const offset = batchCount * batchSize;

    for (let index = 0; index < batchSize; index += 1) {
      const chip = document.createElement('span');
      chip.className = 'jason-chip';
      chip.textContent = 'Jason';
      chip.style.setProperty('--tilt', `${getTilt(offset + index)}deg`);
      fragment.appendChild(chip);
    }

    wall.appendChild(fragment);
    batchCount += 1;
  }

  function nearBottom() {
    return window.innerHeight + window.scrollY >= document.body.offsetHeight - 900;
  }

  function handleScroll() {
    if (nearBottom()) {
      addJasonBatch();
    }
  }

  addJasonBatch();
  addJasonBatch();
  addJasonBatch();

  window.addEventListener('scroll', handleScroll, { passive: true });
})();
