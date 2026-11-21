<?php
declare(strict_types=1);

/** Refill Tracker bootstrap, revision 1.5.3. */
require_once dirname(__DIR__) . '/1-Framework/bootstrap.php';

function refill_inventory_path(string $accountId): string
{
    $directory = __DIR__ . '/storage';
    if (realpath($directory) !== $directory || !is_dir($directory) || !is_writable($directory)
        || !is_file($directory . '/.htaccess')) {
        throw new RuntimeException('Refill Tracker storage is missing, unprotected, or not writable.');
    }

    return $directory . '/' . hash('sha256', $accountId) . '.json';
}
