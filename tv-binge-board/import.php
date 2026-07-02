<?php
/**
 * File: import.php
 * Project: TV Binge Board
 * Description: CSV and JSON import workflow with staging review, duplicate handling, guided TMDB matching, and admin-targeted imports.
 * Author: Jason Lamb / ChatGPT
 * Created: 2026-07-02
 * Modified: 2026-07-02
 * Revision: 1.4.3
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

if (isset($_GET['sample']) && $_GET['sample'] === '1') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="tv-binge-board-import-sample.csv"');
    echo app_import_template_csv();
    exit;
}

$reviewId = app_sanitize_username((string)($_GET['review'] ?? ''));
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    app_verify_csrf();
    $action = (string)($_POST['action'] ?? 'upload');
    try {
        if ($action === 'upload') {
            if (empty($_FILES['import_file']['tmp_name']) || !is_uploaded_file($_FILES['import_file']['tmp_name'])) { throw new RuntimeException('Upload failed.'); }
            $name = basename((string)$_FILES['import_file']['name']);
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            if (!in_array($ext, ['csv','json'], true)) { throw new RuntimeException('Only CSV and JSON imports are supported.'); }
            $id = date('YmdHis') . '-' . bin2hex(random_bytes(3));
            $dest = app_user_dir($targetUsername) . DIRECTORY_SEPARATOR . 'imports' . DIRECTORY_SEPARATOR . $id . '-' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $name);
            if (!move_uploaded_file($_FILES['import_file']['tmp_name'], $dest)) { throw new RuntimeException('Unable to save uploaded file.'); }
            $rawRows = [];
            if ($ext === 'csv') {
                $rawRows = app_read_csv_assoc($dest);
            } else {
                $decoded = json_decode(file_get_contents($dest) ?: '', true);
                if (isset($decoded['items']) && is_array($decoded['items'])) { $rawRows = $decoded['items']; }
                elseif (is_array($decoded)) { $rawRows = $decoded; }
            }
            $library = app_library($targetUsername);
            $existingLookup = app_import_build_existing_lookup($library);
            $items = [];
            foreach ($rawRows as $row) {
                if (!is_array($row)) { continue; }
                $reviewItem = app_import_match_review_item($row, $existingLookup);
                if (trim((string)($reviewItem['title'] ?? '')) === '') { continue; }
                $items[] = $reviewItem;
            }
            $review = [
                '_meta' => app_json_meta('Import review file.'),
                'source_file' => basename($dest),
                'created_at' => date(DATE_ATOM),
                'target_user' => $targetUsername,
                'items' => $items,
            ];
            app_save_json(app_user_dir($targetUsername) . DIRECTORY_SEPARATOR . 'imports' . DIRECTORY_SEPARATOR . 'review-' . $id . '.json', $review);
            app_log_activity((string)$user['username'], 'import-staged', $targetUsername, ['count' => count($items)]);
            header('Location: import.php?review=' . rawurlencode($id) . (app_is_admin($user) ? '&u=' . rawurlencode($targetUsername) : ''));
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
    <p>Upload CSV or JSON. The app stages the data first so you can review duplicates and weak TMDB matches before anything is written.</p>
    <div class="actions wrap-actions"><a class="button secondary" href="<?= e(app_href('import.php?sample=1' . (app_is_admin($user) ? '&u=' . rawurlencode($targetUsername) : ''))) ?>">Download sample CSV</a></div>
    <form method="post" enctype="multipart/form-data" class="stack">
        <input type="hidden" name="csrf_token" value="<?= e(app_csrf_token()) ?>">
        <input type="hidden" name="action" value="upload">
        <?php if (app_is_admin($user)): ?><input type="hidden" name="target_user" value="<?= e($targetUsername) ?>"><?php endif; ?>
        <label>CSV or JSON file <input type="file" name="import_file" accept=".csv,.json,text/csv,application/json" required></label>
        <button type="submit">Upload for review</button>
    </form>
</section>
<?php if ($review): $items = $review['items'] ?? []; ?>
<section class="card">
    <h2>REVIEW IMPORT</h2>
    <p class="muted"><?= e((string)count($items)) ?> parsed item(s). Duplicates are skipped by default. Rows marked <strong>Needs match</strong> should be reviewed before confirm.</p>
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
