<?php
/**
 * File Name: data.php
 * Author: Jason Lamb (with help from ChatGPT)
 * Created Date: 2026-01-18
 * Modified Date: 2026-11-20
 * Revision: 1.0.0
 * Changelog: 1.0.0 - Serialize inventory updates and replace the JSON atomically.
 * Description:
 * Central JSON storage handler for box inventory
 */

date_default_timezone_set('America/New_York');


define('BOX_DATA_FILE', __DIR__ . '/../data/boxes.json');

/**
 * Load the full data structure
 */
function loadBoxData(): array {
    if (!file_exists(BOX_DATA_FILE)) {
        return ['boxes' => []];
    }

    $json = file_get_contents(BOX_DATA_FILE);
    $data = json_decode($json, true);

    if (!is_array($data) || !isset($data['boxes'])) {
        // Safety fallback if JSON is corrupted
        return ['boxes' => []];
    }

    return $data;
}

/** Read and replace the existing inventory under a shared writer lock. */
function updateBoxData(callable $change): bool {
    $lock = fopen(BOX_DATA_FILE . '.lock', 'c');
    if (!$lock) {
        return false;
    }

    $temp = false;
    try {
        if (!flock($lock, LOCK_EX)) {
            return false;
        }

        $data = ['boxes' => []];
        if (is_file(BOX_DATA_FILE)) {
            $json = file_get_contents(BOX_DATA_FILE);
            $data = is_string($json) ? json_decode($json, true) : null;
            if (!is_array($data) || !is_array($data['boxes'] ?? null)) {
                return false;
            }
        }

        $replacement = $change($data);
        if ($replacement === $data) {
            return true;
        }
        $json = json_encode($replacement, JSON_PRETTY_PRINT);
        if ($json === false) {
            return false;
        }
        $temp = tempnam(dirname(BOX_DATA_FILE), 'boxes-');
        return $temp !== false
            && file_put_contents($temp, $json) === strlen($json)
            && rename($temp, BOX_DATA_FILE);
    } finally {
        if ($temp !== false && is_file($temp)) {
            unlink($temp);
        }
        fclose($lock);
    }
}

/**
 * Get a single box by code
 */
function getBox(string $code): ?array {
    $data = loadBoxData();
    return $data['boxes'][$code] ?? null;
}

/**
 * Save or update a single box
 */
function saveBox(string $code, array $box): bool {
    return updateBoxData(static function (array $data) use ($code, $box): array {
        $data['boxes'][$code] = $box;
        return $data;
    });
}

/**
 * Generate a unique box code
 */
function generateBoxCode(array $existingBoxes = []): string {
    do {
        $code = 'BOX' . strtoupper(bin2hex(random_bytes(3)));
    } while (isset($existingBoxes[$code]));
    return $code;
}
