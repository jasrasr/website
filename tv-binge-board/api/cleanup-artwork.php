<?php
/**
 * File: api/cleanup-artwork.php
 * Project: TV Binge Board
 * Description: Admin-only maintenance endpoint that removes cached artwork files no longer referenced by tracked library items.
 * Author: Jason Lamb / ChatGPT
 * Created: 2026-07-02
 * Modified: 2026-07-02
 * Revision: 1.4.2
 */
declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';
$admin = app_require_admin();
app_verify_csrf();

$stats = app_remove_unused_artwork_cache();
app_log_activity((string)$admin['username'], 'artwork-cache-cleanup', 'public-cache', $stats);
app_flash('Artwork cleanup complete. Checked: ' . $stats['checked'] . '. Kept: ' . $stats['kept'] . '. Removed: ' . $stats['removed'] . '. Errors: ' . $stats['errors'] . '.', $stats['errors'] > 0 ? 'warning' : 'success');
header('Location: ' . (string)($_POST['redirect'] ?? '../admin/site-settings.php'));
exit;
