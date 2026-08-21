<?php

declare(strict_types=1);

final class WarrantyStore
{
    public function __construct(private readonly string $path)
    {
    }

    /** @return array{schemaVersion:int,updatedAt:string,records:array<int,array<string,mixed>>} */
    public function all(): array
    {
        if (!is_file($this->path)) {
            return ['schemaVersion' => 1, 'updatedAt' => gmdate(DATE_ATOM), 'records' => []];
        }

        $handle = fopen($this->path, 'rb');
        if ($handle === false) {
            throw new RuntimeException('Warranty data could not be opened.');
        }
        try {
            if (!flock($handle, LOCK_SH)) {
                throw new RuntimeException('Warranty data could not be locked.');
            }
            $json = stream_get_contents($handle);
            flock($handle, LOCK_UN);
        } finally {
            fclose($handle);
        }

        $data = json_decode((string) $json, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($data) || !isset($data['records']) || !is_array($data['records'])) {
            throw new RuntimeException('Warranty data has an invalid structure.');
        }
        return $data;
    }

    /** @param array<string,mixed> $record */
    public function save(array $record): array
    {
        return $this->mutate(function (array $data) use ($record): array {
            $now = gmdate(DATE_ATOM);
            $id = trim((string) ($record['id'] ?? ''));
            $saved = [
                'id' => $id !== '' ? $id : bin2hex(random_bytes(8)),
                'product' => trim((string) $record['product']),
                'category' => trim((string) ($record['category'] ?? '')),
                'manufacturer' => trim((string) ($record['manufacturer'] ?? '')),
                'model' => trim((string) ($record['model'] ?? '')),
                'serialNumber' => trim((string) ($record['serialNumber'] ?? '')),
                'seller' => trim((string) ($record['seller'] ?? '')),
                'provider' => trim((string) ($record['provider'] ?? '')),
                'purchaseDate' => (string) $record['purchaseDate'],
                'warrantyEndDate' => (string) $record['warrantyEndDate'],
                'cost' => round((float) ($record['cost'] ?? 0), 2),
                'receiptUrl' => trim((string) ($record['receiptUrl'] ?? '')),
                'notes' => trim((string) ($record['notes'] ?? '')),
                'createdAt' => $now,
                'updatedAt' => $now,
            ];
            foreach ($data['records'] as $index => $existing) {
                if (($existing['id'] ?? '') === $saved['id']) {
                    $saved['createdAt'] = $existing['createdAt'] ?? $now;
                    $data['records'][$index] = $saved;
                    return [$data, $saved];
                }
            }
            $data['records'][] = $saved;
            return [$data, $saved];
        });
    }

    public function delete(string $id): bool
    {
        return $this->mutate(function (array $data) use ($id): array {
            $before = count($data['records']);
            $data['records'] = array_values(array_filter(
                $data['records'],
                static fn(array $item): bool => ($item['id'] ?? '') !== $id
            ));
            return [$data, count($data['records']) < $before];
        });
    }

    private function mutate(callable $callback): mixed
    {
        $directory = dirname($this->path);
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException('Warranty storage directory could not be created.');
        }
        $lock = fopen($this->path . '.lock', 'c+');
        if ($lock === false || !flock($lock, LOCK_EX)) {
            throw new RuntimeException('Warranty data could not be locked for writing.');
        }
        try {
            $data = $this->all();
            [$data, $result] = $callback($data);
            $data['schemaVersion'] = 1;
            $data['updatedAt'] = gmdate(DATE_ATOM);
            $temp = tempnam($directory, 'warranty-');
            if ($temp === false || file_put_contents($temp, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL) === false) {
                throw new RuntimeException('Warranty data could not be written.');
            }
            if (!rename($temp, $this->path)) {
                if (is_file($temp)) {
                    unlink($temp);
                }
                throw new RuntimeException('Warranty data could not be replaced.');
            }
            return $result;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }
}
