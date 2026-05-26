<?php
/*
===========================================================
 File: download.php
 Author: Jason Lamb
 Created: 2026-03-13
 Revision: 1.0
 Description:
   Secure file download handler with logging
===========================================================
*/

date_default_timezone_set('America/New_York');

$root = realpath($_SERVER['DOCUMENT_ROOT']);
$file = $_GET['file'] ?? '';

$requested = realpath($root . $file);

/* ===============================
   SECURITY CHECK
================================ */

if (!$requested || strpos($requested,$root) !== 0 || !file_exists($requested)) {
    http_response_code(404);
    exit("File not found");
}

/* ===============================
   PREVENTING BOTS FROM POLLUTING STATS
================================ */

if (stripos($_SERVER['HTTP_USER_AGENT'], 'bot') !== false) {
    $logDownload = false;
}

/* ===============================
   LOG DOWNLOAD
================================ */

$logDir  = $root . '/custom-directory/logs';
$logFile = $logDir . '/downloads.json';

if (!file_exists($logDir)) {
    mkdir($logDir,0755,true);
}

$entry = [
    'time' => date('Y-m-d H:i:s'),
    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
    'file' => $file,
    'useragent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
];

$data = [];

if (file_exists($logFile)) {
    $data = json_decode(file_get_contents($logFile),true) ?? [];
}

$data[] = $entry;

file_put_contents(
    $logFile,
    /* json_encode($data,JSON_PRETTY_PRINT) */
    json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
);

/* ===============================
   SERVE FILE
================================ */

header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="'.basename($requested).'"');
header('Content-Length: '.filesize($requested));

readfile($requested);
exit;