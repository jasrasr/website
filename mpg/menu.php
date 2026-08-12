<?php
// ============================================================================
// File: menu.php
// Purpose: Navigation menu + compact stats summary (mobile friendly)
// Revision: 1.7
// ============================================================================

require_once __DIR__ . '/device_init.php';
$plate = $_SESSION['active_plate'] ?? $defaultPlate;
if ($plate && empty($_SESSION['active_plate'])) $_SESSION['active_plate'] = $plate;
$summaryText = '';

if ($plate) {
    $logFile = __DIR__ . "/logs/{$plate}.json";
    if (file_exists($logFile)) {
        $data = json_decode(file_get_contents($logFile), true);
        $weightedMiles = 0;
        $weightedGallons = 0;
        $totalCost = 0;

        if (is_array($data)) {
            foreach ($data as $entry) {
                $totalCost += (float)($entry['total_cost'] ?? 0);
                if (($entry['mpg_miles'] ?? 0) > 0 && ($entry['mpg_gallons'] ?? 0) > 0) {
                    $weightedMiles += (float)$entry['mpg_miles'];
                    $weightedGallons += (float)$entry['mpg_gallons'];
                }
            }
        }

        if ($weightedGallons > 0) {
            $avgMPG = round($weightedMiles / $weightedGallons, 2);
            $costPerMile = $weightedMiles > 0 ? round($totalCost / $weightedMiles, 3) : 0;
            $summaryText = "MPG: {$avgMPG} | Miles: {$weightedMiles} | CPM: \${$costPerMile}";
        }
    }
}
?>
<style>
.menu-bar{margin-top:2rem;padding:1rem;border-top:1px solid #ccc;color:#666;font-size:.92rem}.menu-bar a{margin-right:1.2rem;text-decoration:none;color:#007bff;display:inline-block;margin-bottom:.35rem}
</style>
<div class="menu-bar">
<strong>Menu:</strong>
<a href="fuel_form.php">New Entry</a>
<a href="scan_photos.php">📷 Scan</a>
<?php if ($plate): ?>
<a href="view_latest.php?plate=<?=urlencode($plate)?>">My Last Entry</a>
<a href="view_chart.php?plate=<?=urlencode($plate)?>">My MPG Chart</a>
<a href="view_stats.php?plate=<?=urlencode($plate)?>">My Stats</a>
<a href="view_map.php?plate=<?=urlencode($plate)?>">🗺 Fuel Map</a>
<?php if ($summaryText): ?><div style="margin-top:6px;color:#444;"><?=htmlspecialchars($summaryText)?></div><?php endif; ?>
<?php endif; ?>
<?php if ($isAdminTrusted): ?><a href="admin.php">Admin</a><a href="devices_admin.php">Devices</a><?php endif; ?>
</div>
