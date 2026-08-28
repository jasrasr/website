# Admin User Import and Guided Matching Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add admin-targeted staged imports with a downloadable sample CSV, TMDB-assisted matching, duplicate-skip defaults, and inline fix-up for unresolved rows before confirm.

**Architecture:** Reuse the existing `import.php` staging flow as the central import surface, but add an explicit target-user context so the same page works for self-import and admin-import. Extend normalization and review metadata in helper functions, add a lightweight sample CSV endpoint and inline TMDB search/apply controls, then confirm rows into the selected user's library with partial-overwrite behavior only when the uploaded row supplied a field.

**Tech Stack:** PHP 8, existing JSON storage helpers, TMDB API wrapper in `includes/tmdb.php`, plain JavaScript in `assets/js/app.js`, static PHP regression tests in `tests/`.

## Global Constraints

- Support CSV and JSON only; native `.xlsx` upload remains out of scope.
- Include `type` and optional `tmdb_id` in the sample template.
- Include a JAG sample row using TMDB ID `4376`.
- Default duplicate handling must skip existing items.
- Overwrite must be opt-in and update only fields explicitly supplied by the uploaded row.
- TMDB matching must be deterministic and explainable, not LLM-based.
- Low-confidence rows must stay staged and be fixable inline before confirmation.

---

### Task 1: Add import helpers, template generation, and matching metadata

**Files:**
- Modify: `includes/functions.php`
- Modify: `includes/tmdb.php`
- Test: `tests/import-template-and-matching-test.php`

**Interfaces:**
- Consumes: `app_normalize_import_item(array $row): array`, `app_tmdb_search(string $query): array`, `app_tmdb_details(string $type, int $tmdbId, bool $force = false): array`, `app_apply_tmdb_details_to_item(array $item, array $details, bool $replaceTitle = true): array`
- Produces: `app_import_template_csv(): string`, `app_import_supplied_fields(array $row): array`, `app_import_match_review_item(array $row, array $existing): array`, `app_import_apply_uploaded_fields(array $existingItem, array $reviewItem): array`

- [ ] **Step 1: Write the failing test**

```php
<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$checks = [
    'template csv includes type, tmdb_id, and JAG sample row' => [
        'file' => $root . '/includes/functions.php',
        'needles' => ['function app_import_template_csv', 'type,title,year,tmdb_id,status,rating,season,episode,notes', 'JAG', '4376'],
    ],
    'import helpers capture supplied fields and partial overwrite support' => [
        'file' => $root . '/includes/functions.php',
        'needles' => ['function app_import_supplied_fields', 'function app_import_apply_uploaded_fields', "'supplied_fields'"],
    ],
    'tmdb helpers expose guided match builder' => [
        'file' => $root . '/includes/functions.php',
        'needles' => ['function app_import_match_review_item', "'match_status'", "'match_candidates'"],
    ],
];
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php .\tests\import-template-and-matching-test.php`
Expected: FAIL with missing helper names and template strings.

- [ ] **Step 3: Write minimal implementation**

```php
function app_import_template_csv(): string
{
    $rows = [
        ['type','title','year','tmdb_id','status','rating','season','episode','notes'],
        ['tv','JAG','','4376','watchlist','','','','Sample TMDB-linked TV row'],
    ];
    $fp = fopen('php://temp', 'r+');
    foreach ($rows as $row) { fputcsv($fp, $row); }
    rewind($fp);
    return (string)stream_get_contents($fp);
}

function app_import_supplied_fields(array $row): array
{
    $fields = [];
    foreach ($row as $key => $value) {
        if (!is_string($key)) { continue; }
        if (trim((string)$value) !== '') { $fields[] = $key; }
    }
    return array_values(array_unique($fields));
}
```

Add companion helpers that stage `match_status`, `match_candidates`, `matched_tmdb_id`, and merge only uploaded fields into an existing item.

- [ ] **Step 4: Run test to verify it passes**

Run: `php .\tests\import-template-and-matching-test.php`
Expected: PASS with template and helper checks.

- [ ] **Step 5: Commit**

```bash
git add tv-binge-board/includes/functions.php tv-binge-board/includes/tmdb.php tv-binge-board/tests/import-template-and-matching-test.php
git commit -m "Add import template and guided match helpers"
```

### Task 2: Add admin import entry point and target-user import surface

**Files:**
- Modify: `admin/users.php`
- Modify: `import.php`
- Test: `tests/admin-import-entry-points-test.php`

**Interfaces:**
- Consumes: `app_require_admin()`, `app_find_user(string $username): ?array`, `app_import_template_csv(): string`
- Produces: admin per-user import links, target-user context handling in `import.php`, sample template download action

- [ ] **Step 1: Write the failing test**

```php
<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$checks = [
    'admin users page exposes import action per non-admin user' => [
        'file' => $root . '/admin/users.php',
        'needles' => ['Import', '../import.php?u='],
    ],
    'import page accepts admin target-user context' => [
        'file' => $root . '/import.php',
        'needles' => ['app_is_admin($user)', '$targetUsername', 'Target user'],
    ],
    'import page exposes sample csv download' => [
        'file' => $root . '/import.php',
        'needles' => ['sample=1', 'Download sample CSV'],
    ],
];
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php .\tests\admin-import-entry-points-test.php`
Expected: FAIL with missing import action, target-user handling, and sample download.

- [ ] **Step 3: Write minimal implementation**

```php
// admin/users.php
<a class="button secondary" href="../import.php?u=<?= e($username) ?>">Import</a>

// import.php
$targetUsername = app_is_admin($user) ? app_sanitize_username((string)($_GET['u'] ?? $_POST['target_user'] ?? '')) : (string)$user['username'];
if ($targetUsername === '') { $targetUsername = (string)$user['username']; }
if (!app_find_user($targetUsername)) { http_response_code(404); exit('Target user not found.'); }
```

Add a sample-download branch that streams `app_import_template_csv()` with CSV headers and a visible target-user banner for admin imports.

- [ ] **Step 4: Run test to verify it passes**

Run: `php .\tests\admin-import-entry-points-test.php`
Expected: PASS with import link and target-user import surface checks.

- [ ] **Step 5: Commit**

```bash
git add tv-binge-board/admin/users.php tv-binge-board/import.php tv-binge-board/tests/admin-import-entry-points-test.php
git commit -m "Add admin user import entry points"
```

### Task 3: Extend staging and review flow for TMDB suggestions, duplicates, and overwrite

**Files:**
- Modify: `import.php`
- Modify: `includes/functions.php`
- Test: `tests/import-review-flow-test.php`

**Interfaces:**
- Consumes: `app_import_match_review_item(array $row, array $existing): array`, `app_import_apply_uploaded_fields(array $existingItem, array $reviewItem): array`, `app_library(string $username): array`, `app_save_library(string $username, array $library): void`
- Produces: staged review rows with match metadata, skip-duplicate default, overwrite option on confirm, target-user confirmation logic

- [ ] **Step 1: Write the failing test**

```php
<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$checks = [
    'import review shows duplicate skip default and overwrite option' => [
        'file' => $root . '/import.php',
        'needles' => ['Overwrite matching items', 'include_duplicates', 'skip duplicates by default'],
    ],
    'staged review stores match metadata' => [
        'file' => $root . '/import.php',
        'needles' => ["'match_status'", "'match_candidates'", "'supplied_fields'"],
    ],
    'confirm flow supports partial overwrite into existing items' => [
        'file' => $root . '/import.php',
        'needles' => ['overwrite_matches', 'app_import_apply_uploaded_fields', 'app_find_media_index'],
    ],
];
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php .\tests\import-review-flow-test.php`
Expected: FAIL with missing overwrite and match-review logic.

- [ ] **Step 3: Write minimal implementation**

```php
$reviewItem = app_import_match_review_item($row, $existing);
$items[] = $reviewItem;

if ($index === null) {
    $library['items'][] = $itemToSave;
} elseif (!empty($_POST['overwrite_matches'])) {
    $library['items'][$index] = app_import_apply_uploaded_fields($library['items'][$index], $reviewItem);
}
```

Update the review page to show match badges and duplicate status. Keep confirm blocked or visibly flagged when rows still need a match.

- [ ] **Step 4: Run test to verify it passes**

Run: `php .\tests\import-review-flow-test.php`
Expected: PASS with overwrite and staged metadata checks.

- [ ] **Step 5: Commit**

```bash
git add tv-binge-board/import.php tv-binge-board/includes/functions.php tv-binge-board/tests/import-review-flow-test.php
git commit -m "Extend staged import review and overwrite flow"
```

### Task 4: Add inline unresolved-row TMDB match search/apply controls

**Files:**
- Modify: `import.php`
- Modify: `assets/js/app.js`
- Create: `api/import-match-search.php`
- Test: `tests/import-inline-match-test.php`

**Interfaces:**
- Consumes: `app_tmdb_search(string $query): array`, current staged review file shape, CSRF token helpers
- Produces: per-row inline TMDB search, candidate display, manual apply/clear actions for unresolved rows

- [ ] **Step 1: Write the failing test**

```php
<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$checks = [
    'import review exposes inline match controls' => [
        'file' => $root . '/import.php',
        'needles' => ['Needs match', 'Find match', 'match_status'],
    ],
    'match search endpoint exists' => [
        'file' => $root . '/api/import-match-search.php',
        'needles' => ['app_require_login', 'app_tmdb_search', 'Content-Type: application/json'],
    ],
    'app js wires inline row matching' => [
        'file' => $root . '/assets/js/app.js',
        'needles' => ['import-match', 'Find match', 'fetch(`api/import-match-search.php'],
    ],
];
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php .\tests\import-inline-match-test.php`
Expected: FAIL with missing inline controls and endpoint.

- [ ] **Step 3: Write minimal implementation**

```php
// api/import-match-search.php
header('Content-Type: application/json; charset=utf-8');
$user = app_require_login();
app_verify_csrf();
echo json_encode(['results' => app_tmdb_search((string)($_GET['q'] ?? ''))], JSON_UNESCAPED_SLASHES);
```

Add row-level controls on the review page and JS that searches TMDB, renders a compact candidate list, and writes selected TMDB data back into hidden row inputs for confirm.

- [ ] **Step 4: Run test to verify it passes**

Run: `php .\tests\import-inline-match-test.php`
Expected: PASS with inline match UI and endpoint checks.

- [ ] **Step 5: Commit**

```bash
git add tv-binge-board/import.php tv-binge-board/assets/js/app.js tv-binge-board/api/import-match-search.php tv-binge-board/tests/import-inline-match-test.php
git commit -m "Add inline TMDB matching for staged imports"
```

### Task 5: Final verification and polish

**Files:**
- Modify: `import.php`
- Modify: `admin/users.php`
- Modify: `includes/functions.php`
- Modify: `includes/tmdb.php`
- Modify: `assets/js/app.js`
- Modify/Create: `tests/*.php` touched above

**Interfaces:**
- Consumes: all prior tasks' helpers and UI wiring
- Produces: verified end-to-end admin/user import workflow ready to merge

- [ ] **Step 1: Run focused syntax checks**

```bash
php -l .\import.php
php -l .\admin\users.php
php -l .\includes\functions.php
php -l .\includes\tmdb.php
php -l .\api\import-match-search.php
node --check .\assets\js\app.js
```

Expected: all files report no syntax errors.

- [ ] **Step 2: Run focused regression tests**

```bash
php .\tests\import-template-and-matching-test.php
php .\tests\admin-import-entry-points-test.php
php .\tests\import-review-flow-test.php
php .\tests\import-inline-match-test.php
```

Expected: all PASS.

- [ ] **Step 3: Review final diff for only import-related files**

```bash
git diff -- admin/users.php import.php includes/functions.php includes/tmdb.php assets/js/app.js api/import-match-search.php tests
```

Expected: diff is limited to admin import, template download, guided matching, and tests.

- [ ] **Step 4: Commit final integration changes**

```bash
git add admin/users.php import.php includes/functions.php includes/tmdb.php assets/js/app.js api/import-match-search.php tests
git commit -m "Add admin import workflow and guided TMDB matching"
```

- [ ] **Step 5: Push**

```bash
git push origin main
```

Expected: push succeeds without conflict.
