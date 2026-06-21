<?php declare(strict_types=1);
/**
 * Filename: frontlines-categories-test.php
 * Revision : 1.5.0
 * Description : Verifies the Frontlines categories lib helpers: read/write with locking,
 *               findCategoryIndex, countCategoryAwards (computed from audit log), and
 *               the edit-categories.php page shell + editor JS surface.
 * Author : Jason Lamb (with help from Claude Code)
 * Created Date : 2026-06-17
 * Modified Date : 2026-06-21
 * Changelog :
 * 1.0.0 initial release
 * 1.1.0 Cover edit-categories.php auth shell and editor JS action endpoints
 * 1.2.0 Cover enter-scores-category.php shell + scorer JS award/cap flow
 * 1.3.0 Cover Phase 4 cross-page navigation: data attrs on Frontlines pages and link rendering in shared JS
 * 1.4.0 Cover ranked categories with manual 12000-to-1000 award values
 * 1.5.0 Cover custom category sortOrder in editor and scorer views
 */

function assertTrue(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}" . PHP_EOL);
        exit(1);
    }
}

function assertSame($expected, $actual, string $message): void
{
    if ($expected !== $actual) {
        fwrite(STDERR, "FAIL: {$message} (expected " . var_export($expected, true) . ", got " . var_export($actual, true) . ")" . PHP_EOL);
        exit(1);
    }
}

// Use a temporary data file so we never touch the live frontlines categories file.
$tempDir = sys_get_temp_dir() . '/scoreboard-categories-test-' . bin2hex(random_bytes(4));
mkdir($tempDir, 0775, true);
define('CATEGORIES_DATA_FILE', $tempDir . '/categories.json');

// Pull in only the categories portion of scoreboard_lib by extracting the helper
// definitions and ignoring the SCOREBOARD_DATA_FILE const (which would collide with
// the live frontlines lib if both were loaded in the same process). We use a small
// trick: read the file, strip the const re-definition for SCOREBOARD_DATA_FILE only
// when running under this test, then eval the body. Since the lib is small and we
// only need the category helpers, this keeps the test hermetic.
$libPath = dirname(__DIR__) . '/frontlines/scoreboard_lib.php';
$libSrc  = (string) file_get_contents($libPath);
// Strip the opening <?php and the two const declarations (CATEGORIES_DATA_FILE is
// already defined above; SCOREBOARD_DATA_FILE is not needed here).
$libSrc = preg_replace('/^<\?php[^\n]*[\r\n]+/', '', $libSrc, 1);
$libSrc = preg_replace('/const\s+SCOREBOARD_DATA_FILE[^;]+;\s*[\r\n]+/', '', $libSrc);
$libSrc = preg_replace('/const\s+CATEGORIES_DATA_FILE[^;]+;\s*[\r\n]+/', '', $libSrc);
eval($libSrc);

// ---- defaultCategoriesData ----
$default = defaultCategoriesData();
assertTrue(is_array($default['categories']) && count($default['categories']) === 0, 'default categories list is empty array');
assertTrue(array_key_exists('updatedAt', $default), 'default has updatedAt key');

// ---- ensure + read on empty data ----
ensureCategoriesDataFile();
$initial = readCategoriesData();
assertSame([], $initial['categories'], 'fresh categories file has empty categories array');

// ---- write a category ----
$now = gmdate('c');
$saved = writeCategoriesData(function (array $data) use ($now): array {
    $data['categories'][] = [
        'id'               => 'cat-test-water',
        'name'             => 'Water Challenge',
        'points'           => 100,
        'maxAwardsPerTeam' => 1,
        'active'           => true,
        'created_at'       => $now,
        'modified_at'      => $now,
    ];
    return $data;
});
assertSame(1, count($saved['categories']), 'write returns updated categories list');
assertTrue($saved['updatedAt'] !== null, 'write stamps updatedAt');

// ---- findCategoryIndex ----
$idx = findCategoryIndex($saved, 'cat-test-water');
assertSame(0, $idx, 'findCategoryIndex returns 0 for first inserted category');
assertSame(null, findCategoryIndex($saved, 'cat-does-not-exist'), 'findCategoryIndex returns null for unknown id');

// ---- countCategoryAwards from a synthetic audit log ----
$auditPath = $tempDir . '/audit.json';
file_put_contents($auditPath, json_encode([
    ['action' => 'award-category', 'team_id' => 'team-red',    'category_id' => 'cat-test-water'],
    ['action' => 'award-category', 'team_id' => 'team-red',    'category_id' => 'cat-test-water'],
    ['action' => 'award-category', 'team_id' => 'team-red',    'category_id' => 'cat-other'],
    ['action' => 'award-category', 'team_id' => 'team-blue',   'category_id' => 'cat-test-water'],
    ['action' => 'adjust',         'team_id' => 'team-red',    'category_id' => 'cat-test-water'],
    'not-an-array',
]));
assertSame(2, countCategoryAwards($auditPath, 'team-red',  'cat-test-water'), 'counts only matching award entries');
assertSame(1, countCategoryAwards($auditPath, 'team-blue', 'cat-test-water'), 'counts per-team');
assertSame(0, countCategoryAwards($auditPath, 'team-red',  'cat-not-there'), 'returns 0 when no matches');
assertSame(0, countCategoryAwards($tempDir . '/missing-audit.json', 'team-red', 'cat-test-water'), 'returns 0 when audit file is missing');

// ---- update + remove ----
$updated = writeCategoriesData(function (array $data): array {
    $idx = findCategoryIndex($data, 'cat-test-water');
    $data['categories'][$idx]['points'] = 250;
    $data['categories'][$idx]['maxAwardsPerTeam'] = null;
    return $data;
});
assertSame(250, $updated['categories'][0]['points'], 'update preserves and overwrites field');
assertSame(null, $updated['categories'][0]['maxAwardsPerTeam'], 'maxAwardsPerTeam can be null (unlimited)');

$removed = writeCategoriesData(function (array $data): array {
    $idx = findCategoryIndex($data, 'cat-test-water');
    array_splice($data['categories'], $idx, 1);
    return $data;
});
assertSame(0, count($removed['categories']), 'remove deletes the category');

// ---- sample file shape ----
$samplePath = dirname(__DIR__) . '/frontlines/data/categories.sample.json';
$sample = json_decode((string) file_get_contents($samplePath), true);
assertTrue(is_array($sample) && isset($sample['categories']) && is_array($sample['categories']), 'sample file has categories array');
foreach ($sample['categories'] as $cat) {
    foreach (['id', 'name', 'points', 'maxAwardsPerTeam', 'active', 'created_at', 'modified_at'] as $field) {
        assertTrue(array_key_exists($field, $cat), "sample category has '{$field}' field");
    }
    assertTrue($cat['points'] !== 0 && is_int($cat['points']), 'sample category points is a non-zero int');
}

// ---- edit-categories.php shell + JS + CSS exist and load expected references ----
$root = dirname(__DIR__);
$editPagePath = $root . '/frontlines/edit-categories.php';
$apiPath      = $root . '/frontlines/api.php';
$editJsPath   = $root . '/public/edit-categories.js';
$editCssPath  = $root . '/public/edit-categories.css';
$categoryCssPath = $root . '/public/category-entry.css';
assertTrue(is_file($editPagePath), 'edit-categories.php exists');
assertTrue(is_file($apiPath),      'frontlines/api.php exists');
assertTrue(is_file($editJsPath),   'public/edit-categories.js exists');
assertTrue(is_file($editCssPath),  'public/edit-categories.css exists');
assertTrue(is_file($categoryCssPath), 'public/category-entry.css exists');

$editPageSrc = (string) file_get_contents($editPagePath);
$apiSrc = (string) file_get_contents($apiPath);
assertTrue(strpos($editPageSrc, "requireAuth('frontlines'") !== false, 'edit-categories.php requires Frontlines auth');
assertTrue(strpos($editPageSrc, "'role'") !== false && strpos($editPageSrc, "'admin'") !== false, 'edit-categories.php enforces admin role');
assertTrue(strpos($editPageSrc, 'edit-categories.js') !== false, 'edit-categories.php loads the editor JS');

$editJsSrc = (string) file_get_contents($editJsPath);
assertTrue(strpos($editJsSrc, 'action=list-categories') !== false, 'editor JS calls list-categories');
assertTrue(strpos($editJsSrc, 'action=add-category') !== false,    'editor JS calls add-category');
assertTrue(strpos($editJsSrc, 'action=update-category') !== false, 'editor JS calls update-category');
assertTrue(strpos($editJsSrc, 'action=remove-category') !== false, 'editor JS calls remove-category');
assertTrue(strpos($editJsSrc, 'scoringMode') !== false, 'editor JS should manage category scoring mode.');
assertTrue(strpos($editJsSrc, 'ranked') !== false, 'editor JS should expose ranked category mode.');
assertTrue(strpos($editJsSrc, 'sortOrder') !== false, 'editor JS should manage custom category sort order.');
assertTrue(strpos($editJsSrc, 'sortCategories') !== false, 'editor JS should sort categories by custom order.');
assertTrue(strpos($apiSrc, 'RANKED_CATEGORY_POINTS') !== false, 'API should define ranked category point values.');
assertTrue(strpos($apiSrc, 'awardPoints') !== false, 'API should accept explicit ranked award points.');
assertTrue(strpos($apiSrc, 'Team already has an award for this ranked category.') !== false, 'API should block duplicate ranked category awards per team.');
assertTrue(strpos($apiSrc, 'nextCategorySortOrder') !== false, 'API should assign sortOrder to new categories.');

// ---- enter-scores-category.php shell + JS exist and wire up the award flow ----
$scorerPagePath = $root . '/frontlines/enter-scores-category.php';
$scorerJsPath   = $root . '/public/category-entry.js';
$scorerCssPath  = $root . '/public/category-entry.css';
assertTrue(is_file($scorerPagePath), 'enter-scores-category.php exists');
assertTrue(is_file($scorerJsPath),   'public/category-entry.js exists');
assertTrue(is_file($scorerCssPath),  'public/category-entry.css exists');

$scorerPageSrc = (string) file_get_contents($scorerPagePath);
assertTrue(strpos($scorerPageSrc, "requireAuth('frontlines'") !== false, 'enter-scores-category.php requires Frontlines auth');
assertTrue(strpos($scorerPageSrc, 'category-entry.js') !== false, 'enter-scores-category.php loads the scorer JS');
assertTrue(strpos($scorerPageSrc, 'data-edit-categories-url') !== false, 'enter-scores-category.php exposes edit-categories-url (gated by admin role)');

$scorerJsSrc = (string) file_get_contents($scorerJsPath);
assertTrue(strpos($scorerJsSrc, 'action=list-categories') !== false, 'scorer JS fetches list-categories');
assertTrue(strpos($scorerJsSrc, 'action=scores') !== false,          'scorer JS fetches scores');
assertTrue(strpos($scorerJsSrc, 'action=audit') !== false,           'scorer JS fetches audit for cap-counting');
assertTrue(strpos($scorerJsSrc, 'action=award-category') !== false,  'scorer JS posts award-category');
assertTrue(strpos($scorerJsSrc, 'maxAwardsPerTeam') !== false,       'scorer JS respects maxAwardsPerTeam for client-side disable');
assertTrue(strpos($scorerJsSrc, 'RANKED_CATEGORY_POINTS') !== false, 'scorer JS should render the 12000-to-1000 ranked point ladder.');
assertTrue(strpos($scorerJsSrc, 'awardPoints=') !== false, 'scorer JS should submit explicit ranked award points.');
assertTrue(strpos($scorerJsSrc, 'category-ranked-value-grid') !== false, 'scorer JS should render ranked value buttons.');
assertTrue(strpos($scorerJsSrc, 'sortCategories') !== false, 'scorer JS should sort categories by custom order.');

$categoryCssSrc = (string) file_get_contents($categoryCssPath);
assertTrue(strpos($categoryCssSrc, '.category-ranked-value-grid') !== false, 'category-entry.css should style ranked value grids.');

// ---- Phase 4: cross-page navigation wiring ----
$adminPagePath = $root . '/frontlines/enter-scores.php';
$quickPagePath = $root . '/frontlines/enter-scores-quick.php';
$adminPageSrc  = (string) file_get_contents($adminPagePath);
$quickPageSrc  = (string) file_get_contents($quickPagePath);
assertTrue(strpos($adminPageSrc, 'data-category-entry-url') !== false, 'frontlines enter-scores.php exposes data-category-entry-url');
assertTrue(strpos($adminPageSrc, 'data-edit-categories-url') !== false, 'frontlines enter-scores.php exposes data-edit-categories-url (gated by admin role)');
assertTrue(strpos($quickPageSrc, 'data-category-entry-url') !== false, 'frontlines enter-scores-quick.php exposes data-category-entry-url');
assertTrue(strpos($quickPageSrc, 'data-edit-categories-url') !== false, 'frontlines enter-scores-quick.php exposes data-edit-categories-url (gated by admin role)');

$appJsPath   = $root . '/public/app.js';
$quickJsPath = $root . '/public/quick-entry.js';
$appJsSrc    = (string) file_get_contents($appJsPath);
$quickJsSrc  = (string) file_get_contents($quickJsPath);
assertTrue(strpos($appJsSrc, 'categoryEntryUrl')   !== false, 'app.js reads categoryEntryUrl');
assertTrue(strpos($appJsSrc, 'editCategoriesUrl')  !== false, 'app.js reads editCategoriesUrl');
assertTrue(strpos($appJsSrc, 'Enter Categories')   !== false, 'app.js renders Enter Categories link label');
assertTrue(strpos($appJsSrc, 'Edit Categories')    !== false, 'app.js renders Edit Categories link label');
assertTrue(strpos($quickJsSrc, 'categoryEntryUrl')  !== false, 'quick-entry.js reads categoryEntryUrl');
assertTrue(strpos($quickJsSrc, 'editCategoriesUrl') !== false, 'quick-entry.js reads editCategoriesUrl');
assertTrue(strpos($quickJsSrc, 'Enter Categories')  !== false, 'quick-entry.js renders Enter Categories link label');
assertTrue(strpos($quickJsSrc, 'Edit Categories')   !== false, 'quick-entry.js renders Edit Categories link label');

$teamsSrc      = (string) file_get_contents($root . '/frontlines/teams.php');
$editRosterSrc = (string) file_get_contents($root . '/frontlines/edit-roster.php');
assertTrue(strpos($teamsSrc, './enter-scores-category.php') !== false, 'teams.php links to Enter Categories');
assertTrue(strpos($teamsSrc, './edit-categories.php')       !== false, 'teams.php links to Edit Categories');
assertTrue(strpos($editRosterSrc, './enter-scores-category.php') !== false, 'edit-roster.php links to Enter Categories');
assertTrue(strpos($editRosterSrc, './edit-categories.php')       !== false, 'edit-roster.php links to Edit Categories');

// Cleanup.
foreach (glob($tempDir . '/*') as $f) { unlink($f); }
rmdir($tempDir);

echo "OK frontlines-categories-test" . PHP_EOL;
