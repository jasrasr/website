<?php
/*
# Script        : view_chart.php
# Author        : Jason Lamb (with ChatGPT)
# Revision      : 1.3
# Created Date  : 2025-10-24
# Modified Date : 2025-10-27
# Description   : Displays a line chart of MPG over time with submission timestamps (ET) in tooltips.
*/
session_start();

# $plate = isset($_GET['plate']) ? strtolower(trim($_GET['plate'])) : ''; # LOWERCASE
$plate = isset($_GET['plate']) ? strtoupper(trim($_GET['plate'])) : '';
$logFile = "logs/$plate.json";


if (empty($plate)) {
    echo "❌ No license plate specified.";
    exit;
}

$safePlate = preg_replace("/[^a-zA-Z0-9]/", "_", $plate);
$file = "logs/{$safePlate}.json";

if (!file_exists($file)) {
    echo "❌ No log found for license plate <code>$plate</code>.";
    exit;
}

$data = json_decode(file_get_contents($file), true);

$labels = [];
$mpgValues = [];
$timestamps = [];

foreach ($data as $entry) {
    $labels[] = $entry['date'];
    $mpgValues[] = is_numeric($entry['mpg']) ? $entry['mpg'] : null;

    // Convert submitted UTC time to Eastern Time for tooltip
    if (!empty($entry['submitted'])) {
        $et = (new DateTime($entry['submitted']))->setTimezone(new DateTimeZone('America/New_York'))->format('Y-m-d g:i A T');
        $timestamps[] = $et;
    } else {
        $timestamps[] = '';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>MPG Chart - <?= htmlspecialchars($plate) ?></title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: auto;
            padding-top: 2rem;
        }
        canvas {
            width: 100%;
            height: 400px;
        }
        .back-links {
            margin-top: 20px;
        }
        .back-links a {
            text-decoration: none;
            color: #0066cc;
            margin-right: 10px;
        }
        .back-links a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <h2>MPG Chart for License Plate: <code><?= htmlspecialchars($plate) ?></code></h2>
    <canvas id="mpgChart"></canvas>

    <script>
    const ctx = document.getElementById('mpgChart').getContext('2d');

    const mpgChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: <?= json_encode($labels) ?>,
            datasets: [{
                label: 'MPG',
                data: <?= json_encode($mpgValues) ?>,
                borderColor: '#28a745',
                backgroundColor: 'rgba(40, 167, 69, 0.2)',
                fill: true,
                spanGaps: true,
                tension: 0.3,
                pointRadius: 5,
                pointHoverRadius: 7
            }]
        },
        options: {
            responsive: true,
            plugins: {
                tooltip: {
                    callbacks: {
                        afterLabel: function(context) {
                            const timestamps = <?= json_encode($timestamps) ?>;
                            return "Submitted: " + timestamps[context.dataIndex];
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Miles Per Gallon (MPG)'
                    }
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

    <div class="back-links">
        <a href="index.php">← Back to Entry Form</a>
        <?php if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true): ?>
            | <a href="admin.php">← Admin Panel</a>
        <?php endif; ?>
    </div>

    <?php include 'menu.php'; ?>
</body>
</html>
