<?php
/**
 * Filename   : set-alarm.php
 * Revision   : 1.4.0
 * Description: Emergency alert toggle endpoint for jasr.me time-clock
 * Author     : Jason Lamb (with help from Claude Code CLI)
 * Created    : 2026-05-22
 * Modified   : 2026-05-23
 * Changelog  :
 * 1.0.0  initial release (fire alarm only)
 * 1.1.0  add type parameter to support multiple alarm types (fire, shooter, demo)
 * 1.2.0  activating one alarm type auto-clears all others (mutual exclusivity)
 * 1.3.0  add weather demo alarm type with 5-minute expiresAt auto-expiry
 * 1.4.0  accept custom event/headline/senderName from POST body for weather type
 */

$alarmTypes = [
    'fire'    => ['file' => 'fire-alarm.json',     'message' => 'FIRE ALARM — EVACUATE NOW'],
    'shooter' => ['file' => 'active-shooter.json', 'message' => 'ACTIVE THREAT — LOCKDOWN NOW'],
    'demo'    => ['file' => 'demo-alert.json',     'message' => 'DEMO ALERT — This is a Test'],
    'weather' => ['file' => 'weather-demo.json',   'message' => 'Severe Thunderstorm Warning'],
];

function getDefaultData($type, $cfg) {
    if ($type === 'weather') {
        return [
            'active'      => false,
            'triggeredAt' => null,
            'expiresAt'   => null,
            'event'       => 'Severe Thunderstorm Warning',
            'headline'    => 'Severe Thunderstorm Warning issued by NWS Cleveland OH',
            'senderName'  => 'NWS Cleveland OH',
        ];
    }
    return ['active' => false, 'triggeredAt' => null, 'message' => $cfg['message']];
}

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
        $data = json_decode(file_get_contents($file), true) ?? [];
        // Lazy expiry: if weather demo has passed its expiresAt, report inactive
        if ($type === 'weather' && !empty($data['active']) && !empty($data['expiresAt']) && time() > $data['expiresAt']) {
            $data['active'] = false;
        }
        echo json_encode($data);
    } else {
        echo json_encode(getDefaultData($type, $alarmTypes[$type]));
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
    $now = time();
    // Clear all other alarm types first (mutual exclusivity)
    foreach ($alarmTypes as $otherType => $cfg) {
        if ($otherType !== $type) {
            file_put_contents(
                __DIR__ . '/' . $cfg['file'],
                json_encode(getDefaultData($otherType, $cfg), JSON_PRETTY_PRINT) . "\n"
            );
        }
    }
    // Build activation payload
    if ($type === 'weather') {
        $data = [
            'active'      => true,
            'triggeredAt' => $now,
            'expiresAt'   => $now + 300,
            'event'       => $input['event']      ?? 'Severe Thunderstorm Warning',
            'headline'    => $input['headline']   ?? 'Severe Thunderstorm Warning issued by NWS Cleveland OH',
            'senderName'  => $input['senderName'] ?? 'NWS Cleveland OH',
        ];
    } else {
        $data = ['active' => true, 'triggeredAt' => $now, 'message' => $message];
    }
    file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT) . "\n");
    echo json_encode(['success' => true, 'active' => true, 'expiresAt' => $data['expiresAt'] ?? null]);
    exit;
}

if ($action === 'off') {
    $data = getDefaultData($type, $alarmTypes[$type]);
    file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT) . "\n");
    echo json_encode(['success' => true, 'active' => false]);
    exit;
}

http_response_code(400);
echo json_encode(['error' => 'Unknown action']);
