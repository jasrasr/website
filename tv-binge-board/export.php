<?php
/**
 * File: export.php
 * Project: TV Binge Board
 * Description: Exports a user library as JSON or CSV, with admin support for target-user exports.
 * Author: Jason Lamb / ChatGPT
 * Created: 2026-07-02
 * Modified: 2026-07-02
 * Revision: 1.4.2
 */
declare(strict_types=1);


require_once __DIR__ . '/includes/functions.php';
$user = app_require_login();
$targetUsername = app_is_admin($user) && isset($_GET['u']) ? app_sanitize_username((string)$_GET['u']) : (string)$user['username'];
$target = app_find_user($targetUsername);
if (!$target || (!app_is_admin($user) && $targetUsername !== $user['username'])) { http_response_code(403); exit('Forbidden.'); }
$library = app_library($targetUsername);
$format = strtolower((string)($_GET['format'] ?? 'json'));
$stamp = date('Ymd-His');
if ($format === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="tv-binge-board-' . $targetUsername . '-' . $stamp . '.csv"');
    echo app_export_csv($library['items']);
    exit;
}
header('Content-Type: application/json; charset=utf-8');
header('Content-Disposition: attachment; filename="tv-binge-board-' . $targetUsername . '-' . $stamp . '.json"');
echo json_encode($library, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
exit;
