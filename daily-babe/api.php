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
        $username = trim((string) ($_POST['username'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $confirm = (string) ($_POST['confirmPassword'] ?? '');
        if (!valid_username($username)) {
            json_response(false, 'Use the baby’s first name as the username.', null, [['code' => 'INVALID_USERNAME']], 422);
        }
        if (strlen($password) < 10 || $password !== $confirm) {
            json_response(false, 'Use at least 10 characters and make both password entries match.', null, [['code' => 'INVALID_PASSWORD']], 422);
        }
        $babyId = bin2hex(random_bytes(12));
        initialize_baby_storage($babyId);
        json_write('babies/' . $babyId . '/data/settings.json', [
            'schemaVersion' => 2,
            'babyName' => $username,
            'dueDate' => '2026-11-02',
            'birthDate' => '',
            'birthTime' => '',
            'birthLengthInches' => '',
            'birthWeightPounds' => '',
            'birthWeightOunces' => '',
            'updatedAt' => gmdate('c'),
        ]);
        write_auth([
            'accounts' => [
                normalize_username($username) => [
                    'username' => $username,
                    'babyId' => $babyId,
                    'passwordHash' => password_hash($password, PASSWORD_DEFAULT),
                    'createdAt' => gmdate('c'),
                    'updatedAt' => gmdate('c'),
                ],
            ],
        ]);
        begin_account_session($username, $babyId);
        audit_event('gallery.setup', ['username' => $username]);
        json_response(true, 'Private gallery created.');
    }

    if ($action === 'login') {
        require_csrf();
        $auth = auth_data();
        $username = trim((string) ($_POST['username'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        if (!valid_username($username)) {
            json_response(false, 'Enter the baby’s first name.', null, [['code' => 'INVALID_USERNAME']], 422);
        }
        $key = normalize_username($username);
        $account = $auth['accounts'][$key] ?? null;
        if (is_array($account) && password_verify($password, (string) ($account['passwordHash'] ?? ''))) {
            begin_account_session((string) $account['username'], (string) $account['babyId']);
            audit_event('auth.login');
            json_response(true, 'Signed in.');
        }
        if (isset($auth['passwordHash']) && password_verify($password, (string) $auth['passwordHash'])) {
            $babyId = bin2hex(random_bytes(12));
            migrate_legacy_gallery($babyId, $username);
            $account = [
                'username' => $username,
                'babyId' => $babyId,
                'passwordHash' => (string) $auth['passwordHash'],
                'createdAt' => (string) ($auth['updatedAt'] ?? gmdate('c')),
                'updatedAt' => gmdate('c'),
            ];
            write_auth(['accounts' => [$key => $account]]);
            begin_account_session($username, $babyId);
            audit_event('auth.legacy_migrated');
            json_response(true, 'Gallery upgraded and signed in.');
        }
        usleep(350000);
        audit_event('auth.failed', ['username' => $username]);
        json_response(false, 'That username and password did not match.', null, [['code' => 'INVALID_LOGIN']], 401);
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
        $account = current_account();
        json_response(true, 'Gallery loaded.', [
            'settings' => tracker_settings(),
            'records' => $records,
            'account' => ['username' => (string) $account['username']],
        ]);
    }

    if ($action === 'change_password') {
        require_csrf();
        $currentPassword = (string) ($_POST['currentPassword'] ?? '');
        $newPassword = (string) ($_POST['newPassword'] ?? '');
        $confirmNewPassword = (string) ($_POST['confirmNewPassword'] ?? '');
        $auth = auth_data();
        $key = normalize_username((string) $_SESSION['username']);
        $account = $auth['accounts'][$key] ?? null;
        if (!is_array($account) || !password_verify($currentPassword, (string) ($account['passwordHash'] ?? ''))) {
            json_response(false, 'The current password is incorrect.', null, [['code' => 'INVALID_CURRENT_PASSWORD']], 401);
        }
        if (strlen($newPassword) < 10 || $newPassword !== $confirmNewPassword) {
            json_response(false, 'Use at least 10 characters and make both new password entries match.', null, [['code' => 'INVALID_NEW_PASSWORD']], 422);
        }
        if (password_verify($newPassword, (string) $account['passwordHash'])) {
            json_response(false, 'Choose a new password that is different from the current password.', null, [['code' => 'PASSWORD_UNCHANGED']], 422);
        }
        $auth['accounts'][$key]['passwordHash'] = password_hash($newPassword, PASSWORD_DEFAULT);
        $auth['accounts'][$key]['updatedAt'] = gmdate('c');
        write_auth($auth);
        audit_event('auth.password_changed');
        json_response(true, 'Password changed.');
    }

    if ($action === 'add_baby') {
        require_csrf();
        $username = trim((string) ($_POST['babyUsername'] ?? ''));
        $password = (string) ($_POST['babyPassword'] ?? '');
        $confirm = (string) ($_POST['confirmBabyPassword'] ?? '');
        if (!valid_username($username)) {
            json_response(false, 'Use the baby’s first name as the username.', null, [['code' => 'INVALID_USERNAME']], 422);
        }
        if (strlen($password) < 10 || $password !== $confirm) {
            json_response(false, 'Use at least 10 characters and make both password entries match.', null, [['code' => 'INVALID_PASSWORD']], 422);
        }
        $auth = auth_data();
        $key = normalize_username($username);
        if (isset($auth['accounts'][$key])) {
            json_response(false, 'That baby username already exists.', null, [['code' => 'USERNAME_EXISTS']], 409);
        }
        $babyId = bin2hex(random_bytes(12));
        initialize_baby_storage($babyId);
        $account = [
            'username' => $username,
            'babyId' => $babyId,
            'passwordHash' => password_hash($password, PASSWORD_DEFAULT),
            'createdAt' => gmdate('c'),
            'updatedAt' => gmdate('c'),
        ];
        $auth['accounts'][$key] = $account;
        write_auth($auth);
        begin_account_session($username, $babyId);
        baby_json_write('data/settings.json', [
            'schemaVersion' => 2,
            'babyName' => $username,
            'dueDate' => '2026-11-02',
            'birthDate' => '',
            'birthTime' => '',
            'birthLengthInches' => '',
            'birthWeightPounds' => '',
            'birthWeightOunces' => '',
            'updatedAt' => gmdate('c'),
        ]);
        audit_event('baby.created');
        json_response(true, 'Baby gallery created.', ['username' => $username], [], 201);
    }

    if ($action === 'settings') {
        require_csrf();
        $dueDate = (string) ($_POST['dueDate'] ?? '');
        $birthDate = (string) ($_POST['birthDate'] ?? '');
        $birthTime = trim((string) ($_POST['birthTime'] ?? ''));
        $birthLength = trim((string) ($_POST['birthLengthInches'] ?? ''));
        $birthWeightPounds = trim((string) ($_POST['birthWeightPounds'] ?? ''));
        $birthWeightOunces = trim((string) ($_POST['birthWeightOunces'] ?? ''));
        $latestClientDate = date('Y-m-d', strtotime('+1 day'));
        if (!valid_date($dueDate) || ($birthDate !== '' && (!valid_date($birthDate) || $birthDate > $latestClientDate))) {
            json_response(false, 'Enter valid due and birth dates.', null, [['code' => 'INVALID_DATE']], 422);
        }
        if ($birthTime !== '' && preg_match('/^(?:[01]\\d|2[0-3]):[0-5]\\d$/', $birthTime) !== 1) {
            json_response(false, 'Enter a valid time of birth.', null, [['code' => 'INVALID_TIME']], 422);
        }
        if (($birthLength !== '' && (!is_numeric($birthLength) || (float) $birthLength < 5 || (float) $birthLength > 30))
            || ($birthWeightPounds !== '' && (!ctype_digit($birthWeightPounds) || (int) $birthWeightPounds > 25))
            || ($birthWeightOunces !== '' && (!ctype_digit($birthWeightOunces) || (int) $birthWeightOunces > 15))) {
            json_response(false, 'Enter valid optional birth measurements.', null, [['code' => 'INVALID_MEASUREMENT']], 422);
        }
        $settings = [
            'schemaVersion' => 2,
            'babyName' => trim(substr((string) ($_POST['babyName'] ?? ''), 0, 80)),
            'dueDate' => $dueDate,
            'birthDate' => $birthDate,
            'birthTime' => $birthTime,
            'birthLengthInches' => $birthLength,
            'birthWeightPounds' => $birthWeightPounds,
            'birthWeightOunces' => $birthWeightOunces,
            'updatedAt' => gmdate('c'),
        ];
        baby_json_write('data/settings.json', $settings);
        audit_event('settings.updated');
        json_response(true, 'Settings saved.', $settings);
    }

    if ($action === 'upload') {
        require_csrf();
        $photoDate = (string) ($_POST['photoDate'] ?? '');
        // Permit one calendar day beyond server time for clients near a timezone boundary.
        $latestClientDate = date('Y-m-d', strtotime('+1 day'));
        if (!valid_date($photoDate) || $photoDate > $latestClientDate) {
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
        $destination = baby_storage_path('uploads/' . $filename);
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
        baby_json_write('data/entries.json', $entries);
        audit_event('photo.uploaded', ['id' => $id, 'photoDate' => $photoDate]);
        json_response(true, 'Daily photo saved.', $record, [], 201);
    }

    json_response(false, 'Unknown action.', null, [['code' => 'NOT_FOUND']], 404);
} catch (Throwable $exception) {
    error_log('Baby Daily [' . request_id() . '] ' . $exception->getMessage());
    json_response(false, 'Something went wrong. Try again.', null, [['code' => 'SERVER_ERROR']], 500);
}
