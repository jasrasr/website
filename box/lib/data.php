<?php
/**
 * data.php
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

/**
 * Save the full data structure safely (with file lock)
 */
function saveBoxData(array $data): bool {
    $fp = fopen(BOX_DATA_FILE, 'c+');

    if (!$fp) {
        return false;
    }

    if (!flock($fp, LOCK_EX)) {
        fclose($fp);
        return false;
    }

    ftruncate($fp, 0);
    rewind($fp);
    fwrite($fp, json_encode($data, JSON_PRETTY_PRINT));
    fflush($fp);
    flock($fp, LOCK_UN);
    fclose($fp);

    return true;
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
    $data = loadBoxData();
    $data['boxes'][$code] = $box;
    return saveBoxData($data);
}

/**
 * Generate a unique box code
 */
function generateBoxCode(): string {
    return 'BOX' . strtoupper(bin2hex(random_bytes(3)));
}
