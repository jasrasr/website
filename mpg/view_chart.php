<?php
// ============================================================================
// File: view_chart.php
// Purpose: Display MPG trend and partial fuel events for a license plate
// Author: Jason Lamb (with help from AI)
// Revision: 1.6
//
// Revision Notes:
// 1.6 - Initialize device/session state before any HTML output so menu.php does
//       not trigger late cookie/header warnings.
// 1.5 - Keep partial fills visible as event markers while withholding MPG until
//       the next full fill. Show full-to-full calculation span in tooltips.
// 1.4 - Omit missing/zero MPG values so the initial baseline is not graphed as 0.
// 1.3 - Added ET tooltip support.
// ============================================================================

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/device_init.php';

$plate = strtoupper(trim($_GET['plate'] ?? ''));
$logFile = __DIR__ . "/logs/{$plate}.json";

if (!$plate || !file_exists($logFile)) {
    die("❌ No log found for license plate {$plate}.");
}

$data = json_decode(file_get_contents($logFile), true);
if (!is_array($data) || empty($data)) {
    die("⚠️ Cannot read log for {$plate}.");
}

$labels       = [];
$mpgData      = [];
$partialData  = [];
$eventTips    = [];

foreach ($data as $entry) {
    $fillType = strtolower((string)($entry['fill_type'] ?? 'full'));
    $isPartial = $fillType === 'partial';
    $mpg = isset($entry['mpg']) && is_numeric($entry['mpg']) ? (float)$entry['mpg'] : null;
    $validMpg = !$isPartial && $mpg !== null && $mpg > 0;

    $labels[] = $entry['date'] ?? '—';
    $mpgData[] = $validMpg ? $mpg : null;
    $partialData[] = $isPartial ? 0 : null;

    $eventTips[] = [
        'submitted'   => $entry['submitted_et'] ?? 'N/A',
        'fillType'    => $isPartial ? 'Partial' : 'Full',
        'gallons'     => isset($entry['gallons']) ? (float)$entry['gallons'] : null,
        'station'     => $entry['station_brand'] ?? '',
        'comment'     => $entry['comment'] ?? '',
        'mpgMiles'    => isset($entry['mpg_miles']) && is_numeric($entry['mpg_miles']) ? (float)$entry['mpg_miles'] : null,
        'mpgGallons'  => isset($entry['mpg_gallons']) && is_numeric($entry['mpg_gallons']) ? (float)$entry['mpg_gallons'] : null,
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>MPG Trend - <?php echo htmlspecialchars($plate); ?></title>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
body { font-family:sans-serif;max-width:900px;margin:auto;padding:2rem 1rem; }
canvas { margin-top:2rem; }
a { text-decoration:none;color:#007bff; }
.legend-note { color:#666;font-size:.88rem;margin-top:.5rem; }
</style>
</head>
<body>

<h2>MPG Trend for License Plate: <?php echo htmlspecialchars($plate); ?></h2>
<p class="legend-note">Partial fills remain visible as triangle markers at the event baseline. They do not receive an MPG value; their gallons roll into the next full-to-full calculation.</p>

<canvas id="mpgChart" width="800" height="400"></canvas>

<script>
const labels = <?php echo json_encode($labels); ?>;
const mpgData = <?php echo json_encode($mpgData, JSON_NUMERIC_CHECK); ?>;
const partialData = <?php echo json_encode($partialData, JSON_NUMERIC_CHECK); ?>;
const eventTips = <?php echo json_encode($eventTips, JSON_NUMERIC_CHECK); ?>;

new Chart(document.getElementById('mpgChart'), {
    type: 'line',
    data: {
        labels,
        datasets: [
            {
                label: 'Miles Per Gallon (MPG)',
                data: mpgData,
                borderColor: 'blue',
                backgroundColor: 'rgba(0,0,255,0.15)',
                borderWidth: 2,
                tension: 0.3,
                fill: true,
                spanGaps: true
            },
            {
                label: 'Partial Fill Event',
                data: partialData,
                showLine: false,
                pointStyle: 'triangle',
                pointRadius: 7,
                pointHoverRadius: 9,
                borderColor: '#d97706',
                backgroundColor: '#f59e0b'
            }
        ]
    },
    options: {
        responsive: true,
        interaction: { mode: 'nearest', intersect: false },
        plugins: {
            tooltip: {
                callbacks: {
                    label: (ctx) => {
                        if (ctx.datasetIndex === 1) return 'Partial fill — MPG pending next full fill-up';
                        return `MPG: ${ctx.parsed.y}`;
                    },
                    afterLabel: (ctx) => {
                        const tip = eventTips[ctx.dataIndex] || {};
                        const lines = [];
                        lines.push(`Fill type: ${tip.fillType || 'Full'}`);
                        if (tip.gallons !== null && tip.gallons !== undefined) lines.push(`Gallons: ${tip.gallons}`);
                        if (ctx.datasetIndex === 0 && tip.mpgMiles && tip.mpgGallons) {
                            lines.push(`Full-to-full span: ${tip.mpgMiles} mi / ${tip.mpgGallons} gal`);
                        }
                        if (tip.station) lines.push(`Station: ${tip.station}`);
                        if (tip.comment) lines.push(`Comment: ${tip.comment}`);
                        lines.push(`Submitted (ET): ${tip.submitted || 'N/A'}`);
                        return lines;
                    }
                }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                title: { display:true,text:'MPG' }
            },
            x: {
                title: { display:true,text:'Date' }
            }
        }
    }
});
</script>

<br>
<a href="fuel_form.php">← Back to Entry Form</a>

<?php include 'menu.php'; ?>
</body>
</html>
