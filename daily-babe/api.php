<?php

declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

$action = (string) ($_GET['action'] ?? $_POST['action'] ?? '');

try {
    if ($action === 'setup') {
        require_csrf();
        if (is_configured()) {
            json_response(false, 'The gallery is already configured.', null, [['code' => 'ALREADY_CONFIGURED']], 409);
        }
        $password = (string) ($_POST['password'] ?? '');
        $confirm = (string) ($_POST['confirmPassword'] ?? '');
        if (strlen($password) < 10 || $password !== $confirm) {
            json_response(false, 'Use at least 10 characters and make both passwords match.', null, [['code' => 'INVALID_PASSWORD']], 422);
        }
        json_write('data/auth.json', ['schemaVersion' => 1, 'passwordHash' => password_hash($password, PASSWORD_DEFAULT), 'updatedAt' => gmdate('c')]);
        session_regenerate_id(true);
        $_SESSION['authenticated'] = true;
        audit_event('gallery.setup');
        json_response(true, 'Private gallery created.');
    }

    if ($action === 'login') {
        require_csrf();
        $auth = json_read('data/auth.json', []);
        $password = (string) ($_POST['password'] ?? '');
        if (!isset($auth['passwordHash']) || !password_verify($password, (string) $auth['passwordHash'])) {
            usleep(350000);
            audit_event('auth.failed');
            json_response(false, 'That password did not match.', null, [['code' => 'INVALID_LOGIN']], 401);
        }
        session_regenerate_id(true);
        $_SESSION['authenticated'] = true;
        audit_event('auth.login');
        json_response(true, 'Signed in.');
    }

    if ($action === 'logout') {
        require_auth();
        require_csrf();
        audit_event('auth.logout');
        $_SESSION = [];
        session_destroy();
        json_response(true, 'Signed out.');
    }

    require_auth();

    if ($action === 'list') {
        $entries = tracker_entries();
        $records = array_values($entries['records'] ?? []);
        usort($records, static fn(array $a, array $b): int => strcmp((string) $b['photoDate'], (string) $a['photoDate']));
        json_response(true, 'Gallery loaded.', ['settings' => tracker_settings(), 'records' => $records]);
    }

    if ($action === 'settings') {
        require_csrf();
        $dueDate = (string) ($_POST['dueDate'] ?? '');
        $birthDate = (string) ($_POST['birthDate'] ?? '');
        if (!valid_date($dueDate) || ($birthDate !== '' && (!valid_date($birthDate) || $birthDate > date('Y-m-d')))) {
            json_response(false, 'Enter valid due and birth dates.', null, [['code' => 'INVALID_DATE']], 422);
        }
        $settings = [
            'schemaVersion' => 1,
            'babyName' => trim(substr((string) ($_POST['babyName'] ?? ''), 0, 80)),
            'dueDate' => $dueDate,
            'birthDate' => $birthDate,
            'updatedAt' => gmdate('c'),
        ];
        json_write('data/settings.json', $settings);
        audit_event('settings.updated');
        json_response(true, 'Settings saved.', $settings);
    }

    if ($action === 'upload') {
        require_csrf();
        $photoDate = (string) ($_POST['photoDate'] ?? '');
        if (!valid_date($photoDate) || $photoDate > date('Y-m-d')) {
            json_response(false, 'Choose today or an earlier valid date.', null, [['code' => 'INVALID_DATE', 'field' => 'photoDate']], 422);
        }
        if (!isset($_FILES['photo']) || !is_array($_FILES['photo']) || (int) $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
            json_response(false, 'Choose a photo to upload.', null, [['code' => 'UPLOAD_FAILED']], 422);
        }
        global $config;
        $upload = $_FILES['photo'];
        if ((int) $upload['size'] > (int) ($config['max_upload_bytes'] ?? 15728640)) {
            json_response(false, 'That photo is larger than the 15 MB limit.', null, [['code' => 'FILE_TOO_LARGE']], 413);
        }
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file((string) $upload['tmp_name']);
        $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        if (!is_string($mime) || !isset($extensions[$mime])) {
            json_response(false, 'Use a JPEG, PNG, or WebP image.', null, [['code' => 'UNSUPPORTED_IMAGE']], 415);
        }
        $id = bin2hex(random_bytes(12));
        $filename = $id . '.' . $extensions[$mime];
        $destination = storage_path('uploads/' . $filename);
        if (!move_uploaded_file((string) $upload['tmp_name'], $destination)) {
            throw new RuntimeException('Unable to store the uploaded photo.');
        }
        chmod($destination, 0640);
        $entries = tracker_entries();
        $records = $entries['records'] ?? [];
        foreach ($records as $record) {
            if (($record['photoDate'] ?? '') === $photoDate) {
                unlink($destination);
                json_response(false, 'That date already has a photo. Delete or replace support is planned; choose another date for now.', null, [['code' => 'DATE_EXISTS']], 409);
            }
        }
        $record = [
            'id' => $id,
            'photoDate' => $photoDate,
            'filename' => $filename,
            'mimeType' => $mime,
            'note' => trim(substr((string) ($_POST['note'] ?? ''), 0, 300)),
            'uploadedAt' => gmdate('c'),
        ];
        $records[] = $record;
        $entries['records'] = $records;
        $entries['updatedAt'] = gmdate('c');
        json_write('data/entries.json', $entries);
        audit_event('photo.uploaded', ['id' => $id, 'photoDate' => $photoDate]);
        json_response(true, 'Daily photo saved.', $record, [], 201);
    }

    json_response(false, 'Unknown action.', null, [['code' => 'NOT_FOUND']], 404);
} catch (Throwable $exception) {
    error_log('Baby Daily [' . request_id() . '] ' . $exception->getMessage());
    json_response(false, 'Something went wrong. Try again.', null, [['code' => 'SERVER_ERROR']], 500);
}

