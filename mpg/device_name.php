<?php
// ============================================================================
// File: device_name.php
// Purpose: Let the current device choose a friendly display name.
//          Does NOT trust or unblock the device; purely cosmetic/identification.
// Revision: 1.0
// Author: Jason Lamb
// ============================================================================

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/device_init.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Optional redirect target after naming (fallback: fuel_form.php)
$next = $_GET['next'] ?? 'fuel_form.php';
$next = preg_replace('/[^a-zA-Z0-9_\-\.\/\?=&]/', '', $next); // simple sanitization

// Load device whitelist
$deviceWhitelistFile = __DIR__ . "/device_whitelist.json";
$deviceWhitelist = file_exists($deviceWhitelistFile)
    ? json_decode(file_get_contents($deviceWhitelistFile), true)
    : [];

if (!is_array($deviceWhitelist)) {
    $deviceWhitelist = [];
}

$currentName = $deviceName ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newName = trim($_POST['device_name'] ?? '');

    if (isset($deviceWhitelist[$deviceId])) {
        $deviceWhitelist[$deviceId]['device_name'] = $newName !== '' ? $newName : null;
        file_put_contents($deviceWhitelistFile, json_encode($deviceWhitelist, JSON_PRETTY_PRINT));
    }

    header("Location: " . $next);
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Name This Device</title>
<style>
body{font-family:sans-serif;max-width:600px;margin:auto;padding-top:2rem;}
label{display:block;margin-top:0.8rem;}
input[type="text"]{width:100%;padding:0.5rem;margin-top:0.3rem;}
button{margin-top:1rem;padding:0.5rem 1.1rem;}
.note{margin-top:0.5rem;font-size:0.9rem;color:#666;}
</style>
</head>
<body>

<h2>Name This Device</h2>

<p>
This helps you recognize which device is which later (for example, in the admin panel).
You might use names like:
</p>
<ul>
    <li>"Jason's iPhone"</li>
    <li>"Work Laptop Chrome"</li>
    <li>"Home PC Edge"</li>
</ul>

<form method="post" action="device_name.php?next=<?php echo urlencode($next); ?>">
    <label for="device_name">Device Name</label>
    <input type="text" id="device_name" name="device_name"
           value="<?php echo htmlspecialchars($currentName); ?>"
           placeholder="e.g. Jason's iPhone">

    <div class="note">
        This does <strong>not</strong> grant admin access or trust this device; it is only a label.
    </div>

    <button type="submit">Save Name</button>
</form>

<p style="margin-top:1.5rem;">
    <a href="<?php echo htmlspecialchars($next); ?>">Skip for now</a>
</p>

<?php include 'menu.php'; ?>

</body>
</html>
