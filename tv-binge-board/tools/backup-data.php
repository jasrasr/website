<?php
/**
 * File: tools/backup-data.php
 * Project: TV Binge Board
 * Description: Command-line backup helper that zips the data folder while excluding locks and temporary files.
 * Author: Jason Lamb / ChatGPT
 * Created: 2026-07-02
 * Modified: 2026-07-02
 * Revision: 1.4.2
 */
declare(strict_types=1);


require_once __DIR__ . '/../includes/functions.php';
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('This script is CLI-only.');
}
if (!class_exists('ZipArchive')) {
    fwrite(STDERR, "ZipArchive is not available in this PHP build.\n");
    exit(1);
}
app_ensure_dir(APP_BACKUP_DIR);
$zipPath = APP_BACKUP_DIR . DIRECTORY_SEPARATOR . 'tv-binge-board-data-' . date('Ymd-His') . '.zip';
$zip = new ZipArchive();
if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    fwrite(STDERR, "Unable to create backup zip.\n");
    exit(1);
}
$baseLen = strlen(APP_DATA_DIR) + 1;
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(APP_DATA_DIR, FilesystemIterator::SKIP_DOTS));
foreach ($iterator as $file) {
    $path = $file->getPathname();
    if (str_contains($path, DIRECTORY_SEPARATOR . 'backups' . DIRECTORY_SEPARATOR)) { continue; }
    if (preg_match('/\.(lock|tmp\.[a-f0-9]+)$/', $path)) { continue; }
    $zip->addFile($path, substr($path, $baseLen));
}
$zip->close();
echo $zipPath . PHP_EOL;
