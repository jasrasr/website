<?php
/*
# Author        : Jason Lamb (with ChatGPT)
# Script        : admin.php
# Description   : Protected admin dashboard to manage per-vehicle logs.
*/

session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit;
}

$logDir = "logs";
$files = glob("$logDir/*.json");
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Fuel Log Admin</title>
    <style>
        body { font-family: sans-serif; max-width: 900px; margin: auto; padding-top: 2rem; }
        table { width: 100%; border-collapse: collapse; margin-top: 2rem; }
        th, td { border: 1px solid #ccc; padding: 0.5rem; text-align: center; }
        th { background-color: #f2f2f2; }
        a { text-decoration: none; color: #007BFF; }
    </style>
</head>
<body>

<h2>Fuel Log Admin Panel</h2>
<p><a href="logout.php">Log out</a></p>

<table>
    <thead>
        <tr>
            <th>License Plate</th>
            <th>Total Entries</th>
            <th>Last Date</th>
            <th>View</th>
            <th>Chart</th>
            <th>CSV</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($files as $file): 
        $plate = basename($file, ".json");
        $data = json_decode(file_get_contents($file), true);
        $count = count($data);
        $lastDate = end($data)['date'] ?? 'N/A';
    ?>
        <tr>
            <td><?= htmlspecialchars($plate) ?></td>
            <td><?= $count ?></td>
            <td><?= htmlspecialchars($lastDate) ?></td>
            <td><a href="view_latest.php?plate=<?= $plate ?>">🔍</a></td>
            <td><a href="view_chart.php?plate=<?= $plate ?>">📈</a></td>
            <td><a href="export_csv.php?plate=<?= $plate ?>">⬇️</a></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>

</body>
</html>
