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
        $babyName = trim((string) ($_POST['babyName'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $confirm = (string) ($_POST['confirmPassword'] ?? '');
        if (!valid_username($username) || $babyName === '' || strlen($babyName) > 80) {
            json_response(false, 'Enter a username and the baby’s name.', null, [['code' => 'INVALID_NAME']], 422);
        }
        if (strlen($password) < 10 || $password !== $confirm) {
            json_response(false, 'Use at least 10 characters and make both password entries match.', null, [['code' => 'INVALID_PASSWORD']], 422);
        }
        $babyId = bin2hex(random_bytes(12));
        initialize_baby_storage($babyId);
        json_write('babies/' . $babyId . '/data/settings.json', [
            'schemaVersion' => 2,
            'babyName' => $babyName,
            'dueDate' => '2026-11-02',
            'birthDate' => '',
            'birthTime' => '',
            'birthLengthInches' => '',
            'birthWeightPounds' => '',
            'birthWeightOunces' => '',
            'updatedAt' => gmdate('c'),
        ]);
        write_auth([
            'babies' => [$babyId => ['name' => $babyName, 'createdAt' => gmdate('c')]],
            'users' => [
                normalize_username($username) => [
                    'userId' => bin2hex(random_bytes(12)),
                    'username' => $username,
                    'babyIds' => [$babyId],
                    'homeBabyId' => $babyId,
                    'role' => 'super_admin',
                    'passwordHash' => password_hash($password, PASSWORD_DEFAULT),
                    'createdAt' => gmdate('c'),
                    'updatedAt' => gmdate('c'),
                ],
            ],
        ]);
        begin_account_session(auth_data()['users'][normalize_username($username)]);
        audit_event('gallery.setup', ['username' => $username]);
        json_response(true, 'Private gallery created.');
    }

    if ($action === 'login') {
        require_csrf();
        $auth = auth_data();
        $username = trim((string) ($_POST['username'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        if (!valid_username($username)) {
            json_response(false, 'Enter your username.', null, [['code' => 'INVALID_USERNAME']], 422);
        }
        $key = normalize_username($username);
        $account = $auth['users'][$key] ?? null;
        if (is_array($account) && password_verify($password, (string) ($account['passwordHash'] ?? ''))) {
            begin_account_session($account);
            audit_event('auth.login');
            json_response(true, 'Signed in.');
        }
        if (isset($auth['passwordHash']) && password_verify($password, (string) $auth['passwordHash'])) {
            $babyId = bin2hex(random_bytes(12));
            migrate_legacy_gallery($babyId, $username);
            $backup = storage_path('backups/auth-before-user-management.json');
            if (!is_file($backup) && !copy(storage_path('data/auth.json'), $backup)) {
                throw new RuntimeException('Unable to back up legacy authentication.');
            }
            $babyName = (string) (json_read('babies/' . $babyId . '/data/settings.json', [])['babyName'] ?? $username);
            $account = [
                'userId' => bin2hex(random_bytes(12)),
                'username' => $username,
                'babyIds' => [$babyId],
                'homeBabyId' => $babyId,
                'role' => 'super_admin',
                'passwordHash' => (string) $auth['passwordHash'],
                'createdAt' => (string) ($auth['updatedAt'] ?? gmdate('c')),
                'updatedAt' => gmdate('c'),
            ];
            write_auth(['users' => [$key => $account], 'babies' => [$babyId => ['name' => $babyName, 'createdAt' => gmdate('c')]]]);
            begin_account_session($account);
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
            'account' => ['username' => (string) $account['username'], 'role' => (string) ($account['role'] ?? 'user')],
            'babyId' => current_baby_id(),
        ]);
    }

    if ($action === 'select_baby') {
        require_csrf();
        $babyId = (string) ($_POST['babyId'] ?? '');
        $auth = auth_data();
        if (!isset($auth['babies'][$babyId])) {
            json_response(false, 'That baby gallery was not found.', null, [['code' => 'NOT_FOUND']], 404);
        }
        if (!can_access_baby(current_account(), $babyId, $auth)) {
            json_response(false, 'You cannot open that gallery.', null, [['code' => 'FORBIDDEN']], 403);
        }
        $_SESSION['selectedBabyId'] = $babyId;
        audit_event('baby.selected', ['selectedBabyId' => $babyId]);
        json_response(true, 'Baby gallery selected.');
    }

    if ($action === 'change_password') {
        require_csrf();
        $currentPassword = (string) ($_POST['currentPassword'] ?? '');
        $newPassword = (string) ($_POST['newPassword'] ?? '');
        $confirmNewPassword = (string) ($_POST['confirmNewPassword'] ?? '');
        $auth = auth_data();
        $key = normalize_username((string) $_SESSION['username']);
        $account = $auth['users'][$key] ?? null;
        if (!is_array($account) || !password_verify($currentPassword, (string) ($account['passwordHash'] ?? ''))) {
            json_response(false, 'The current password is incorrect.', null, [['code' => 'INVALID_CURRENT_PASSWORD']], 401);
        }
        if (strlen($newPassword) < 10 || $newPassword !== $confirmNewPassword) {
            json_response(false, 'Use at least 10 characters and make both new password entries match.', null, [['code' => 'INVALID_NEW_PASSWORD']], 422);
        }
        if (password_verify($newPassword, (string) $account['passwordHash'])) {
            json_response(false, 'Choose a new password that is different from the current password.', null, [['code' => 'PASSWORD_UNCHANGED']], 422);
        }
        $auth['users'][$key]['passwordHash'] = password_hash($newPassword, PASSWORD_DEFAULT);
        $auth['users'][$key]['updatedAt'] = gmdate('c');
        write_auth($auth);
        audit_event('auth.password_changed');
        json_response(true, 'Password changed.');
    }

    if ($action === 'add_baby') {
        require_csrf();
        if (!is_super_admin()) {
            json_response(false, 'Only the admin can add a baby.', null, [['code' => 'FORBIDDEN']], 403);
        }
        $babyName = trim((string) ($_POST['babyName'] ?? ''));
        if ($babyName === '' || strlen($babyName) > 80) {
            json_response(false, 'Enter the baby’s name.', null, [['code' => 'INVALID_NAME']], 422);
        }
        $auth = auth_data();
        $babyId = bin2hex(random_bytes(12));
        initialize_baby_storage($babyId);
        json_write('babies/' . $babyId . '/data/settings.json', [
            'schemaVersion' => 2,
            'babyName' => $babyName,
            'dueDate' => '2026-11-02',
            'birthDate' => '',
            'birthTime' => '',
            'birthLengthInches' => '',
            'birthWeightPounds' => '',
            'birthWeightOunces' => '',
            'updatedAt' => gmdate('c'),
        ]);
        $auth['babies'][$babyId] = ['name' => $babyName, 'createdAt' => gmdate('c')];
        write_auth($auth);
        $_SESSION['selectedBabyId'] = $babyId;
        audit_event('baby.created', ['babyId' => $babyId]);
        json_response(true, 'Baby gallery created.', ['babyId' => $babyId], [], 201);
    }

    if ($action === 'users') {
        if (!is_super_admin()) {
            json_response(false, 'Only the admin can manage users.', null, [['code' => 'FORBIDDEN']], 403);
        }
        $auth = auth_data();
        $users = [];
        foreach ($auth['users'] as $user) {
            $users[] = [
                'username' => $user['username'],
                'role' => $user['role'],
                'babyIds' => $user['babyIds'] ?? [],
            ];
        }
        json_response(true, 'Users loaded.', ['users' => $users, 'babies' => $auth['babies']]);
    }

    if ($action === 'add_user') {
        require_csrf();
        if (!is_super_admin()) {
            json_response(false, 'Only the admin can manage users.', null, [['code' => 'FORBIDDEN']], 403);
        }
        $username = trim((string) ($_POST['username'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $confirm = (string) ($_POST['confirmPassword'] ?? '');
        $babyIds = $_POST['babyIds'] ?? [];
        $auth = auth_data();
        if (!valid_username($username)) {
            json_response(false, 'Enter a valid username.', null, [['code' => 'INVALID_USERNAME']], 422);
        }
        if (isset($auth['users'][normalize_username($username)])) {
            json_response(false, 'That username already exists.', null, [['code' => 'USERNAME_EXISTS']], 409);
        }
        if (strlen($password) < 10 || $password !== $confirm) {
            json_response(false, 'Use at least 10 characters and make both password entries match.', null, [['code' => 'INVALID_PASSWORD']], 422);
        }
        if (!is_array($babyIds) || $babyIds === [] || count(array_filter($babyIds, 'is_string')) !== count($babyIds) || array_diff($babyIds, array_keys($auth['babies']))) {
            json_response(false, 'Choose at least one valid gallery.', null, [['code' => 'INVALID_ACCESS']], 422);
        }
        $babyIds = array_values(array_unique($babyIds));
        $auth['users'][normalize_username($username)] = [
            'userId' => bin2hex(random_bytes(12)),
            'username' => $username,
            'role' => 'user',
            'passwordHash' => password_hash($password, PASSWORD_DEFAULT),
            'babyIds' => $babyIds,
            'homeBabyId' => $babyIds[0],
            'createdAt' => gmdate('c'),
            'updatedAt' => gmdate('c'),
        ];
        write_auth($auth);
        audit_event('user.created', ['createdUsername' => $username]);
        json_response(true, 'User created.', null, [], 201);
    }

    if ($action === 'update_user_access') {
        require_csrf();
        if (!is_super_admin()) {
            json_response(false, 'Only the admin can manage users.', null, [['code' => 'FORBIDDEN']], 403);
        }
        $key = normalize_username((string) ($_POST['username'] ?? ''));
        $babyIds = $_POST['babyIds'] ?? [];
        $auth = auth_data();
        if (!isset($auth['users'][$key])) {
            json_response(false, 'User not found.', null, [['code' => 'NOT_FOUND']], 404);
        }
        if (!is_array($babyIds) || $babyIds === [] || count(array_filter($babyIds, 'is_string')) !== count($babyIds) || array_diff($babyIds, array_keys($auth['babies']))) {
            json_response(false, 'Choose at least one valid gallery.', null, [['code' => 'INVALID_ACCESS']], 422);
        }
        $babyIds = array_values(array_unique($babyIds));
        $auth['users'][$key]['babyIds'] = $babyIds;
        if (!in_array($auth['users'][$key]['homeBabyId'], $babyIds, true)) {
            $auth['users'][$key]['homeBabyId'] = $babyIds[0];
        }
        $auth['users'][$key]['updatedAt'] = gmdate('c');
        write_auth($auth);
        audit_event('user.access_updated', ['updatedUsername' => $auth['users'][$key]['username']]);
        json_response(true, 'Gallery access updated.');
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
        $auth = auth_data();
        $babyId = current_baby_id();
        $displayName = $settings['babyName'] !== '' ? $settings['babyName'] : 'Baby';
        if (($auth['babies'][$babyId]['name'] ?? '') !== $displayName) {
            $auth['babies'][$babyId]['name'] = $displayName;
            write_auth($auth);
        }
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
                json_response(false, 'That date already has a photo. Remove it first or choose another date.', null, [['code' => 'DATE_EXISTS']], 409);
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

    if ($action === 'delete_photo') {
        require_csrf();
        $id = (string) ($_POST['id'] ?? '');
        if (preg_match('/^[a-f0-9]{24}$/', $id) !== 1) {
            json_response(false, 'Photo not found.', null, [['code' => 'NOT_FOUND']], 404);
        }
        $entries = tracker_entries();
        $records = $entries['records'] ?? [];
        $index = null;
        foreach ($records as $key => $record) {
            if (($record['id'] ?? '') === $id) {
                $index = $key;
                break;
            }
        }
        if ($index === null) {
            json_response(false, 'Photo not found.', null, [['code' => 'NOT_FOUND']], 404);
        }
        $record = $records[$index];
        $filename = (string) ($record['filename'] ?? '');
        if (preg_match('/^' . preg_quote($id, '/') . '\\.(?:jpg|png|webp)$/', $filename) !== 1) {
            throw new RuntimeException('Invalid stored photo filename.');
        }
        $path = baby_storage_path('uploads/' . $filename);
        $temporary = baby_storage_path('uploads/.deleting-' . $id . '-' . bin2hex(random_bytes(4)));
        $moved = false;
        if (is_file($path)) {
            if (!rename($path, $temporary)) {
                throw new RuntimeException('Unable to prepare photo deletion.');
            }
            $moved = true;
        }
        unset($records[$index]);
        $entries['records'] = array_values($records);
        $entries['updatedAt'] = gmdate('c');
        try {
            baby_json_write('data/entries.json', $entries);
        } catch (Throwable $exception) {
            if ($moved) {
                rename($temporary, $path);
            }
            throw $exception;
        }
        if ($moved && !unlink($temporary)) {
            error_log('Baby Daily [' . request_id() . '] Unable to remove deleted photo file.');
        }
        audit_event('photo.deleted', ['id' => $id, 'photoDate' => (string) ($record['photoDate'] ?? ''), 'selectedBabyId' => current_baby_id()]);
        json_response(true, 'Photo removed.');
    }

    json_response(false, 'Unknown action.', null, [['code' => 'NOT_FOUND']], 404);
} catch (Throwable $exception) {
    error_log('Baby Daily [' . request_id() . '] ' . $exception->getMessage());
    json_response(false, 'Something went wrong. Try again.', null, [['code' => 'SERVER_ERROR']], 500);
}
