<?php
// ============================================================================
// File: manage_entries.php
// Purpose: Manage individual entries for a given plate (verify per entry)
// Revision: 1.0
// Author: Jason Lamb
// ============================================================================

error_reporting(E_ALL);
ini_set('display_errors', 1);


if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


require_once __DIR__ . '/device_init.php';

if (!$isAdminTrusted) {
    die("<h2>Access denied — your IP or device is not authorized.</h2>");
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
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Manage Entries - <?php echo htmlspecialchars($plate); ?></title>
<style>
body{font-family:sans-serif;max-width:1100px;margin:auto;padding-top:2rem;}
table{width:100%;border-collapse:collapse;margin-top:2rem;}
th,td{border:1px solid #ccc;padding:0.5rem;text-align:center;}
th{background-color:#f2f2f2;}
.badge-yes{color:green;font-weight:bold;}
.badge-no{color:red;font-weight:bold;}
button.verify-btn{
    background:green;
    color:white;
    border:none;
    padding:6px 12px;
    cursor:pointer;
    border-radius:4px;
}
button.verify-btn:hover{
    opacity:0.9;
}
a{text-decoration:none;color:#007BFF;}
</style>
</head>
<body>

<h2>Manage Entries for License Plate: <?php echo htmlspecialchars($plate); ?></h2>
<p><a href="admin.php">← Back to Admin Panel</a></p>

<table>
<thead>
<tr>
    <th>#</th>
    <th>Date</th>
    <th>Odometer</th>
    <th>Miles</th>
    <th>Gallons</th>
    <th>Price/Gal</th>
    <th>Total Cost</th>
    <th>MPG</th>
    <th>Submitted (ET)</th>
    <th>Verified</th>
    <th>Action</th>
</tr>
</thead>
<tbody>
<?php foreach ($data as $index => $entry): 
    $verified = strtolower($entry['verified'] ?? 'no');
    $isVerified = ($verified === 'yes');
?>
<tr>
    <td><?php echo $index; ?></td>
    <td><?php echo htmlspecialchars($entry['date'] ?? '—'); ?></td>
    <td><?php echo htmlspecialchars($entry['odometer'] ?? '—'); ?></td>
    <td><?php echo htmlspecialchars($entry['miles'] ?? '—'); ?></td>
    <td><?php echo htmlspecialchars($entry['gallons'] ?? '—'); ?></td>
    <td><?php echo isset($entry['price_per_gallon']) ? number_format((float)$entry['price_per_gallon'], 3) : '—'; ?></td>
    <td><?php echo isset($entry['total_cost']) ? '$' . number_format((float)$entry['total_cost'], 2) : '—'; ?></td>
    <td><?php echo htmlspecialchars($entry['mpg'] ?? '—'); ?></td>
    <td><?php echo htmlspecialchars($entry['submitted_et'] ?? '—'); ?></td>
    <td>
        <?php if ($isVerified): ?>
            <span class="badge-yes">Yes</span>
        <?php else: ?>
            <span class="badge-no">No</span>
        <?php endif; ?>
    </td>
    <td>
        <?php if (!$isVerified): ?>
            <form method="post" action="verify_entry.php" style="display:inline;">
                <input type="hidden" name="plate" value="<?php echo htmlspecialchars($plate); ?>">
                <input type="hidden" name="index" value="<?php echo $index; ?>">
                <button type="submit" class="verify-btn">Verify</button>
            </form>
        <?php else: ?>
            <!-- No action for already verified entries -->
            <span class="badge-yes">Verified ✔</span>
        <?php endif; ?>
    </td>
</tr>
<?php endforeach; ?>
</tbody>
</table>

</body>
</html>

<?php include 'menu.php'; ?>
