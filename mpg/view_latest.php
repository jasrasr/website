<?php
$plate = isset($_GET['plate']) ? strtolower(trim($_GET['plate'])) : '';

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
$latest = end($data);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Latest MPG - <?= htmlspecialchars($plate) ?></title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 500px; margin: auto; padding-top: 2rem; }
        table { width: 100%; border-collapse: collapse; }
        td { padding: 0.5rem; border-bottom: 1px solid #ccc; }
        th { text-align: left; padding-top: 1rem; }
         a {
        text-decoration: none;
        color: #0066cc;
        margin-right: 10px;
    }

    a:hover {
        text-decoration: underline;
    }
    </style>
</head>
<body>
    <h2>Latest Entry for <code><?= htmlspecialchars($plate) ?></code></h2>
    <table>
        <tr><td><strong>Date:</strong></td><td><?= htmlspecialchars($latest['date']) ?></td></tr>
        <tr><td><strong>Odometer:</strong></td><td><?= htmlspecialchars($latest['odometer']) ?></td></tr>
        <tr><td><strong>Gallons:</strong></td><td><?= htmlspecialchars($latest['gallons']) ?></td></tr>
        <tr><td><strong>Price per Gallon:</strong></td><td>$<?= htmlspecialchars($latest['price']) ?></td></tr>
        <tr><td><strong>MPG:</strong></td><td><?= is_null($latest['mpg']) ? 'N/A' : $latest['mpg'] . ' mpg' ?></td></tr>
    </table>

<div style="margin-top: 20px;">
    <a href="fuel_form.php">← Back to Entry Form</a>
    <?php if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true): ?>
        | <a href="admin.php">← Admin Panel</a>
    <?php endif; ?>
</div>

</body>
</html>

