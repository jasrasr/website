<?php
/**
 * File: upload-screenshot.php
 * Project: TV Binge Board
 * Description: Screenshot-assisted import staging page with protected uploads, OCR/AI text processing, confidence-scored guesses, and manual approve/reject review before import staging.
 * Author: Jason Lamb / ChatGPT
 * Created: 2026-07-02
 * Modified: 2026-07-03
 * Revision: 1.5.0
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/tmdb.php';
$user = app_require_login();
if (!app_can_track($user)) { app_page_header('Upload Screenshot'); echo '<section class="card"><h1>Screenshot upload disabled for admin</h1></section>'; app_page_footer(); exit; }
$username = (string)$user['username'];
$error = '';

function app_screenshot_queue_path(string $username): string
{
    return app_user_file($username, 'screenshot-imports.json');
}

function app_screenshot_queue(string $username): array
{
    $queue = app_load_json(app_screenshot_queue_path($username), ['_meta' => app_json_meta('Screenshot-assisted import review queue.'), 'items' => []]);
    if (!isset($queue['items']) || !is_array($queue['items'])) { $queue['items'] = []; }
    return $queue;
}

function app_save_screenshot_queue(string $username, array $queue): void
{
    $queue['_meta']['updated_at'] = date(DATE_ATOM);
    app_save_json(app_screenshot_queue_path($username), $queue);
}

function app_screenshot_item_index(array $queue, string $id): ?int
{
    foreach (($queue['items'] ?? []) as $index => $item) {
        if ((string)($item['id'] ?? '') === $id) { return (int)$index; }
    }
    return null;
}

function app_screenshot_clean_title(string $line): string
{
    $line = preg_replace('/^\s*[#*\-\d.)]+\s*/', '', $line) ?? $line;
    $line = preg_replace('/\b(19|20)\d{2}\b/', '', $line) ?? $line;
    $line = preg_replace('/\bS\s*\d{1,3}\s*E\s*\d{1,4}\b/i', '', $line) ?? $line;
    $line = preg_replace('/\bseason\s*\d{1,3}\s*(episode|ep)?\s*\d{0,4}\b/i', '', $line) ?? $line;
    $line = preg_replace('/\b(watched|watching|completed|complete|want to watch|dropped|movie|tv|series|show)\b/i', '', $line) ?? $line;
    $line = trim($line, " \t\n\r\0\x0B-–—:|,.;[](){}");
    return preg_replace('/\s+/', ' ', $line) ?? $line;
}

function app_screenshot_guess_from_line(string $line, int $index): ?array
{
    $source = trim($line);
    if ($source === '') { return null; }
    $year = '';
    if (preg_match('/\b((?:19|20)\d{2})\b/', $source, $match)) { $year = $match[1]; }
    $season = null;
    $episode = null;
    if (preg_match('/\bS\s*(\d{1,3})\s*E\s*(\d{1,4})\b/i', $source, $match)) {
        $season = max(1, (int)$match[1]);
        $episode = max(1, (int)$match[2]);
    } elseif (preg_match('/\bseason\s*(\d{1,3}).*?\b(?:episode|ep)\s*(\d{1,4})\b/i', $source, $match)) {
        $season = max(1, (int)$match[1]);
        $episode = max(1, (int)$match[2]);
    }
    $title = app_screenshot_clean_title($source);
    if ($title === '' || strlen($title) < 2) { return null; }
    $type = ($season !== null && $episode !== null) ? 'tv' : 'movie';
    $status = $type === 'tv' ? 'watching' : 'watchlist';
    $confidence = 35;
    $confidence += strlen($title) >= 3 ? 25 : 0;
    $confidence += $year !== '' ? 10 : 0;
    $confidence += $type === 'tv' ? 20 : 0;
    $confidence += preg_match('/[A-Za-z]{3,}/', $title) ? 10 : 0;
    $confidence = max(20, min(95, $confidence));
    return [
        'id' => 'guess-' . $index,
        'source_text' => $source,
        'title' => $title,
        'type' => $type,
        'year' => $year,
        'status' => $status,
        'season' => $season,
        'episode' => $episode,
        'notes' => 'Parsed from screenshot OCR/AI text.',
        'confidence' => $confidence,
        'decision' => $confidence >= 70 ? 'approve' : 'review',
    ];
}

function app_screenshot_parse_text(string $text): array
{
    $guesses = [];
    $lines = preg_split('/\R+/', $text) ?: [];
    $index = 1;
    foreach ($lines as $line) {
        $guess = app_screenshot_guess_from_line((string)$line, $index);
        if ($guess !== null) { $guesses[] = $guess; $index++; }
        if (count($guesses) >= 100) { break; }
    }
    return $guesses;
}

function app_screenshot_guess_row(array $guess, array $post, int $index): ?array
{
    $decision = (string)($post['decision'][$index] ?? 'reject');
    if ($decision !== 'approve') { return null; }
    $title = trim((string)($post['title'][$index] ?? ($guess['title'] ?? '')));
    if ($title === '') { return null; }
    $type = (string)($post['type'][$index] ?? ($guess['type'] ?? 'movie'));
    $type = in_array($type, ['movie','tv'], true) ? $type : 'movie';
    $status = (string)($post['status'][$index] ?? ($guess['status'] ?? 'watchlist'));
    if (!array_key_exists($status, app_statuses())) { $status = 'watchlist'; }
    $season = trim((string)($post['season'][$index] ?? ($guess['season'] ?? '')));
    $episode = trim((string)($post['episode'][$index] ?? ($guess['episode'] ?? '')));
    $notes = trim((string)($post['notes'][$index] ?? ($guess['notes'] ?? '')));
    $confidence = max(0, min(100, (int)($guess['confidence'] ?? 0)));
    $row = [
        'type' => $type,
        'title' => $title,
        'year' => trim((string)($post['year'][$index] ?? ($guess['year'] ?? ''))),
        'status' => $status,
        'rating' => '',
        'season' => $type === 'tv' ? $season : '',
        'episode' => $type === 'tv' ? $episode : '',
        'notes' => trim($notes . ($notes !== '' ? ' ' : '') . 'Screenshot guess confidence: ' . $confidence . '%.'),
        'overview' => '',
    ];
    return $row;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    app_verify_csrf();
    $action = (string)($_POST['action'] ?? 'upload');
    try {
        if ($action === 'upload') {
            if (empty($_FILES['screenshot']['tmp_name']) || !is_uploaded_file($_FILES['screenshot']['tmp_name'])) { throw new RuntimeException('Upload failed.'); }
            if ((int)($_FILES['screenshot']['size'] ?? 0) > APP_MAX_UPLOAD_BYTES) { throw new RuntimeException('File is too large.'); }
            $tmp = (string)$_FILES['screenshot']['tmp_name'];
            $info = @getimagesize($tmp);
            if ($info === false) { throw new RuntimeException('Uploaded file is not a valid image.'); }
            $mime = (string)($info['mime'] ?? '');
            $ext = match ($mime) { 'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', default => '' };
            if ($ext === '') { throw new RuntimeException('Only JPG, PNG, and WebP screenshots are supported.'); }
            $id = date('YmdHis') . '-' . bin2hex(random_bytes(3));
            $destName = 'screenshot-' . $id . '.' . $ext;
            $dest = app_user_dir($username) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . $destName;
            if (!move_uploaded_file($tmp, $dest)) { throw new RuntimeException('Unable to save screenshot.'); }
            $queue = app_screenshot_queue($username);
            $queue['items'][] = ['id' => $id, 'filename' => $destName, 'mime' => $mime, 'width' => (int)$info[0], 'height' => (int)$info[1], 'status' => 'needs-processing', 'notes' => 'Paste OCR/AI text output to generate review guesses. No library data has changed.', 'guesses' => [], 'created_at' => date(DATE_ATOM)];
            app_save_screenshot_queue($username, $queue);
            app_log_activity($username, 'screenshot-uploaded', $username, ['id' => $id]);
            app_flash('Screenshot uploaded. Paste OCR/AI text to process guesses.', 'success');
            header('Location: upload-screenshot.php?item=' . rawurlencode($id)); exit;
        }
        if ($action === 'process_text') {
            $id = app_sanitize_username((string)($_POST['item_id'] ?? ''));
            $ocrText = trim((string)($_POST['ocr_text'] ?? ''));
            if ($id === '') { throw new RuntimeException('Missing screenshot queue item.'); }
            if ($ocrText === '') { throw new RuntimeException('Paste OCR/AI text before processing.'); }
            $queue = app_screenshot_queue($username);
            $index = app_screenshot_item_index($queue, $id);
            if ($index === null) { throw new RuntimeException('Screenshot queue item not found.'); }
            $guesses = app_screenshot_parse_text($ocrText);
            if ($guesses === []) { throw new RuntimeException('No usable show or movie guesses were found in that text.'); }
            $queue['items'][$index]['ocr_text'] = $ocrText;
            $queue['items'][$index]['guesses'] = $guesses;
            $queue['items'][$index]['status'] = 'needs-review';
            $queue['items'][$index]['processed_at'] = date(DATE_ATOM);
            app_save_screenshot_queue($username, $queue);
            app_log_activity($username, 'screenshot-processed', $username, ['id' => $id, 'guesses' => count($guesses)]);
            app_flash('Screenshot text processed. Review and approve the guesses below.', 'success');
            header('Location: upload-screenshot.php?item=' . rawurlencode($id)); exit;
        }
        if ($action === 'save_guesses') {
            $id = app_sanitize_username((string)($_POST['item_id'] ?? ''));
            if ($id === '') { throw new RuntimeException('Missing screenshot queue item.'); }
            $queue = app_screenshot_queue($username);
            $index = app_screenshot_item_index($queue, $id);
            if ($index === null) { throw new RuntimeException('Screenshot queue item not found.'); }
            $item = $queue['items'][$index];
            $guesses = is_array($item['guesses'] ?? null) ? $item['guesses'] : [];
            $rows = [];
            $approved = 0;
            $rejected = 0;
            foreach ($guesses as $guessIndex => $guess) {
                if (!is_array($guess)) { continue; }
                $row = app_screenshot_guess_row($guess, $_POST, (int)$guessIndex);
                if ($row === null) { $rejected++; continue; }
                $rows[] = $row;
                $approved++;
            }
            if ($rows === []) { throw new RuntimeException('Approve at least one guess before creating an import review.'); }
            $library = app_library($username);
            $existingLookup = app_import_build_existing_lookup($library);
            $reviewItems = [];
            foreach ($rows as $rowIndex => $row) {
                $reviewItem = app_import_match_review_item($row, $existingLookup);
                $reviewItem['screenshot_id'] = $id;
                $reviewItem['screenshot_guess_index'] = $rowIndex;
                $reviewItems[] = $reviewItem;
            }
            $reviewId = date('YmdHis') . '-screenshot-' . substr(sha1($id), 0, 8);
            $review = [
                '_meta' => app_json_meta('Screenshot-assisted import review file.'),
                'source_type' => 'screenshot',
                'source_file' => (string)($item['filename'] ?? ''),
                'source_screenshot_id' => $id,
                'created_at' => date(DATE_ATOM),
                'target_user' => $username,
                'items' => $reviewItems,
            ];
            app_save_json(app_user_dir($username) . DIRECTORY_SEPARATOR . 'imports' . DIRECTORY_SEPARATOR . 'review-' . $reviewId . '.json', $review);
            $queue['items'][$index]['status'] = 'review-created';
            $queue['items'][$index]['review_id'] = $reviewId;
            $queue['items'][$index]['approved_count'] = $approved;
            $queue['items'][$index]['rejected_count'] = $rejected;
            $queue['items'][$index]['review_created_at'] = date(DATE_ATOM);
            app_save_screenshot_queue($username, $queue);
            app_log_activity($username, 'screenshot-review-created', $username, ['id' => $id, 'review_id' => $reviewId, 'approved' => $approved, 'rejected' => $rejected]);
            app_flash('Screenshot guesses staged for import review. Confirm there before anything is added to your library.', 'success');
            header('Location: import.php?review=' . rawurlencode($reviewId)); exit;
        }
    } catch (Throwable $ex) { $error = $ex->getMessage(); }
}

$queue = app_screenshot_queue($username);
$selectedId = app_sanitize_username((string)($_GET['item'] ?? ''));
$selectedIndex = $selectedId !== '' ? app_screenshot_item_index($queue, $selectedId) : null;
$selectedItem = $selectedIndex !== null ? $queue['items'][$selectedIndex] : null;
app_page_header('Upload Screenshot');
?>
<section class="card">
    <h1>Upload screenshot for assisted import</h1>
    <?php if ($error): ?><div class="alert danger"><?= e($error) ?></div><?php endif; ?>
    <p>This stores the screenshot in your protected user data folder and creates a review queue entry. The upload step does not parse or import automatically.</p>
    <form method="post" enctype="multipart/form-data" class="stack">
        <input type="hidden" name="csrf_token" value="<?= e(app_csrf_token()) ?>">
        <input type="hidden" name="action" value="upload">
        <label>Screenshot <input type="file" name="screenshot" accept="image/png,image/jpeg,image/webp" required></label>
        <button type="submit">Upload screenshot</button>
    </form>
</section>
<section class="card">
    <h2>Review queue</h2>
    <?php if (empty($queue['items'])): ?><p class="muted">No screenshots queued.</p><?php endif; ?>
    <div class="screenshot-queue-list">
        <?php foreach (array_reverse($queue['items'] ?? []) as $item): ?>
            <?php $id = (string)($item['id'] ?? ''); ?>
            <article class="screenshot-queue-item">
                <h3><?= e((string)($item['filename'] ?? 'screenshot')) ?></h3>
                <p class="muted"><?= e((string)($item['status'] ?? '')) ?> · <?= e((string)($item['created_at'] ?? '')) ?> · <?= e((string)($item['width'] ?? '?')) ?>×<?= e((string)($item['height'] ?? '?')) ?></p>
                <p class="muted"><?= e((string)($item['notes'] ?? '')) ?></p>
                <div class="actions wrap-actions">
                    <a class="button secondary" href="<?= e(app_href('upload-screenshot.php?item=' . rawurlencode($id))) ?>">Open</a>
                    <?php if (!empty($item['review_id'])): ?><a class="button secondary" href="<?= e(app_href('import.php?review=' . rawurlencode((string)$item['review_id']))) ?>">Open import review</a><?php endif; ?>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
</section>
<?php if ($selectedItem): ?>
<section class="card">
    <h2>Process screenshot text</h2>
    <p class="muted">Queue item: <strong><?= e((string)($selectedItem['filename'] ?? $selectedId)) ?></strong></p>
    <p>Use iPhone Live Text, another OCR tool, or an AI vision pass to extract text from the screenshot, then paste that text here. This processing step only creates guesses; it still does not write to your library.</p>
    <form method="post" class="stack">
        <input type="hidden" name="csrf_token" value="<?= e(app_csrf_token()) ?>">
        <input type="hidden" name="action" value="process_text">
        <input type="hidden" name="item_id" value="<?= e($selectedId) ?>">
        <label>OCR/AI text output
            <textarea name="ocr_text" rows="8" placeholder="Example: The Crown S2E4&#10;Lost S1E8&#10;The Matrix 1999"><?= e((string)($selectedItem['ocr_text'] ?? '')) ?></textarea>
        </label>
        <button type="submit">Process text into guesses</button>
    </form>
</section>
<?php $guesses = is_array($selectedItem['guesses'] ?? null) ? $selectedItem['guesses'] : []; if ($guesses): ?>
<section class="card">
    <h2>Review parsed guesses</h2>
    <p class="muted">Approve only the rows you want to stage. Approved rows go to the normal import review screen before anything is added to your library.</p>
    <form method="post" class="stack">
        <input type="hidden" name="csrf_token" value="<?= e(app_csrf_token()) ?>">
        <input type="hidden" name="action" value="save_guesses">
        <input type="hidden" name="item_id" value="<?= e($selectedId) ?>">
        <div class="screenshot-guess-list">
        <?php foreach ($guesses as $index => $guess): ?>
            <?php if (!is_array($guess)) { continue; } $confidence = max(0, min(100, (int)($guess['confidence'] ?? 0))); ?>
            <article class="screenshot-guess-card">
                <div class="media-title-row">
                    <h3><?= e((string)($guess['title'] ?? 'Untitled guess')) ?></h3>
                    <span class="pill">Confidence <?= e((string)$confidence) ?>%</span>
                </div>
                <div class="confidence-bar"><span style="width: <?= e((string)$confidence) ?>%"></span></div>
                <p class="muted">Source text: <?= e((string)($guess['source_text'] ?? '')) ?></p>
                <div class="grid-3">
                    <label>Decision
                        <select name="decision[<?= e((string)$index) ?>]">
                            <option value="approve" <?= (string)($guess['decision'] ?? '') === 'approve' ? 'selected' : '' ?>>Approve</option>
                            <option value="reject" <?= (string)($guess['decision'] ?? '') !== 'approve' ? 'selected' : '' ?>>Reject</option>
                        </select>
                    </label>
                    <label>Type
                        <select name="type[<?= e((string)$index) ?>]">
                            <option value="movie" <?= (string)($guess['type'] ?? '') === 'movie' ? 'selected' : '' ?>>Movie</option>
                            <option value="tv" <?= (string)($guess['type'] ?? '') === 'tv' ? 'selected' : '' ?>>TV</option>
                        </select>
                    </label>
                    <label>Status
                        <select name="status[<?= e((string)$index) ?>]">
                            <?php foreach (app_statuses() as $status => $label): ?><option value="<?= e($status) ?>" <?= (string)($guess['status'] ?? '') === $status ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
                        </select>
                    </label>
                </div>
                <label>Title <input type="text" name="title[<?= e((string)$index) ?>]" value="<?= e((string)($guess['title'] ?? '')) ?>"></label>
                <div class="grid-3">
                    <label>Year <input type="text" name="year[<?= e((string)$index) ?>]" value="<?= e((string)($guess['year'] ?? '')) ?>"></label>
                    <label>Season <input type="number" name="season[<?= e((string)$index) ?>]" min="0" value="<?= e((string)($guess['season'] ?? '')) ?>"></label>
                    <label>Episode <input type="number" name="episode[<?= e((string)$index) ?>]" min="0" value="<?= e((string)($guess['episode'] ?? '')) ?>"></label>
                </div>
                <label>Notes <textarea name="notes[<?= e((string)$index) ?>]" rows="2"><?= e((string)($guess['notes'] ?? '')) ?></textarea></label>
            </article>
        <?php endforeach; ?>
        </div>
        <button type="submit">Create import review from approved guesses</button>
    </form>
</section>
<?php endif; endif; app_page_footer(); ?>
