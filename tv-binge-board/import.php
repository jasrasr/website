<?php
/**
 * File: import.php
 * Project: TV Binge Board
 * Description: CSV and JSON import workflow with staging review, duplicate detection, and explicit confirmation.
 * Author: Jason Lamb / ChatGPT
 * Created: 2026-07-02
 * Modified: 2026-07-02
 * Revision: 1.4.2
 */
declare(strict_types=1);


require_once __DIR__ . '/includes/functions.php';
$user = app_require_login();
if (!app_can_track($user)) { app_page_header('Import'); echo '<section class="card"><h1>Import disabled for admin</h1></section>'; app_page_footer(); exit; }
$username = (string)$user['username'];
$reviewId = app_sanitize_username((string)($_GET['review'] ?? ''));
$error = '';

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
            $dest = app_user_dir($username) . DIRECTORY_SEPARATOR . 'imports' . DIRECTORY_SEPARATOR . $id . '-' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $name);
            if (!move_uploaded_file($_FILES['import_file']['tmp_name'], $dest)) { throw new RuntimeException('Unable to save uploaded file.'); }
            $rawRows = [];
            if ($ext === 'csv') { $rawRows = app_read_csv_assoc($dest); }
            else {
                $decoded = json_decode(file_get_contents($dest) ?: '', true);
                if (isset($decoded['items']) && is_array($decoded['items'])) { $rawRows = $decoded['items']; }
                elseif (is_array($decoded)) { $rawRows = $decoded; }
            }
            $library = app_library($username);
            $existing = [];
            foreach ($library['items'] as $item) { $existing[(string)($item['uid'] ?? '')] = true; $existing[strtolower((string)($item['type'] ?? '') . '|' . (string)($item['title'] ?? ''))] = true; }
            $items = [];
            foreach ($rawRows as $row) {
                if (!is_array($row)) { continue; }
                $item = app_normalize_import_item($row);
                if (trim((string)$item['title']) === '') { continue; }
                $item['duplicate'] = !empty($existing[$item['uid']]) || !empty($existing[strtolower($item['type'] . '|' . $item['title'])]);
                $items[] = $item;
            }
            $review = ['_meta' => app_json_meta('Import review file.'), 'source_file' => basename($dest), 'created_at' => date(DATE_ATOM), 'items' => $items];
            app_save_json(app_user_dir($username) . DIRECTORY_SEPARATOR . 'imports' . DIRECTORY_SEPARATOR . 'review-' . $id . '.json', $review);
            app_log_activity($username, 'import-staged', $username, ['count' => count($items)]);
            header('Location: import.php?review=' . rawurlencode($id)); exit;
        }
        if ($action === 'confirm') {
            $id = app_sanitize_username((string)($_POST['review_id'] ?? ''));
            $reviewPath = app_user_dir($username) . DIRECTORY_SEPARATOR . 'imports' . DIRECTORY_SEPARATOR . 'review-' . $id . '.json';
            $review = app_load_json($reviewPath, []);
            $library = app_library($username);
            $added = 0;
            foreach (($review['items'] ?? []) as $item) {
                if (!empty($item['duplicate']) && empty($_POST['include_duplicates'])) { continue; }
                unset($item['duplicate']);
                $index = app_find_media_index($library, (string)$item['uid']);
                if ($index === null) { $library['items'][] = $item; $added++; }
            }
            app_save_library($username, $library);
            app_log_activity($username, 'import-confirmed', $username, ['added' => $added]);
            app_flash('Import complete. Added ' . $added . ' item(s).', 'success');
            header('Location: watchlist.php'); exit;
        }
    } catch (Throwable $ex) { $error = $ex->getMessage(); }
}

$review = [];
if ($reviewId !== '') {
    $review = app_load_json(app_user_dir($username) . DIRECTORY_SEPARATOR . 'imports' . DIRECTORY_SEPARATOR . 'review-' . $reviewId . '.json', []);
}
app_page_header('Import');
?>
<section class="card">
    <h1>Import library data</h1>
    <?php if ($error): ?><div class="alert danger"><?= e($error) ?></div><?php endif; ?>
    <p>Upload CSV or JSON. The app stages the data first so you can review duplicates before anything is written into your list.</p>
    <form method="post" enctype="multipart/form-data" class="stack">
        <input type="hidden" name="csrf_token" value="<?= e(app_csrf_token()) ?>">
        <input type="hidden" name="action" value="upload">
        <label>CSV or JSON file <input type="file" name="import_file" accept=".csv,.json,text/csv,application/json" required></label>
        <button type="submit">Upload for review</button>
    </form>
</section>
<?php if ($review): $items = $review['items'] ?? []; ?>
<section class="card">
    <h2>Review import</h2>
    <p class="muted"><?= e((string)count($items)) ?> parsed item(s). Duplicates are skipped unless you explicitly include them.</p>
    <form method="post" class="stack">
        <input type="hidden" name="csrf_token" value="<?= e(app_csrf_token()) ?>">
        <input type="hidden" name="action" value="confirm">
        <input type="hidden" name="review_id" value="<?= e($reviewId) ?>">
        <label class="checkbox-row"><input type="checkbox" name="include_duplicates" value="1"> Include duplicates</label>
        <button type="submit">Confirm import</button>
    </form>
</section>
<div class="media-list">
    <?php foreach ($items as $item): ?>
    <article class="media-card"><div class="poster placeholder-poster">?</div><div><h3><?= e((string)($item['title'] ?? 'Untitled')) ?></h3><p class="muted"><?= e((string)($item['type'] ?? '')) ?> · <?= e((string)($item['status'] ?? '')) ?> <?= !empty($item['duplicate']) ? '· duplicate' : '' ?></p><p><?= e(app_excerpt((string)($item['overview'] ?? ''), 150)) ?></p></div></article>
    <?php endforeach; ?>
</div>
<?php endif; app_page_footer(); ?>
