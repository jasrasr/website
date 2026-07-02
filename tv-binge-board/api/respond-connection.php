<?php
/**
 * File: api/respond-connection.php
 * Project: TV Binge Board
 * Description: Accepts or declines an incoming connection request.
 * Author: Jason Lamb / ChatGPT
 * Created: 2026-07-02
 * Modified: 2026-07-02
 * Revision: 1.4.2
 */
declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';
$user = app_require_login();
app_verify_csrf();
if (!app_can_track($user)) {
    http_response_code(403);
    exit('Admin accounts cannot create connections.');
}
$me = (string)$user['username'];
$from = app_sanitize_username((string)($_POST['from_user'] ?? ''));
$response = (string)($_POST['response'] ?? 'decline');
if ($from === '' || !app_find_user($from)) {
    http_response_code(400);
    exit('Invalid request.');
}

$myData = app_connections($me);
$fromData = app_connections($from);
$myData['incoming_requests'] = array_values(array_diff($myData['incoming_requests'], [$from]));
$fromData['outgoing_requests'] = array_values(array_diff($fromData['outgoing_requests'], [$me]));

if ($response === 'accept') {
    if (!in_array($from, $myData['connections'], true)) $myData['connections'][] = $from;
    if (!in_array($me, $fromData['connections'], true)) $fromData['connections'][] = $me;
    app_flash('Connection accepted.', 'success');
} else {
    app_flash('Connection declined.', 'info');
}
app_save_connections($me, $myData);
app_save_connections($from, $fromData);
header('Location: ../connections.php');
exit;
