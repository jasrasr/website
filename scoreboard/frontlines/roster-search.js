// Filename: frontlines/roster-search.js
// Revision : 1.0.0
// Description : Filters Frontlines roster team cards by team, leader, member,
//               gender/grade suffix, or sponsor without changing roster data.
// Author : Jason Lamb (with help from ChatGPT)
// Created Date : 2026-06-20
// Modified Date : 2026-06-20
// Changelog :
// 1.0.0 Initial roster search with result count, clear action, and empty state

(() => {
  'use strict';

  const searchInput = document.querySelector('#roster-search-input');
  const clearButton = document.querySelector('#roster-search-clear');
  const resultStatus = document.querySelector('#roster-search-status');
  const emptyState = document.querySelector('#roster-search-empty');
  const cards = Array.from(document.querySelectorAll('.roster-card'));

  if (!searchInput || !clearButton || !resultStatus || !emptyState || cards.length === 0) {
    return;
  }

  const searchableCards = cards.map((card) => ({
    card,
    text: card.textContent.replace(/\s+/g, ' ').trim().toLocaleLowerCase()
  }));

  function pluralize(count, singular, plural = `${singular}s`) {
    return count === 1 ? singular : plural;
  }

  function updateSearch() {
    const rawQuery = searchInput.value.trim();
    const tokens = rawQuery.toLocaleLowerCase().split(/\s+/).filter(Boolean);
    let visibleCount = 0;

    searchableCards.forEach(({ card, text }) => {
      const matches = tokens.length === 0 || tokens.every((token) => text.includes(token));
      card.hidden = !matches;
      if (matches) visibleCount += 1;
    });

    clearButton.hidden = rawQuery === '';
    emptyState.hidden = visibleCount !== 0;

    if (rawQuery === '') {
      resultStatus.textContent = `Showing all ${cards.length} ${pluralize(cards.length, 'team')}.`;
      return;
    }

    resultStatus.textContent = visibleCount === 0
      ? `No teams match “${rawQuery}”.`
      : `Showing ${visibleCount} of ${cards.length} ${pluralize(cards.length, 'team')}.`;
  }

  function clearSearch() {
    searchInput.value = '';
    updateSearch();
    searchInput.focus();
  }

  searchInput.addEventListener('input', updateSearch);
  searchInput.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && searchInput.value !== '') {
      event.preventDefault();
      clearSearch();
    }
  });
  clearButton.addEventListener('click', clearSearch);

  updateSearch();
})();
