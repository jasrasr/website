<?php
// ============================================================================
// File: save_log.php
// Purpose: Save entry, compute MPG, record IP + device, add miles + verified,
//          show restricted summary for non-admin/trusted users, bump entry_count.
// Revision: 2.1
// Author: Jason Lamb
// ============================================================================

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/device_init.php';

// Sanitizer
function sanitize($v) { return htmlspecialchars(trim($v), ENT_QUOTES, 'UTF-8'); }

// Capture IP address (stored in JSON only)
$ipAddress = $visitorIP;

// ------------------------------------------------------------
// Determine license plate (priority):
// 1. Manual text (licensePlate)
// 2. Dropdown (plateDropdown)
// 3. Device default (already applied as selected in form)
// ------------------------------------------------------------
$dropdown = strtoupper(trim($_POST['plateDropdown'] ?? ''));
$textbox  = strtoupper(trim($_POST['licensePlate'] ?? ''));

$plate = $textbox !== "" ? $textbox : $dropdown;
$plate = preg_replace("/[^A-Z0-9]/", "", $plate);

if ($plate === "") {
    die("<h2>Error: License plate is required.</h2>");
}

// Store active plate for menu.php
$_SESSION['active_plate'] = $plate;

// ------------------------------------------------------------
// Other form fields
// ------------------------------------------------------------
$date     = sanitize($_POST['date'] ?? '');
$odometer = floatval($_POST['odometer'] ?? 0);

if ($odometer <= 0) {
    die("<h2>Error: Odometer must be greater than zero.</h2>");
}

// ------------------------------------------------------------
// Fuel math: need ANY 2 of pricePerGallon, gallons, totalPrice
// ------------------------------------------------------------
$pg_input_raw  = $_POST['pricePerGallon'] ?? "";
$gal_input_raw = $_POST['gallons']        ?? "";
$ttl_input_raw = $_POST['totalPrice']     ?? "";

$pg_input  = ($pg_input_raw !== "")  ? floatval($pg_input_raw)  : null;
$gal_input = ($gal_input_raw !== "") ? floatval($gal_input_raw) : null;
$ttl_input = ($ttl_input_raw !== "") ? floatval($ttl_input_raw) : null;

$provided = 0;
if (!is_null($pg_input))  $provided++;
if (!is_null($gal_input)) $provided++;
if (!is_null($ttl_input)) $provided++;

if ($provided < 2) {
    $missing = [];
    if (is_null($pg_input))  $missing[] = "Price per gallon";
    if (is_null($gal_input)) $missing[] = "Gallons";
    if (is_null($ttl_input)) $missing[] = "Total price";
    die("<h2>Error: " . implode(", ", $missing) . " missing — cannot calculate.</h2>");
}

// Calculate missing value
if (is_null($ttl_input)) {
    $ttl_input = round($pg_input * $gal_input, 2);
} elseif (is_null($pg_input)) {
    $pg_input = round($ttl_input / $gal_input, 3);
} elseif (is_null($gal_input)) {
    $gal_input = round($ttl_input / $pg_input, 3);
}

// Apply your +0.009 rule to price
$price_per_gallon = $pg_input + 0.009;
$gallons          = $gal_input;
$total_cost       = $ttl_input;

// ------------------------------------------------------------
// Prepare logs directory + file
// ------------------------------------------------------------
$logDir = __DIR__ . '/logs/';
if (!is_dir($logDir)) mkdir($logDir, 0755, true);

$logFile = $logDir . "{$plate}.json";

// ------------------------------------------------------------
// Determine previous odometer → compute miles driven
// ------------------------------------------------------------
$previousOdometer = 0;
$miles = 0;

if (file_exists($logFile)) {
    $oldData = json_decode(file_get_contents($logFile), true);
    if (is_array($oldData) && count($oldData) > 0) {
        $previousOdometer = floatval(end($oldData)['odometer'] ?? 0);
        $miles = round(max(0, $odometer - $previousOdometer), 1);
    }
} else {
    $miles = 0; // first entry
}

// ------------------------------------------------------------
// MPG calculation
// ------------------------------------------------------------
$mpg = ($gallons > 0 && $miles > 0) ? round($miles / $gallons, 2) : 0;

// ------------------------------------------------------------
// ET timestamp
// ------------------------------------------------------------
$tz = new DateTimeZone('America/New_York');
$submittedET = (new DateTime('now', $tz))->format('Y-m-d H:i:s T');

// get last existing entry
$lastEntry = end($entries) ?: null;

if ($lastEntry) {
    $lastOdo = floatval($lastEntry['odometer']);
    $miles = $odometer - $lastOdo;

    if ($odometer == $lastOdo || $miles <= 0) {
        http_response_code(400);
        echo "Duplicate or invalid entry (odometer unchanged or invalid miles).";
        exit;
    }
}


// ------------------------------------------------------------
// Load existing entries + append new one
// ------------------------------------------------------------
$entries = [];
if (file_exists($logFile)) {
    $entries = json_decode(file_get_contents($logFile), true) ?: [];
}

// ------------------------------------------------------------
// Build new JSON entry
// ------------------------------------------------------------
$newEntry = [
    "license_plate"    => $plate,
    "date"             => $date,
    "odometer"         => $odometer,
    "miles"            => $miles,
    "gallons"          => $gallons,
    "price_per_gallon" => $price_per_gallon,
    "total_cost"       => $total_cost,
    "mpg"              => $mpg,
    "submitted_et"     => $submittedET,
    "ip_address"       => $ipAddress,
    "device_id"        => $deviceId,
    "verified"         => "no"
];

$entries[] = $newEntry;
file_put_contents($logFile, json_encode($entries, JSON_PRETTY_PRINT));

// ------------------------------------------------------------
// Increment entry_count for this device
// ------------------------------------------------------------
$deviceWhitelistFile = __DIR__ . "/device_whitelist.json";
$deviceWhitelist = [];

if (file_exists($deviceWhitelistFile)) {
    $decoded = json_decode(file_get_contents($deviceWhitelistFile), true);
    if (is_array($decoded)) {
        $deviceWhitelist = $decoded;
    }
}

if (isset($deviceWhitelist[$deviceId])) {
    $deviceWhitelist[$deviceId]['entry_count'] = ($deviceWhitelist[$deviceId]['entry_count'] ?? 0) + 1;
    file_put_contents($deviceWhitelistFile, json_encode($deviceWhitelist, JSON_PRETTY_PRINT));
}

// Reload current counts for the page
$deviceEntryCount = $deviceWhitelist[$deviceId]['entry_count'] ?? 1;
$deviceName       = $deviceWhitelist[$deviceId]['device_name'] ?? null;

?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Entry Saved - <?php echo $plate; ?></title>
<style>
body{font-family:sans-serif;max-width:900px;margin:auto;padding-top:2rem;}
a{color:#007bff;text-decoration:none;font-size:1.05rem;}
.notice-box{
    margin-top:1.5rem;
    padding:0.8rem 1rem;
    border:1px solid #ccc;
    background:#f9f9f9;
    font-size:0.95rem;
}
.notice-box strong{color:#333;}
</style>
</head>
<body>

<?php if (!$isAdminTrusted): ?>

    <h2>Fuel Entry Saved!</h2>

    <h3>Your Entry Summary</h3>

    <p>
        <strong>License Plate:</strong> <?php echo $plate; ?><br>
        <strong>Date:</strong> <?php echo $date; ?><br>
        <strong>Odometer:</strong> <?php echo $odometer; ?><br>
        <strong>Miles Driven:</strong> <?php echo $miles; ?><br>
        <strong>Gallons:</strong> <?php echo $gallons; ?><br>
        <strong>Price per Gallon:</strong> $<?php echo number_format($price_per_gallon, 3); ?><br>
        <strong>Total Cost:</strong> $<?php echo number_format($total_cost, 2); ?><br>
        <strong>Calculated MPG:</strong> <?php echo $mpg; ?><br>
        <strong>Submitted (ET):</strong> <?php echo $submittedET; ?><br>
    </p>

    <?php
    // Build summary for this plate
    $totalMiles   = 0;
    $totalGallons = 0;
    $totalCostAll = 0;
    $avgMPG       = 0;
    $costPerMile  = 0;

    if (file_exists($logFile)) {
        $all = json_decode(file_get_contents($logFile), true);

        foreach ($all as $entry) {
            if (!isset($entry['miles']) || $entry['miles'] <= 0) continue;
            if (!isset($entry['gallons']) || $entry['gallons'] <= 0) continue;

            $totalMiles   += $entry['miles'];
            $totalGallons += $entry['gallons'];
            $totalCostAll += $entry['total_cost'] ?? 0;
        }

        $avgMPG      = $totalGallons > 0 ? round($totalMiles / $totalGallons, 2) : 0;
        $costPerMile = $totalMiles > 0 ? round($totalCostAll / $totalMiles, 3) : 0;
    }
    ?>

    <h3>Your Vehicle Summary</h3>
    <p>
        Miles: <?php echo $totalMiles; ?><br>
        Gallons: <?php echo $totalGallons; ?><br>
        Cost: $<?php echo number_format($totalCostAll,2); ?><br>
        Average MPG: <?php echo $avgMPG; ?><br>
        Cost per Mile: $<?php echo $costPerMile; ?><br>
    </p>

    <?php if (empty($deviceName) && $deviceEntryCount >= 1): ?>
        <div class="notice-box">
            <strong>Optional:</strong> Give this device a friendly name (e.g. "Jason's iPhone")
            so you can recognize it later in the admin panel.<br>
            <a href="device_name.php?next=<?php echo urlencode('fuel_form.php'); ?>">Name this device</a>
        </div>
    <?php endif; ?>

    <hr>

    <a href="view_latest.php?plate=<?php echo urlencode($plate); ?>">View My Latest Entry</a><br>
    <a href="view_chart.php?plate=<?php echo urlencode($plate); ?>">View My MPG Chart</a><br>
    <a href="view_stats.php?plate=<?php echo urlencode($plate); ?>">View My Stats</a><br>
    <a href="fuel_form.php">Submit Another Entry</a>

<?php else: ?>

    <h2>Fuel Entry Saved for <?php echo $plate; ?></h2>

    <p>
        <strong>Date:</strong> <?php echo $date; ?><br>
        <strong>Odometer:</strong> <?php echo $odometer; ?><br>
        <strong>Miles Driven:</strong> <?php echo $miles; ?><br>
        <strong>Gallons:</strong> <?php echo $gallons; ?><br>
        <strong>Price per Gallon:</strong> $<?php echo number_format($price_per_gallon,3); ?><br>
        <strong>Total Cost:</strong> $<?php echo number_format($total_cost,2); ?><br>
        <strong>Calculated MPG:</strong> <?php echo $mpg; ?><br>
        <strong>Submitted (ET):</strong> <?php echo $submittedET; ?><br>
        <strong>Device ID:</strong> <?php echo htmlspecialchars($deviceId); ?><br>
        <strong>Device Name:</strong> <?php echo $deviceName ? htmlspecialchars($deviceName) : '—'; ?><br>
        <strong>Device Entries:</strong> <?php echo $deviceEntryCount; ?><br>
    </p>

    <p>
        <?php if (empty($deviceName)): ?>
            <a href="device_name.php?next=<?php echo urlencode('admin.php'); ?>">Name this device</a><br>
        <?php endif; ?>
        <a href="admin.php">← Back to Admin Panel</a><br>
        <a href="fuel_form.php">Submit Another Entry</a>
    </p>

<?php endif; ?>

</body>
</html>

<?php include 'menu.php'; ?>
