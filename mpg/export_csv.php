<?php
/*
# Author        : Jason Lamb (with ChatGPT)
# Rev 1.2 - added submission date/time
# Script        : export_csv.php
# Description   : Converts per-vehicle JSON fuel logs into downloadable CSV.
# Usage         : export_csv.php?plate=jasrasr
*/

$plate = isset($_GET['plate']) ? strtolower(trim($_GET['plate'])) : '';
if (empty($plate)) {
    exit("❌ License plate required (use ?plate=XXXX).");
}

$safePlate = preg_replace("/[^a-zA-Z0-9]/", "_", $plate);
$file = "logs/{$safePlate}.json";

if (!file_exists($file)) {
    exit("❌ Log file not found for license plate: <code>$plate</code>");
}

$data = json_decode(file_get_contents($file), true);

header('Content-Type: text/csv');
header("Content-Disposition: attachment; filename=\"{$safePlate}_fuel_log.csv\"");

$csv = fopen('php://output', 'w');
fputcsv($csv, ['Date', 'Odometer', 'Gallons', 'Price', 'Total', 'MPG', 'Submitted']);

foreach ($data as $entry) {
    fputcsv($csv, [
        $entry['date'],
        $entry['odometer'],
        $entry['gallons'],
        $entry['price'],
        $entry['total'],
        $entry['mpg'],
        $entry['submitted'] ?? ''
    ]);
}


fclose($csv);
exit;
?>