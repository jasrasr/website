<?php
/*
===========================================================
 File: toggle_favorite.php
 Author: Jason Lamb
 Created: 2026-02-17
 Modified: 2026-02-17
 Revision: 2.0
 Description:
   Centralized favorite toggle handler.
   - Supports files and directories
   - Stores relative paths
   - Prevents directory traversal
   - Backward compatible with old JSON
===========================================================
*/

header('Content-Type: application/json');

date_default_timezone_set('America/New_York');

$root = realpath($_SERVER['DOCUMENT_ROOT']);
$favoritesFile = $root . '/custom-directory/favorites.json';

/* ===========================================================
   Ensure directory + file exist
=========================================================== */

if (!file_exists(dirname($favoritesFile))) {
    mkdir(dirname($favoritesFile), 0755, true);
}

if (!file_exists($favoritesFile)) {
    file_put_contents(
        $favoritesFile,
        json_encode(['favorites' => []], JSON_PRETTY_PRINT)
    );
}

/* ===========================================================
   Validate input
=========================================================== */

$inputPath = $_POST['file'] ?? '';

if (!$inputPath) {
    echo json_encode(['success' => false, 'error' => 'No file specified']);
    exit;
}

/*
   Expecting a relative path like:
   /files/weather
   /files/test.png
*/

$normalizedPath = realpath($root . '/' . ltrim($inputPath, '/'));

if (!$normalizedPath || strpos($normalizedPath, $root) !== 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid path']);
    exit;
}

/* Convert back to relative path */
$relativePath = str_replace($root, '', $normalizedPath);

/* ===========================================================
   Load existing favorites
=========================================================== */

$data = json_decode(file_get_contents($favoritesFile), true);
$favorites = $data['favorites'] ?? [];

/* Backward compatibility:
   If old entries exist like "weather",
   convert them to relative paths automatically.
*/
$favorites = array_map(function ($item) use ($root) {

    if (str_starts_with($item, '/')) {
        return $item;
    }

    $possible = realpath($root . '/' . $item);
    if ($possible && strpos($possible, $root) === 0) {
        return str_replace($root, '', $possible);
    }

    return $item;

}, $favorites);

/* ===========================================================
   Toggle favorite
=========================================================== */

if (in_array($relativePath, $favorites, true)) {

    $favorites = array_values(
        array_filter($favorites, fn($f) => $f !== $relativePath)
    );

    $isFavorite = false;

} else {

    $favorites[] = $relativePath;
    $isFavorite = true;
}

/* ===========================================================
   Save
=========================================================== */

file_put_contents(
    $favoritesFile,
    json_encode(['favorites' => array_values($favorites)], JSON_PRETTY_PRINT)
);

/* ===========================================================
   Response
=========================================================== */

echo json_encode([
    'success'  => true,
    'favorite' => $isFavorite,
    'path'     => $relativePath
]);
