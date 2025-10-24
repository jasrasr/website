<?php
/*
# Author        : Jason Lamb (with ChatGPT)
# Script        : view_chart.php
# Description   : Displays an MPG trend chart over time for a specific vehicle.
# Usage         : view_chart.php?plate=jasrasr
# Dependencies  : Chart.js (loaded via CDN)
*/

$plate = isset($_GET['plate']) ? strtolower(trim($_GET['plate'])) : '';
if (empty($plate)) {
    exit("❌ License plate required (use ?plate=XXXX)");
}

$safePlate = preg_replace("/[^a-zA-Z0-9]/", "_", $plate);
$file = "logs/{$safePlate}.json";

if (!file_exists($file)) {
    exit("❌ No log found for license plate <code>$plate</code>");
}

$data = json_decode(file_get_contents($file), true);

// Filter entries with valid MPG
$mpgPoints = [];
foreach ($data as $entry) {
    if (isset($entry['mpg']) && is_numeric($entry['mpg'])) {
        $mpgPoints[] = [
            'date' => $entry['date'],
            'mpg' => $entry['mpg']
        ];
    }
}

if (empty($mpgPoints)) {
    exit("❌ No MPG data available for <code>$plate</code>.");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>MPG Trend: <?= htmlspecialchars($plate) ?></title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body {
            font-family: sans-serif;
            max-width: 800px;
            margin: auto;
            padding: 2rem;
            text-align: center;
        }
        canvas {
            margin-top: 2rem;
        }
        a {
            display: inline-block;
            margin-top: 1rem;
            color: #007BFF;
            text-decoration: none;
        }
    </style>
</head>
<body>

<h2>MPG Trend for <code><?= htmlspecialchars($plate) ?></code></h2>

<canvas id="mpgChart" width="700" height="400"></canvas>

<script>
    const ctx = document.getElementById('mpgChart').getContext('2d');
    const mpgChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: <?= json_encode(array_column($mpgPoints, 'date')) ?>,
            datasets: [{
                label: 'MPG',
                data: <?= json_encode(array_column($mpgPoints, 'mpg')) ?>,
                borderColor: 'rgba(75, 192, 192, 1)',
                backgroundColor: 'rgba(75, 192, 192, 0.2)',
                borderWidth: 2,
                pointRadius: 4,
                tension: 0.25
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    title: {
                        display: true,
                        text: 'Miles Per Gallon'
                    },
                    beginAtZero: true
                },
                x: {
                    title: {
                        display: true,
                        text: 'Date'
                    }
                }
            }
        }
    });
</script>

<a href="index.html">← Back to Entry Form</a>

</body>
</html>
