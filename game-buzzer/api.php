<?php
declare(strict_types=1);
require __DIR__ . '/lib.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') gb_fail('Use POST.', 405);
    if (strtolower(trim(explode(';', $_SERVER['CONTENT_TYPE'] ?? '')[0])) !== 'application/json') gb_fail('Use application/json.', 415);
    $body = file_get_contents('php://input', false, null, 0, 20001);
    if (strlen($body) > 20000) gb_fail('Request too large.', 413);
    $in = json_decode($body, true, 32, JSON_THROW_ON_ERROR);
    if (!is_array($in)) gb_fail('Invalid request.');
    $action = gb_string($in, 'action', 30);
    $config = gb_config();
    if ($action === 'create') {
        $password = $config['create_password'] ?? '';
        if (strlen($password) < 12 || $password === 'REPLACE-WITH-A-LONG-PRIVATE-PASSWORD') gb_fail('Host setup required: configure config.local.php first.', 503);
        if (!hash_equals($password, gb_string($in, 'password', 200))) gb_fail('Incorrect room creation password.', 403);
        $room = gb_new($in);
        $out = gb_room($config, $room['id'], fn(&$r) => ['id' => $r['id'], 'host' => $r['host']], $room);
    } else {
        $id = gb_string($in, 'room', 12);
        // Fail before creating a lock file for arbitrary nonexistent room IDs.
        if (!preg_match('/^[a-f0-9]{12}$/D', $id) || !is_file(gb_dir($config) . '/' . $id . '.php')) gb_fail('Room not found.', 404);
        $out = gb_room($config, $id, fn(&$r) => gb_apply($r, $action, $in));
    }
    echo json_encode($out, JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
    $status = $e instanceof RuntimeException && $e->getCode() >= 400 && $e->getCode() <= 599 ? $e->getCode() : 500;
    http_response_code($status);
    echo json_encode(['error' => $status === 500 ? 'Server error. Please retry or contact the host.' : $e->getMessage()]);
}
