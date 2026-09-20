<?php
declare(strict_types=1);
namespace Jasr\Framework;

final class JsonStore
{
    public static function read(string $path): array
    {
        if (!is_file($path)) return ['schemaVersion' => 1, 'records' => []];
        $data = json_decode((string)file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($data) || ($data['schemaVersion'] ?? null) !== 1 || !is_array($data['records'] ?? null)) {
            throw new \RuntimeException('Invalid storage schema.');
        }
        return $data;
    }

    // Callback returns the entire replacement records array. Dedicated lock survives rename.
    public static function update(string $path, callable $change): array
    {
        $lock = fopen($path . '.lock', 'c');
        if (!$lock || !flock($lock, LOCK_EX)) throw new \RuntimeException('Storage lock failed.');
        $temp = false;
        try {
            $records = $change(self::read($path)['records']);
            if (!is_array($records)) throw new \RuntimeException('Invalid replacement records.');
            $data = ['schemaVersion' => 1, 'updatedAt' => gmdate(DATE_ATOM), 'records' => $records];
            $json = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
            $temp = tempnam(dirname($path), '.json-');
            if ($temp === false || file_put_contents($temp, $json) !== strlen($json) || !rename($temp, $path)) {
                throw new \RuntimeException('Storage write failed.');
            }
            return $records;
        } finally {
            if ($temp !== false && is_file($temp)) unlink($temp);
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }
}
