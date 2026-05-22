<?php
/**
 * Filename   : save-alerts.php
 * Revision   : 1.1.1
 * Description: Alerts backend for jasr.me time-clock; handles load and save
 * Author     : Jason Lamb (with help from Claude Code CLI)
 * Created    : 2026-04-21
 * Modified   : 2026-05-22
 * Changelog  :
 * 1.0.0  initial release
 * 1.1.0  remove token auth for demo — PIN gate on admin.html is sufficient
 * 1.1.1  store lastModified as Unix timestamp; browser converts to local time
 */

$alertsFile = __DIR__ . '/alerts.json';

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
    $lastModified = time(); // Unix timestamp — client converts to local time
    $data = [
        'rev'          => $rev,
        'lastModified' => $lastModified,
        'alerts'       => $input['alerts'] ?? []
    ];
    file_put_contents($alertsFile, json_encode($data, JSON_PRETTY_PRINT) . "\n");
    echo json_encode(['success' => true, 'rev' => $rev, 'lastModified' => $lastModified]);
    exit;
}

http_response_code(400);
echo json_encode(['error' => 'Unknown action']);
