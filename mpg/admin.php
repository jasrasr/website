<?php
// ============================================================================
// File: admin.php
// Purpose: Admin dashboard for vehicle MPG tracker
// Revision: 2.3
// Author: Jason Lamb
// ============================================================================
error_reporting(E_ALL);ini_set('display_errors',1);require_once __DIR__.'/device_init.php';
$isPasswordAdmin=!empty($_SESSION['admin_logged_in']);
if(!$isAdminTrusted&&!$isPasswordAdmin){header('Location: login.php?return=admin');exit;}
$currentIP=$visitorIP;
?>
<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"><title>Fuel Log Admin Panel</title><style>body{font-family:sans-serif;max-width:1100px;margin:auto;padding:1rem}table{width:100%;border-collapse:collapse;margin-top:2rem}th,td{border:1px solid #ccc;padding:.5rem;text-align:center}th{background:#f2f2f2}a{text-decoration:none;color:#007BFF;font-size:1.05rem}.ip-ok,.badge-yes{color:green;font-weight:bold}.ip-bad,.badge-no{color:red;font-weight:bold}.auth-note{background:#eef6ff;border:1px solid #b8d8ff;padding:.7rem;border-radius:6px}button.verify-btn{background:green;color:white;border:0;padding:6px 12px;cursor:pointer;border-radius:4px}</style></head><body>
<h2>Fuel Log Admin Panel</h2>
<p><strong>Your IP:</strong> <?=htmlspecialchars($currentIP)?><br><strong>IP Whitelisted:</strong> <?=$isIPWhitelisted?'<span class="ip-ok">Yes</span>':'<span class="ip-bad">No</span>'?><br><strong>Device Trusted:</strong> <?=$isDeviceTrusted?'<span class="ip-ok">Yes</span>':'<span class="ip-bad">No</span>'?><br><strong>Password Session:</strong> <?=$isPasswordAdmin?'<span class="ip-ok">Yes</span>':'No'?><br><strong>Device ID:</strong> <?=htmlspecialchars($deviceId)?></p>
<?php if($isPasswordAdmin&&!$isAdminTrusted):?><p class="auth-note">You are signed in with the admin password, but this browser/device is not currently trusted. You can use the admin area and open Manage Devices to review or trust this device.</p><?php endif;?>
<p><a href="devices_admin.php">Manage Devices</a> | <a href="logout.php">Log out</a></p>
<table><thead><tr><th>License Plate</th><th>Total Entries</th><th>Last Date</th><th>Last Miles</th><th>Last MPG</th><th>Last Total Cost</th><th>Last Verified</th><th>View</th><th>MPG Chart</th><th>Price & Miles Charts</th><th>CSV</th><th>JSON</th><th>Manage Entries</th></tr></thead><tbody>
<?php $files=glob(__DIR__.'/logs/*.json');foreach($files as $file):$plate=basename($file,'.json');$data=json_decode(file_get_contents($file),true);if(!is_array($data)||!count($data))continue;$total=count($data);$last=end($data);$lastDate=$last['date']??'—';$lastMiles=$last['miles']??'—';$lastMpg=$last['mpg']??'—';$lastCost=$last['total_cost']??'—';$isVerified=strtolower($last['verified']??'no')==='yes';?>
<tr><td><?=htmlspecialchars($plate)?></td><td><?=$total?></td><td><?=htmlspecialchars($lastDate)?></td><td><?=htmlspecialchars($lastMiles)?></td><td><?=htmlspecialchars($lastMpg)?></td><td>$<?=number_format((float)$lastCost,2)?></td><td><?=$isVerified?'<span class="badge-yes">Yes</span>':'<span class="badge-no">No</span>'?></td><td><a href="view_latest.php?plate=<?=urlencode($plate)?>">🔍</a></td><td><a href="view_chart.php?plate=<?=urlencode($plate)?>">📈</a></td><td><a href="view_stats.php?plate=<?=urlencode($plate)?>">📊</a></td><td><a href="export_csv.php?plate=<?=urlencode($plate)?>">⬇️</a></td><td><a href="logs/<?=urlencode($plate)?>.json" target="_blank">📄</a></td><td><a href="manage_entries.php?plate=<?=urlencode($plate)?>">Manage</a></td></tr><?php endforeach;?></tbody></table>
<?php include 'menu.php';?></body></html>
