<?php
/**
 * save-alerts.php — Alerts backend for jasr.me time-clock
 * Handles first-time setup, login, load, and save actions.
 */

$configFile = __DIR__ . '/.alerts_config';
$tokenFile  = __DIR__ . '/.alerts_token';
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

// ── Setup (first run only) ────────────────────────────────────────────────
if ($action === 'setup') {
    if (file_exists($configFile)) {
        http_response_code(403);
        echo json_encode(['error' => 'Already configured']);
        exit;
    }
    $password = $input['password'] ?? '';
    $confirm  = $input['confirm']  ?? '';
    if (strlen($password) < 6) {
        http_response_code(400);
        echo json_encode(['error' => 'Password must be at least 6 characters']);
        exit;
    }
    if ($password !== $confirm) {
        http_response_code(400);
        echo json_encode(['error' => 'Passwords do not match']);
        exit;
    }
    $hash = password_hash($password, PASSWORD_DEFAULT);
    file_put_contents($configFile, $hash);
    echo json_encode(['success' => true]);
    exit;
}

// ── Status check ─────────────────────────────────────────────────────────
if ($action === 'status') {
    echo json_encode(['configured' => file_exists($configFile)]);
    exit;
}

// ── Login ─────────────────────────────────────────────────────────────────
if ($action === 'login') {
    if (!file_exists($configFile)) {
        http_response_code(503);
        echo json_encode(['error' => 'Not configured', 'setup_required' => true]);
        exit;
    }
    $hash     = trim(file_get_contents($configFile));
    $password = $input['password'] ?? '';
    if (!password_verify($password, $hash)) {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid password']);
        exit;
    }
    $token  = bin2hex(random_bytes(32));
    $expiry = time() + (8 * 3600); // 8-hour session
    file_put_contents($tokenFile, "$token:$expiry");
    echo json_encode(['success' => true, 'token' => $token]);
    exit;
}

// ── Token validation ──────────────────────────────────────────────────────
function validateToken($tokenFile) {
    $provided = $_SERVER['HTTP_X_AUTH_TOKEN'] ?? '';
    if (!$provided || !file_exists($tokenFile)) return false;
    $parts = explode(':', trim(file_get_contents($tokenFile)), 2);
    if (count($parts) !== 2) return false;
    return $provided === $parts[0] && time() < (int)$parts[1];
}

if (!validateToken($tokenFile)) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated or session expired']);
    exit;
}

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
    $lastModified = date('m/d/Y, h:i:s A');
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
