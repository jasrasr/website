<?php
// ============================================================================
// File: admin.php
// Purpose: Admin dashboard for vehicle MPG tracker
// Revision: 2.2
// Author: Jason Lamb
// ============================================================================

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/device_init.php';

if (!$isAdminTrusted) {
    die("<h2>Access denied — your IP or device is not authorized.</h2>");
}

$currentIP = $visitorIP;
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Fuel Log Admin Panel</title>
<style>
body{font-family:sans-serif;max-width:1100px;margin:auto;padding-top:2rem;}
table{width:100%;border-collapse:collapse;margin-top:2rem;}
th,td{border:1px solid #ccc;padding:0.5rem;text-align:center;}
th{background-color:#f2f2f2;}
a{text-decoration:none;color:#007BFF;font-size:1.05rem;}
.ip-ok{color:green;font-weight:bold;}
.ip-bad{color:red;font-weight:bold;}
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
</style>
</head>
<body>

<h2>Fuel Log Admin Panel</h2>

<p>
<strong>Your IP:</strong> <?php echo $currentIP; ?><br>
<strong>IP Whitelisted:</strong>
    <?php echo $isIPWhitelisted ? '<span class="ip-ok">Yes</span>' : '<span class="ip-bad">No</span>'; ?><br>
<strong>Device Trusted:</strong>
    <?php echo $isDeviceTrusted ? '<span class="ip-ok">Yes</span>' : '<span class="ip-bad">No</span>'; ?><br>
<strong>Device ID:</strong> <?php echo htmlspecialchars($deviceId); ?>
</p>

<p>
    <a href="devices_admin.php">Manage Devices</a> |
    <a href="logout.php">Log out</a>
</p>

<table>
    <thead>
<tr>
    <th>License Plate</th>
    <th>Total Entries</th>
    <th>Last Date</th>
    <th>Last Miles</th>
    <th>Last MPG</th>
    <th>Last Total Cost</th>
    <th>Last Verified</th>
    <th>View</th>
    <th>MPG Chart</th>
    <th>Price & Miles Charts</th>
    <th>CSV</th>
    <th>JSON</th>
    <th>Manage Entries</th>
</tr>
    </thead>
    <tbody>
<?php
$logDir = __DIR__ . "/logs/";
$files = glob($logDir . "*.json");

foreach ($files as $file):
    $plate = basename($file, ".json");
    $data  = json_decode(file_get_contents($file), true);

    if (!is_array($data) || count($data) === 0) continue;

    $total       = count($data);
    $last        = end($data);
    $lastDate    = $last['date'] ?? '—';
    $lastMiles   = $last['miles'] ?? '—';
    $lastMpg     = $last['mpg'] ?? '—';
    $lastCost    = $last['total_cost'] ?? '—';
    $lastVerified = strtolower($last['verified'] ?? 'no');
    $isVerified   = ($lastVerified === 'yes');
?>
<tr>
    <td><?php echo htmlspecialchars($plate); ?></td>
    <td><?php echo $total; ?></td>
    <td><?php echo htmlspecialchars($lastDate); ?></td>
    <td><?php echo htmlspecialchars($lastMiles); ?></td>
    <td><?php echo htmlspecialchars($lastMpg); ?></td>
    <td>$<?php echo number_format((float)$lastCost, 2); ?></td>
    <td>
        <?php if ($isVerified): ?>
            <span class="badge-yes">Yes</span>
        <?php else: ?>
            <span class="badge-no">No</span>
        <?php endif; ?>
    </td>
    <td><a href="view_latest.php?plate=<?php echo urlencode($plate); ?>">🔍</a></td>
    <td><a href="view_chart.php?plate=<?php echo urlencode($plate); ?>">📈</a></td>
    <td><a href="view_stats.php?plate=<?php echo urlencode($plate); ?>">📊</a></td>
    <td><a href="export_csv.php?plate=<?php echo urlencode($plate); ?>">⬇️</a></td>
    <td><a href="logs/<?php echo urlencode($plate); ?>.json" target="_blank">📄</a></td>
    <td><a href="manage_entries.php?plate=<?php echo urlencode($plate); ?>">Manage</a></td>
</tr>
<?php endforeach; ?>
    </tbody>
</table>

</body>
</html>

<?php include 'menu.php'; ?>
