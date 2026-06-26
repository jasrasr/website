// Filename: frontlines/category-navigation.js
// Revision : 1.1.0
// Description : Keeps Frontlines category-score and rankings links consistently labeled,
//               and injects top shortcuts on score-entry pages after automatic re-renders.
// Author : Jason Lamb (with help from ChatGPT)
// Created Date : 2026-06-20
// Modified Date : 2026-06-23
// Changelog :
// 1.0.0 Rename category-score links and inject top shortcuts on full and quick entry pages
// 1.1.0 Add Full Rankings shortcuts for signed-in Frontlines admins/scorers

(() => {
  'use strict';

  const categoryPath = 'enter-scores-category.php';
  const categoryLabel = 'Add Category Score';
  const rankingsPath = 'rankings.php';
  const rankingsLabel = 'Full Rankings';

  function linkEndsWith(link, path) {
    try {
      return new URL(link.href, window.location.href).pathname.endsWith(`/${path}`);
    } catch {
      return false;
    }
  }

  function isCategoryLink(link) {
    return linkEndsWith(link, categoryPath);
  }

  function isRankingsLink(link) {
    return linkEndsWith(link, rankingsPath);
  }

  function buildLink(path, label, dataName) {
    const link = document.createElement('a');
    link.className = 'au-btn';
    link.href = `./${path}`;
    link.textContent = label;
    link.dataset[dataName] = 'top';
    return link;
  }

  function buildCategoryLink() {
    return buildLink(categoryPath, categoryLabel, 'categoryScoreLink');
  }

  function buildRankingsLink() {
    return buildLink(rankingsPath, rankingsLabel, 'fullRankingsLink');
  }

  function normalizeKnownLinks() {
    document.querySelectorAll('a[href]').forEach((link) => {
      if (isCategoryLink(link) && link.textContent.trim() !== categoryLabel) {
        link.textContent = categoryLabel;
      }
      if (isRankingsLink(link) && link.textContent.trim() !== rankingsLabel) {
        link.textContent = rankingsLabel;
      }
    });
  }

  function ensureAdminTopLinks() {
    const header = document.querySelector('#app .page-header');
    if (!header) return;

    let actions = header.querySelector('.header-actions');
    if (!actions) {
      actions = document.createElement('div');
      actions.className = 'header-actions';
      header.appendChild(actions);
    }

    if (!actions.querySelector('[data-full-rankings-link="top"]')) {
      actions.appendChild(buildRankingsLink());
    }

    if (!actions.querySelector('[data-category-score-link="top"]')) {
      actions.appendChild(buildCategoryLink());
    }
  }

  function ensureQuickTopLinks() {
    const header = document.querySelector('#quick-entry-app .quick-header');
    if (!header) return;

    let nav = header.nextElementSibling;
    if (!nav?.matches('[data-frontlines-top-nav="true"]')) {
      nav = document.createElement('nav');
      nav.className = 'quick-links';
      nav.dataset.frontlinesTopNav = 'true';
      nav.setAttribute('aria-label', 'Frontlines shortcuts');
      header.insertAdjacentElement('afterend', nav);
    }

    if (!nav.querySelector('[data-full-rankings-link="top"]')) {
      nav.appendChild(buildRankingsLink());
    }

    if (!nav.querySelector('[data-category-score-link="top"]')) {
      nav.appendChild(buildCategoryLink());
    }
  }

  function ensureFooterRankingsLinks() {
    document.querySelectorAll('.admin-footer-actions, .quick-links').forEach((container) => {
      if (container.closest('[data-frontlines-top-nav="true"]')) return;
      if (container.querySelector('[data-full-rankings-link="footer"]') ||
          Array.from(container.querySelectorAll('a[href]')).some(isRankingsLink)) {
        return;
      }

      const link = buildRankingsLink();
      link.dataset.fullRankingsLink = 'footer';
      container.appendChild(link);
    });
  }

  function syncFrontlinesNavigation() {
    normalizeKnownLinks();

    if (document.body.dataset.pageType === 'admin') {
      ensureAdminTopLinks();
    }

    if (document.body.classList.contains('quick-entry-body') &&
        !document.body.classList.contains('category-entry-body')) {
      ensureQuickTopLinks();
    }

    ensureFooterRankingsLinks();
  }

  const observer = new MutationObserver(syncFrontlinesNavigation);
  observer.observe(document.body, { childList: true, subtree: true });
  syncFrontlinesNavigation();
})();
