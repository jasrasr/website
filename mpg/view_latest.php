<?php
// ============================================================================
// File: view_latest.php
// Purpose: Display the most recent fuel entry for a license plate
// Revision: 1.3
// ============================================================================

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Normalize plate
$plate = strtoupper(trim($_GET['plate'] ?? ''));
$logFile = __DIR__ . "/logs/{$plate}.json";

if (!$plate || !file_exists($logFile)) {
    die("❌ No log found for license plate {$plate}.");
}

// Load log
$data = json_decode(file_get_contents($logFile), true);
if (!$data) {
    die("⚠️ Log is unreadable for {$plate}.");
}

// Most recent entry
$latest = end($data);

function safe($arr, $key, $default = "—") {
    return isset($arr[$key]) && $arr[$key] !== "" ? $arr[$key] : $default;
}

$date           = safe($latest, 'date');
$odometer       = safe($latest, 'odometer');
$gallons        = safe($latest, 'gallons');
$pricePG        = safe($latest, 'price_per_gallon');
$totalCost      = safe($latest, 'total_cost');
$mpg            = safe($latest, 'mpg');
$submittedET    = safe($latest, 'submitted_et', 'N/A');

?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Latest Entry - <?php echo $plate; ?></title>
<style>
body{font-family:sans-serif;max-width:900px;margin:auto;padding-top:2rem;}
table{border-collapse:collapse;width:100%;margin-top:1rem;}
th,td{border:1px solid #ccc;padding:0.6rem;text-align:left;}
th{background:#f0f0f0;width:200px;}
a{text-decoration:none;color:#007BFF;}
</style>
</head>
<body>

<h2>Latest Entry for License Plate: <?php echo $plate; ?></h2>

<table>
<tr><th>Date</th><td><?php echo $date; ?></td></tr>
<tr><th>Odometer</th><td><?php echo $odometer; ?></td></tr>
<tr><th>Gallons</th><td><?php echo $gallons; ?></td></tr>
<tr><th>Price per Gallon</th><td>$<?php echo number_format((float)$pricePG,3); ?></td></tr>
<tr><th>Total Cost</th><td>$<?php echo number_format((float)$totalCost,2); ?></td></tr>
<tr><th>MPG</th><td><?php echo $mpg; ?> mpg</td></tr>
<tr><th>Submitted (ET)</th><td><?php echo $submittedET; ?></td></tr>
</table>

<br>
<a href="fuel_form.php">← Back to Entry Form</a>

</body>
</html>

<?php include 'menu.php'; ?>
