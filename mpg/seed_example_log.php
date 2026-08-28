<?php
// ============================================================================
// File: seed_example_log.php
// Purpose: Generate a deterministic one-year synthetic MPG log for ABC1234.
// Revision: 1.0
// Author: Jason Lamb
// ============================================================================

error_reporting(E_ALL);
ini_set('display_errors', 1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Password-authenticated admin session only. Device/IP trust alone is not enough.
if (empty($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(403);
    exit('<h2>Access denied.</h2><p>Admin password login is required.</p>');
}

$plate = 'ABC1234';
$logDir = __DIR__ . '/logs/';
$logFile = $logDir . $plate . '.json';

if (!is_dir($logDir) && !mkdir($logDir, 0755, true)) {
    exit('<h2>Could not create logs directory.</h2>');
}

function randFloat(float $min, float $max, int $decimals = 3): float {
    $scale = 10 ** $decimals;
    return round(mt_rand((int)round($min * $scale), (int)round($max * $scale)) / $scale, $decimals);
}

function etTimestamp(DateTimeImmutable $date): string {
    $tz = new DateTimeZone('America/New_York');
    $hour = 18;
    $minute = mt_rand(0, 59);
    $second = mt_rand(0, 59);
    return $date
        ->setTimezone($tz)
        ->setTime($hour, $minute, $second)
        ->format('Y-m-d H:i:s T');
}

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    mt_srand(2026);

    $start = new DateTimeImmutable('2026-01-01', new DateTimeZone('America/New_York'));
    $end = new DateTimeImmutable('2026-12-31', new DateTimeZone('America/New_York'));

    // Random starting odometer around 20,000 miles.
    $odometer = randFloat(19800, 20500, 1);

    // Start mid-$3 range, then move gradually per fill while remaining $3-$5.
    $price = randFloat(3.35, 3.85, 3);

    $entries = [];
    $date = $start;
    $index = 0;

    while ($date <= $end) {
        $gallons = randFloat(10, 20, 3);

        // Small price movement per fill-up.
        $price += randFloat(-0.120, 0.120, 3);
        $price = max(3.000, min(5.000, round($price, 3)));
        $totalCost = round($gallons * $price, 2);

        if ($index === 0) {
            $miles = 0.0;
            $mpg = null;
            $mpgMiles = null;
            $mpgGallons = null;
        } else {
            // Synthetic fuel economy range keeps mileage realistic for 10-20 gallon fills.
            $targetMpg = randFloat(17.5, 24.5, 2);
            $miles = round($gallons * $targetMpg, 1);
            $odometer = round($odometer + $miles, 1);
            $mpg = round($miles / $gallons, 2);
            $mpgMiles = $miles;
            $mpgGallons = $gallons;
        }

        $entries[] = [
            'license_plate' => $plate,
            'date' => $date->format('Y-m-d'),
            'odometer' => $odometer,
            'miles' => $miles,
            'gallons' => $gallons,
            'price_per_gallon' => $price,
            'total_cost' => $totalCost,
            'fill_type' => 'full',
            'mpg' => $mpg,
            'mpg_miles' => $mpgMiles,
            'mpg_gallons' => $mpgGallons,
            'submitted_et' => etTimestamp($date),
            'ip_address' => '127.0.0.1',
            'device_id' => 'device_a1b2c3d4e5f60718',
            'source' => 'manual',
            'verified' => 'yes',
            'comment' => 'Synthetic example data'
        ];

        $index++;
        $date = $date->modify('+' . mt_rand(5, 7) . ' days');
    }

    $json = json_encode($entries, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if ($json === false || file_put_contents($logFile, $json, LOCK_EX) === false) {
        exit('<h2>Failed to write example log.</h2>');
    }

    $first = $entries[0];
    $last = end($entries);
    $message = sprintf(
        'Created %d entries for %s from %s through %s. Odometer: %.1f → %.1f miles.',
        count($entries),
        $plate,
        $first['date'],
        $last['date'],
        $first['odometer'],
        $last['odometer']
    );
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Seed ABC1234 Example Log</title>
<style>
body{font-family:sans-serif;max-width:720px;margin:auto;padding:2rem 1rem;line-height:1.45}
.notice{padding:1rem;background:#eaf7ea;border-left:4px solid #2e8b57;margin:1rem 0}
.warning{padding:1rem;background:#fff3cd;border-left:4px solid #d39e00;margin:1rem 0}
button{padding:.65rem 1rem;font-size:1rem;cursor:pointer}
a{color:#007bff;text-decoration:none}
</style>
</head>
<body>
<h2>Generate ABC1234 Example Fuel Log</h2>
<p>This creates a deterministic synthetic fuel history for <strong>ABC1234</strong> covering calendar year 2026.</p>
<div class="warning"><strong>Warning:</strong> Running this replaces the current <code>logs/ABC1234.json</code> file.</div>
<?php if ($message): ?>
<div class="notice"><?=htmlspecialchars($message)?></div>
<p><a href="dashboard.php">Open Dashboard Lookup</a> | <a href="view_stats.php?plate=ABC1234">View ABC1234 Stats</a></p>
<?php endif; ?>
<form method="post" onsubmit="return confirm('Replace ABC1234.json with the synthetic 2026 example log?');">
<button type="submit">Generate / Replace Example Log</button>
</form>
<p><a href="admin.php">← Back to Admin</a></p>
</body>
</html>
