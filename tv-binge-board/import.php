<?php
/**
 * File: import.php
 * Project: TV Binge Board
 * Description: CSV/JSON import workflow with CSV column mapping, staging review, duplicate handling, error reports, guided TMDB matching, and admin-targeted imports.
 * Author: Jason Lamb / ChatGPT
 * Created: 2026-07-02
 * Modified: 2026-07-02
 * Revision: 1.4.6
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/tmdb.php';
$user = app_require_login();
$requestedTarget = app_sanitize_username((string)($_GET['u'] ?? $_POST['target_user'] ?? ''));
$targetUsername = app_is_admin($user) && $requestedTarget !== '' ? $requestedTarget : (string)$user['username'];
$target = app_find_user($targetUsername);
if (!$target) { http_response_code(404); exit('Target user not found.'); }
if (!app_is_admin($user) && $targetUsername !== (string)$user['username']) { http_response_code(403); exit('Forbidden.'); }
if (!app_is_admin($user) && !app_can_track($user)) { app_page_header('IMPORT'); echo '<section class="card"><h1>IMPORT DISABLED</h1></section>'; app_page_footer(); exit; }

function app_import_field_options(): array
{
    return [
        '' => 'Skip column',
        'title' => 'Title',
        'type' => 'Type',
        'status' => 'Status',
        'rating' => 'Rating',
        'season' => 'Season',
        'episode' => 'Episode',
        'notes' => 'Notes',
        'overview' => 'Overview',
        'year' => 'Year',
        'tmdb_id' => 'TMDB ID',
        'release_date' => 'Release date',
        'poster_path' => 'Poster path',
        'poster_url' => 'Poster URL',
        'tmdb_url' => 'TMDB URL',
    ];
}

function app_import_normalize_header(string $header): string
{
    $header = strtolower(trim($header));
    $header = preg_replace('/^\xEF\xBB\xBF/', '', $header) ?? $header;
    return preg_replace('/[^a-z0-9]+/', '_', $header) ?? $header;
}

function app_import_guess_field(string $header): string
{
    $normalized = trim(app_import_normalize_header($header), '_');
    $aliases = [
        'title' => ['title', 'name', 'show', 'show_name', 'movie', 'movie_title', 'series', 'series_title'],
        'type' => ['type', 'media_type', 'kind', 'format'],
        'status' => ['status', 'watch_status', 'state'],
        'rating' => ['rating', 'score', 'stars', 'my_rating'],
        'season' => ['season', 'last_season', 'current_season', 's'],
        'episode' => ['episode', 'last_episode', 'current_episode', 'ep', 'e'],
        'notes' => ['notes', 'note', 'comments', 'comment', 'description'],
        'overview' => ['overview', 'summary', 'plot', 'synopsis'],
        'year' => ['year', 'release_year', 'first_air_year'],
        'tmdb_id' => ['tmdb_id', 'tmdb', 'themoviedb_id'],
        'release_date' => ['release_date', 'air_date', 'first_air_date'],
        'poster_path' => ['poster_path'],
        'poster_url' => ['poster_url', 'poster'],
        'tmdb_url' => ['tmdb_url', 'url'],
    ];
    foreach ($aliases as $field => $fieldAliases) {
        if (in_array($normalized, $fieldAliases, true)) { return $field; }
    }
    return '';
}

function app_import_csv_headers(string $path): array
{
    $handle = fopen($path, 'r');
    if (!$handle) { throw new RuntimeException('Unable to read CSV headers.'); }
    $headers = fgetcsv($handle);
    fclose($handle);
    if (!is_array($headers) || $headers === []) { throw new RuntimeException('CSV file has no header row.'); }
    $clean = [];
    foreach ($headers as $header) {
        $header = preg_replace('/^\xEF\xBB\xBF/', '', (string)$header) ?? (string)$header;
        $header = trim($header);
        if ($header !== '') { $clean[] = $header; }
    }
    if ($clean === []) { throw new RuntimeException('CSV header row is empty.'); }
    return $clean;
}

function app_import_map_row(array $row, array $headers, array $columnMap): array
{
    $mapped = [];
    $usedFields = [];
    foreach ($headers as $index => $header) {
        $field = (string)($columnMap[(string)$index] ?? '');
        if ($field === '' || !array_key_exists($field, app_import_field_options())) { continue; }
        if (isset($usedFields[$field])) { continue; }
        $mapped[$field] = $row[$header] ?? '';
        $usedFields[$field] = true;
    }
    return $mapped;
}

function app_import_is_blank_row(array $row): bool
{
    foreach ($row as $value) {
        if (trim((string)$value) !== '') { return false; }
    }
    return true;
}

function app_import_write_error_report(string $targetUsername, string $id, array $errors): string
{
    if ($errors === []) { return ''; }
    $filename = 'import-errors-' . $id . '.csv';
    $path = app_user_dir($targetUsername) . DIRECTORY_SEPARATOR . 'imports' . DIRECTORY_SEPARATOR . $filename;
    $handle = fopen($path, 'w');
    if (!$handle) { return ''; }
    fputcsv($handle, ['row', 'reason', 'source_row_json']);
    foreach ($errors as $error) {
        fputcsv($handle, [
            (string)($error['row'] ?? ''),
            (string)($error['reason'] ?? 'Unknown import error.'),
            json_encode($error['source_row'] ?? [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ]);
    }
    fclose($handle);
    return $filename;
}

function app_import_build_review(string $targetUsername, string $id, string $sourceFile, array $rawRows, array $existingLookup, array &$errors): array
{
    $items = [];
    foreach ($rawRows as $rowIndex => $row) {
        if (!is_array($row)) { continue; }
        if (app_import_is_blank_row($row)) { continue; }
        $reviewItem = app_import_match_review_item($row, $existingLookup);
        if (trim((string)($reviewItem['title'] ?? '')) === '') {
            $errors[] = [
                'row' => (int)$rowIndex + 2,
                'reason' => 'Missing title after column mapping.',
                'source_row' => $row,
            ];
            continue;
        }
        $items[] = $reviewItem;
    }
    $errorReportFile = app_import_write_error_report($targetUsername, $id, $errors);
    return [
        '_meta' => app_json_meta('Import review file.'),
        'source_file' => $sourceFile,
        'created_at' => date(DATE_ATOM),
        'target_user' => $targetUsername,
        'error_count' => count($errors),
        'error_report_file' => $errorReportFile,
        'items' => $items,
    ];
}

function app_import_review_url(string $id, string $targetUsername, array $user): string
{
    return 'import.php?review=' . rawurlencode($id) . (app_is_admin($user) ? '&u=' . rawurlencode($targetUsername) : '');
}

if (isset($_GET['sample']) && $_GET['sample'] === '1') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="tv-binge-board-import-sample.csv"');
    echo app_import_template_csv();
    exit;
}

$errorReportId = app_sanitize_username((string)($_GET['errors'] ?? ''));
if ($errorReportId !== '') {
    $reportPath = app_user_dir($targetUsername) . DIRECTORY_SEPARATOR . 'imports' . DIRECTORY_SEPARATOR . 'import-errors-' . $errorReportId . '.csv';
    if (!is_file($reportPath)) { http_response_code(404); exit('Import error report not found.'); }
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="import-errors-' . $errorReportId . '.csv"');
    readfile($reportPath);
    exit;
}

$reviewId = app_sanitize_username((string)($_GET['review'] ?? ''));
$mapId = app_sanitize_username((string)($_GET['map'] ?? ''));
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    app_verify_csrf();
    $action = (string)($_POST['action'] ?? 'upload');
    try {
        if ($action === 'upload') {
            if (empty($_FILES['import_file']['tmp_name']) || !is_uploaded_file($_FILES['import_file']['tmp_name'])) { throw new RuntimeException('Upload failed.'); }
            if ((int)($_FILES['import_file']['size'] ?? 0) > APP_MAX_UPLOAD_BYTES) { throw new RuntimeException('Import file is too large.'); }
            $name = basename((string)$_FILES['import_file']['name']);
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            if (!in_array($ext, ['csv','json'], true)) { throw new RuntimeException('Only CSV and JSON imports are supported.'); }
            $id = date('YmdHis') . '-' . bin2hex(random_bytes(3));
            $safeName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $name);
            $dest = app_user_dir($targetUsername) . DIRECTORY_SEPARATOR . 'imports' . DIRECTORY_SEPARATOR . $id . '-' . $safeName;
            if (!move_uploaded_file($_FILES['import_file']['tmp_name'], $dest)) { throw new RuntimeException('Unable to save uploaded file.'); }
            if ($ext === 'csv') {
                $headers = app_import_csv_headers($dest);
                $detectedMap = [];
                foreach ($headers as $index => $header) { $detectedMap[(string)$index] = app_import_guess_field($header); }
                $mapping = [
                    '_meta' => app_json_meta('CSV import column mapping file.'),
                    'source_file' => basename($dest),
                    'created_at' => date(DATE_ATOM),
                    'target_user' => $targetUsername,
                    'headers' => $headers,
                    'detected_map' => $detectedMap,
                ];
                app_save_json(app_user_dir($targetUsername) . DIRECTORY_SEPARATOR . 'imports' . DIRECTORY_SEPARATOR . 'map-' . $id . '.json', $mapping);
                header('Location: import.php?map=' . rawurlencode($id) . (app_is_admin($user) ? '&u=' . rawurlencode($targetUsername) : ''));
                exit;
            }
            $decoded = json_decode(file_get_contents($dest) ?: '', true);
            if (isset($decoded['items']) && is_array($decoded['items'])) { $rawRows = $decoded['items']; }
            elseif (is_array($decoded)) { $rawRows = $decoded; }
            else { throw new RuntimeException('JSON import could not be parsed.'); }
            $library = app_library($targetUsername);
            $existingLookup = app_import_build_existing_lookup($library);
            $errors = [];
            $review = app_import_build_review($targetUsername, $id, basename($dest), $rawRows, $existingLookup, $errors);
            app_save_json(app_user_dir($targetUsername) . DIRECTORY_SEPARATOR . 'imports' . DIRECTORY_SEPARATOR . 'review-' . $id . '.json', $review);
            app_log_activity((string)$user['username'], 'import-staged', $targetUsername, ['count' => count($review['items'] ?? []), 'errors' => count($errors)]);
            header('Location: ' . app_import_review_url($id, $targetUsername, $user));
            exit;
        }
        if ($action === 'map_columns') {
            $id = app_sanitize_username((string)($_POST['map_id'] ?? ''));
            $mapPath = app_user_dir($targetUsername) . DIRECTORY_SEPARATOR . 'imports' . DIRECTORY_SEPARATOR . 'map-' . $id . '.json';
            $mapping = app_load_json($mapPath, []);
            $sourceFile = basename((string)($mapping['source_file'] ?? ''));
            $headers = is_array($mapping['headers'] ?? null) ? array_values(array_map('strval', $mapping['headers'])) : [];
            if ($id === '' || $sourceFile === '' || $headers === []) { throw new RuntimeException('Column mapping file is invalid.'); }
            $sourcePath = app_user_dir($targetUsername) . DIRECTORY_SEPARATOR . 'imports' . DIRECTORY_SEPARATOR . $sourceFile;
            if (!is_file($sourcePath)) { throw new RuntimeException('Original CSV import file is missing.'); }
            $columnMap = array_map('strval', (array)($_POST['column_map'] ?? []));
            if (!in_array('title', $columnMap, true)) { throw new RuntimeException('Map one column to Title before continuing.'); }
            $rawRows = app_read_csv_assoc($sourcePath);
            $mappedRows = [];
            foreach ($rawRows as $row) {
                if (!is_array($row)) { continue; }
                $mappedRows[] = app_import_map_row($row, $headers, $columnMap);
            }
            $library = app_library($targetUsername);
            $existingLookup = app_import_build_existing_lookup($library);
            $errors = [];
            $review = app_import_build_review($targetUsername, $id, $sourceFile, $mappedRows, $existingLookup, $errors);
            $review['column_map'] = $columnMap;
            $review['source_headers'] = $headers;
            app_save_json(app_user_dir($targetUsername) . DIRECTORY_SEPARATOR . 'imports' . DIRECTORY_SEPARATOR . 'review-' . $id . '.json', $review);
            app_log_activity((string)$user['username'], 'import-mapped', $targetUsername, ['count' => count($review['items'] ?? []), 'errors' => count($errors)]);
            header('Location: ' . app_import_review_url($id, $targetUsername, $user));
            exit;
        }
        if ($action === 'confirm') {
            $id = app_sanitize_username((string)($_POST['review_id'] ?? ''));
            $reviewPath = app_user_dir($targetUsername) . DIRECTORY_SEPARATOR . 'imports' . DIRECTORY_SEPARATOR . 'review-' . $id . '.json';
            $review = app_load_json($reviewPath, []);
            $library = app_library($targetUsername);
            $added = 0;
            $updated = 0;
            $skipped = 0;
            $overwrite = !empty($_POST['overwrite_matches']);
            $excludeRows = array_fill_keys(array_map('strval', array_keys((array)($_POST['exclude_row'] ?? []))), true);
            $matchedIds = (array)($_POST['matched_tmdb_id'] ?? []);
            $matchedTypes = (array)($_POST['matched_type'] ?? []);
            foreach (($review['items'] ?? []) as $index => $item) {
                if (!is_array($item)) { continue; }
                if (isset($excludeRows[(string)$index])) { $skipped++; continue; }
                $matchedTmdbId = max(0, (int)($matchedIds[$index] ?? ($item['matched_tmdb_id'] ?? 0)));
                $matchedType = app_sanitize_username((string)($matchedTypes[$index] ?? ($item['matched_type'] ?? '')));
                if ($matchedTmdbId > 0 && in_array($matchedType, ['movie','tv'], true)) {
                    $item['matched_tmdb_id'] = $matchedTmdbId;
                    $item['matched_type'] = $matchedType;
                    try {
                        $details = app_tmdb_details($matchedType, $matchedTmdbId, false);
                        $base = app_normalize_import_item((array)($item['source_row'] ?? []));
                        $base = app_apply_tmdb_details_to_item($base, $details, true);
                        $base['uid'] = app_make_media_uid($matchedType, $matchedTmdbId, (string)($base['title'] ?? ''));
                        $base['source_row'] = $item['source_row'] ?? [];
                        $base['supplied_fields'] = $item['supplied_fields'] ?? [];
                        $base['matched_tmdb_id'] = $matchedTmdbId;
                        $base['matched_type'] = $matchedType;
                        $base['duplicate'] = $item['duplicate'] ?? false;
                        $item = $base;
                    } catch (Throwable $ex) {
                    }
                }
                $indexInLibrary = app_find_media_index($library, (string)($item['uid'] ?? ''));
                if ($indexInLibrary === null) {
                    $library['items'][] = app_import_apply_uploaded_fields($item, $item);
                    $added++;
                    continue;
                }
                if (!empty($item['duplicate']) && !$overwrite) {
                    $skipped++;
                    continue;
                }
                if ($overwrite) {
                    $library['items'][$indexInLibrary] = app_import_apply_uploaded_fields($library['items'][$indexInLibrary], $item);
                    $updated++;
                }
            }
            app_save_library($targetUsername, $library);
            app_log_activity((string)$user['username'], 'import-confirmed', $targetUsername, ['added' => $added, 'updated' => $updated, 'skipped' => $skipped]);
            app_flash('IMPORT COMPLETE. Added ' . $added . ', updated ' . $updated . ', skipped ' . $skipped . '.', 'success');
            header('Location: ' . (app_is_admin($user) ? 'admin/user-library.php?u=' . rawurlencode($targetUsername) : 'watchlist.php'));
            exit;
        }
    } catch (Throwable $ex) {
        $error = $ex->getMessage();
    }
}

$mapping = [];
$mappingRows = [];
if ($mapId !== '') {
    $mapping = app_load_json(app_user_dir($targetUsername) . DIRECTORY_SEPARATOR . 'imports' . DIRECTORY_SEPARATOR . 'map-' . $mapId . '.json', []);
    $sourceFile = basename((string)($mapping['source_file'] ?? ''));
    if ($sourceFile !== '') {
        $sourcePath = app_user_dir($targetUsername) . DIRECTORY_SEPARATOR . 'imports' . DIRECTORY_SEPARATOR . $sourceFile;
        if (is_file($sourcePath)) { $mappingRows = array_slice(app_read_csv_assoc($sourcePath), 0, 5); }
    }
}

$review = [];
if ($reviewId !== '') {
    $review = app_load_json(app_user_dir($targetUsername) . DIRECTORY_SEPARATOR . 'imports' . DIRECTORY_SEPARATOR . 'review-' . $reviewId . '.json', []);
}
app_page_header('IMPORT');
?>
<section class="card">
    <h1>IMPORT LIBRARY DATA</h1>
    <?php if ($error): ?><div class="alert danger"><?= e($error) ?></div><?php endif; ?>
    <?php if (app_is_admin($user)): ?><p class="muted">Target user: <strong>@<?= e($targetUsername) ?></strong></p><?php endif; ?>
    <p>Upload CSV or JSON. CSV files now stop at a column-mapping screen first, then the app stages the data so you can review duplicates and weak TMDB matches before anything is written.</p>
    <div class="actions wrap-actions"><a class="button secondary" href="<?= e(app_href('import.php?sample=1' . (app_is_admin($user) ? '&u=' . rawurlencode($targetUsername) : ''))) ?>">Download sample CSV</a></div>
    <form method="post" enctype="multipart/form-data" class="stack">
        <input type="hidden" name="csrf_token" value="<?= e(app_csrf_token()) ?>">
        <input type="hidden" name="action" value="upload">
        <?php if (app_is_admin($user)): ?><input type="hidden" name="target_user" value="<?= e($targetUsername) ?>"><?php endif; ?>
        <label>CSV or JSON file <input type="file" name="import_file" accept=".csv,.json,text/csv,application/json" required></label>
        <button type="submit">Upload for review</button>
    </form>
</section>
<?php if ($mapping): $headers = is_array($mapping['headers'] ?? null) ? array_values(array_map('strval', $mapping['headers'])) : []; $detectedMap = (array)($mapping['detected_map'] ?? []); ?>
<section class="card">
    <h2>MAP CSV COLUMNS</h2>
    <p class="muted">Match each uploaded CSV column to the app field it should populate. Leave unused columns set to Skip column.</p>
    <form method="post" class="stack">
        <input type="hidden" name="csrf_token" value="<?= e(app_csrf_token()) ?>">
        <input type="hidden" name="action" value="map_columns">
        <input type="hidden" name="map_id" value="<?= e($mapId) ?>">
        <?php if (app_is_admin($user)): ?><input type="hidden" name="target_user" value="<?= e($targetUsername) ?>"><?php endif; ?>
        <div class="import-map-grid">
            <?php foreach ($headers as $index => $header): $selected = (string)($detectedMap[(string)$index] ?? ''); ?>
                <label><?= e($header) ?>
                    <select name="column_map[<?= e((string)$index) ?>]">
                        <?php foreach (app_import_field_options() as $field => $label): ?>
                            <option value="<?= e($field) ?>" <?= $selected === $field ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            <?php endforeach; ?>
        </div>
        <?php if ($mappingRows): ?>
            <details class="import-preview" open>
                <summary>Preview first <?= e((string)count($mappingRows)) ?> row(s)</summary>
                <div class="table-scroll">
                    <table class="import-preview-table">
                        <thead><tr><?php foreach ($headers as $header): ?><th><?= e($header) ?></th><?php endforeach; ?></tr></thead>
                        <tbody>
                            <?php foreach ($mappingRows as $row): ?>
                                <tr><?php foreach ($headers as $header): ?><td><?= e(app_excerpt((string)($row[$header] ?? ''), 80)) ?></td><?php endforeach; ?></tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </details>
        <?php endif; ?>
        <button type="submit">Continue to import review</button>
    </form>
</section>
<?php endif; ?>
<?php if ($review): $items = $review['items'] ?? []; ?>
<section class="card">
    <h2>REVIEW IMPORT</h2>
    <p class="muted"><?= e((string)count($items)) ?> parsed item(s). Duplicates are skipped by default. Rows marked <strong>Needs match</strong> should be reviewed before confirm.</p>
    <?php if ((int)($review['error_count'] ?? 0) > 0 && !empty($review['error_report_file'])): ?>
        <p class="alert danger"><?= e((string)$review['error_count']) ?> row(s) could not be staged. <a href="<?= e(app_href('import.php?errors=' . rawurlencode($reviewId) . (app_is_admin($user) ? '&u=' . rawurlencode($targetUsername) : ''))) ?>">Download import error report</a>.</p>
    <?php endif; ?>
    <form method="post" class="stack">
        <input type="hidden" name="csrf_token" value="<?= e(app_csrf_token()) ?>">
        <input type="hidden" name="action" value="confirm">
        <input type="hidden" name="review_id" value="<?= e($reviewId) ?>">
        <?php if (app_is_admin($user)): ?><input type="hidden" name="target_user" value="<?= e($targetUsername) ?>"><?php endif; ?>
        <label class="checkbox-row"><input type="checkbox" name="overwrite_matches" value="1"> Overwrite matching items</label>
        <div class="media-list">
            <?php foreach ($items as $index => $item): ?>
            <?php
                $matchStatus = (string)($item['match_status'] ?? 'unmatched');
                $matchStatusLabel = str_replace('_', ' ', ucwords($matchStatus, '_'));
                $matchedId = (int)($item['matched_tmdb_id'] ?? 0);
                $matchedType = (string)($item['matched_type'] ?? '');
                $candidates = is_array($item['match_candidates'] ?? null) ? $item['match_candidates'] : [];
            ?>
            <article class="media-card import-match-card" data-row-index="<?= e((string)$index) ?>">
                <div class="poster placeholder-poster">?</div>
                <div class="media-body">
                    <h3><?= e((string)($item['title'] ?? 'Untitled')) ?></h3>
                    <p class="muted"><?= e((string)($item['type'] ?? '')) ?><?= !empty($item['year']) ? ' · ' . e((string)$item['year']) : '' ?><?= !empty($item['duplicate']) ? ' · duplicate' : '' ?> · <?= e($matchStatusLabel) ?></p>
                    <p><?= e((string)($item['notes'] ?? '')) ?></p>
                    <input type="hidden" name="matched_tmdb_id[<?= e((string)$index) ?>]" value="<?= e((string)$matchedId) ?>">
                    <input type="hidden" name="matched_type[<?= e((string)$index) ?>]" value="<?= e($matchedType) ?>">
                    <div class="actions small wrap-actions">
                        <label class="checkbox-row"><input type="checkbox" name="exclude_row[<?= e((string)$index) ?>]" value="1"> Exclude row</label>
                    </div>
                    <div class="stack import-match" data-row-index="<?= e((string)$index) ?>">
                        <label>Find match
                            <input type="text" value="<?= e((string)($item['match_query'] ?? $item['title'] ?? '')) ?>" data-match-query>
                        </label>
                        <button class="secondary" type="button" data-find-match>Find match</button>
                        <div class="muted" data-match-summary>
                            <?php if ($matchedId > 0): ?>Selected match: <?= e((string)$matchedType) ?> #<?= e((string)$matchedId) ?><?php else: ?>No TMDB match selected yet.<?php endif; ?>
                        </div>
                        <div class="stack" data-match-results>
                            <?php foreach ($candidates as $candidate): ?>
                            <button class="secondary" type="button" data-apply-match data-tmdb-id="<?= e((string)($candidate['tmdb_id'] ?? 0)) ?>" data-type="<?= e((string)($candidate['type'] ?? '')) ?>"><?= e((string)($candidate['title'] ?? '')) ?><?= !empty($candidate['year']) ? ' (' . e((string)$candidate['year']) . ')' : '' ?></button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </article>
            <?php endforeach; ?>
        </div>
        <button type="submit">Confirm import</button>
    </form>
</section>
<?php endif; app_page_footer(); ?>
