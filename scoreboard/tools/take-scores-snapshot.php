<?php declare(strict_types=1);
/**
 * Filename: tools/take-scores-snapshot.php
 * Revision : 1.0.0
 * Description : CLI helper that copies every instance's data/scores.json to a
 *               timestamped file under data/snapshots/. Intended to be wired
 *               up via cron or the hosting scheduler (e.g., daily at 03:00).
 *               Old snapshots are pruned to keep at most SNAPSHOT_RETENTION
 *               files per instance.
 * Author : Jason Lamb (with help from Claude Code)
 * Created Date : 2026-06-18
 * Modified Date : 2026-06-18
 * Changelog :
 * 1.0.0 initial release
 *
 * Usage (from the scoreboard project root, on the server):
 *     php tools/take-scores-snapshot.php
 *
 * Suggested cron entry (run daily at 03:00 UTC):
 *     0 3 * * * cd /home/youruser/public_html/github/scoreboard && /usr/bin/php tools/take-scores-snapshot.php >> data/snapshot.log 2>&1
 *
 * On cPanel / Hostinger:
 *     Create a Cron Job pointing at the same command.
 *
 * Restore from a snapshot:
 *     cp data/snapshots/<instance>/2026-06-18-03.json data/scores.json
 *     (replace <instance> with root / youth / collide / frontlines)
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('This script can only be run from the command line.');
}

const SNAPSHOT_RETENTION = 30; // Keep last 30 snapshots per instance
const INSTANCES = [
    'root'       => __DIR__ . '/../data',
    'youth'      => __DIR__ . '/../youth/data',
    'collide'    => __DIR__ . '/../collide/data',
    'frontlines' => __DIR__ . '/../frontlines/data',
];

$stamp = gmdate('Y-m-d-H');
$now   = gmdate('c');
$summary = [];

foreach (INSTANCES as $name => $dataDir) {
    $scoresPath = $dataDir . '/scores.json';
    if (!is_file($scoresPath)) {
        $summary[] = "[{$name}] no scores.json yet, skipped";
        continue;
    }

    $snapDir = $dataDir . '/snapshots';
    if (!is_dir($snapDir) && !mkdir($snapDir, 0775, true)) {
        $summary[] = "[{$name}] could not create {$snapDir}";
        continue;
    }

    $snapPath = $snapDir . '/' . $stamp . '.json';
    if (!@copy($scoresPath, $snapPath)) {
        $summary[] = "[{$name}] copy failed: {$scoresPath} -> {$snapPath}";
        continue;
    }

    // Prune oldest snapshots beyond retention.
    $existing = glob($snapDir . '/*.json') ?: [];
    sort($existing); // ISO YYYY-MM-DD-HH sorts lexicographically
    while (count($existing) > SNAPSHOT_RETENTION) {
        $oldest = array_shift($existing);
        if (is_file($oldest)) @unlink($oldest);
    }

    $summary[] = "[{$name}] snapshot saved to {$snapPath}";
}

fwrite(STDOUT, "{$now} snapshot pass:\n  " . implode("\n  ", $summary) . PHP_EOL);
