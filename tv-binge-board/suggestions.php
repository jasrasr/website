<?php
/**
 * File: suggestions.php
 * Project: TV Binge Board
 * Description: Public suggestion and bug report board with required email capture, optional PNG/JPG screenshot attachments, logged-in profile email prefilling, JSON storage, and issue-style listing.
 * Author: Jason Lamb / ChatGPT
 * Created: 2026-07-05
 * Modified: 2026-07-05
 * Revision: 1.1.0
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';

function app_suggestions_path(): string
{
    return APP_DATA_DIR . DIRECTORY_SEPARATOR . 'suggestions.json';
}

function app_suggestion_upload_dir(): string
{
    return APP_PUBLIC_CACHE_DIR . DIRECTORY_SEPARATOR . 'suggestions';
}

function app_suggestion_upload_url(string $fileName): string
{
    return APP_PUBLIC_CACHE_URL . '/suggestions/' . ltrim($fileName, '/');
}

function app_default_suggestions(): array
{
    return ['_meta' => app_json_meta('Public feature suggestions and bug reports.'), 'items' => []];
}

function app_load_suggestions(): array
{
    $data = app_load_json(app_suggestions_path(), app_default_suggestions());
    if (!isset($data['items']) || !is_array($data['items'])) { $data['items'] = []; }
    return $data;
}

function app_save_suggestions(array $data): void
{
    $data['_meta']['updated_at'] = date(DATE_ATOM);
    $data['_meta']['version'] = APP_VERSION;
    app_save_json(app_suggestions_path(), $data);
}

function app_suggestion_types(): array
{
    return [
        'feature' => 'Feature update',
        'bug' => 'Bug report',
        'usability' => 'Usability / layout',
        'data' => 'Data / metadata issue',
        'question' => 'Question',
        'other' => 'Other',
    ];
}

function app_mask_email(string $email): string
{
    $email = trim($email);
    if (!str_contains($email, '@')) { return $email; }
    [$name, $domain] = explode('@', $email, 2);
    $prefix = substr($name, 0, 1);
    return $prefix . str_repeat('*', max(1, min(5, strlen($name) - 1))) . '@' . $domain;
}

function app_suggestion_status_label(string $status): string
{
    return match ($status) {
        'planned' => 'Planned',
        'in-progress' => 'In progress',
        'done' => 'Done',
        'closed' => 'Closed',
        default => 'Open',
    };
}

function app_suggestion_handle_upload(string $id): array
{
    if (!isset($_FILES['attachment']) || !is_array($_FILES['attachment'])) {
        return ['attachment' => null, 'error' => ''];
    }
    $file = $_FILES['attachment'];
    $error = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($error === UPLOAD_ERR_NO_FILE) {
        return ['attachment' => null, 'error' => ''];
    }
    if ($error !== UPLOAD_ERR_OK) {
        return ['attachment' => null, 'error' => 'Screenshot upload failed. Try a smaller PNG or JPG.'];
    }
    $size = (int)($file['size'] ?? 0);
    if ($size <= 0 || $size > APP_MAX_UPLOAD_BYTES) {
        return ['attachment' => null, 'error' => 'Screenshot must be under ' . number_format(APP_MAX_UPLOAD_BYTES / 1048576, 1) . ' MB.'];
    }
    $tmp = (string)($file['tmp_name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        return ['attachment' => null, 'error' => 'Screenshot upload could not be validated.'];
    }
    $original = (string)($file['name'] ?? 'screenshot');
    $extension = strtolower(pathinfo($original, PATHINFO_EXTENSION));
    $allowedExtensions = ['jpg' => 'jpg', 'jpeg' => 'jpg', 'png' => 'png'];
    if (!isset($allowedExtensions[$extension])) {
        return ['attachment' => null, 'error' => 'Only PNG and JPG screenshots are accepted. iPhone screenshots are normally PNG; convert HEIC photos to PNG or JPG first.'];
    }
    $imageInfo = @getimagesize($tmp);
    if (!is_array($imageInfo)) {
        return ['attachment' => null, 'error' => 'Uploaded file is not a valid PNG or JPG image.'];
    }
    $mime = (string)($imageInfo['mime'] ?? '');
    if (!in_array($mime, ['image/png', 'image/jpeg'], true)) {
        return ['attachment' => null, 'error' => 'Only PNG and JPG screenshots are accepted.'];
    }
    $normalExtension = $mime === 'image/png' ? 'png' : 'jpg';
    if ($allowedExtensions[$extension] !== $normalExtension) {
        return ['attachment' => null, 'error' => 'Screenshot extension does not match the image type.'];
    }
    $directory = app_suggestion_upload_dir();
    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
        return ['attachment' => null, 'error' => 'Could not create the public screenshot upload folder.'];
    }
    $fileName = $id . '.' . $normalExtension;
    $target = $directory . DIRECTORY_SEPARATOR . $fileName;
    if (!move_uploaded_file($tmp, $target)) {
        return ['attachment' => null, 'error' => 'Could not save screenshot upload.'];
    }
    return [
        'attachment' => [
            'type' => 'screenshot',
            'file_name' => $fileName,
            'original_name' => $original,
            'url' => app_suggestion_upload_url($fileName),
            'mime' => $mime,
            'size_bytes' => $size,
            'width' => (int)($imageInfo[0] ?? 0),
            'height' => (int)($imageInfo[1] ?? 0),
            'uploaded_at' => date(DATE_ATOM),
        ],
        'error' => '',
    ];
}

$user = app_current_user();
$profile = $user ? app_profile((string)$user['username']) : [];
$prefillEmail = trim((string)($profile['email'] ?? $user['email'] ?? ''));
$prefillName = trim((string)($profile['display_name'] ?? $user['display_name'] ?? $user['username'] ?? ''));
$data = app_load_suggestions();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    app_verify_csrf();
    $honeypot = trim((string)($_POST['website'] ?? ''));
    $type = (string)($_POST['type'] ?? 'feature');
    $types = app_suggestion_types();
    $title = trim((string)($_POST['title'] ?? ''));
    $body = trim((string)($_POST['body'] ?? ''));
    $email = trim((string)($_POST['email'] ?? ''));
    $name = trim((string)($_POST['name'] ?? $prefillName));

    if ($honeypot !== '') { $errors[] = 'Submission rejected.'; }
    if (!array_key_exists($type, $types)) { $type = 'other'; }
    if ($title === '' || strlen($title) < 4) { $errors[] = 'Title is required.'; }
    if ($body === '' || strlen($body) < 10) { $errors[] = 'Description is required.'; }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { $errors[] = 'A valid email address is required.'; }

    $id = 'tvbb-' . date('Ymd-His') . '-' . substr(bin2hex(random_bytes(4)), 0, 8);
    $upload = ['attachment' => null, 'error' => ''];
    if (!$errors) {
        $upload = app_suggestion_handle_upload($id);
        if ($upload['error'] !== '') { $errors[] = $upload['error']; }
    }

    if (!$errors) {
        $item = [
            'id' => $id,
            'number' => count($data['items']) + 1,
            'type' => $type,
            'status' => 'open',
            'title' => $title,
            'body' => $body,
            'email' => $email,
            'name' => $name !== '' ? $name : ($user ? (string)$user['username'] : 'Guest'),
            'username' => $user ? (string)$user['username'] : '',
            'created_at' => date(DATE_ATOM),
            'updated_at' => date(DATE_ATOM),
            'source' => $user ? 'logged-in' : 'public',
            'attachment' => $upload['attachment'],
        ];
        $data['items'][] = $item;
        app_save_suggestions($data);

        if ($user && $prefillEmail === '') {
            $profile['email'] = $email;
            app_save_profile((string)$user['username'], $profile);
            $user['email'] = $email;
            app_update_account($user);
        }

        app_flash('Suggestion submitted.', 'success');
        header('Location: suggestions.php#' . rawurlencode($id));
        exit;
    }
}

$filterType = (string)($_GET['type'] ?? '');
$filterStatus = (string)($_GET['status'] ?? '');
$items = array_reverse($data['items']);
if ($filterType !== '') { $items = array_values(array_filter($items, static fn($item) => ($item['type'] ?? '') === $filterType)); }
if ($filterStatus !== '') { $items = array_values(array_filter($items, static fn($item) => ($item['status'] ?? 'open') === $filterStatus)); }
$types = app_suggestion_types();

app_page_header('Suggestions');
?>
<style>.honeypot{position:absolute;left:-9999px;opacity:0;height:0;overflow:hidden}.suggestion-attachment{width:min(100%,360px);border:1px solid var(--border);border-radius:.85rem;background:#030712}.suggestion-attachment-link{display:inline-block;margin-top:.35rem}</style>
<section class="card">
    <h1>Suggestions and bugs</h1>
    <p>Submit a feature update, bug, or usability note. Items are saved to JSON and shown below like a lightweight issue tracker.</p>
    <?php if ($user && $prefillEmail === ''): ?><p class="alert">Add your email below. It will be saved to your profile after your first suggestion.</p><?php endif; ?>
    <?php foreach ($errors as $error): ?><p class="alert danger"><?= e($error) ?></p><?php endforeach; ?>
    <form method="post" class="stack" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= e(app_csrf_token()) ?>">
        <label class="honeypot">Website <input name="website" autocomplete="off" tabindex="-1"></label>
        <div class="grid-2">
            <label>Type
                <select name="type"><?php foreach ($types as $value => $label): ?><option value="<?= e($value) ?>"><?= e($label) ?></option><?php endforeach; ?></select>
            </label>
            <label>Email required
                <input type="email" name="email" required value="<?= e($prefillEmail) ?>" placeholder="name@example.com">
            </label>
        </div>
        <label>Name
            <input name="name" value="<?= e($prefillName) ?>" placeholder="Your name">
        </label>
        <label>Title
            <input name="title" required maxlength="120" placeholder="Short issue-style title">
        </label>
        <label>Description
            <textarea name="body" required rows="5" placeholder="What should change? What did you expect? What happened?"></textarea>
        </label>
        <label>Optional screenshot
            <input type="file" name="attachment" accept=".png,.jpg,.jpeg,image/png,image/jpeg">
        </label>
        <p class="muted">PNG or JPG only, up to <?= e(number_format(APP_MAX_UPLOAD_BYTES / 1048576, 1)) ?> MB. iPhone screenshots are usually PNG; HEIC photos must be converted to PNG or JPG first.</p>
        <button type="submit">Submit suggestion</button>
    </form>
</section>
<section class="card">
    <h2>Public board</h2>
    <form method="get" class="actions small">
        <select name="type"><option value="">All types</option><?php foreach ($types as $value => $label): ?><option value="<?= e($value) ?>" <?= $filterType === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select>
        <select name="status"><option value="">All statuses</option><?php foreach (['open' => 'Open', 'planned' => 'Planned', 'in-progress' => 'In progress', 'done' => 'Done', 'closed' => 'Closed'] as $value => $label): ?><option value="<?= e($value) ?>" <?= $filterStatus === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select>
        <button class="secondary" type="submit">Filter</button>
        <a class="button secondary" href="suggestions.php">Clear</a>
    </form>
</section>
<section class="user-list">
<?php if (!$items): ?><article class="card"><p class="muted">No suggestions match this view yet.</p></article><?php endif; ?>
<?php foreach ($items as $item): $type = (string)($item['type'] ?? 'other'); $status = (string)($item['status'] ?? 'open'); $attachment = is_array($item['attachment'] ?? null) ? $item['attachment'] : null; ?>
<article class="user-card stacked-card" id="<?= e((string)($item['id'] ?? '')) ?>">
    <div class="user-card-main"><div><strong>#<?= e((string)($item['number'] ?? '')) ?> <?= e((string)($item['title'] ?? 'Untitled')) ?></strong><p class="muted"><?= e($types[$type] ?? 'Other') ?> · <?= e(app_suggestion_status_label($status)) ?> · <?= e(date('M j, Y g:i A', strtotime((string)($item['created_at'] ?? 'now')) ?: time())) ?></p></div><span class="pill <?= $status === 'open' ? 'success' : '' ?>"><?= e(app_suggestion_status_label($status)) ?></span></div>
    <p><?= nl2br(e((string)($item['body'] ?? ''))) ?></p>
    <?php if ($attachment && !empty($attachment['url'])): ?><p><a class="suggestion-attachment-link" href="<?= e(app_href((string)$attachment['url'])) ?>" target="_blank" rel="noopener"><img class="suggestion-attachment" src="<?= e(app_href((string)$attachment['url'])) ?>" alt="Attached screenshot for <?= e((string)($item['title'] ?? 'suggestion')) ?>" loading="lazy"></a></p><p class="muted">Attachment: <?= e((string)($attachment['original_name'] ?? $attachment['file_name'] ?? 'screenshot')) ?></p><?php endif; ?>
    <p class="muted">Submitted by <?= e((string)($item['name'] ?? 'Guest')) ?><?= !empty($item['username']) ? ' @' . e((string)$item['username']) : '' ?> · <?= e(app_mask_email((string)($item['email'] ?? ''))) ?></p>
</article>
<?php endforeach; ?>
</section>
<?php app_page_footer(); ?>
