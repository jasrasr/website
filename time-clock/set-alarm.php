<?php
/**
 * Filename   : set-alarm.php
 * Revision   : 1.0.0
 * Description: Fire alarm toggle endpoint for jasr.me time-clock
 * Author     : Jason Lamb (with help from Claude Code CLI)
 * Created    : 2026-05-22
 * Modified   : 2026-05-22
 * Changelog  :
 * 1.0.0  initial release
 */

$alarmFile = __DIR__ . '/fire-alarm.json';

header('Content-Type: application/json');

// GET — return current status
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (file_exists($alarmFile)) {
        echo file_get_contents($alarmFile);
    } else {
        echo json_encode(['active' => false, 'triggeredAt' => null, 'message' => 'FIRE ALARM — EVACUATE NOW']);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$input  = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $input['action'] ?? '';

if ($action === 'on') {
    $data = [
        'active'      => true,
        'triggeredAt' => time(),
        'message'     => 'FIRE ALARM — EVACUATE NOW'
    ];
    file_put_contents($alarmFile, json_encode($data, JSON_PRETTY_PRINT) . "\n");
    echo json_encode(['success' => true, 'active' => true]);
    exit;
}

if ($action === 'off') {
    $data = [
        'active'      => false,
        'triggeredAt' => null,
        'message'     => 'FIRE ALARM — EVACUATE NOW'
    ];
    file_put_contents($alarmFile, json_encode($data, JSON_PRETTY_PRINT) . "\n");
    echo json_encode(['success' => true, 'active' => false]);
    exit;
}

http_response_code(400);
echo json_encode(['error' => 'Unknown action']);
