<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<?php
// ============================================================================
// File: export_csv.php
// Purpose: Export fuel log entries in CSV format
// Revision: 1.5
// ============================================================================

error_reporting(E_ALL);
ini_set('display_errors', 1);

$plate = strtoupper(trim($_GET['plate'] ?? ''));
$logFile = __DIR__ . "/logs/{$plate}.json";

if (!$plate || !file_exists($logFile)) {
    die("❌ No log found for license plate {$plate}.");
}

$data = json_decode(file_get_contents($logFile), true);
if (!is_array($data)) {
    die("⚠️ Invalid data for {$plate}.");
}

// CSV headers
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="'.$plate.'_fuel_log.csv"');

$csv = fopen('php://output', 'w');

// ------------------------
// Write CSV header row
// ------------------------
fputcsv(
    $csv,
    [
        'license_plate',
        'date',
        'odometer',
        'gallons',
        'price_per_gallon',
        'total_cost',
        'mpg',
        'submitted_et',
        'ip_address'
    ],
    ",",
    '"',
    "\\"
);

// ------------------------
// Write CSV rows
// ------------------------
foreach ($data as $row) {
    fputcsv(
        $csv,
        [
            $row['license_plate']    ?? "",
            $row['date']             ?? "",
            $row['odometer']         ?? "",
            $row['gallons']          ?? "",
            $row['price_per_gallon'] ?? "",
            $row['total_cost']       ?? "",
            $row['mpg']              ?? "",
            $row['submitted_et']     ?? "",
            $row['ip_address']       ?? ""
        ],
        ",",
        '"',
        "\\"
    );
}

fclose($csv);
exit;
?>

<?php include 'menu.php'; ?>
