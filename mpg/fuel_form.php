<?php
// ============================================================================
// File: fuel_form.php
// Purpose: Fuel entry form with IP/device-based access logic for dropdown
// Revision: 2.3
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
<title>Fuel Entry</title>
<style>
body{font-family:sans-serif;max-width:900px;margin:auto;padding-top:2rem;}
label{display:block;margin-top:0.8rem;}
input, select{
    width:100%;
    max-width:320px;
    padding:0.4rem;
    margin-top:0.2rem;
}
button{margin-top:1.2rem;padding:0.5rem 1.2rem;}
.note{color:#666;font-size:0.9rem;margin-top:0.2rem;}
.calculated{
    background:#f0f0f0;
}
.calc-label{
    font-size:0.8rem;
    color:#2a7;
    margin-left:6px;
}
.clear-btn{
    padding:0.35rem 0.6rem;
    cursor:pointer;
    border:1px solid #ccc;
    background:#f8f8f8;
    border-radius:4px;
}
.clear-btn:hover{
    background:#eee;
}

</style>
</head>
<body>

<h2>Fuel Entry</h2>

<form method="post" action="save_log.php" id="fuelForm">

<?php if ($canUseDropdown && !empty($knownPlates)): ?>
<label for="plateDropdown">Select a License Plate:</label>
<select id="plateDropdown" name="plateDropdown">
    <option value="">-- Select Plate --</option>
    <?php foreach ($knownPlates as $p):
        $isDefault = ($defaultPlate && strtoupper($p) === strtoupper($defaultPlate));
        $label = $isDefault ? "$p (default)" : $p;
    ?>
    <option value="<?= htmlspecialchars($p) ?>" <?= $isDefault ? 'selected' : '' ?>>
        <?= htmlspecialchars($label) ?>
    </option>
    <?php endforeach; ?>
</select>
<div class="note">
    <?= $defaultPlate ? "Default plate for this device: $defaultPlate" : "Choose a plate or enter a new one." ?>
</div>
<?php endif; ?>

<label for="licensePlate">License Plate (A-Z a-z 0-9)</label>
<input type="text" id="licensePlate" name="licensePlate"
       value="<?= htmlspecialchars($activePlate ?? '') ?>"
       placeholder="Enter plate if not using dropdown">

<label>Date (defaults to today)</label>
<input type="date" name="date" value="<?= $today ?>">

<label>Odometer Reading (up to .#)</label>
<input type="number" name="odometer" step="0.1" min="0">

<label>
    Price per Gallon ($, enter .### — .009 added automatically)
</label>
<div style="display:flex;gap:6px;align-items:center;">
    <input type="number" id="price" name="pricePerGallon" step="0.001" min="0">
    <button type="button" class="clear-btn" data-clear="price">✖</button>
</div>
<label>Total Price ($)</label>
<div style="display:flex;gap:6px;align-items:center;">
    <input type="number" id="total" name="totalPrice" step="0.01" min="0">
    <button type="button" class="clear-btn" data-clear="total">✖</button>
</div>

<label>Total Gallons (up to .###)</label>
<div style="display:flex;gap:6px;align-items:center;">
    <input type="number" id="gallons" name="gallons" step="0.001" min="0">
    <button type="button" class="clear-btn" data-clear="gallons">✖</button>
</div>


<div class="note">
Enter <strong>any two</strong> of Price, Total, Gallons.  
The third is auto-calculated.
</div>

<button type="submit">Save Entry</button>
</form>

<?php include 'menu.php'; ?>

<script>
const price   = document.getElementById('price');
const total   = document.getElementById('total');
const gallons = document.getElementById('gallons');

function num(v){ return v === '' ? null : parseFloat(v); }

function resetCalc(el){
    el.readOnly = false;
    el.classList.remove('calculated');
}

function setCalc(el,val,dec){
    el.value = val.toFixed(dec);
    el.readOnly = true;
    el.classList.add('calculated');
}

function calculate(){
    const p = num(price.value);
    const t = num(total.value);
    const g = num(gallons.value);

    [price,total,gallons].forEach(resetCalc);

    const filled = [p,t,g].filter(v=>v!==null).length;
    if (filled !== 2) return;

    if (p !== null && g !== null) setCalc(total, p * g, 2);
    else if (p !== null && t !== null && p>0) setCalc(gallons, t / p, 3);
    else if (g !== null && t !== null && g>0) setCalc(price, t / g, 3);
}

[price,total,gallons].forEach(el=>{
    el.addEventListener('input', calculate);
});

// Clear buttons
document.querySelectorAll('.clear-btn').forEach(btn=>{
    btn.addEventListener('click', ()=>{
        const id = btn.dataset.clear;
        const el = document.getElementById(id);

        el.value = '';
        resetCalc(el);

        // Also unlock others and re-evaluate
        [price,total,gallons].forEach(resetCalc);
        calculate();
    });
});
</script>


</body>
</html>
