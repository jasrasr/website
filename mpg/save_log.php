<?php
/*
# Revision      : 1.11
# Author        : Jason Lamb (with ChatGPT)
# Created Date  : 2025-10-23
# Modified Date : 2025-10-24
# Description   : Saves fuel log entry to JSON. Calculates MPG if valid, appends entry,
#                 displays confirmation and previous 5 entries, and reloads form.
# Revision History:
#   1.0 - Initial version saving entries to per-plate JSON files
#   1.2 - Added .009 to price field for accurate calculation
#   1.3 - Added MPG calculations and file locking
#   1.4 - Prevented out-of-order date calculations
#   1.5 - Added optional Total Price field
#   1.6 - Removed redirect, displayed form again
#   1.7 - Ensured logs folder is created, wrapped form in full HTML
#   1.8 - Displayed calculated MPG and history above the form
#   1.11 - add submission date/time 
*/

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

function sanitize($value) {
    return htmlspecialchars(trim($value));
}

// ──────────────── PROCESS INPUT ────────────────
$plate = strtolower(preg_replace("/[^a-zA-Z0-9]/", "", $_POST['licensePlate'] ?? 'unknown'));
$date = sanitize($_POST['date'] ?? '');
$odometer = floatval($_POST['odometer'] ?? 0);
$gallons = floatval($_POST['gallons'] ?? 0);
$price = floatval($_POST['price'] ?? 0) + 0.009;
$total = isset($_POST['total']) ? floatval($_POST['total']) : null;

// ──────────────── ENSURE LOG DIRECTORY ────────────────
$logDir = __DIR__ . '/logs';
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}
$logFile = "$logDir/{$plate}.json";

// ──────────────── LOAD EXISTING DATA ────────────────
$entries = [];
if (file_exists($logFile)) {
    $json = file_get_contents($logFile);
    $entries = json_decode($json, true) ?: [];
}

// ──────────────── SORT & FIND LAST VALID ENTRY ────────────────
usort($entries, fn($a, $b) => strtotime($a['date']) <=> strtotime($b['date']));
$lastValid = null;
foreach (array_reverse($entries) as $entry) {
    if (!empty($entry['date']) && isset($entry['odometer']) && isset($entry['gallons'])) {
        if (strtotime($entry['date']) < strtotime($date) && $odometer > $entry['odometer']) {
            $lastValid = $entry;
            break;
        }
    }
}

// ──────────────── CALCULATE MPG ────────────────
$mpg = null;
if ($lastValid) {
    $distance = $odometer - $lastValid['odometer'];
    $mpg = ($gallons > 0 && $distance > 0) ? round($distance / $gallons, 2) : null;
}

// ──────────────── COLLECT SUBMITTED DATE/TIME FOR LOG  ────────────────

$submitted = date('c'); // ISO 8601 (e.g., 2025-10-25T16:38:45)

$newEntry = [
    'date' => $date,
    'odometer' => floatval($odometer),
    'gallons' => floatval($gallons),
    'price' => floatval($price),
    'total' => $total === '' ? null : floatval($total),
    'mpg' => $mpg,
    'submitted' => $submitted
];


// ──────────────── SAVE NEW ENTRY ────────────────
$newEntry = [
    'date' => $date,
    'odometer' => floatval($odometer),
    'gallons' => floatval($gallons),
    'price' => round($price, 3),
    'total' => $total === '' ? null : floatval($total),
    'mpg' => $mpg,
    'submitted' => $submitted
];


$entries[] = $newEntry;
file_put_contents($logFile, json_encode($entries, JSON_PRETTY_PRINT), LOCK_EX);

// ──────────────── DISPLAY HTML ────────────────
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Fuel Log Saved</title>
    <style>
        body { font-family: sans-serif; padding: 20px; max-width: 600px; margin: auto; }
        label { display: block; margin-top: 10px; }
        input, button { display: block; width: 100%; padding: 8px; margin-top: 4px; }
        h2, h3 { margin-top: 30px; }
        .entry { border-bottom: 1px solid #ccc; padding: 8px 0; }
        .success { background: #e0ffe0; padding: 10px; border: 1px solid #8f8; }
    </style>
</head>
<body>

    <div class="success">
        ✅ Entry for <strong><?= strtoupper($plate) ?></strong> saved.
        <?= is_null($mpg) ? "<br>MPG: <em>Not available</em>" : "<br>MPG: <strong>$mpg</strong>" ?>
    </div>

    <h3>Previous 5 Entries</h3>
    <?php
    $recent = array_slice(array_reverse($entries), 0, 5);
    foreach ($recent as $e) {
        echo "<div class='entry'>";
        echo "<strong>Date:</strong> {$e['date']}<br>";
        echo "<strong>Odometer:</strong> {$e['odometer']}<br>";
        echo "<strong>Gallons:</strong> {$e['gallons']}<br>";
        echo "<strong>Price:</strong> \${$e['price']}<br>";
        echo "<strong>Total:</strong> " . (isset($e['total']) ? "\${$e['total']}" : '—') . "<br>";
        echo "<strong>MPG:</strong> " . (isset($e['mpg']) ? $e['mpg'] : '—');
        echo "</div>";
    }
    ?>

    <h3>Add Another Entry</h3>
    <?php include 'fuel_form.php'; ?>
    <br>
    <a href=index.php>HOME</a>

</body>
</html>

