<?php
/**
 * File: upload-screenshot.php
 * Project: TV Binge Board
 * Description: Screenshot-assisted import staging page with image validation and manual review queue creation.
 * Author: Jason Lamb / ChatGPT
 * Created: 2026-07-02
 * Modified: 2026-07-02
 * Revision: 1.4.2
 */
declare(strict_types=1);


require_once __DIR__ . '/includes/functions.php';
$user = app_require_login();
if (!app_can_track($user)) { app_page_header('Upload Screenshot'); echo '<section class="card"><h1>Screenshot upload disabled for admin</h1></section>'; app_page_footer(); exit; }
$username = (string)$user['username'];
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    app_verify_csrf();
    try {
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
        $indexPath = app_user_file($username, 'screenshot-imports.json');
        $queue = app_load_json($indexPath, ['_meta' => app_json_meta('Screenshot-assisted import review queue.'), 'items' => []]);
        $queue['items'][] = ['id' => $id, 'filename' => $destName, 'mime' => $mime, 'width' => (int)$info[0], 'height' => (int)$info[1], 'status' => 'needs-review', 'notes' => 'OCR/AI parsing is not implemented yet. Review manually before importing.', 'created_at' => date(DATE_ATOM)];
        $queue['_meta']['updated_at'] = date(DATE_ATOM);
        app_save_json($indexPath, $queue);
        app_log_activity($username, 'screenshot-uploaded', $username, ['id' => $id]);
        app_flash('Screenshot uploaded to the review queue. No library data was changed.', 'success');
        header('Location: upload-screenshot.php'); exit;
    } catch (Throwable $ex) { $error = $ex->getMessage(); }
}
$queue = app_load_json(app_user_file($username, 'screenshot-imports.json'), ['items' => []]);
app_page_header('Upload Screenshot');
?>
<section class="card">
    <h1>Upload screenshot for future import</h1>
    <?php if ($error): ?><div class="alert danger"><?= e($error) ?></div><?php endif; ?>
    <p>This stores the screenshot in your protected user data folder and creates a review queue entry. It does not parse or import automatically.</p>
    <form method="post" enctype="multipart/form-data" class="stack">
        <input type="hidden" name="csrf_token" value="<?= e(app_csrf_token()) ?>">
        <label>Screenshot <input type="file" name="screenshot" accept="image/png,image/jpeg,image/webp" required></label>
        <button type="submit">Upload screenshot</button>
    </form>
</section>
<section class="card">
    <h2>Review queue</h2>
    <?php if (empty($queue['items'])): ?><p class="muted">No screenshots queued.</p><?php endif; ?>
    <ul class="compact-list">
        <?php foreach (array_reverse($queue['items'] ?? []) as $item): ?>
        <li><strong><?= e((string)($item['filename'] ?? 'screenshot')) ?></strong> · <?= e((string)($item['status'] ?? '')) ?> · <?= e((string)($item['created_at'] ?? '')) ?></li>
        <?php endforeach; ?>
    </ul>
</section>
<?php app_page_footer(); ?>
