<?php
/** Filename: bootstrap.php | Revision: 1.0.0 | Author: Jason Lamb with ChatGPT
 * Created: 2026-11-17 | Modified: 2026-11-17 | Description: Kingdom Connections initialization. */
declare(strict_types=1);
require_once __DIR__ . '/lib/JsonStore.php';
require_once __DIR__ . '/Identity.php';
require_once __DIR__ . '/upload.php';
if (PHP_SAPI !== 'cli') {
    session_name('KC'.substr(hash('sha256', __DIR__),0,16));
    $cookiePath = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/') . '/';
    session_set_cookie_params(['path'=>$cookiePath,'httponly'=>true,'secure'=>!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off','samesite'=>'Lax']);
    ini_set('session.use_strict_mode','1');
    session_start();
    header('Cache-Control: no-store');
}
require_once __DIR__ . '/ServiceRecords.php';
require_once __DIR__ . '/Directory.php';
$config = ['storage_path' => __DIR__ . '/storage'];
if (is_file(__DIR__ . '/config.local.php')) $config = array_replace($config, require __DIR__ . '/config.local.php');
// Serialize app-level writes which may span the account and listing files.
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $writeLock = fopen($config['storage_path'].'/requests.lock','c');
    if (!$writeLock || !flock($writeLock, LOCK_EX)) { http_response_code(503); exit('Storage unavailable.'); }
}
$auth = new Identity($config['storage_path']);
$directory = new KingdomDirectory($config['storage_path'] . '/directory.json');
function h(mixed $value): string { return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function ratingLabel(array $business): string {
    $ratings = $business['ratings'] ?? [];
    return $ratings ? number_format(array_sum(array_column($ratings, 'stars')) / count($ratings), 1) . ' / 5 · ' . count($ratings) . ' rating(s)' : 'Not yet rated';
}
