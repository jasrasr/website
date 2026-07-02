<?php
/**
 * File: includes/json-store.php
 * Project: TV Binge Board
 * Description: Safe JSON read/write helpers with file locking, atomic saves, and directory utilities.
 * Author: Jason Lamb / ChatGPT
 * Created: 2026-07-02
 * Modified: 2026-07-02
 * Revision: 1.4.2
 */
declare(strict_types=1);


require_once __DIR__ . '/config.php';

function app_ensure_dir(string $path): void
{
    if (!is_dir($path) && !mkdir($path, 0755, true) && !is_dir($path)) {
        throw new RuntimeException('Unable to create directory: ' . $path);
    }
}

function app_load_json(string $path, array $default = []): array
{
    if (!is_file($path)) {
        return $default;
    }

    $fp = fopen($path, 'rb');
    if ($fp === false) {
        return $default;
    }

    try {
        flock($fp, LOCK_SH);
        $contents = stream_get_contents($fp);
        flock($fp, LOCK_UN);
    } finally {
        fclose($fp);
    }

    if ($contents === false || trim($contents) === '') {
        return $default;
    }

    $decoded = json_decode($contents, true);
    return is_array($decoded) ? $decoded : $default;
}

function app_save_json(string $path, array $data): void
{
    app_ensure_dir(dirname($path));

    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        throw new RuntimeException('Unable to encode JSON for: ' . $path);
    }

    $lockPath = $path . '.lock';
    $lock = fopen($lockPath, 'c');
    if ($lock === false) {
        throw new RuntimeException('Unable to open lock file: ' . $lockPath);
    }

    try {
        flock($lock, LOCK_EX);
        $tmp = $path . '.tmp.' . bin2hex(random_bytes(4));
        if (file_put_contents($tmp, $json . PHP_EOL, LOCK_EX) === false) {
            throw new RuntimeException('Unable to write temporary JSON file: ' . $tmp);
        }
        chmod($tmp, 0644);
        if (!rename($tmp, $path)) {
            @unlink($tmp);
            throw new RuntimeException('Unable to replace JSON file: ' . $path);
        }
        flock($lock, LOCK_UN);
    } finally {
        fclose($lock);
    }
}

function app_json_meta(string $description): array
{
    return [
        'project' => APP_NAME,
        'description' => $description,
        'version' => APP_VERSION,
        'updated_at' => date(DATE_ATOM),
    ];
}

function app_read_csv_assoc(string $path): array
{
    $rows = [];
    $handle = fopen($path, 'rb');
    if ($handle === false) {
        return $rows;
    }
    $headers = fgetcsv($handle);
    if (!is_array($headers)) {
        fclose($handle);
        return $rows;
    }
    $headers = array_map(static fn($h) => strtolower(trim((string)$h)), $headers);
    while (($data = fgetcsv($handle)) !== false) {
        $row = [];
        foreach ($headers as $index => $header) {
            if ($header !== '') {
                $row[$header] = $data[$index] ?? '';
            }
        }
        $rows[] = $row;
    }
    fclose($handle);
    return $rows;
}
