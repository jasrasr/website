<?php
/**
 * File: api/search-tmdb.php
 * Project: TV Binge Board
 * Description: Authenticated JSON endpoint for searching TMDB movie and TV metadata.
 * Author: Jason Lamb / ChatGPT
 * Created: 2026-07-02
 * Modified: 2026-07-02
 * Revision: 1.4.2
 */
declare(strict_types=1);

require_once __DIR__ . '/../includes/tmdb.php';
header('Content-Type: application/json; charset=utf-8');
$user = app_require_login();
if (!app_can_track($user)) {
    http_response_code(403);
    echo json_encode(['error' => 'Admin accounts cannot track media.']);
    exit;
}

try {
    $results = app_tmdb_search((string)($_GET['q'] ?? ''));
    echo json_encode(['results' => $results], JSON_UNESCAPED_SLASHES);
} catch (Throwable $ex) {
    http_response_code(400);
    echo json_encode(['error' => $ex->getMessage()], JSON_UNESCAPED_SLASHES);
}
