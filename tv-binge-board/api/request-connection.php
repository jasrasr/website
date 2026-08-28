<?php
/**
 * File: api/request-connection.php
 * Project: TV Binge Board
 * Description: Creates a pending connection request between two normal users.
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
$from = (string)$user['username'];
$target = app_sanitize_username((string)($_POST['target_user'] ?? ''));
$targetAccount = app_find_user($target);
if (!$targetAccount || ($targetAccount['role'] ?? '') === 'admin' || $target === $from) {
    http_response_code(400);
    exit('Invalid connection target.');
}

$fromData = app_connections($from);
$targetData = app_connections($target);
if (!in_array($target, $fromData['connections'], true) && !in_array($target, $fromData['outgoing_requests'], true)) {
    $fromData['outgoing_requests'][] = $target;
}
if (!in_array($from, $targetData['incoming_requests'], true)) {
    $targetData['incoming_requests'][] = $from;
}
app_save_connections($from, $fromData);
app_save_connections($target, $targetData);
app_flash('Connection request sent.', 'success');
header('Location: ../connections.php');
exit;
