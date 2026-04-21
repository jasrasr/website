<?php
// ============================================================================
// File: view_chart.php
// Purpose: Display an MPG trend chart for a license plate
// Author: Jason Lamb (with help from AI)
// Created: 2026-01-XX
// Modified: 2026-02-17
// Revision: 1.4
//
// Revision Notes:
// 1.4 - Omit entries where MPG is missing or <= 0 to prevent initial 0 value
//       from appearing on chart. Ensures only valid calculated MPG values
//       are graphed.
// 1.3 - Added ET tooltip support
// ============================================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

error_reporting(E_ALL);
ini_set('display_errors', 1);

$plate = strtoupper(trim($_GET['plate'] ?? ''));
$logFile = __DIR__ . "/logs/{$plate}.json";

if (!$plate || !file_exists($logFile)) {
    die("❌ No log found for license plate {$plate}.");
}

$data = json_decode(file_get_contents($logFile), true);
if (!$data) {
    die("⚠️ Cannot read log for {$plate}.");
}

$labels      = [];
$mpgData     = [];
$etTooltips  = [];

foreach ($data as $entry) {

    // Skip entries without valid MPG
    if (!isset($entry['mpg']) || (float)$entry['mpg'] <= 0) {
        continue;
    }

    $labels[]     = $entry['date'] ?? '—';
    $mpgData[]    = (float)$entry['mpg'];
    $etTooltips[] = $entry['submitted_et'] ?? 'N/A';
}

?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>MPG Trend - <?php echo htmlspecialchars($plate); ?></title>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
body {
    font-family: sans-serif;
    max-width: 900px;
    margin: auto;
    padding-top: 2rem;
}
canvas {
    margin-top: 2rem;
}
a {
    text-decoration: none;
    color: #007bff;
}
</style>
</head>
<body>

<h2>MPG Trend for License Plate: <?php echo htmlspecialchars($plate); ?></h2>

<canvas id="mpgChart" width="800" height="400"></canvas>

<script>
const labels  = <?php echo json_encode($labels); ?>;
const mpgData = <?php echo json_encode($mpgData, JSON_NUMERIC_CHECK); ?>;
const etTips  = <?php echo json_encode($etTooltips); ?>;

new Chart(document.getElementById('mpgChart'), {
    type: 'line',
    data: {
        labels: labels,
        datasets: [{
            label: "Miles Per Gallon (MPG)",
            data: mpgData,
            borderColor: "blue",
            backgroundColor: "rgba(0,0,255,0.15)",
            borderWidth: 2,
            tension: 0.3,
            fill: true
        }]
    },
    options: {
        responsive: true,
        plugins: {
            tooltip: {
                callbacks: {
                    afterLabel: (ctx) => "Submitted (ET): " + etTips[ctx.dataIndex]
                }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                title: { display: true, text: "MPG" }
            },
            x: {
                title: { display: true, text: "Date" }
            }
        }
    }
});
</script>

<br>
<a href="fuel_form.php">← Back to Entry Form</a>

</body>
</html>

<?php include 'menu.php'; ?>
