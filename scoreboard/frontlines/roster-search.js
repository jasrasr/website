// Filename: frontlines/roster-search.js
// Revision : 1.1.0
// Description : Filters Frontlines roster team cards by team, leader, member,
//               gender/grade suffix, or sponsor without changing roster data.
// Author : Jason Lamb (with help from ChatGPT)
// Created Date : 2026-06-20
// Modified Date : 2026-06-21
// Changelog :
// 1.0.0 Initial roster search with result count, clear action, and empty state
// 1.1.0 Show only matching people/sponsor rows inside matching team cards

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

  function normalizedText(element) {
    return element.textContent.replace(/\s+/g, ' ').trim().toLocaleLowerCase();
  }

  function matchesTokens(text, tokens) {
    return tokens.length === 0 || tokens.every((token) => text.includes(token));
  }

  const searchableCards = cards.map((card) => ({
    card,
    text: normalizedText(card),
    items: Array.from(card.querySelectorAll('[data-roster-search-item]')).map((item) => ({
      item,
      text: normalizedText(item)
    })),
    sections: Array.from(card.querySelectorAll('[data-roster-search-section]'))
  }));

  function pluralize(count, singular, plural = `${singular}s`) {
    return count === 1 ? singular : plural;
  }

  function updateSearch() {
    const rawQuery = searchInput.value.trim();
    const tokens = rawQuery.toLocaleLowerCase().split(/\s+/).filter(Boolean);
    let visibleCount = 0;

    searchableCards.forEach(({ card, text, items, sections }) => {
      const matches = matchesTokens(text, tokens);
      card.hidden = !matches;
      card.dataset.rosterSearchMatchOnly = rawQuery === '' ? 'false' : 'true';

      items.forEach(({ item, text: itemText }) => {
        const itemMatches = rawQuery === '' || matchesTokens(itemText, tokens);
        item.hidden = !itemMatches;
      });

      sections.forEach((section) => {
        const visibleItems = Array.from(section.querySelectorAll('[data-roster-search-item]'))
          .filter((item) => !item.hidden);
        section.dataset.rosterSearchSectionEmpty = visibleItems.length === 0 ? 'true' : 'false';
      });

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
