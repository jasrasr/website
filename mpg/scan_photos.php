<?php
// ============================================================================
// File: scan_photos.php
// Purpose: Capture photos of odometer, price/gallon, and pump total for auto-fill
// Revision: 1.0
// Author: Jason Lamb
// ============================================================================

require_once __DIR__ . '/device_init.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Scan Fuel Photos</title>
<style>
* { box-sizing: border-box; }
body {
    font-family: sans-serif;
    max-width: 600px;
    margin: auto;
    padding: 1rem;
    background: #f4f4f4;
}
h2 { margin-bottom: 0.2rem; }
.subtitle { color: #666; font-size: 0.88rem; margin-bottom: 1.2rem; }

.card {
    background: white;
    border-radius: 10px;
    padding: 1rem;
    margin-bottom: 0.9rem;
    box-shadow: 0 1px 4px rgba(0,0,0,0.1);
}
.card-header {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 0.4rem;
}
.card-icon { font-size: 1.3rem; }
.card-title { font-weight: bold; font-size: 1rem; }
.card-desc { color: #666; font-size: 0.83rem; margin-bottom: 0.7rem; margin-top: 0.2rem; }

.capture-btn {
    display: block;
    width: 100%;
    padding: 0.65rem;
    background: #007bff;
    color: white;
    border: none;
    border-radius: 7px;
    cursor: pointer;
    font-size: 0.95rem;
    text-align: center;
}
.capture-btn:hover { background: #0056b3; }
input[type="file"] { display: none; }

.preview-wrap { display: none; margin-top: 0.7rem; }
.preview-wrap img {
    width: 100%;
    max-height: 170px;
    object-fit: cover;
    border-radius: 6px;
    border: 1px solid #ddd;
}
.status-ok {
    display: inline-block;
    background: #d4edda;
    color: #155724;
    font-size: 0.78rem;
    padding: 2px 8px;
    border-radius: 10px;
    margin-top: 4px;
}

#errorBox {
    display: none;
    background: #f8d7da;
    color: #721c24;
    padding: 0.7rem;
    border-radius: 7px;
    margin-bottom: 0.8rem;
    font-size: 0.9rem;
}

#scanBtn {
    display: block;
    width: 100%;
    padding: 0.85rem;
    font-size: 1.05rem;
    background: #28a745;
    color: white;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    margin-top: 0.3rem;
}
#scanBtn:hover { background: #1e7e34; }
#scanBtn:disabled { background: #999; cursor: not-allowed; }

.loading {
    display: none;
    text-align: center;
    padding: 1.5rem 1rem;
    color: #555;
}
.spinner {
    display: inline-block;
    width: 34px;
    height: 34px;
    border: 4px solid #ddd;
    border-top-color: #007bff;
    border-radius: 50%;
    animation: spin 0.75s linear infinite;
    margin-bottom: 0.5rem;
}
@keyframes spin { to { transform: rotate(360deg); } }

#results {
    display: none;
    background: white;
    border-radius: 10px;
    padding: 1.1rem;
    margin-top: 0.9rem;
    box-shadow: 0 1px 4px rgba(0,0,0,0.1);
}
#results h3 { margin: 0 0 0.3rem 0; }
#results .note { font-size: 0.82rem; color: #666; margin-bottom: 0.9rem; }

.result-row {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    margin-bottom: 0.6rem;
}
.result-row label {
    width: 150px;
    flex-shrink: 0;
    font-size: 0.88rem;
    color: #444;
}
.result-row input {
    flex: 1;
    padding: 0.42rem 0.6rem;
    border: 1px solid #ccc;
    border-radius: 5px;
    font-size: 0.95rem;
}
.result-row input.filled { border-color: #28a745; background: #f6fff8; }

#useValuesBtn {
    display: block;
    width: 100%;
    padding: 0.8rem;
    font-size: 1rem;
    background: #007bff;
    color: white;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    margin-top: 0.8rem;
}
#useValuesBtn:hover { background: #0056b3; }
</style>
</head>
<body>

<h2>📷 Scan Fuel Photos</h2>
<p class="subtitle">Take up to 3 photos at the pump — skip any you'd rather type manually.</p>

<div id="errorBox"></div>

<!-- Odometer -->
<div class="card">
    <div class="card-header">
        <span class="card-icon">🔢</span>
        <span class="card-title">Odometer Reading</span>
    </div>
    <p class="card-desc">Dashboard odometer showing total mileage (e.g. 84824.8 mi).</p>
    <button class="capture-btn" onclick="document.getElementById('inputOdometer').click()">📷 Take / Upload Photo</button>
    <input type="file" id="inputOdometer" accept="image/*" capture="environment">
    <div class="preview-wrap" id="previewOdometer">
        <img id="imgOdometer" src="" alt="Odometer preview">
        <span class="status-ok">✓ Photo added</span>
    </div>
</div>

<!-- Price per Gallon -->
<div class="card">
    <div class="card-header">
        <span class="card-icon">💲</span>
        <span class="card-title">Price per Gallon</span>
    </div>
    <p class="card-desc">Pump face showing the price (e.g. Regular $3.69 9/10).</p>
    <button class="capture-btn" onclick="document.getElementById('inputPrice').click()">📷 Take / Upload Photo</button>
    <input type="file" id="inputPrice" accept="image/*" capture="environment">
    <div class="preview-wrap" id="previewPrice">
        <img id="imgPrice" src="" alt="Price per gallon preview">
        <span class="status-ok">✓ Photo added</span>
    </div>
</div>

<!-- Pump Total & Gallons -->
<div class="card">
    <div class="card-header">
        <span class="card-icon">⛽</span>
        <span class="card-title">Pump Total & Gallons</span>
    </div>
    <p class="card-desc">Pump display showing "THIS SALE $" amount and total GALLONS dispensed.</p>
    <button class="capture-btn" onclick="document.getElementById('inputPump').click()">📷 Take / Upload Photo</button>
    <input type="file" id="inputPump" accept="image/*" capture="environment">
    <div class="preview-wrap" id="previewPump">
        <img id="imgPump" src="" alt="Pump total preview">
        <span class="status-ok">✓ Photo added</span>
    </div>
</div>

<button id="scanBtn" disabled>🔍 Extract Data from Photos</button>

<div class="loading" id="loadingDiv">
    <div class="spinner"></div>
    <div>Reading photos with AI…</div>
</div>

<div id="results">
    <h3>Extracted Values</h3>
    <p class="note">Review and correct any values before continuing.</p>
    <div class="result-row">
        <label>Odometer (mi)</label>
        <input type="number" id="resOdometer" step="0.1" placeholder="e.g. 84824.8">
    </div>
    <div class="result-row">
        <label>Price / Gallon ($)</label>
        <input type="number" id="resPricePerGallon" step="0.001" placeholder="e.g. 3.699">
    </div>
    <div class="result-row">
        <label>Total Cost ($)</label>
        <input type="number" id="resTotalCost" step="0.01" placeholder="e.g. 42.76">
    </div>
    <div class="result-row">
        <label>Gallons</label>
        <input type="number" id="resGallons" step="0.001" placeholder="e.g. 12.290">
    </div>
    <button id="useValuesBtn">✅ Use These Values → Open Entry Form</button>
</div>

<?php include 'menu.php'; ?>

<script>
const photoInputs = {
    odometer: { input: document.getElementById('inputOdometer'), preview: document.getElementById('previewOdometer'), img: document.getElementById('imgOdometer') },
    price:    { input: document.getElementById('inputPrice'),    preview: document.getElementById('previewPrice'),    img: document.getElementById('imgPrice') },
    pump:     { input: document.getElementById('inputPump'),     preview: document.getElementById('previewPump'),     img: document.getElementById('imgPump') }
};

let fileCount = 0;

Object.values(photoInputs).forEach(({ input, preview, img }) => {
    input.addEventListener('change', () => {
        const file = input.files[0];
        if (!file) return;
        const reader = new FileReader();
        reader.onload = e => {
            img.src = e.target.result;
            preview.style.display = 'block';
        };
        reader.readAsDataURL(file);
        fileCount++;
        document.getElementById('scanBtn').disabled = false;
    });
});

document.getElementById('scanBtn').addEventListener('click', async () => {
    const formData = new FormData();
    if (photoInputs.odometer.input.files[0]) formData.append('odometer', photoInputs.odometer.input.files[0]);
    if (photoInputs.price.input.files[0])    formData.append('price',    photoInputs.price.input.files[0]);
    if (photoInputs.pump.input.files[0])     formData.append('pump',     photoInputs.pump.input.files[0]);

    document.getElementById('scanBtn').disabled = true;
    document.getElementById('loadingDiv').style.display = 'block';
    document.getElementById('results').style.display = 'none';
    document.getElementById('errorBox').style.display = 'none';

    try {
        const resp = await fetch('process_photos.php', { method: 'POST', body: formData });
        if (!resp.ok) throw new Error('Server error ' + resp.status);
        const data = await resp.json();

        if (data.error) { showError(data.error); return; }

        setResult('resOdometer',      data.odometer);
        setResult('resPricePerGallon', data.pricePerGallon);
        setResult('resTotalCost',      data.totalCost);
        setResult('resGallons',        data.gallons);

        document.getElementById('results').style.display = 'block';
        document.getElementById('results').scrollIntoView({ behavior: 'smooth' });

    } catch (e) {
        showError('Request failed: ' + e.message);
    } finally {
        document.getElementById('loadingDiv').style.display = 'none';
        document.getElementById('scanBtn').disabled = false;
    }
});

function setResult(id, val) {
    const el = document.getElementById(id);
    if (val !== null && val !== undefined && val !== '') {
        el.value = val;
        el.classList.add('filled');
    }
}

document.getElementById('useValuesBtn').addEventListener('click', () => {
    const params = new URLSearchParams();
    const fields = {
        odometer:      document.getElementById('resOdometer').value,
        pricePerGallon: document.getElementById('resPricePerGallon').value,
        totalCost:     document.getElementById('resTotalCost').value,
        gallons:       document.getElementById('resGallons').value
    };
    Object.entries(fields).forEach(([k, v]) => { if (v) params.set(k, v); });
    window.location.href = 'fuel_form.php?' + params.toString();
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
