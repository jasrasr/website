<?php
/**
 * File: api/delete-media.php
 * Project: TV Binge Board
 * Description: Deletes a movie or show from a current user library, or a target user library for admins.
 * Author: Jason Lamb / ChatGPT
 * Created: 2026-07-02
 * Modified: 2026-07-02
 * Revision: 1.4.2
 */
declare(strict_types=1);


require_once __DIR__ . '/../includes/functions.php';
$user = app_require_login();
app_verify_csrf();
$targetUser = app_is_admin($user) ? app_sanitize_username((string)($_POST['target_user'] ?? '')) : (string)$user['username'];
if ($targetUser === '' || !app_find_user($targetUser)) { http_response_code(400); exit('Invalid target user.'); }
$uid = (string)($_POST['uid'] ?? '');
$library = app_library($targetUser);
$before = count($library['items']);
$library['items'] = array_values(array_filter($library['items'], fn($item) => ($item['uid'] ?? '') !== $uid));
app_save_library($targetUser, $library);
if (count($library['items']) !== $before) {
    $cleanupStats = app_remove_unused_artwork_cache();
    app_log_activity((string)$user['username'], 'media-deleted', $targetUser, ['uid' => $uid, 'artwork_cleanup' => $cleanupStats]);
    app_flash('Item deleted. Removed unused cached artwork files: ' . $cleanupStats['removed'] . '.', 'success');
} else {
    app_flash('Item was not found.', 'warning');
}
header('Location: ' . (string)($_POST['redirect'] ?? '../watchlist.php'));
exit;
