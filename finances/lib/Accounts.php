<?php
declare(strict_types=1);
namespace Jasr\Finances;

/** Legacy account IDs and filenames remain authoritative during the pilot migration. */
final class Accounts
{
    public static function config(): array
    {
        $config = ['authentication' => 'legacy', 'users' => [
            'student' => ['password' => 'budget123'], 'parent' => ['password' => 'budget123'],
        ]];
        // The historical config.local.php is tracked. Use an ignored override
        // rather than removing the deployed file or committing live credentials.
        foreach (['config.local.php', 'config.private.php'] as $name) {
            $file = dirname(__DIR__) . '/' . $name;
            if (!is_file($file)) continue;
            $local = require $file;
            if (!is_array($local)) throw new \RuntimeException('Invalid finance configuration.');
            $config = array_replace($config, $local);
        }
        if (!is_array($config['users']) || !in_array($config['authentication'], ['legacy', 'shared'], true)) {
            throw new \RuntimeException('Invalid finance authentication configuration.');
        }
        return $config;
    }
    public static function filename(string $id): string
    {
        return (preg_replace('/[^a-zA-Z0-9_-]/', '_', $id) ?: 'user') . '.json';
    }
    public static function dataPath(string $id): string { return dirname(__DIR__) . '/data/' . self::filename($id); }
    public static function storage(): string { return dirname(__DIR__) . '/data/identity'; }
    public static function inventory(): array
    {
        $config = self::config(); $rows = []; $files = [];
        foreach ($config['users'] as $id => $credentials) {
            $id = (string)$id; $file = self::filename($id); $path = self::dataPath($id); $errors = [];
            if (!is_array($credentials)) $errors[] = 'Invalid legacy account configuration.';
            if (!is_file($path) || is_link($path)) $errors[] = 'Existing budget file is missing or is a symbolic link.';
            else {
                try { self::decode((string)file_get_contents($path)); }
                catch (\Throwable $e) { $errors[] = 'Budget JSON is invalid; restore or repair it before linking.'; }
            }
            $files[$file][] = $id;
            $rows[$id] = ['filename' => $file, 'errors' => $errors];
        }
        foreach ($files as $ids) if (count($ids) > 1) foreach ($ids as $id) {
            $rows[$id]['errors'][] = 'Multiple legacy usernames resolve to the same budget file.';
        }
        // Report orphan data instead of silently treating it as a new or empty account.
        foreach (glob(dirname(__DIR__) . '/data/*.json') ?: [] as $path) {
            $file = basename($path);
            if (!isset($files[$file])) $rows['[unconfigured file] ' . $file] = ['filename' => $file,
                'errors' => ['Budget exists without a configured legacy account. Restore its account configuration first.']];
        }
        return $rows;
    }
    public static function decode(string $raw): array
    {
        $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($data) || !is_array($data['income'] ?? null) || !is_array($data['expenses'] ?? null)) {
            throw new \RuntimeException('Invalid budget data.');
        }
        return $data;
    }
    public static function locked(string $id, callable $callback): mixed
    {
        $path = self::dataPath($id);
        $lock = fopen($path . '.lock', 'c');
        if (!$lock || !flock($lock, LOCK_EX)) throw new \RuntimeException('Budget lock failed.');
        try { return $callback($path); }
        finally { flock($lock, LOCK_UN); fclose($lock); }
    }
    public static function snapshot(string $id, callable $callback): mixed
    {
        // Validate before opening a lock so missing budgets/directories are never created.
        $row = self::inventory()[$id] ?? null;
        if (!$row || $row['errors']) throw new \InvalidArgumentException('Legacy account or budget is missing, invalid, or ambiguous. Check the dry-run report.');
        return self::locked($id, function (string $path) use ($id, $callback): mixed {
            $row = self::inventory()[$id] ?? null;
            if (!$row || $row['errors']) throw new \InvalidArgumentException('Legacy account changed. Run preview again.');
            $raw = file_get_contents($path);
            if ($raw === false) throw new \RuntimeException('Cannot read budget.');
            self::decode($raw);
            return $callback(['filename' => self::filename($id), 'budget' => $raw,
                'legacyAccount' => self::config()['users'][$id], 'authentication' => self::config()['authentication']]);
        });
    }
    public static function read(string $id): array
    {
        return self::snapshot($id, fn(array $s): array => self::decode($s['budget']));
    }
    public static function write(string $id, array $budget, bool $mustExist): void
    {
        self::locked($id, function (string $path) use ($budget, $mustExist): void {
            if (is_link($path)) throw new \RuntimeException('Invalid budget path.');
            if ($mustExist && !is_file($path)) throw new \RuntimeException('Linked budget is missing. Restore it before saving.');
            if (is_file($path)) {
                // Preserve legacy metadata that the current editing form does not expose.
                $budget = array_replace(self::decode((string)file_get_contents($path)), $budget);
            }
            $raw = json_encode($budget, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            self::decode($raw);
            $temp = tempnam(dirname($path), '.budget-');
            if ($temp === false) throw new \RuntimeException('Cannot stage budget.');
            try {
                if (file_put_contents($temp, $raw) !== strlen($raw) || !rename($temp, $path)) throw new \RuntimeException('Cannot save budget.');
            } finally { if (is_file($temp)) unlink($temp); }
        });
    }
}
