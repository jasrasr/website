<?php

declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

$action = (string) ($_GET['action'] ?? $_POST['action'] ?? '');

try {
    if ($action === 'setup' || $action === 'login' || $action === 'change_password' || $action === 'add_user') {
        json_response(false, 'Daily Babe now uses central User Management for accounts and passwords.', [
            'portal' => daily_auth()->portal(),
        ], [['code' => 'CENTRAL_AUTH_REQUIRED']], 410);
    }

    if ($action === 'logout') {
        require_auth();
        require_csrf();
        audit_event('auth.logout');
        daily_auth()->logout();
        json_response(true, 'Signed out.', ['portal' => daily_auth()->portal()]);
    }

    require_auth();

    if ($action === 'list') {
        $entries = tracker_entries();
        $records = array_values($entries['records'] ?? []);
        usort($records, static fn(array $a, array $b): int => strcmp((string) $b['photoDate'], (string) $a['photoDate']));
        $account = current_account();
        $settings = tracker_settings();
        $settings['visibility'] = gallery_visibility($settings);
        $settings['publicSlug'] = public_gallery_slug($settings);
        json_response(true, 'Gallery loaded.', [
            'settings' => $settings,
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
        $_SESSION['daily_babe_selected_baby_id'] = $babyId;
        audit_event('baby.selected', ['selectedBabyId' => $babyId]);
        json_response(true, 'Baby gallery selected.');
    }

    if ($action === 'add_baby') {
        require_csrf();
        require_project_role('admin');
        $babyName = trim((string) ($_POST['babyName'] ?? ''));
        if ($babyName === '' || strlen($babyName) > 80) {
            json_response(false, 'Enter the baby’s name.', null, [['code' => 'INVALID_NAME']], 422);
        }
        $auth = auth_data();
        $babyId = bin2hex(random_bytes(12));
        initialize_baby_storage($babyId, $babyName);
        $auth['babies'][$babyId] = ['name' => $babyName, 'createdAt' => gmdate('c')];
        write_auth($auth);
        $_SESSION['daily_babe_selected_baby_id'] = $babyId;
        audit_event('baby.created', ['babyId' => $babyId]);
        json_response(true, 'Baby gallery created.', ['babyId' => $babyId], [], 201);
    }

    if ($action === 'users') {
        require_project_role('admin');
        $auth = tracker_state();
        $users = [];
        foreach (central_users_with_project_access() as $user) {
            $membership = $auth['memberships'][$user['id']] ?? ['babyIds' => []];
            $users[] = [
                'id' => $user['id'],
                'username' => $user['username'],
                'name' => $user['name'],
                'role' => $user['projectRole'],
                'babyIds' => $membership['babyIds'] ?? [],
            ];
        }
        json_response(true, 'Users loaded.', ['users' => $users, 'babies' => $auth['babies']]);
    }

    if ($action === 'update_user_access') {
        require_csrf();
        require_project_role('admin');
        $userId = (string) ($_POST['userId'] ?? '');
        $babyIds = $_POST['babyIds'] ?? [];
        $auth = tracker_state();
        $knownUsers = array_column(central_users_with_project_access(), null, 'id');
        if (!isset($knownUsers[$userId])) {
            json_response(false, 'User not found.', null, [['code' => 'NOT_FOUND']], 404);
        }
        if (!is_array($babyIds) || count(array_filter($babyIds, 'is_string')) !== count($babyIds) || array_diff($babyIds, array_keys($auth['babies']))) {
            json_response(false, 'Choose valid galleries.', null, [['code' => 'INVALID_ACCESS']], 422);
        }
        $babyIds = array_values(array_unique($babyIds));
        if ($babyIds === []) {
            unset($auth['memberships'][$userId]);
        } else {
            $existingHome = (string) ($auth['memberships'][$userId]['homeBabyId'] ?? '');
            $auth['memberships'][$userId] = [
                'babyIds' => $babyIds,
                'homeBabyId' => in_array($existingHome, $babyIds, true) ? $existingHome : $babyIds[0],
                'updatedAt' => gmdate('c'),
            ];
        }
        write_auth($auth);
        audit_event('membership.updated', ['updatedCentralUserId' => $userId, 'babyIds' => $babyIds]);
        json_response(true, 'Gallery access updated.');
    }

    if ($action === 'settings') {
        require_project_role('member');
        require_csrf();
        $dueDate = (string) ($_POST['dueDate'] ?? '');
        $birthDate = (string) ($_POST['birthDate'] ?? '');
        $birthTime = trim((string) ($_POST['birthTime'] ?? ''));
        $birthLength = trim((string) ($_POST['birthLengthInches'] ?? ''));
        $birthWeightPounds = trim((string) ($_POST['birthWeightPounds'] ?? ''));
        $birthWeightOunces = trim((string) ($_POST['birthWeightOunces'] ?? ''));
        $reminderEnabled = (string) ($_POST['reminderEnabled'] ?? '') === '1';
        $reminderTime = trim((string) ($_POST['reminderTime'] ?? '20:00'));
        $reminderEmailsInput = trim((string) ($_POST['reminderEmails'] ?? ''));
        $visibility = (string) ($_POST['visibility'] ?? (((string) ($_POST['publicEnabled'] ?? '') === '1') ? 'unlisted' : 'private'));
        if (!in_array($visibility, ['private', 'unlisted', 'public'], true)) {
            json_response(false, 'Choose a valid gallery visibility.', null, [['code' => 'INVALID_VISIBILITY']], 422);
        }
        $babyName = trim(substr((string) ($_POST['babyName'] ?? ''), 0, 80));
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
        if (preg_match('/^(?:[01]\\d|2[0-3]):[0-5]\\d$/', $reminderTime) !== 1) {
            json_response(false, 'Choose a valid reminder time.', null, [['code' => 'INVALID_REMINDER_TIME']], 422);
        }
        $reminderEmails = $reminderEmailsInput === ''
            ? []
            : preg_split('/[\\s,;]+/', $reminderEmailsInput, -1, PREG_SPLIT_NO_EMPTY);
        $reminderEmails = array_values(array_unique(array_map(
            static fn(string $email): string => strtolower(trim($email)),
            is_array($reminderEmails) ? $reminderEmails : []
        )));
        if (array_filter($reminderEmails, static fn(string $email): bool => !filter_var($email, FILTER_VALIDATE_EMAIL)) !== []) {
            json_response(false, 'Enter valid reminder email addresses.', null, [['code' => 'INVALID_REMINDER_EMAIL']], 422);
        }
        if ($reminderEnabled && $reminderEmails === []) {
            json_response(false, 'Add at least one email address for reminders.', null, [['code' => 'REMINDER_EMAIL_REQUIRED']], 422);
        }
        $publicCandidate = [
            'babyName' => $babyName,
            'birthDate' => $birthDate,
        ];
        $publicSlug = public_gallery_slug($publicCandidate);
        if ($visibility !== 'private' && $publicSlug === '') {
            json_response(false, 'Add the baby’s first name and actual birth date before sharing the gallery.', null, [['code' => 'PUBLIC_LINK_REQUIRES_BIRTH_DETAILS']], 422);
        }
        if ($visibility !== 'private') {
            $existingPublicGallery = public_gallery_by_slug($publicSlug);
            if ($existingPublicGallery !== null && $existingPublicGallery['babyId'] !== current_baby_id()) {
                json_response(false, 'That public baby link is already in use.', null, [['code' => 'PUBLIC_LINK_EXISTS']], 409);
            }
        }
        $settings = [
            'schemaVersion' => 5,
            'babyName' => $babyName,
            'dueDate' => $dueDate,
            'birthDate' => $birthDate,
            'birthTime' => $birthTime,
            'birthLengthInches' => $birthLength,
            'birthWeightPounds' => $birthWeightPounds,
            'birthWeightOunces' => $birthWeightOunces,
            'reminderEnabled' => $reminderEnabled,
            'reminderTime' => $reminderTime,
            'reminderEmails' => implode(', ', $reminderEmails),
            'visibility' => $visibility,
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
        $settings['visibility'] = $visibility;
        $settings['publicSlug'] = $publicSlug;
        json_response(true, 'Settings saved.', $settings);
    }

    if ($action === 'upload') {
        require_project_role('member');
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
        require_project_role('member');
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

