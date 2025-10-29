<?php
/*
# Author        : Jason Lamb (with ChatGPT)
# Script        : save_log.php
# Revision      : 1.13
# Created Date  : 2025-10-23
# Modified Date : 2025-10-28
# Description   : Save fuel log entry to JSON per vehicle, compute MPG, append with timestamp in ET.
*/

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

function sanitize($value) {
    return htmlspecialchars(trim($value));
}

$plate = strtolower(preg_replace("/[^a-zA-Z0-9]/", "", $_POST['licensePlate'] ?? 'unknown'));
$date = sanitize($_POST['date'] ?? '');
$odometer = floatval($_POST['odometer'] ?? 0);
$gallons = floatval($_POST['gallons'] ?? 0);
$price = floatval($_POST['price'] ?? 0) + 0.009;
$total = isset($_POST['total']) ? floatval($_POST['total']) : null;

// Ensure logs folder
$logDir = __DIR__ . '/logs';
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}
$logFile = "$logDir/{$plate}.json";

// Load data
$entries = [];
if (file_exists($logFile)) {
    $json = file_get_contents($logFile);
    $entries = json_decode($json, true) ?: [];
}

// Sort by date
usort($entries, fn($a, $b) => strtotime($a['date']) <=> strtotime($b['date']));
$lastValid = null;
foreach (array_reverse($entries) as $entry) {
    if (!empty($entry['date']) && isset($entry['odometer']) && isset($entry['gallons'])) {
        if (strtotime($entry['date']) < strtotime($date) && $odometer > $entry['odometer']) {
            $lastValid = $entry;
            break;
        }
    }
}

// Calculate MPG
$mpg = null;
if ($lastValid) {
    $distance = $odometer - $lastValid['odometer'];
    $mpg = ($gallons > 0 && $distance > 0) ? round($distance / $gallons, 2) : null;
}

// Timestamp in Eastern Time
$tz = new DateTimeZone('America/New_York');
$submitted = (new DateTime('now', $tz))->format(DateTime::ATOM);

// Final entry
$newEntry = [
    "license_plate"    => $licensePlate,
    "date"             => $date,
    "odometer"         => $odometer,
    "gallons"          => $gallons,
    "price_per_gallon" => $pricePerGallon,
    "total_cost"       => $totalCost,
    "mpg"              => $mpg,
    "submitted_et"     => $submittedET,
];


$entries[] = $newEntry;
$result = file_put_contents($logFile, json_encode($entries, JSON_PRETTY_PRINT), LOCK_EX);
if ($result === false) {
    die("❌ Failed to write to log file. Check permissions for <code>$logFile</code>.");
}

// Confirmation output
echo "✅ Entry saved for plate $plate. <a href='view_latest.php?plate=$plate'>View Latest</a>";
?>
