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

// Pre-fill values passed from scan_photos.php via GET
$prefill = [
    'odometer'       => isset($_GET['odometer'])       ? (float)$_GET['odometer']      : null,
    'pricePerGallon' => isset($_GET['pricePerGallon'])  ? (float)$_GET['pricePerGallon'] : null,
    'totalPrice'     => isset($_GET['totalCost'])       ? (float)$_GET['totalCost']      : null,
    'gallons'        => isset($_GET['gallons'])         ? (float)$_GET['gallons']        : null,
];
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

<div id="formError" style="display:none;background:#f8d7da;color:#721c24;padding:0.7rem 1rem;border-radius:7px;margin-bottom:1rem;font-size:0.9rem;max-width:360px;"></div>

<div id="successCard" style="display:none;background:white;border-radius:10px;padding:1.2rem;margin-bottom:1rem;box-shadow:0 1px 4px rgba(0,0,0,0.1);border-left:5px solid #28a745;max-width:360px;">
    <h3 style="margin:0 0 0.6rem;color:#28a745;">✅ Entry Saved!</h3>
    <div id="successDetails" style="font-size:0.9rem;line-height:1.8;"></div>
    <div style="margin-top:1rem;display:flex;gap:0.7rem;flex-wrap:wrap;">
        <a href="fuel_form.php" style="background:#007bff;color:white;padding:0.5rem 1rem;border-radius:6px;text-decoration:none;font-size:0.9rem;">+ New Entry</a>
        <a id="viewLatestLink" href="#" style="background:#6c757d;color:white;padding:0.5rem 1rem;border-radius:6px;text-decoration:none;font-size:0.9rem;">🔍 View Entry</a>
    </div>
</div>

<p style="margin-bottom:1rem;">
    <a href="scan_photos.php" style="background:#007bff;color:white;padding:0.4rem 0.9rem;border-radius:6px;text-decoration:none;font-size:0.9rem;">📷 Scan Photos Instead</a>
</p>

<form id="fuelForm">

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
<input type="number" name="odometer" step="0.1" min="0"
       value="<?= $prefill['odometer'] !== null ? $prefill['odometer'] : '' ?>">

<label>
    Price per Gallon ($) — enter 2 decimals (e.g. 3.69) and .009 is added, or enter full price (e.g. 3.699)
</label>
<div style="display:flex;gap:6px;align-items:center;">
    <input type="number" id="price" name="pricePerGallon" step="0.001" min="0"
           value="<?= $prefill['pricePerGallon'] !== null ? $prefill['pricePerGallon'] : '' ?>">
    <button type="button" class="clear-btn" data-clear="price">✖</button>
</div>
<label>Total Price ($)</label>
<div style="display:flex;gap:6px;align-items:center;">
    <input type="number" id="total" name="totalPrice" step="0.01" min="0"
           value="<?= $prefill['totalPrice'] !== null ? $prefill['totalPrice'] : '' ?>">
    <button type="button" class="clear-btn" data-clear="total">✖</button>
</div>

<label>Total Gallons (up to .###)</label>
<div style="display:flex;gap:6px;align-items:center;">
    <input type="number" id="gallons" name="gallons" step="0.001" min="0"
           value="<?= $prefill['gallons'] !== null ? $prefill['gallons'] : '' ?>">
    <button type="button" class="clear-btn" data-clear="gallons">✖</button>
</div>


<div class="note">
Enter <strong>any two</strong> of Price, Total, Gallons.  
The third is auto-calculated.
</div>

<button type="submit" id="submitBtn">Save Entry</button>
</form>

<?php include 'menu.php'; ?>

<div style="margin-top:2rem;padding-top:0.5rem;border-top:1px solid #ddd;color:#aaa;font-size:0.75rem;text-align:center;">
    fuel_form.php — Rev 2.5 — Updated: <?php $mt = new DateTime('@'.filemtime(__FILE__)); $mt->setTimezone(new DateTimeZone('America/New_York')); echo $mt->format('Y-m-d H:i (g:i A T)'); ?>
</div>

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

// Run on load in case values were pre-filled from scan
calculate();

function normalizePlateInput(value) {
    return value.trim().toUpperCase().replace(/[^A-Z0-9]/g, '');
}

// ── AJAX form submission ──────────────────────────────────────────────────────
document.getElementById('fuelForm').addEventListener('submit', async (e) => {
    e.preventDefault();

    const submitBtn = document.getElementById('submitBtn');
    submitBtn.disabled = true;
    submitBtn.textContent = 'Saving…';
    document.getElementById('formError').style.display = 'none';

    const form = e.target;
    const plateDropdown = normalizePlateInput(form.plateDropdown?.value ?? '');
    const licensePlate = normalizePlateInput(form.licensePlate?.value ?? '');
    if (form.licensePlate) form.licensePlate.value = licensePlate;

    const body = new URLSearchParams({
        plateDropdown:   plateDropdown,
        licensePlate:    licensePlate,
        date:            form.date?.value           ?? '',
        odometer:        form.odometer?.value       ?? '',
        pricePerGallon:  form.pricePerGallon?.value ?? '',
        totalPrice:      form.totalPrice?.value     ?? '',
        gallons:         form.gallons?.value        ?? ''
    });

    try {
        const resp = await fetch('auto_save.php', { method: 'POST', body });
        const data = await resp.json();

        if (data.error) {
            showFormError(data.error);
        } else {
            document.getElementById('successDetails').innerHTML =
                `<b>Plate:</b> ${data.plate}<br>
                 <b>Date:</b> ${data.date}<br>
                 <b>Odometer:</b> ${data.odometer}<br>
                 <b>Miles driven:</b> ${data.miles}<br>
                 <b>Gallons:</b> ${data.gallons}<br>
                 <b>Price/gal:</b> $${data.price}<br>
                 <b>Total:</b> $${data.total}<br>
                 <b>MPG:</b> ${data.mpg}<br>
                 <b>Submitted:</b> ${data.submitted}`;
            document.getElementById('viewLatestLink').href = `view_latest.php?plate=${encodeURIComponent(data.plate)}`;
            document.getElementById('successCard').style.display = 'block';
            document.getElementById('fuelForm').style.display = 'none';
            document.getElementById('successCard').scrollIntoView({ behavior: 'smooth' });
        }
    } catch (err) {
        showFormError('Save failed: ' + err.message);
    } finally {
        submitBtn.disabled = false;
        submitBtn.textContent = 'Save Entry';
    }
});

function showFormError(msg) {
    const el = document.getElementById('formError');
    el.textContent = '⚠️ ' + msg;
    el.style.display = 'block';
    el.scrollIntoView({ behavior: 'smooth' });
}

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
