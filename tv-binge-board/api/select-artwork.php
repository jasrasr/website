<?php
/**
 * File: api/select-artwork.php
 * Project: TV Binge Board
 * Description: Saves a preferred TMDB poster or backdrop choice for a linked item and caches the selected image locally when possible.
 * Author: Jason Lamb / ChatGPT
 * Created: 2026-07-02
 * Modified: 2026-07-02
 * Revision: 1.4.5
 */
declare(strict_types=1);

require_once __DIR__ . '/../includes/tmdb.php';
$user = app_require_login();
app_verify_csrf();

$targetUser = app_is_admin($user) ? app_sanitize_username((string)($_POST['target_user'] ?? '')) : (string)$user['username'];
if ($targetUser === '' || !app_find_user($targetUser)) { http_response_code(400); exit('Invalid target user.'); }
if (!app_is_admin($user) && !app_can_track($user)) { http_response_code(403); exit('This account cannot track media.'); }

$uid = (string)($_POST['uid'] ?? '');
$kind = (string)($_POST['kind'] ?? '');
$imagePath = trim((string)($_POST['image_path'] ?? ''));
if (!in_array($kind, ['poster', 'backdrop'], true)) { http_response_code(400); exit('Invalid artwork type.'); }
if (!preg_match('#^/[A-Za-z0-9._-]+\.(jpg|jpeg|png|webp)$#i', $imagePath)) { http_response_code(400); exit('Invalid TMDB image path.'); }

$library = app_library($targetUser);
$index = app_find_media_index($library, $uid);
if ($index === null) { http_response_code(404); exit('Item not found.'); }
$item = $library['items'][$index];
$tmdbId = (int)($item['tmdb_id'] ?? 0);
$type = (string)($item['type'] ?? 'movie');
if ($tmdbId <= 0 || !in_array($type, ['movie', 'tv'], true)) { http_response_code(400); exit('Item is not linked to TMDB.'); }

if ($kind === 'poster') {
    $item['poster_path'] = $imagePath;
    $item = app_tmdb_cache_item_poster($item, true);
} else {
    $item['backdrop_path'] = $imagePath;
    $filename = app_tmdb_cached_image_filename('backdrop', $type, $tmdbId, $imagePath, 'w500');
    $download = app_tmdb_download_image($imagePath, 'w500', 'backdrops', $filename, true);
    $item['backdrop_source_url'] = app_tmdb_image_url($imagePath, 'w500');
    $item['backdrop_last_checked_at'] = date(DATE_ATOM);
    if (!empty($download['ok']) && !empty($download['path'])) {
        $item['local_backdrop_path'] = (string)$download['path'];
        $item['backdrop_cached_source_path'] = $imagePath;
        $item['backdrop_cached_at'] = date(DATE_ATOM);
        unset($item['backdrop_cache_error']);
    } else {
        $item['backdrop_cache_error'] = (string)($download['reason'] ?? 'unknown');
    }
}

$item['artwork_selected_at'] = date(DATE_ATOM);
$item['updated_at'] = date(DATE_ATOM);
$library['items'][$index] = $item;
app_save_library($targetUser, $library);
app_log_activity((string)$user['username'], 'artwork-selected', $targetUser, ['uid' => $uid, 'kind' => $kind]);
app_flash(ucfirst($kind) . ' selection saved.', 'success');
$location = '../artwork.php?uid=' . rawurlencode($uid) . (app_is_admin($user) ? '&u=' . rawurlencode($targetUser) : '');
header('Location: ' . $location);
exit;
