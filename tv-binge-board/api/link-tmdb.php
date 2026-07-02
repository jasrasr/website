<?php
/**
 * File: api/link-tmdb.php
 * Project: TV Binge Board
 * Description: Links an existing manual media item to a TMDB record, merges metadata safely, and caches local poster art.
 * Author: Jason Lamb / ChatGPT
 * Created: 2026-07-02
 * Modified: 2026-07-02
 * Revision: 1.4.2
 */
declare(strict_types=1);

require_once __DIR__ . '/../includes/tmdb.php';
$user = app_require_login();
app_verify_csrf();

$targetUser = app_is_admin($user) ? app_sanitize_username((string)($_POST['target_user'] ?? '')) : (string)$user['username'];
if ($targetUser === '' || !app_find_user($targetUser)) { http_response_code(400); exit('Invalid target user.'); }
if (!app_is_admin($user) && !app_can_track($user)) { http_response_code(403); exit('This account cannot track media.'); }

$uid = (string)($_POST['uid'] ?? '');
$type = (string)($_POST['type'] ?? 'movie');
$type = in_array($type, ['movie', 'tv'], true) ? $type : 'movie';
$tmdbId = max(0, (int)($_POST['tmdb_id'] ?? 0));
if ($uid === '' || $tmdbId <= 0) { http_response_code(400); exit('Missing item or TMDB ID.'); }

$library = app_library($targetUser);
$index = app_find_media_index($library, $uid);
if ($index === null) { http_response_code(404); exit('Item not found.'); }

try {
    $original = $library['items'][$index];
    $details = app_tmdb_details($type, $tmdbId, true);
    $newUid = app_make_media_uid($type, $tmdbId, (string)($details['title'] ?? $original['title'] ?? ''));
    $linked = app_apply_tmdb_details_to_item($original, $details, true);
    $linked['uid'] = $newUid;
    $linked['type'] = $type;
    $linked['tmdb_id'] = $tmdbId;
    $linked['tmdb_url'] = app_tmdb_external_url($type, $tmdbId);
    $linked['linked_at'] = date(DATE_ATOM);
    $linked = app_tmdb_cache_item_artwork($linked, false, false, false);
    unset($linked['_artwork_stats']);

    $duplicateIndex = app_find_media_index($library, $newUid);
    if ($duplicateIndex !== null && $duplicateIndex !== $index) {
        $existing = $library['items'][$duplicateIndex];
        $existing['notes'] = trim((string)($existing['notes'] ?? '') . "\n" . (string)($linked['notes'] ?? ''));
        $existing['episodes'] = array_values(array_merge($existing['episodes'] ?? [], $linked['episodes'] ?? []));
        $existing = app_apply_tmdb_details_to_item($existing, $details, true);
        $existing['uid'] = $newUid;
        $existing = app_tmdb_cache_item_artwork($existing, false, false, false);
        unset($existing['_artwork_stats']);
        $existing['updated_at'] = date(DATE_ATOM);
        $library['items'][$duplicateIndex] = $existing;
        array_splice($library['items'], $index, 1);
        app_flash('Linked to an existing TMDB item and merged the manual item.', 'success');
    } else {
        $library['items'][$index] = $linked;
        app_flash('Item linked to TMDB.', 'success');
    }

    app_save_library($targetUser, $library);
    app_log_activity((string)$user['username'], 'media-linked-to-tmdb', $targetUser, ['old_uid' => $uid, 'new_uid' => $newUid, 'tmdb_id' => $tmdbId]);
    header('Location: ../item.php?uid=' . rawurlencode($newUid) . (app_is_admin($user) ? '&u=' . rawurlencode($targetUser) : ''));
} catch (Throwable $ex) {
    app_flash('TMDB link failed: ' . $ex->getMessage(), 'danger');
    header('Location: ../tmdb-link.php?uid=' . rawurlencode($uid) . (app_is_admin($user) ? '&u=' . rawurlencode($targetUser) : ''));
}
exit;
