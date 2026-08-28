<?php
/**
 * File: api/import-match-search.php
 * Project: TV Binge Board
 * Description: Authenticated JSON endpoint for import-review TMDB matching suggestions.
 * Author: Jason Lamb / ChatGPT
 * Created: 2026-07-02
 * Modified: 2026-07-02
 * Revision: 1.4.3
 */
declare(strict_types=1);

require_once __DIR__ . '/../includes/tmdb.php';
header('Content-Type: application/json; charset=utf-8');
$user = app_require_login();
if (!app_can_track($user) && !app_is_admin($user)) {
    http_response_code(403);
    echo json_encode(['error' => 'Import matching is not available for this account.']);
    exit;
}
try {
    $query = trim((string)($_GET['q'] ?? ''));
    $type = trim((string)($_GET['type'] ?? ''));
    $results = app_tmdb_search($query);
    if (in_array($type, ['movie', 'tv'], true)) {
        $results = array_values(array_filter($results, static fn($row) => is_array($row) && (($row['type'] ?? '') === $type)));
    }
    echo json_encode(['results' => array_slice($results, 0, 5)], JSON_UNESCAPED_SLASHES);
} catch (Throwable $ex) {
    http_response_code(400);
    echo json_encode(['error' => $ex->getMessage()]);
}
