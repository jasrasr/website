<?php
// view.php - Full history for a single device (Revision 1.0)

function h($str) {
    return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
}

$device = $_GET['device'] ?? '';
$device = preg_replace('/[^a-zA-Z0-9\-_]/', '', $device);

if ($device === '') {
    http_response_code(400);
    echo "Missing or invalid device parameter.";
    exit;
}

$logDir = __DIR__ . "/logs/";
$logFile = $logDir . $device . ".json";

if (!file_exists($logFile)) {
    http_response_code(404);
    echo "No log found for device: " . h($device);
    exit;
}

$json = file_get_contents($logFile);
$entries = json_decode($json, true);

if (!is_array($entries) || count($entries) === 0) {
    echo "No entries found for device: " . h($device);
    exit;
}

// Sort newest → oldest by ServerReceived, then TimeLocal
$sortField1 = "ServerReceived";
$sortField2 = "TimeLocal";

usort($entries, function($a, $b) use ($sortField1, $sortField2) {
    $aTime = $a[$sortField1] ?? $a[$sortField2] ?? null;
    $bTime = $b[$sortField1] ?? $b[$sortField2] ?? null;

    $aTs = $aTime ? strtotime($aTime) : 0;
    $bTs = $bTime ? strtotime($bTime) : 0;

    return $bTs <=> $aTs;
});

// Collect all keys across all entries so new fields auto-appear
$allKeys = [];
foreach ($entries as $entry) {
    foreach ($entry as $key => $value) {
        $allKeys[$key] = true;
    }
}
$allKeys = array_keys($allKeys);

// Move some common keys to the front if present
$preferredOrder = [
    "ServerReceived",
    "TimeLocal",
    "TimeUTC",
    "ComputerName",
    "Model",
    "UserName",
    "Domain",
    "OSVersion",
    "OSEdition",
    "BuildNumber",
    "SerialNumber",
    "LocalIP",
    "PublicIP",
    "UptimeMinutes",
    "BootTime"
];

$orderedKeys = [];
foreach ($preferredOrder as $k) {
    if (in_array($k, $allKeys, true)) {
        $orderedKeys[] = $k;
    }
}
foreach ($allKeys as $k) {
    if (!in_array($k, $orderedKeys, true)) {
        $orderedKeys[] = $k;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>History for <?= h($device) ?></title>
    <style>
        body { font-family: system-ui, -apple-system, BlinkMacSystemFont, sans-serif; margin: 20px; }
        h1 { margin-bottom: 5px; }
        .small { font-size: 12px; color: #666; margin-bottom: 15px; }
        table { border-collapse: collapse; width: 100%; margin-top: 10px; font-size: 13px; }
        th, td { border: 1px solid #ccc; padding: 6px; vertical-align: top; }
        th { background: #eee; position: sticky; top: 0; z-index: 1; }
        tr:nth-child(even) { background: #f9f9f9; }
        a { text-decoration: none; color: #0645ad; }
        a:hover { text-decoration: underline; }
        .scroll-container { max-height: 70vh; overflow: auto; }
        code { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace; }
    </style>
</head>
<body>

<h1>History for <?= h($device) ?></h1>
<p class="small">
    Showing all stored heartbeat entries for this device (newest first).  
    <a href="dashboard.php">&larr; Back to dashboard</a>
</p>

<div class="scroll-container">
    <table>
        <thead>
        <tr>
            <th>#</th>
            <?php foreach ($orderedKeys as $key): ?>
                <th><?= h($key) ?></th>
            <?php endforeach; ?>
        </tr>
        </thead>
        <tbody>
        <?php
        $index = 1;
        foreach ($entries as $entry):
        ?>
            <tr>
                <td><?= $index++ ?></td>
                <?php foreach ($orderedKeys as $key):
                    $val = $entry[$key] ?? "";
                    // Simple formatting for arrays/objects
                    if (is_array($val) || is_object($val)) {
                        $val = json_encode($val, JSON_UNESCAPED_SLASHES);
                    }
                    ?>
                    <td><code><?= h($val) ?></code></td>
                <?php endforeach; ?>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

</body>
</html>
