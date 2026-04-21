<?php
// ============================================================================
// File: view_stats.php
// Purpose: Show Price per Gallon and Miles Driven charts for a plate
// Revision: 1.0
// Author: Jason Lamb
// ============================================================================

error_reporting(E_ALL);
ini_set('display_errors', 1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$plate = strtoupper(trim($_GET['plate'] ?? ''));
$plate = preg_replace('/[^A-Z0-9]/', '', $plate);

if ($plate === '') {
    die("<h2>Error: No license plate specified.</h2>");
}

$logFile = __DIR__ . "/logs/{$plate}.json";
if (!file_exists($logFile)) {
    die("<h2>No log file found for plate: " . htmlspecialchars($plate) . "</h2>");
}

$data = json_decode(file_get_contents($logFile), true);
if (!is_array($data) || count($data) === 0) {
    die("<h2>No entries found for plate: " . htmlspecialchars($plate) . "</h2>");
}

$labels      = [];
$priceData   = [];
$milesData   = [];
$etTooltips  = [];

foreach ($data as $entry) {
    $labels[]    = $entry['date'] ?? '—';
    $priceData[] = isset($entry['price_per_gallon']) ? (float)$entry['price_per_gallon'] : 0;
    $milesData[] = isset($entry['miles']) ? (float)$entry['miles'] : 0;
    $etTooltips[] = $entry['submitted_et'] ?? 'N/A';
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Stats - <?php echo htmlspecialchars($plate); ?></title>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
body{font-family:sans-serif;max-width:1100px;margin:auto;padding-top:2rem;}
canvas{margin-top:2rem;}
a{text-decoration:none;color:#007bff;}
h2,h3{margin-bottom:0.3rem;}
.subtitle{color:#666;margin-top:0;margin-bottom:1.2rem;}
</style>
</head>
<body>

<?php
// Compute summary (ignoring miles <= 0)
$totalMiles = 0;
$totalGallons = 0;
$totalCost = 0;
$validEntries = 0;

foreach ($data as $entry) {
    if (!isset($entry['miles']) || $entry['miles'] <= 0) continue;
    if (!isset($entry['gallons']) || $entry['gallons'] <= 0) continue;

    $totalMiles += $entry['miles'];
    $totalGallons += $entry['gallons'];
    $totalCost += $entry['total_cost'] ?? 0;
    $validEntries++;
}

$avgMPG = $validEntries > 0 ? round($totalMiles / $totalGallons, 2) : 0;
$costPerMile = $totalMiles > 0 ? round($totalCost / $totalMiles, 3) : 0;

// Build summary line
$summaryLine = 
    "Miles: {$totalMiles} | Gallons: {$totalGallons} | " .
    "Cost: \$" . number_format($totalCost, 2) . 
    " | MPG: {$avgMPG} | CPM: \${$costPerMile}";
?>

<h3><?php echo htmlspecialchars($plate); ?> — Summary</h3>
<div style="margin-bottom:18px;color:#444;font-size:1.05rem;">
    <?php echo $summaryLine; ?>
</div>


<h2>Stats for License Plate: <?php echo htmlspecialchars($plate); ?></h2>
<p class="subtitle"><a href="admin.php">← Back to Admin Panel</a></p>

<h3>Price per Gallon Over Time</h3>
<canvas id="priceChart" width="800" height="350"></canvas>

<h3>Miles Driven Between Fills</h3>
<canvas id="milesChart" width="800" height="350"></canvas>

<script>
const labels   = <?php echo json_encode($labels); ?>;
const prices   = <?php echo json_encode($priceData, JSON_NUMERIC_CHECK); ?>;
const miles    = <?php echo json_encode($milesData, JSON_NUMERIC_CHECK); ?>;
const etTips   = <?php echo json_encode($etTooltips); ?>;

// Price per Gallon chart
new Chart(document.getElementById('priceChart'), {
    type: 'line',
    data: {
        labels: labels,
        datasets: [{
            label: "Price per Gallon ($)",
            data: prices,
            borderWidth: 2,
            borderColor: "rgba(0, 123, 255, 1)",
            backgroundColor: "rgba(0, 123, 255, 0.15)",
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
            y: { beginAtZero: false, title: { display: true, text: "Price ($/gal)" } },
            x: { title: { display: true, text: "Date" } }
        }
    }
});

// Miles Driven chart
new Chart(document.getElementById('milesChart'), {
    type: 'bar',
    data: {
        labels: labels,
        datasets: [{
            label: "Miles Driven Between Fills",
            data: miles,
            borderWidth: 1,
            borderColor: "rgba(40, 167, 69, 1)",
            backgroundColor: "rgba(40, 167, 69, 0.4)"
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
            y: { beginAtZero: true, title: { display: true, text: "Miles" } },
            x: { title: { display: true, text: "Date" } }
        }
    }
});
</script>
<h3>Total Miles vs Total Cost (Cumulative)</h3>
<canvas id="totalChart" width="800" height="350"></canvas>

<script>
// --- Build cumulative totals ---
let cumulativeMiles = [];
let cumulativeCost  = [];
let milesSum = 0;
let costSum  = 0;

const gallonsData = <?php echo json_encode(array_column($data, 'gallons'), JSON_NUMERIC_CHECK); ?>;
const priceDataFull = <?php echo json_encode($priceData, JSON_NUMERIC_CHECK); ?>;

for (let i = 0; i < labels.length; i++) {
    // Add miles
    milesSum += miles[i];
    cumulativeMiles.push(parseFloat(milesSum.toFixed(1)));

    // Add cost (gallons * ppg)
    let entryCost = gallonsData[i] * priceDataFull[i];
    costSum += entryCost;
    cumulativeCost.push(parseFloat(costSum.toFixed(2)));
}

// --- Total Miles vs Total Cost Chart ---
new Chart(document.getElementById('totalChart'), {
    type: 'line',
    data: {
        labels: labels,
        datasets: [
            {
                label: "Total Miles Driven",
                data: cumulativeMiles,
                borderColor: "rgba(0, 123, 255, 1)",
                backgroundColor: "rgba(0, 123, 255, 0.15)",
                yAxisID: 'y1',
                borderWidth: 2,
                tension: 0.3,
                fill: false
            },
            {
                label: "Total Fuel Cost ($)",
                data: cumulativeCost,
                borderColor: "rgba(220, 53, 69, 1)",
                backgroundColor: "rgba(220, 53, 69, 0.15)",
                yAxisID: 'y2',
                borderWidth: 2,
                tension: 0.3,
                fill: false
            }
        ]
    },
    options: {
        responsive: true,
        interaction: { mode: 'index', intersect: false },
        stacked: false,
        plugins: {
            tooltip: {
                callbacks: {
                    afterLabel: (ctx) => "Submitted (ET): " + etTips[ctx.dataIndex]
                }
            }
        },
        scales: {
            y1: {
                type: 'linear',
                position: 'left',
                title: { display: true, text: "Total Miles" },
                grid: { drawOnChartArea: true }
            },
            y2: {
                type: 'linear',
                position: 'right',
                title: { display: true, text: "Total Cost ($)" },
                grid: { drawOnChartArea: false }
            },
            x: {
                title: { display: true, text: "Date" }
            }
        }
    }
});

</script>

</body>
</html>

<?php include 'menu.php'; ?>
