<?php declare(strict_types=1);
/**
 * Filename: frontlines-categories-test.php
 * Revision : 1.1.0
 * Description : Verifies the Frontlines categories lib helpers: read/write with locking,
 *               findCategoryIndex, countCategoryAwards (computed from audit log), and
 *               the edit-categories.php page shell + editor JS surface.
 * Author : Jason Lamb (with help from Claude Code)
 * Created Date : 2026-06-17
 * Modified Date : 2026-06-17
 * Changelog :
 * 1.0.0 initial release
 * 1.1.0 Cover edit-categories.php auth shell and editor JS action endpoints
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
$editJsPath   = $root . '/public/edit-categories.js';
$editCssPath  = $root . '/public/edit-categories.css';
assertTrue(is_file($editPagePath), 'edit-categories.php exists');
assertTrue(is_file($editJsPath),   'public/edit-categories.js exists');
assertTrue(is_file($editCssPath),  'public/edit-categories.css exists');

$editPageSrc = (string) file_get_contents($editPagePath);
assertTrue(strpos($editPageSrc, "requireAuth('frontlines'") !== false, 'edit-categories.php requires Frontlines auth');
assertTrue(strpos($editPageSrc, "'role'") !== false && strpos($editPageSrc, "'admin'") !== false, 'edit-categories.php enforces admin role');
assertTrue(strpos($editPageSrc, 'edit-categories.js') !== false, 'edit-categories.php loads the editor JS');

$editJsSrc = (string) file_get_contents($editJsPath);
assertTrue(strpos($editJsSrc, 'action=list-categories') !== false, 'editor JS calls list-categories');
assertTrue(strpos($editJsSrc, 'action=add-category') !== false,    'editor JS calls add-category');
assertTrue(strpos($editJsSrc, 'action=update-category') !== false, 'editor JS calls update-category');
assertTrue(strpos($editJsSrc, 'action=remove-category') !== false, 'editor JS calls remove-category');

// Cleanup.
foreach (glob($tempDir . '/*') as $f) { unlink($f); }
rmdir($tempDir);

echo "OK frontlines-categories-test" . PHP_EOL;
