<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/bootstrap.php';
$cutoff = gmdate('Y-m-d', time() - config()['retention_days'] * 86400);
$count = 0;
foreach (glob(storage() . '/events-*.json') ?: [] as $file) {
    if (preg_match('/^events-(\d{4}-\d{2}-\d{2})\.json$/D', basename($file), $match) && $match[1] < $cutoff) {
        // Historical dates cannot receive new collector events (timestamps are server-assigned).
        if (unlink($file)) $count++;
        if (is_file($file . '.lock')) unlink($file . '.lock');
    }
}
echo "Removed $count expired daily files.\n";
