<?php
// ============================================================================
// File: menu.php
// Purpose: Navigation menu + compact stats summary (mobile friendly)
// Revision: 1.6
// ============================================================================

require_once __DIR__ . '/device_init.php';

// Active plate from session (or from device default)
$plate = $_SESSION['active_plate'] ?? $defaultPlate;

if ($plate && empty($_SESSION['active_plate'])) {
    $_SESSION['active_plate'] = $plate;
}

$summaryText = "";

// If plate is valid, compute compact summary for menu
if ($plate) {
    $logFile = __DIR__ . "/logs/{$plate}.json";
    if (file_exists($logFile)) {
        $data = json_decode(file_get_contents($logFile), true);

        $totalMiles = 0;
        $totalGallons = 0;
        $totalCost = 0;
        $entryCount = 0;

        foreach ($data as $entry) {
            if (!isset($entry['miles']) || $entry['miles'] <= 0) continue;
            if (!isset($entry['gallons']) || $entry['gallons'] <= 0) continue;

            $totalMiles   += $entry['miles'];
            $totalGallons += $entry['gallons'];
            $totalCost    += $entry['total_cost'] ?? 0;
            $entryCount++;
        }

        if ($entryCount > 0) {
            $avgMPG      = round($totalMiles / $totalGallons, 2);
            $costPerMile = $totalMiles > 0 ? round($totalCost / $totalMiles, 3) : 0;

            $summaryText =
                "MPG: {$avgMPG} | Miles: {$totalMiles} | CPM: \${$costPerMile}";
        }
    }
}
?>
<style>
.menu-bar {
    margin-top: 2rem;
    padding: 1rem;
    border-top: 1px solid #ccc;
    color: #666;
    font-size: 0.92rem;
}
.menu-bar a {
    margin-right: 1.2rem;
    text-decoration: none;
    color: #007bff;
}
</style>

<div class="menu-bar">
    <strong>Menu:</strong>
    <a href="fuel_form.php">New Entry</a>
    <a href="scan_photos.php">📷 Scan</a>

    <?php if ($plate): ?>
        <a href="view_latest.php?plate=<?php echo urlencode($plate); ?>">My Last Entry</a>
        <a href="view_chart.php?plate=<?php echo urlencode($plate); ?>">My MPG Chart</a>
        <a href="view_stats.php?plate=<?php echo urlencode($plate); ?>">My Stats</a>

        <?php if ($summaryText): ?>
            <div style="margin-top:6px;color:#444;">
                <?php echo $summaryText; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <?php if ($isAdminTrusted): ?>
        <a href="admin.php">Admin</a>
        <a href="devices_admin.php">Devices</a>
    <?php endif; ?>
</div>
