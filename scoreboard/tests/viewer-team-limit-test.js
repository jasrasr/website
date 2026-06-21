// Filename: viewer-team-limit-test.js
// Revision : 1.0.0
// Description : Verifies viewer team limit behavior keeps tied cutoff teams visible.
// Author : Jason Lamb (with help from Codex CLI)
// Created Date : 2026-06-21
// Modified Date : 2026-06-21
// Changelog :
// 1.0.0 Initial top-three-with-ties viewer limit coverage

const fs = require('fs');
const path = require('path');
const vm = require('vm');

function assert(condition, message) {
  if (!condition) {
    console.error(`FAIL: ${message}`);
    process.exit(1);
  }
}

const appPath = path.join(__dirname, '..', 'public', 'app.js');
const source = fs.readFileSync(appPath, 'utf8').replace(/init\(\)\.catch\([\s\S]*$/, '');

let renderedHtml = '';
const sandbox = {
  Intl,
  Number,
  String,
  Math,
  console,
  window: {
    scrollY: 0,
    pageYOffset: 0,
    scrollTo() {}
  },
  document: {
    body: {
      dataset: {
        pageType: 'viewer',
        viewerTeamLimit: '3',
        rosterUrl: ''
      }
    },
    querySelector(selector) {
      if (selector === '#app') {
        return {
          set innerHTML(value) {
            renderedHtml = value;
          },
          get innerHTML() {
            return renderedHtml;
          }
        };
      }
      return null;
    }
  }
};

vm.createContext(sandbox);
vm.runInContext(source, sandbox, { filename: appPath });

function team(id, score) {
  return {
    id: `team-${id}`,
    name: `Team ${id}`,
    color: '#64748b',
    score,
    score_changed_at: `2026-06-21T00:00:${String(id).padStart(2, '0')}Z`
  };
}

function renderAndCount(scores) {
  renderedHtml = '';
  sandbox.renderViewer({
    title: 'Frontlines',
    updatedAt: null,
    teams: scores.map((score, index) => team(index + 1, score))
  });
  return {
    count: (renderedHtml.match(/class="team-card viewer-card"/g) || []).length,
    html: renderedHtml
  };
}

let result = renderAndCount([120, 110, 100, 90, 80, 70]);
assert(result.count === 3, 'distinct scores should render exactly the top 3 teams.');
assert(result.html.includes('--viewer-cols: 3; --viewer-rows: 1;'), 'three visible teams should fill one full-width row.');
assert(result.html.includes('Showing 3 of 6 teams'), 'distinct scores should show a hidden-count note.');

result = renderAndCount([120, 110, 100, 100, 90, 80]);
assert(result.count === 4, 'teams tied at the third-place cutoff should remain visible.');
assert(result.html.includes('--viewer-cols: 2; --viewer-rows: 2;'), 'four visible teams should fill a balanced 2x2 grid.');
assert(result.html.includes('Showing 4 of 6 teams'), 'tie-expanded scores should report the visible team count.');

result = renderAndCount([0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0]);
assert(result.count === 12, 'all-zero teams should all remain visible because they are tied at the cutoff.');
assert(result.html.includes('--viewer-cols: 4; --viewer-rows: 3;'), 'twelve tied teams should use a 4x3 grid to fill the viewer.');
assert(!result.html.includes('Showing 12 of 12 teams'), 'all visible tied teams should not show a hidden-count note.');

console.log('PASS: viewer-team-limit-test.js');

// Example Usage:
//   node .\tests\viewer-team-limit-test.js
