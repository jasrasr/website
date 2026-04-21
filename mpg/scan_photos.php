<?php
// ============================================================================
// File: scan_photos.php
// Purpose: Multi-photo upload, AI extracts values, saves entry directly
// Revision: 2.0
// Author: Jason Lamb
// ============================================================================

require_once __DIR__ . '/device_init.php';

// Known plates for selector
$logDir = __DIR__ . '/logs/';
$knownPlates = [];
if (is_dir($logDir)) {
    foreach (glob($logDir . '*.json') as $f) {
        $p = basename($f, '.json');
        if ($p !== '') $knownPlates[] = $p;
    }
}
sort($knownPlates);

$canUseDropdown = $isIPWhitelisted || $isDeviceTrusted;
$activePlate    = $_SESSION['active_plate'] ?? $defaultPlate ?? '';
$today          = (new DateTime('now', new DateTimeZone('America/New_York')))->format('Y-m-d');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Scan Fuel Photos</title>
<style>
* { box-sizing: border-box; }
body { font-family: sans-serif; max-width: 560px; margin: auto; padding: 1rem; background: #f4f4f4; }
h2 { margin-bottom: 0.2rem; }
.subtitle { color: #666; font-size: 0.88rem; margin-bottom: 1.2rem; }

.upload-card {
    background: white; border-radius: 10px; padding: 1.2rem;
    box-shadow: 0 1px 4px rgba(0,0,0,0.1); margin-bottom: 1rem;
}
.upload-card p { color: #555; font-size: 0.85rem; margin: 0.3rem 0 0.9rem; }

#uploadBtn {
    display: block; width: 100%; padding: 0.8rem;
    background: #007bff; color: white; border: none;
    border-radius: 8px; font-size: 1rem; cursor: pointer; text-align: center;
}
#uploadBtn:hover { background: #0056b3; }
input[type="file"] { display: none; }

#thumbs { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 0.8rem; }
#thumbs img {
    width: 90px; height: 70px; object-fit: cover; border-radius: 6px;
    border: 2px solid #ddd; cursor: zoom-in; transition: border-color 0.15s;
}
#thumbs img:hover { border-color: #007bff; }

/* Lightbox */
#lightbox {
    display: none; position: fixed; inset: 0; z-index: 1000;
    background: rgba(0,0,0,0.92); justify-content: center; align-items: center;
}
#lightbox.open { display: flex; }
#lightbox img {
    max-width: 96vw; max-height: 92vh; object-fit: contain;
    border-radius: 6px; box-shadow: 0 0 30px rgba(0,0,0,0.6);
}
#lightboxClose {
    position: absolute; top: 14px; right: 18px;
    color: white; font-size: 2rem; cursor: pointer;
    line-height: 1; background: none; border: none; padding: 0;
}
#photoCount { font-size: 0.82rem; color: #28a745; margin-top: 0.4rem; display: none; }

#errorBox {
    display: none; background: #f8d7da; color: #721c24;
    padding: 0.7rem; border-radius: 7px; margin-bottom: 0.8rem; font-size: 0.9rem;
}

#scanBtn {
    display: block; width: 100%; padding: 0.85rem; font-size: 1.05rem;
    background: #28a745; color: white; border: none; border-radius: 8px; cursor: pointer;
}
#scanBtn:hover { background: #1e7e34; }
#scanBtn:disabled { background: #999; cursor: not-allowed; }

.loading { display: none; text-align: center; padding: 1.5rem; color: #555; }
.spinner {
    display: inline-block; width: 34px; height: 34px;
    border: 4px solid #ddd; border-top-color: #007bff;
    border-radius: 50%; animation: spin 0.75s linear infinite; margin-bottom: 0.5rem;
}
@keyframes spin { to { transform: rotate(360deg); } }

#review {
    display: none; background: white; border-radius: 10px;
    padding: 1.2rem; margin-top: 0.9rem; box-shadow: 0 1px 4px rgba(0,0,0,0.1);
}
#review h3 { margin: 0 0 0.3rem; }
#review .note { font-size: 0.82rem; color: #666; margin-bottom: 1rem; }

.field { margin-bottom: 0.7rem; }
.field label { display: block; font-size: 0.85rem; color: #444; margin-bottom: 0.2rem; }
.field input, .field select {
    width: 100%; padding: 0.45rem 0.6rem; border: 1px solid #ccc;
    border-radius: 5px; font-size: 0.95rem;
}
.field input.filled { border-color: #28a745; background: #f6fff8; }

#saveBtn {
    display: block; width: 100%; padding: 0.85rem; font-size: 1.05rem;
    background: #28a745; color: white; border: none; border-radius: 8px;
    cursor: pointer; margin-top: 0.8rem;
}
#saveBtn:hover { background: #1e7e34; }

.footer { margin-top: 2rem; padding-top: 0.5rem; border-top: 1px solid #ddd; color: #aaa; font-size: 0.75rem; text-align: center; }
a { color: #007bff; text-decoration: none; }
</style>
</head>
<body>

<h2>📷 Scan Fuel Photos</h2>
<p class="subtitle">Select up to 3 photos — odometer, price per gallon, pump total & gallons. AI figures out which is which.</p>

<div id="errorBox"></div>

<div class="upload-card">
    <p>Select all your pump/odometer photos at once from your photo library.</p>
    <button id="uploadBtn" onclick="document.getElementById('photoInput').click()">📷 Select Photos</button>
    <input type="file" id="photoInput" accept="image/*" multiple>
    <div id="photoCount"></div>
    <div id="thumbs"></div>
</div>

<button id="scanBtn" disabled>🔍 Scan &amp; Extract Data</button>

<div class="loading" id="loadingDiv">
    <div class="spinner"></div>
    <div>Reading photos with AI…</div>
</div>

<div id="review">
    <h3>Review &amp; Save</h3>
    <p class="note">Correct anything that looks wrong, then tap Save Entry.</p>

    <div class="field">
        <label>License Plate</label>
        <?php if ($canUseDropdown && !empty($knownPlates)): ?>
        <select id="revPlate">
            <option value="">-- Select Plate --</option>
            <?php foreach ($knownPlates as $p): ?>
            <option value="<?= htmlspecialchars($p) ?>" <?= strtoupper($activePlate) === $p ? 'selected' : '' ?>>
                <?= htmlspecialchars($p) ?><?= (strtoupper($activePlate) === $p) ? ' (default)' : '' ?>
            </option>
            <?php endforeach; ?>
        </select>
        <?php else: ?>
        <input type="text" id="revPlate" value="<?= htmlspecialchars($activePlate) ?>" placeholder="Enter plate">
        <?php endif; ?>
    </div>

    <div class="field">
        <label>Date</label>
        <input type="date" id="revDate" value="<?= $today ?>">
    </div>
    <div class="field">
        <label>Odometer (mi)</label>
        <input type="number" id="revOdometer" step="0.1" placeholder="e.g. 84824.8">
    </div>
    <div class="field">
        <label>Price per Gallon ($)</label>
        <input type="number" id="revPrice" step="0.001" placeholder="e.g. 3.699">
    </div>
    <div class="field">
        <label>Total Cost ($)</label>
        <input type="number" id="revTotal" step="0.01" placeholder="e.g. 42.76">
    </div>
    <div class="field">
        <label>Gallons</label>
        <input type="number" id="revGallons" step="0.001" placeholder="e.g. 12.290">
    </div>

    <button id="saveBtn">💾 Save Entry</button>
</div>

<!-- Lightbox -->
<div id="lightbox">
    <button id="lightboxClose" onclick="closeLightbox()">✕</button>
    <img id="lightboxImg" src="" alt="Full size photo">
</div>

<!-- Hidden form that submits directly to save_log.php -->
<form id="saveForm" method="post" action="save_log.php" style="display:none;">
    <input type="hidden" name="licensePlate" id="fPlate">
    <input type="hidden" name="date"         id="fDate">
    <input type="hidden" name="odometer"     id="fOdometer">
    <input type="hidden" name="pricePerGallon" id="fPrice">
    <input type="hidden" name="totalPrice"   id="fTotal">
    <input type="hidden" name="gallons"      id="fGallons">
</form>

<?php include 'menu.php'; ?>

<div class="footer">
    scan_photos.php — Rev 2.1 — Updated: <?php $mt = new DateTime('@'.filemtime(__FILE__)); $mt->setTimezone(new DateTimeZone('America/New_York')); echo $mt->format('Y-m-d h:i A T'); ?>
</div>

<script>
const photoInput = document.getElementById('photoInput');
const thumbsDiv  = document.getElementById('thumbs');
const photoCount = document.getElementById('photoCount');
const scanBtn    = document.getElementById('scanBtn');

photoInput.addEventListener('change', () => {
    thumbsDiv.innerHTML = '';
    const files = Array.from(photoInput.files);
    if (!files.length) return;

    files.forEach(file => {
        const reader = new FileReader();
        reader.onload = e => {
            const img = document.createElement('img');
            img.src = e.target.result;
            img.title = 'Tap to expand';
            img.addEventListener('click', () => openLightbox(e.target.result));
            thumbsDiv.appendChild(img);
        };
        reader.readAsDataURL(file);
    });

    photoCount.textContent = `${files.length} photo${files.length > 1 ? 's' : ''} selected`;
    photoCount.style.display = 'block';
    scanBtn.disabled = false;
    document.getElementById('review').style.display = 'none';
    document.getElementById('errorBox').style.display = 'none';
});

scanBtn.addEventListener('click', async () => {
    const files = Array.from(photoInput.files);
    if (!files.length) return;

    const formData = new FormData();
    files.forEach((f, i) => formData.append('images[]', f));

    scanBtn.disabled = true;
    document.getElementById('loadingDiv').style.display = 'block';
    document.getElementById('review').style.display = 'none';
    document.getElementById('errorBox').style.display = 'none';

    try {
        const resp = await fetch('process_photos.php', { method: 'POST', body: formData });
        if (!resp.ok) throw new Error('Server error ' + resp.status);
        const data = await resp.json();

        if (data.error) { showError(data.error); return; }

        setField('revOdometer', data.odometer);
        setField('revPrice',    data.pricePerGallon);
        setField('revTotal',    data.totalCost);
        setField('revGallons',  data.gallons);

        document.getElementById('review').style.display = 'block';
        document.getElementById('review').scrollIntoView({ behavior: 'smooth' });

    } catch (e) {
        showError('Request failed: ' + e.message);
    } finally {
        document.getElementById('loadingDiv').style.display = 'none';
        scanBtn.disabled = false;
    }
});

document.getElementById('saveBtn').addEventListener('click', () => {
    const plate = document.getElementById('revPlate').value.trim();
    if (!plate) { alert('Please enter or select a license plate.'); return; }

    const odometer = document.getElementById('revOdometer').value;
    if (!odometer) { alert('Odometer reading is required.'); return; }

    document.getElementById('fPlate').value    = plate;
    document.getElementById('fDate').value     = document.getElementById('revDate').value;
    document.getElementById('fOdometer').value = odometer;
    document.getElementById('fPrice').value    = document.getElementById('revPrice').value;
    document.getElementById('fTotal').value    = document.getElementById('revTotal').value;
    document.getElementById('fGallons').value  = document.getElementById('revGallons').value;

    document.getElementById('saveForm').submit();
});

function setField(id, val) {
    if (val === null || val === undefined || val === '') return;
    const el = document.getElementById(id);
    el.value = val;
    el.classList.add('filled');
}

function openLightbox(src) {
    document.getElementById('lightboxImg').src = src;
    document.getElementById('lightbox').classList.add('open');
}
function closeLightbox() {
    document.getElementById('lightbox').classList.remove('open');
}
// Close on backdrop tap
document.getElementById('lightbox').addEventListener('click', e => {
    if (e.target === document.getElementById('lightbox')) closeLightbox();
});

function showError(msg) {
    const el = document.getElementById('errorBox');
    el.textContent = '⚠️ ' + msg;
    el.style.display = 'block';
    el.scrollIntoView({ behavior: 'smooth' });
}
</script>

</body>
</html>
