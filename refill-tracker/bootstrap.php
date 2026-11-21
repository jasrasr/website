<?php
declare(strict_types=1);

/** Refill Tracker bootstrap, revision 1.0.0. */
require_once dirname(__DIR__) . '/1-Framework/bootstrap.php';

function refill_inventory_path(string $accountId, string $documentRoot): string
{
    $configFile = __DIR__ . '/config.local.php';
    $config = is_file($configFile) ? require $configFile : [];
    $configured = $config['storage_path'] ?? null;
    $directory = is_string($configured) && str_starts_with($configured, '/')
        ? realpath($configured) : false;
    $root = $documentRoot === '' ? false : realpath($documentRoot);
    if ($root === false || $directory === false || !is_dir($directory) || !is_writable($directory)
        || $directory === $root || str_starts_with($directory, rtrim($root, '/') . '/')) {
        throw new RuntimeException('Invalid Refill Tracker storage directory.');
    }

    return $directory . '/' . hash('sha256', $accountId) . '.json';
}
