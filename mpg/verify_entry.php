<?php
// ============================================================================
// File: verify_entry.php
// Purpose: Mark a specific entry as verified for a given plate
// Revision: 1.0
// Author: Jason Lamb
// ============================================================================

error_reporting(E_ALL);
ini_set('display_errors', 1);



if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


require_once __DIR__ . '/device_init.php';

if (!$isAdminTrusted) {
    die("<h2>Access denied — your IP or device is not authorized.</h2>");
}


$plate = strtoupper(trim($_POST['plate'] ?? ''));
$plate = preg_replace('/[^A-Z0-9]/', '', $plate);
$index = isset($_POST['index']) ? intval($_POST['index']) : -1;

if ($plate === '' || $index < 0) {
    die("<h2>Invalid request: missing plate or index.</h2>");
}

$logFile = __DIR__ . "/logs/{$plate}.json";
if (!file_exists($logFile)) {
    die("<h2>No log file found for plate: " . htmlspecialchars($plate) . "</h2>");
}

$data = json_decode(file_get_contents($logFile), true);
if (!is_array($data) || !array_key_exists($index, $data)) {
    die("<h2>Invalid entry index for this plate.</h2>");
}

// Mark this entry as verified
$data[$index]['verified'] = 'yes';

// Save back to file
file_put_contents($logFile, json_encode($data, JSON_PRETTY_PRINT));

// Redirect back to manage_entries
header("Location: manage_entries.php?plate=" . urlencode($plate));
exit;
