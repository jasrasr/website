<?php
/**
 * File: api/admin-user-action.php
 * Project: TV Binge Board
 * Description: Admin-only account creation, enable, disable, and password reset actions.
 * Author: Jason Lamb / ChatGPT
 * Created: 2026-07-02
 * Modified: 2026-07-02
 * Revision: 1.4.3
 */
declare(strict_types=1);


require_once __DIR__ . '/../includes/functions.php';
$admin = app_require_admin();
app_verify_csrf();
$action = (string)($_POST['action'] ?? '');
try {
    if ($action === 'create_user') {
        $created = app_create_user(
            (string)($_POST['username'] ?? ''),
            (string)($_POST['new_password'] ?? ''),
            (string)($_POST['display_name'] ?? '')
        );
        $createdUsername = (string)($created['username'] ?? '');
        app_log_activity((string)$admin['username'], 'user-created-admin', $createdUsername);
        app_flash('User created.', 'success');
        header('Location: ../admin/users.php');
        exit;
    }

    $targetUsername = app_sanitize_username((string)($_POST['target_user'] ?? ''));
    $target = $targetUsername !== '' ? app_find_user($targetUsername) : null;
    if (!$target) { http_response_code(404); exit('Target account not found.'); }

    if ($action === 'disable') {
        if (($target['role'] ?? '') === 'admin') { throw new RuntimeException('Admin accounts cannot be disabled here.'); }
        $target['disabled'] = true;
        app_update_account($target);
        app_log_activity((string)$admin['username'], 'user-disabled', $targetUsername);
        app_flash('User disabled.', 'success');
    } elseif ($action === 'enable') {
        $target['disabled'] = false;
        app_update_account($target);
        app_log_activity((string)$admin['username'], 'user-enabled', $targetUsername);
        app_flash('User enabled.', 'success');
    } elseif ($action === 'reset_password') {
        app_admin_reset_password((string)$admin['username'], $targetUsername, (string)($_POST['new_password'] ?? ''));
        app_flash('Password reset.', 'success');
    } else {
        throw new RuntimeException('Unknown action.');
    }
} catch (Throwable $ex) {
    app_flash($ex->getMessage(), 'danger');
}
header('Location: ../admin/users.php');
exit;
