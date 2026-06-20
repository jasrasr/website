// Filename: frontlines/category-navigation.js
// Revision : 1.0.0
// Description : Keeps Frontlines category-score links consistently labeled and
//               adds an Add Category Score shortcut near the top of score-entry pages.
// Author : Jason Lamb (with help from ChatGPT)
// Created Date : 2026-06-20
// Modified Date : 2026-06-20
// Changelog :
// 1.0.0 Rename category-score links and inject top shortcuts on full and quick entry pages

(() => {
  'use strict';

  const categoryPath = 'enter-scores-category.php';
  const categoryLabel = 'Add Category Score';

  function isCategoryLink(link) {
    try {
      return new URL(link.href, window.location.href).pathname.endsWith(`/${categoryPath}`);
    } catch {
      return false;
    }
  }

  function buildCategoryLink() {
    const link = document.createElement('a');
    link.className = 'au-btn';
    link.href = `./${categoryPath}`;
    link.textContent = categoryLabel;
    link.dataset.categoryScoreLink = 'top';
    return link;
  }

  function renameCategoryLinks() {
    document.querySelectorAll('a[href]').forEach((link) => {
      if (isCategoryLink(link) && link.textContent.trim() !== categoryLabel) {
        link.textContent = categoryLabel;
      }
    });
  }

  function ensureAdminTopLink() {
    const header = document.querySelector('#app .page-header');
    if (!header) return;

    let actions = header.querySelector('.header-actions');
    if (!actions) {
      actions = document.createElement('div');
      actions.className = 'header-actions';
      header.appendChild(actions);
    }

    if (!actions.querySelector('[data-category-score-link="top"]')) {
      actions.appendChild(buildCategoryLink());
    }
  }

  function ensureQuickTopLink() {
    const header = document.querySelector('#quick-entry-app .quick-header');
    if (!header) return;

    const existingNav = header.nextElementSibling;
    if (existingNav?.matches('[data-category-score-nav="top"]')) return;

    const nav = document.createElement('nav');
    nav.className = 'quick-links';
    nav.dataset.categoryScoreNav = 'top';
    nav.setAttribute('aria-label', 'Category score shortcut');
    nav.appendChild(buildCategoryLink());
    header.insertAdjacentElement('afterend', nav);
  }

  function syncCategoryNavigation() {
    renameCategoryLinks();

    if (document.body.dataset.pageType === 'admin') {
      ensureAdminTopLink();
    }

    if (document.body.classList.contains('quick-entry-body') &&
        !document.body.classList.contains('category-entry-body')) {
      ensureQuickTopLink();
    }
  }

  const observer = new MutationObserver(syncCategoryNavigation);
  observer.observe(document.body, { childList: true, subtree: true });
  syncCategoryNavigation();
})();
