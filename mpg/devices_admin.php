<?php
// ============================================================================
// File: devices_admin.php
// Purpose: Manage device whitelist (trust/untrust, block/unblock, name, plate).
// Revision: 2.0
// Author: Jason Lamb
// ============================================================================

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/device_init.php';

if (!$isAdminTrusted) {
    die("<h2>Access denied — your IP or device is not authorized.</h2>");
}

$deviceWhitelistFile = __DIR__ . "/device_whitelist.json";
$deviceWhitelist = file_exists($deviceWhitelistFile)
    ? json_decode(file_get_contents($deviceWhitelistFile), true)
    : [];

if (!is_array($deviceWhitelist)) {
    $deviceWhitelist = [];
}

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action   = $_POST['action']    ?? '';
    $targetId = $_POST['device_id'] ?? '';
    $plate    = isset($_POST['plate'])       ? strtoupper(trim($_POST['plate']))       : null;
    $newName  = isset($_POST['device_name']) ? trim($_POST['device_name'])             : null;

    if (isset($deviceWhitelist[$targetId])) {
        if ($action === 'trust') {
            $deviceWhitelist[$targetId]['trusted'] = true;
        } elseif ($action === 'untrust') {
            $deviceWhitelist[$targetId]['trusted'] = false;
        } elseif ($action === 'block') {
            $deviceWhitelist[$targetId]['blocked'] = true;
        } elseif ($action === 'unblock') {
            $deviceWhitelist[$targetId]['blocked'] = false;
        } elseif ($action === 'delete') {
            unset($deviceWhitelist[$targetId]);
        } elseif ($action === 'update_plate') {
            $deviceWhitelist[$targetId]['plate'] = $plate ?: null;
        } elseif ($action === 'update_name') {
            $deviceWhitelist[$targetId]['device_name'] = $newName !== '' ? $newName : null;
        }

        file_put_contents($deviceWhitelistFile, json_encode($deviceWhitelist, JSON_PRETTY_PRINT));
    }

    header("Location: devices_admin.php");
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Device Management</title>
<style>
body{font-family:sans-serif;max-width:1100px;margin:auto;padding-top:2rem;}
table{width:100%;border-collapse:collapse;margin-top:1.5rem;}
th,td{border:1px solid #ccc;padding:0.4rem;text-align:center;font-size:0.9rem;}
th{background:#f2f2f2;}
.badge-yes{color:green;font-weight:bold;}
.badge-no{color:red;font-weight:bold;}
.badge-block{color:#c00;font-weight:bold;}
form{display:inline;}
small{color:#777;}
input[type="text"]{padding:0.2rem;font-size:0.85rem;}
</style>
</head>
<body>

<h2>Device Management</h2>
<p><a href="admin.php">← Back to Admin Panel</a></p>

<table>
<thead>
<tr>
    <th>Device ID</th>
    <th>Device Name</th>
    <th>Trusted</th>
    <th>Blocked</th>
    <th>Default Plate</th>
    <th>Visits</th>
    <th>Entries</th>
    <th>Created</th>
    <th>Last Seen</th>
    <th>Last IP</th>
    <th>User Agent</th>
    <th>Actions</th>
</tr>
</thead>
<tbody>
<?php foreach ($deviceWhitelist as $id => $info): ?>
<?php
    $trusted    = !empty($info['trusted']);
    $blocked    = !empty($info['blocked']);
    $name       = $info['device_name'] ?? '';
    $plate      = $info['plate'] ?? '';
    $created    = $info['created_at'] ?? '';
    $lastSeen   = $info['last_seen']  ?? '';
    $lastIP     = $info['last_ip']    ?? '';
    $ua         = $info['user_agent'] ?? '';
    $visits     = $info['visit_count'] ?? 0;
    $entries    = $info['entry_count'] ?? 0;
?>
<tr>
    <td><?php echo htmlspecialchars($id); ?></td>
    <td>
        <form method="post" action="devices_admin.php">
            <input type="hidden" name="device_id" value="<?php echo htmlspecialchars($id); ?>">
            <input type="hidden" name="action" value="update_name">
            <input type="text" name="device_name"
                   value="<?php echo htmlspecialchars($name); ?>"
                   placeholder="e.g. Jason's iPhone" style="width:140px;">
            <button type="submit">Save</button>
        </form>
    </td>
    <td>
        <?php if ($trusted): ?>
            <span class="badge-yes">Yes</span>
        <?php else: ?>
            <span class="badge-no">No</span>
        <?php endif; ?>
    </td>
    <td>
        <?php if ($blocked): ?>
            <span class="badge-block">Blocked</span>
        <?php else: ?>
            <span class="badge-no">No</span>
        <?php endif; ?>
    </td>
    <td>
        <form method="post" action="devices_admin.php">
            <input type="hidden" name="device_id" value="<?php echo htmlspecialchars($id); ?>">
            <input type="hidden" name="action" value="update_plate">
            <input type="text" name="plate" value="<?php echo htmlspecialchars($plate); ?>" style="width:70px;">
            <button type="submit">Save</button>
        </form>
    </td>
    <td><?php echo (int)$visits; ?></td>
    <td><?php echo (int)$entries; ?></td>
    <td><?php echo htmlspecialchars($created); ?></td>
    <td><?php echo htmlspecialchars($lastSeen); ?></td>
    <td><?php echo htmlspecialchars($lastIP); ?></td>
    <td>
        <small>
            <?php echo htmlspecialchars(strlen($ua) > 80 ? substr($ua,0,77).'...' : $ua); ?>
        </small>
    </td>
    <td>
        <form method="post" action="devices_admin.php">
            <input type="hidden" name="device_id" value="<?php echo htmlspecialchars($id); ?>">
            <input type="hidden" name="action" value="<?php echo $trusted ? 'untrust' : 'trust'; ?>">
            <button type="submit">
                <?php echo $trusted ? 'Untrust' : 'Trust'; ?>
            </button>
        </form>

        <form method="post" action="devices_admin.php">
            <input type="hidden" name="device_id" value="<?php echo htmlspecialchars($id); ?>">
            <input type="hidden" name="action" value="<?php echo $blocked ? 'unblock' : 'block'; ?>">
            <button type="submit">
                <?php echo $blocked ? 'Unblock' : 'Block'; ?>
            </button>
        </form>

        <form method="post" action="devices_admin.php" onsubmit="return confirm('Delete this device entry?');">
            <input type="hidden" name="device_id" value="<?php echo htmlspecialchars($id); ?>">
            <input type="hidden" name="action" value="delete">
            <button type="submit">Delete</button>
        </form>
    </td>
</tr>
<?php endforeach; ?>
</tbody>
</table>

<?php include 'menu.php'; ?>

</body>
</html>
