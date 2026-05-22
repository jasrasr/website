<?php
/**
 * Filename   : save-alerts.php
 * Revision   : 1.3.0
 * Description: Alerts backend for jasr.me time-clock; handles load and save
 * Author     : Jason Lamb (with help from Claude Code CLI)
 * Created    : 2026-04-21
 * Modified   : 2026-05-22
 * Changelog  :
 * 1.0.0  initial release
 * 1.1.0  remove token auth for demo — PIN gate on admin.html is sufficient
 * 1.1.1  store lastModified as Unix timestamp; browser converts to local time
 * 1.2.0  write alerts-backup.json before every save for manual recovery
 * 1.3.0  keep rolling 10-revision history in alerts-backup.json
 * 1.3.1  add human-readable savedAt field to each backup entry
 */

$alertsFile = __DIR__ . '/alerts.json';
$backupFile = __DIR__ . '/alerts-backup.json';
const MAX_BACKUPS = 10;

header('Content-Type: application/json');

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$action = $_GET['action'] ?? '';
$input  = json_decode(file_get_contents('php://input'), true) ?? [];

// ── Load alerts ───────────────────────────────────────────────────────────
if ($action === 'load') {
    if (file_exists($alertsFile)) {
        echo file_get_contents($alertsFile);
    } else {
        echo json_encode(['rev' => 1, 'lastModified' => '', 'alerts' => []]);
    }
    exit;
}

// ── Save alerts ───────────────────────────────────────────────────────────
if ($action === 'save') {
    $rev          = ((int)($input['rev'] ?? 0)) + 1;
    $lastModified = time();
    $data = [
        'rev'          => $rev,
        'lastModified' => $lastModified,
        'alerts'       => $input['alerts'] ?? []
    ];

    // Snapshot current alerts.json into rolling backup history
    if (file_exists($alertsFile)) {
        $current            = json_decode(file_get_contents($alertsFile), true);
        $tz = new DateTimeZone('America/New_York');
        $current['savedAt'] = (new DateTime('now', $tz))->format('Y-m-d h:i:s A T');
        $existing = file_exists($backupFile)
            ? (json_decode(file_get_contents($backupFile), true)['backups'] ?? [])
            : [];
        array_unshift($existing, $current);
        $backups = array_slice($existing, 0, MAX_BACKUPS);
        file_put_contents($backupFile, json_encode(['backups' => $backups], JSON_PRETTY_PRINT) . "\n");
    }

    file_put_contents($alertsFile, json_encode($data, JSON_PRETTY_PRINT) . "\n");
    echo json_encode(['success' => true, 'rev' => $rev, 'lastModified' => $lastModified]);
    exit;
}

http_response_code(400);
echo json_encode(['error' => 'Unknown action']);
