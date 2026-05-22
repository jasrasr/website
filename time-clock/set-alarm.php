<?php
/**
 * Filename   : set-alarm.php
 * Revision   : 1.2.0
 * Description: Emergency alert toggle endpoint for jasr.me time-clock
 * Author     : Jason Lamb (with help from Claude Code CLI)
 * Created    : 2026-05-22
 * Modified   : 2026-05-22
 * Changelog  :
 * 1.0.0  initial release (fire alarm only)
 * 1.1.0  add type parameter to support multiple alarm types (fire, shooter, demo)
 * 1.2.0  activating one alarm type auto-clears all others (mutual exclusivity)
 */

$alarmTypes = [
    'fire'    => ['file' => 'fire-alarm.json',     'message' => 'FIRE ALARM — EVACUATE NOW'],
    'shooter' => ['file' => 'active-shooter.json', 'message' => 'ACTIVE THREAT — LOCKDOWN NOW'],
    'demo'    => ['file' => 'demo-alert.json',     'message' => 'DEMO ALERT — This is a Test'],
];

header('Content-Type: application/json');

// GET — return current status for a given type
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $type = $_GET['type'] ?? 'fire';
    if (!isset($alarmTypes[$type])) {
        http_response_code(400);
        echo json_encode(['error' => 'Unknown alarm type']);
        exit;
    }
    $file = __DIR__ . '/' . $alarmTypes[$type]['file'];
    if (file_exists($file)) {
        echo file_get_contents($file);
    } else {
        echo json_encode(['active' => false, 'triggeredAt' => null, 'message' => $alarmTypes[$type]['message']]);
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
$type   = $input['type']   ?? 'fire';

if (!isset($alarmTypes[$type])) {
    http_response_code(400);
    echo json_encode(['error' => 'Unknown alarm type']);
    exit;
}

$file    = __DIR__ . '/' . $alarmTypes[$type]['file'];
$message = $alarmTypes[$type]['message'];

if ($action === 'on') {
    // Clear all other alarm types before activating this one
    foreach ($alarmTypes as $otherType => $cfg) {
        if ($otherType !== $type) {
            $otherData = ['active' => false, 'triggeredAt' => null, 'message' => $cfg['message']];
            file_put_contents(__DIR__ . '/' . $cfg['file'], json_encode($otherData, JSON_PRETTY_PRINT) . "\n");
        }
    }
    $data = ['active' => true, 'triggeredAt' => time(), 'message' => $message];
    file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT) . "\n");
    echo json_encode(['success' => true, 'active' => true]);
    exit;
}

if ($action === 'off') {
    $data = ['active' => false, 'triggeredAt' => null, 'message' => $message];
    file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT) . "\n");
    echo json_encode(['success' => true, 'active' => false]);
    exit;
}

http_response_code(400);
echo json_encode(['error' => 'Unknown action']);
