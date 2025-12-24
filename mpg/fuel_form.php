<?php
// ============================================================================
// File: fuel_form.php
// Purpose: Fuel entry form with IP/device-based access logic for dropdown
// Revision: 2.2
// ============================================================================

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/device_init.php';

// Build list of known plates from logs
$logDir = __DIR__ . '/logs/';
$knownPlates = [];

if (is_dir($logDir)) {
    foreach (glob($logDir . '*.json') as $file) {
        $p = basename($file, '.json');
        if ($p !== '') {
            $knownPlates[] = $p;
        }
    }
}

$knownPlates = array_unique($knownPlates);
sort($knownPlates);

// Determine whether this device/IP can use dropdown
$canUseDropdown = $isIPWhitelisted || $isDeviceTrusted;

// Active plate: session or device default
$activePlate = $_SESSION['active_plate'] ?? $defaultPlate;
if ($activePlate && empty($_SESSION['active_plate'])) {
    $_SESSION['active_plate'] = $activePlate;
}

// Today's date default
$today = (new DateTime('now', new DateTimeZone('America/New_York')))->format('Y-m-d');
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Fuel Entry Form</title>
<style>
body{font-family:sans-serif;max-width:900px;margin:auto;padding-top:2rem;}
label{display:block;margin-top:0.8rem;}
input[type="text"], input[type="number"], input[type="date"], select{
    width:100%;
    max-width:320px;
    padding:0.4rem;
    margin-top:0.2rem;
}
button{
    margin-top:1.2rem;
    padding:0.5rem 1.2rem;
}
.note{color:#666;font-size:0.9rem;margin-top:0.2rem;}
</style>
</head>
<body>

<h2>Fuel Entry</h2>

<form method="post" action="save_log.php">

<script>
document.addEventListener("DOMContentLoaded", () => {
  const form = document.querySelector("form");
  form.addEventListener("submit", () => {
    // disable submit to make double-click less likely
    const btn = form.querySelector("button[type=submit], input[type=submit]");
    if (btn) btn.disabled = true;
  });
});
</script>


    <?php if ($canUseDropdown && !empty($knownPlates)): ?>
        <label for="plateDropdown">Select a License Plate:</label>
        <select id="plateDropdown" name="plateDropdown">
            <option value="">-- Select Plate --</option>
            <?php
            foreach ($knownPlates as $p) {
                $isDefault = ($defaultPlate && strtoupper($p) === strtoupper($defaultPlate));
                $label = $isDefault ? "$p (default)" : $p;
                echo '<option value="' . htmlspecialchars($p) . '"'
                     . ($isDefault ? ' selected' : '')
                     . '>' . htmlspecialchars($label) . '</option>';
            }
            ?>
        </select>
        <div class="note">
            <?php if ($defaultPlate): ?>
                Default plate for this device: <?php echo htmlspecialchars($defaultPlate); ?> (you may change it below).
            <?php else: ?>
                Choose a plate or enter a new one.
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <label for="licensePlate">License Plate (A-Z a-z 0-9)</label>
    <input type="text" id="licensePlate" name="licensePlate" 
           value="<?php echo $activePlate ? htmlspecialchars($activePlate) : ''; ?>"
           placeholder="Enter plate if not using dropdown">

    <label for="date">Date (defaults to today)</label>
    <input type="date" id="date" name="date" value="<?php echo $today; ?>">

    <label for="odometer">Odometer Reading (up to .1)</label>
    <input type="number" id="odometer" name="odometer" step="0.1" min="0">

    <label for="pricePerGallon">Price per Gallon ($, up to .001)</label>
    <input type="number" id="pricePerGallon" name="pricePerGallon" step="0.001" min="0">

    <label for="totalPrice">Total Price ($)</label>
    <input type="number" id="totalPrice" name="totalPrice" step="0.01" min="0">

    <label for="gallons">Total Gallons (up to .001)</label>
    <input type="number" id="gallons" name="gallons" step="0.001" min="0">

    <div class="note">
        Enter any <strong>two</strong> of: Price per Gallon, Total Price, Gallons.  
        The third will be calculated. If fewer than 2 are provided, you’ll get an error.
    </div>

    <button type="submit">Save Entry</button>
</form>

<?php include 'menu.php'; ?>
<script>
const priceInput   = document.querySelector('input[name="pricePerGallon"]');
const totalInput   = document.querySelector('input[name="totalPrice"]');
const gallonsInput = document.querySelector('input[name="gallons"]');

let lastEdited = null;

function parseVal(el) {
    return el.value !== "" ? parseFloat(el.value) : null;
}

function markCalculated(el, value, decimals) {
    el.value = value.toFixed(decimals);
    el.readOnly = true;
    el.style.backgroundColor = '#f0f0f0';
}

function clearCalculated(el) {
    if (el !== lastEdited) {
        el.readOnly = false;
        el.style.backgroundColor = '';
    }
}

function liveCalculate() {
    const price   = parseVal(priceInput);
    const total   = parseVal(totalInput);
    const gallons = parseVal(gallonsInput);

    // Reset calculated state (except field being edited)
    [priceInput, totalInput, gallonsInput].forEach(clearCalculated);

    // Determine calculation based on last edited field
    if (lastEdited === priceInput && price !== null && gallons !== null) {
        markCalculated(totalInput, price * gallons, 2);
    }
    else if (lastEdited === priceInput && price !== null && total !== null && price > 0) {
        markCalculated(gallonsInput, total / price, 3);
    }
    else if (lastEdited === gallonsInput && gallons !== null && price !== null) {
        markCalculated(totalInput, price * gallons, 2);
    }
    else if (lastEdited === gallonsInput && gallons !== null && total !== null && gallons > 0) {
        markCalculated(priceInput, total / gallons, 3);
    }
    else if (lastEdited === totalInput && total !== null && gallons !== null && gallons > 0) {
        markCalculated(priceInput, total / gallons, 3);
    }
    else if (lastEdited === totalInput && total !== null && price !== null && price > 0) {
        markCalculated(gallonsInput, total / price, 3);
    }
}

// Track which field user edits
[priceInput, totalInput, gallonsInput].forEach(el => {
    el.addEventListener('input', () => {
        lastEdited = el;
        liveCalculate();
    });
});
</script>


</body>
</html>
