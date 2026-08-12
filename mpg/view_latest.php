<?php
// ============================================================================
// File: view_latest.php
// Purpose: Display the most recent fuel entry for a license plate
// Revision: 1.4
//
// Revision Notes:
// 1.4 - Show fill type, partial MPG status, station, comments, location,
//       and full-to-full MPG span when available.
// 1.3 - Existing latest-entry display.
// ============================================================================

error_reporting(E_ALL);
ini_set('display_errors', 1);

$plate = strtoupper(trim($_GET['plate'] ?? ''));
$logFile = __DIR__ . "/logs/{$plate}.json";

if (!$plate || !file_exists($logFile)) {
    die("❌ No log found for license plate {$plate}.");
}

$data = json_decode(file_get_contents($logFile), true);
if (!is_array($data) || empty($data)) {
    die("⚠️ Log is unreadable for {$plate}.");
}

$latest = end($data);

function safe($arr, $key, $default = '—') {
    return isset($arr[$key]) && $arr[$key] !== '' ? $arr[$key] : $default;
}

function out($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$date        = safe($latest, 'date');
$odometer    = safe($latest, 'odometer');
$miles       = safe($latest, 'miles');
$gallons     = safe($latest, 'gallons');
$pricePG     = safe($latest, 'price_per_gallon', 0);
$totalCost   = safe($latest, 'total_cost', 0);
$fillType    = strtolower((string)($latest['fill_type'] ?? 'full')) === 'partial' ? 'Partial' : 'Full';
$submittedET = safe($latest, 'submitted_et', 'N/A');
$station     = trim((string)($latest['station_brand'] ?? ''));
$comment     = trim((string)($latest['comment'] ?? ''));
$latitude    = $latest['latitude'] ?? null;
$longitude   = $latest['longitude'] ?? null;
$locationSource = trim((string)($latest['location_source'] ?? ''));
$mpgMiles    = $latest['mpg_miles'] ?? null;
$mpgGallons  = $latest['mpg_gallons'] ?? null;

if ($fillType === 'Partial') {
    $mpgDisplay = 'Pending next full fill-up';
} elseif (isset($latest['mpg']) && is_numeric($latest['mpg']) && (float)$latest['mpg'] > 0) {
    $mpgDisplay = number_format((float)$latest['mpg'], 2) . ' mpg';
} else {
    $mpgDisplay = 'N/A — baseline full fill';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Latest Entry - <?php echo out($plate); ?></title>
<style>
body{font-family:sans-serif;max-width:900px;margin:auto;padding:2rem 1rem;}
table{border-collapse:collapse;width:100%;margin-top:1rem;}
th,td{border:1px solid #ccc;padding:0.6rem;text-align:left;vertical-align:top;}
th{background:#f0f0f0;width:210px;}
a{text-decoration:none;color:#007BFF;}
.status-partial{font-weight:600;color:#9a6700;}
</style>
</head>
<body>

<h2>Latest Entry for License Plate: <?php echo out($plate); ?></h2>

<table>
<tr><th>Date</th><td><?php echo out($date); ?></td></tr>
<tr><th>Odometer</th><td><?php echo out($odometer); ?></td></tr>
<tr><th>Miles Since Prior Fuel Event</th><td><?php echo out($miles); ?></td></tr>
<tr><th>Gallons</th><td><?php echo out($gallons); ?></td></tr>
<tr><th>Price per Gallon</th><td>$<?php echo number_format((float)$pricePG, 3); ?></td></tr>
<tr><th>Total Cost</th><td>$<?php echo number_format((float)$totalCost, 2); ?></td></tr>
<tr><th>Fill Type</th><td><?php echo out($fillType); ?></td></tr>
<tr><th>MPG</th><td class="<?= $fillType === 'Partial' ? 'status-partial' : '' ?>"><?php echo out($mpgDisplay); ?></td></tr>
<?php if ($mpgMiles !== null && $mpgGallons !== null): ?>
<tr><th>MPG Calculation Span</th><td><?php echo out($mpgMiles); ?> miles / <?php echo out($mpgGallons); ?> gallons</td></tr>
<?php endif; ?>
<?php if ($station !== ''): ?>
<tr><th>Station Brand</th><td><?php echo out($station); ?></td></tr>
<?php endif; ?>
<?php if ($latitude !== null && $longitude !== null): ?>
<tr><th>Location</th><td><?php echo out($latitude); ?>, <?php echo out($longitude); ?><?= $locationSource !== '' ? ' (' . out($locationSource) . ')' : '' ?></td></tr>
<?php endif; ?>
<?php if ($comment !== ''): ?>
<tr><th>Comment</th><td><?php echo nl2br(out($comment)); ?></td></tr>
<?php endif; ?>
<tr><th>Submitted (ET)</th><td><?php echo out($submittedET); ?></td></tr>
</table>

<br>
<a href="fuel_form.php">← Back to Entry Form</a>

<?php include 'menu.php'; ?>
</body>
</html>
