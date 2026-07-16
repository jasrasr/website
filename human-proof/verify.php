<?php
/*
 * verify.php — server-side gate for the "Robot or human?" hold check.
 *
 * GET  : issues a one-time nonce bound to the visitor's session.
 * POST : validates that nonce, marks the session as human-verified, and
 *        returns the destination. The destination string lives ONLY here on
 *        the server, so it never appears in the page source the visitor sees.
 *
 * NOTE: the press-and-hold gesture is UX, not proof — a script can fake it.
 *       For real bot protection, verify a Cloudflare Turnstile / hCaptcha
 *       token here too (see the block marked TURNSTILE below).
 */

session_start();
header('Content-Type: application/json');
header('Cache-Control: no-store');

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    // Issue a one-time token bound to this session.
    $nonce = bin2hex(random_bytes(16));
    $_SESSION['gate_nonce']      = $nonce;
    $_SESSION['gate_nonce_time'] = time();
    echo json_encode(['nonce' => $nonce]);
    exit;
}

if ($method === 'POST') {
    $body  = json_decode(file_get_contents('php://input'), true) ?: [];
    $nonce = isset($body['nonce']) ? (string) $body['nonce'] : '';

    // ── TURNSTILE (optional, real bot protection) ───────────────────────────
    // $token = $body['turnstile'] ?? '';
    // $resp = file_get_contents('https://challenges.cloudflare.com/turnstile/v0/siteverify', false,
    //   stream_context_create(['http' => [
    //     'method'  => 'POST',
    //     'header'  => 'Content-Type: application/x-www-form-urlencoded',
    //     'content' => http_build_query(['secret' => 'YOUR_SECRET', 'response' => $token]),
    //   ]]));
    // if (!(json_decode($resp, true)['success'] ?? false)) {
    //     http_response_code(403);
    //     echo json_encode(['ok' => false, 'error' => 'challenge failed']);
    //     exit;
    // }
    // ────────────────────────────────────────────────────────────────────────

    $valid = isset($_SESSION['gate_nonce'])
        && hash_equals($_SESSION['gate_nonce'], $nonce)
        && (time() - ($_SESSION['gate_nonce_time'] ?? 0) <= 300); // 5-minute window

    if (!$valid) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'invalid or expired token']);
        exit;
    }

    // Consume the nonce (single use) and mark this session verified.
    unset($_SESSION['gate_nonce'], $_SESSION['gate_nonce_time']);
    $_SESSION['human_verified']      = true;
    $_SESSION['human_verified_time'] = time();

    // The destination is decided here, server-side — not in the client.
    echo json_encode(['ok' => true, 'next' => 'next.php']);
    exit;
}

http_response_code(405);
echo json_encode(['ok' => false, 'error' => 'method not allowed']);
