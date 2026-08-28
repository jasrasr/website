<?php
if (session_status() === PHP_SESSION_NONE) session_start();
// ============================================================================
// File: export_csv.php
// Purpose: Export fuel log entries in CSV format
// Revision: 1.6
// ============================================================================

error_reporting(E_ALL);
ini_set('display_errors', 1);

$plate = preg_replace('/[^A-Z0-9]/', '', strtoupper(trim($_GET['plate'] ?? '')));
$logFile = __DIR__ . "/logs/{$plate}.json";
if (!$plate || !file_exists($logFile)) die("❌ No log found for license plate {$plate}.");
$data = json_decode(file_get_contents($logFile), true);
if (!is_array($data)) die("⚠️ Invalid data for {$plate}.");

header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="'.$plate.'_fuel_log.csv"');
$csv = fopen('php://output', 'w');

$headers = [
    'license_plate','date','odometer','miles','gallons','price_per_gallon','total_cost',
    'fill_type','mpg','mpg_miles','mpg_gallons','station_brand','station_location_id',
    'station_location_label','station_city','station_street','station_intersection',
    'latitude','longitude','location_source','comment','source','verified','submitted_et','ip_address','device_id'
];
fputcsv($csv, $headers, ',', '"', "\\");

foreach ($data as $row) {
    $out = [];
    foreach ($headers as $h) $out[] = $row[$h] ?? '';
    fputcsv($csv, $out, ',', '"', "\\");
}

fclose($csv);
exit;
?>
