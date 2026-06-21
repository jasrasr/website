// Filename: edit-categories.js
// Revision : 1.1.0
// Description : Admin-only editor for Frontlines goal categories. Lists categories,
//               supports add/edit/remove via the REST API, and toggles per-category
//               active flag. Frontlines-only feature; loaded from edit-categories.php.
// Author : Jason Lamb (with help from Claude Code)
// Created Date : 2026-06-17
// Modified Date : 2026-06-21
// Changelog :
// 1.0.0 initial release
// 1.1.0 Add ranked category mode for 12000-to-1000 award values

const EDIT_CATEGORIES_REVISION = '1.1.0';

let categoriesState = null;
let statusMessage = '';

async function fetchCategories() {
  const apiUrl = document.body.dataset.apiUrl || './api.php';
  const response = await fetch(`${apiUrl}?action=list-categories`, { credentials: 'same-origin' });
  if (!response.ok) {
    throw new Error('Unable to load categories.');
  }
  categoriesState = await response.json();
}

async function postJson(path, body) {
  const apiUrl = document.body.dataset.apiUrl || './api.php';
  const url = path.startsWith('?') ? `${apiUrl}${path}` : `${apiUrl}?${path}`;
  const response = await fetch(url, {
    method: 'POST',
    credentials: 'same-origin',
    headers: { 'Content-Type': 'application/json' },
    body: body ? JSON.stringify(body) : null
  });
  let data = null;
  try { data = await response.json(); } catch { data = null; }
  if (!response.ok) {
    const message = (data && data.error) || `Request failed (${response.status}).`;
    throw new Error(message);
  }
  return data;
}

function el(tag, options = {}) {
  const element = document.createElement(tag);
  if (options.className) element.className = options.className;
  if (options.text !== undefined) element.textContent = options.text;
  if (options.html !== undefined) element.innerHTML = options.html;
  if (options.attributes) {
    Object.entries(options.attributes).forEach(([name, value]) => element.setAttribute(name, value));
  }
  if (options.dataset) {
    Object.entries(options.dataset).forEach(([name, value]) => { element.dataset[name] = value; });
  }
  return element;
}

function setStatus(message) {
  statusMessage = message;
  const status = document.querySelector('#editor-status');
  if (status) status.textContent = message;
}

function renderRow(category) {
  const row = el('section', {
    className: 'category-row',
    dataset: { categoryId: category.id }
  });

  const grid = el('div', { className: 'category-row-grid' });

  const nameLabel = el('label', { className: 'category-field' });
  nameLabel.append(el('span', { text: 'Name' }));
  nameLabel.append(el('input', { attributes: { name: 'name', type: 'text', value: category.name || '' } }));

  const pointsLabel = el('label', { className: 'category-field' });
  pointsLabel.append(el('span', { text: 'Points (use negative for penalty)' }));
  pointsLabel.append(el('input', {
    attributes: { name: 'points', type: 'number', step: '1', value: String(category.points ?? 0) }
  }));

  const modeLabel = el('label', { className: 'category-field category-field-active category-field-ranked' });
  const rankedCheckbox = el('input', { attributes: { name: 'scoringMode', type: 'checkbox', value: 'ranked' } });
  rankedCheckbox.checked = category.scoringMode === 'ranked';
  modeLabel.append(rankedCheckbox);
  modeLabel.append(el('span', { text: 'Ranked category (12000 to 1000)' }));

  const maxLabel = el('label', { className: 'category-field' });
  maxLabel.append(el('span', { text: 'Max awards per team (blank = unlimited)' }));
  maxLabel.append(el('input', {
    attributes: {
      name: 'maxAwardsPerTeam',
      type: 'number',
      min: '1',
      step: '1',
      value: category.maxAwardsPerTeam == null ? '' : String(category.maxAwardsPerTeam)
    }
  }));

  const activeLabel = el('label', { className: 'category-field category-field-active' });
  const activeCheckbox = el('input', { attributes: { name: 'active', type: 'checkbox' } });
  activeCheckbox.checked = !!category.active;
  activeLabel.append(activeCheckbox);
  activeLabel.append(el('span', { text: 'Active (visible to scorers)' }));

  grid.append(nameLabel, pointsLabel, maxLabel, modeLabel, activeLabel);

  const actions = el('div', { className: 'category-row-actions' });
  const saveButton = el('button', {
    className: 'positive',
    text: 'Save',
    attributes: { type: 'button' },
    dataset: { action: 'save-category' }
  });
  const deleteButton = el('button', {
    className: 'negative',
    text: 'Delete',
    attributes: { type: 'button' },
    dataset: { action: 'delete-category' }
  });
  actions.append(saveButton, deleteButton);

  row.append(grid, actions);
  return row;
}

function renderAddForm() {
  const section = el('section', { className: 'category-add au-section' });
  section.append(el('h2', { className: 'au-heading', text: 'Add a new category' }));

  const form = el('form', {
    className: 'category-add-form',
    dataset: { action: 'add-category' },
    attributes: { autocomplete: 'off' }
  });

  const grid = el('div', { className: 'category-row-grid' });

  const nameLabel = el('label', { className: 'category-field' });
  nameLabel.append(el('span', { text: 'Name' }));
  nameLabel.append(el('input', { attributes: { name: 'name', type: 'text', required: 'required', placeholder: 'Water Challenge' } }));

  const pointsLabel = el('label', { className: 'category-field' });
  pointsLabel.append(el('span', { text: 'Points (use negative for penalty)' }));
  pointsLabel.append(el('input', { attributes: { name: 'points', type: 'number', step: '1', placeholder: '100' } }));

  const modeLabel = el('label', { className: 'category-field category-field-active category-field-ranked' });
  modeLabel.append(el('input', { attributes: { name: 'scoringMode', type: 'checkbox', value: 'ranked' } }));
  modeLabel.append(el('span', { text: 'Ranked category (12000 to 1000)' }));

  const maxLabel = el('label', { className: 'category-field' });
  maxLabel.append(el('span', { text: 'Max awards per team (blank = unlimited)' }));
  maxLabel.append(el('input', { attributes: { name: 'maxAwardsPerTeam', type: 'number', min: '1', step: '1', placeholder: 'unlimited' } }));

  grid.append(nameLabel, pointsLabel, maxLabel, modeLabel);

  const submit = el('button', {
    className: 'positive',
    text: 'Add Category',
    attributes: { type: 'submit' }
  });

  form.append(grid, submit);
  section.append(form);
  return section;
}

function renderHeader() {
  const username = document.body.dataset.username || '';
  const backUrl = document.body.dataset.backUrl || './enter-scores-quick.php';
  const enterCategoriesUrl = document.body.dataset.enterCategoriesUrl || './enter-scores-category.php';
  const editRosterUrl = document.body.dataset.editRosterUrl || './edit-roster.php';
  const scoreboardsUrl = document.body.dataset.scoreboardsUrl || '../scoreboards.php';
  const passwordUrl = document.body.dataset.passwordUrl || '../change-password.php';
  const logoutUrl = document.body.dataset.logoutUrl || '../logout.php';

  const header = el('header', { className: 'page-header' });
  const titleBlock = el('div');
  titleBlock.append(el('p', { text: `Admin — ${username}` }));
  titleBlock.append(el('h1', { text: 'Frontlines Goal Categories' }));
  titleBlock.append(el('p', { className: 'updated-at', text: 'Define point-value goals here. Scorers see them on Enter Categories.' }));
  header.append(titleBlock);

  const links = el('div', { className: 'header-actions' });
  links.append(el('a', { className: 'au-btn', text: 'Quick Entry', attributes: { href: backUrl } }));
  links.append(el('a', { className: 'au-btn', text: 'Enter Categories', attributes: { href: enterCategoriesUrl } }));
  links.append(el('a', { className: 'au-btn', text: 'Edit Roster', attributes: { href: editRosterUrl } }));
  links.append(el('a', { className: 'au-btn', text: 'All Scoreboards', attributes: { href: scoreboardsUrl } }));
  links.append(el('a', { className: 'au-btn', text: 'Change Password', attributes: { href: passwordUrl } }));
  links.append(el('a', { className: 'au-btn negative', text: 'Sign out', attributes: { href: logoutUrl } }));
  header.append(links);

  return header;
}

function render() {
  const app = document.querySelector('#edit-categories-app');
  if (!app) return;

  app.innerHTML = '';
  app.append(renderHeader());

  const status = el('p', {
    className: 'status-text',
    attributes: { id: 'editor-status' },
    text: statusMessage || 'Add, edit, or remove categories below.'
  });
  app.append(status);

  app.append(renderAddForm());

  const list = el('section', { className: 'category-list au-section' });
  list.append(el('h2', { className: 'au-heading', text: 'Existing categories' }));

  const categories = (categoriesState && Array.isArray(categoriesState.categories))
    ? categoriesState.categories.slice().sort((a, b) => (a.name || '').localeCompare(b.name || ''))
    : [];

  if (categories.length === 0) {
    list.append(el('p', { className: 'updated-at', text: 'No categories yet — add your first one using the form above.' }));
  } else {
    categories.forEach((category) => list.append(renderRow(category)));
  }

  app.append(list);

  app.append(el('p', {
    className: 'updated-at quick-revision',
    text: `edit-categories.js v${EDIT_CATEGORIES_REVISION}`
  }));
}

function readRowPayload(row) {
  const nameInput = row.querySelector('input[name="name"]');
  const pointsInput = row.querySelector('input[name="points"]');
  const maxInput = row.querySelector('input[name="maxAwardsPerTeam"]');
  const activeInput = row.querySelector('input[name="active"]');
  const rankedInput = row.querySelector('input[name="scoringMode"]');

  const name = (nameInput?.value || '').trim();
  const pointsRaw = pointsInput?.value || '';
  const maxRaw = maxInput?.value || '';
  const active = activeInput ? activeInput.checked : true;
  const scoringMode = rankedInput?.checked ? 'ranked' : 'fixed';

  if (name === '') throw new Error('Category name cannot be empty.');
  if (scoringMode === 'fixed' && (pointsRaw === '' || Number.isNaN(Number(pointsRaw)) || Number(pointsRaw) === 0)) {
    throw new Error('Points must be a non-zero number.');
  }
  return {
    name,
    points: scoringMode === 'ranked' ? 0 : parseInt(pointsRaw, 10),
    scoringMode,
    maxAwardsPerTeam: maxRaw === '' ? 'unlimited' : parseInt(maxRaw, 10),
    active
  };
}

async function handleClick(event) {
  const button = event.target.closest('button[data-action]');
  if (!button) return;

  try {
    if (button.dataset.action === 'save-category') {
      const row = button.closest('.category-row');
      if (!row) return;
      const payload = readRowPayload(row);
      const categoryId = row.dataset.categoryId;
      await postJson(`action=update-category&category=${encodeURIComponent(categoryId)}`, payload);
      await fetchCategories();
      setStatus(`Saved "${payload.name}".`);
      render();
    }

    if (button.dataset.action === 'delete-category') {
      const row = button.closest('.category-row');
      if (!row) return;
      const name = row.querySelector('input[name="name"]')?.value || 'this category';
      if (!window.confirm(`Delete "${name}"? Past awards in the audit log are preserved, but the category will no longer appear.`)) {
        return;
      }
      const categoryId = row.dataset.categoryId;
      await postJson(`action=remove-category&category=${encodeURIComponent(categoryId)}`);
      await fetchCategories();
      setStatus(`Deleted "${name}".`);
      render();
    }
  } catch (error) {
    setStatus(error.message);
  }
}

async function handleSubmit(event) {
  const form = event.target.closest('form[data-action="add-category"]');
  if (!form) return;
  event.preventDefault();

  try {
    const formData = new FormData(form);
    const name = String(formData.get('name') || '').trim();
    const pointsRaw = String(formData.get('points') || '').trim();
    const maxRaw = String(formData.get('maxAwardsPerTeam') || '').trim();
    const scoringMode = formData.get('scoringMode') === 'ranked' ? 'ranked' : 'fixed';

    if (name === '') throw new Error('Category name cannot be empty.');
    if (scoringMode === 'fixed' && (pointsRaw === '' || Number.isNaN(Number(pointsRaw)) || Number(pointsRaw) === 0)) {
      throw new Error('Points must be a non-zero number.');
    }

    const payload = {
      name,
      points: scoringMode === 'ranked' ? 0 : parseInt(pointsRaw, 10),
      scoringMode,
      maxAwardsPerTeam: maxRaw === '' ? 'unlimited' : parseInt(maxRaw, 10)
    };

    await postJson('action=add-category', payload);
    await fetchCategories();
    setStatus(`Added "${name}".`);
    render();
  } catch (error) {
    setStatus(error.message);
  }
}

async function init() {
  try {
    await fetchCategories();
    render();
  } catch (error) {
    const app = document.querySelector('#edit-categories-app');
    if (app) {
      app.innerHTML = '';
      app.append(el('p', { className: 'status-text', text: error.message }));
    }
    return;
  }

  document.addEventListener('click', handleClick);
  document.addEventListener('submit', handleSubmit);
}

init();
