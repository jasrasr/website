<?php
require_once __DIR__ . '/config.php';
ensureAppFolders();
$entries = readLogEntries();
$counts = plateCounts($entries);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= h(APP_NAME) ?></title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<main class="container">
    <nav class="nav">
        <a href="index.php">Upload</a>
        <a href="view_log.php">View Log</a>
    </nav>

    <header class="page-header">
        <div>
            <h1><?= h(APP_NAME) ?></h1>
            <p class="small">Batch upload plate photos. Each file is hashed, scanned, logged, and checked for duplicate files and repeated plate values.</p>
        </div>
        <div class="status-box">
            <strong>Mode:</strong> <?= h(SCAN_MODE) ?><br>
            <strong>Entries:</strong> <?= count($entries) ?><br>
            <strong>Unique plates:</strong> <?= count($counts) ?>
        </div>
    </header>

    <section class="card">
        <label for="photos">License plate photos</label>
        <input type="file" id="photos" accept="image/jpeg,image/png,image/webp,image/heic,image/heif" multiple>
        <div class="actions">
            <button id="startBtn" type="button" disabled>Process Selected Photos</button>
            <button id="clearBtn" type="button" class="secondary">Clear Queue</button>
        </div>
        <div id="progress" class="progress" hidden>
            <div id="progressBar"></div>
        </div>
        <p id="summary" class="small">No files selected.</p>
    </section>

    <section class="card">
        <h2>Batch Results</h2>
        <table>
            <thead>
                <tr>
                    <th>File</th>
                    <th>Plate</th>
                    <th>Confidence</th>
                    <th>Status</th>
                    <th>Duplicate</th>
                </tr>
            </thead>
            <tbody id="results">
                <tr><td colspan="5" class="small">Results will appear as each photo finishes.</td></tr>
            </tbody>
        </table>
    </section>
</main>

<script>
const input = document.getElementById('photos');
const startBtn = document.getElementById('startBtn');
const clearBtn = document.getElementById('clearBtn');
const summary = document.getElementById('summary');
const results = document.getElementById('results');
const progress = document.getElementById('progress');
const progressBar = document.getElementById('progressBar');
let queue = [];

input.addEventListener('change', () => {
    queue = Array.from(input.files || []);
    startBtn.disabled = queue.length === 0;
    summary.textContent = queue.length ? `${queue.length} file${queue.length === 1 ? '' : 's'} selected.` : 'No files selected.';
});

clearBtn.addEventListener('click', () => {
    input.value = '';
    queue = [];
    startBtn.disabled = true;
    summary.textContent = 'No files selected.';
    results.innerHTML = '<tr><td colspan="5" class="small">Results will appear as each photo finishes.</td></tr>';
    progress.hidden = true;
    progressBar.style.width = '0%';
});

startBtn.addEventListener('click', async () => {
    if (!queue.length) return;
    startBtn.disabled = true;
    results.innerHTML = '';
    progress.hidden = false;
    let ok = 0;
    let dupes = 0;
    let failed = 0;

    for (let i = 0; i < queue.length; i++) {
        const file = queue[i];
        const row = addRow(file.name, 'Scanning...', '', 'Working', '');
        const form = new FormData();
        form.append('photo', file);

        try {
            const resp = await fetch('process_upload.php', { method: 'POST', body: form });
            const data = await resp.json();
            if (!resp.ok || data.error) {
                failed++;
                updateRow(row, file.name, data.plate || '', data.confidence || '', data.error || `HTTP ${resp.status}`, '');
            } else {
                ok++;
                if (data.duplicate_file || data.duplicate_plate) dupes++;
                updateRow(row, file.name, data.plate || '', data.confidence || '', data.status || 'Logged', duplicateText(data));
            }
        } catch (e) {
            failed++;
            updateRow(row, file.name, '', '', 'Request failed: ' + e.message, '');
        }

        const pct = Math.round(((i + 1) / queue.length) * 100);
        progressBar.style.width = pct + '%';
        summary.textContent = `${i + 1} of ${queue.length} processed. Logged: ${ok}. Duplicates flagged: ${dupes}. Failed: ${failed}.`;
    }

    startBtn.disabled = false;
});

function addRow(file, plate, confidence, status, duplicate) {
    const row = document.createElement('tr');
    row.innerHTML = '<td></td><td></td><td></td><td></td><td></td>';
    results.appendChild(row);
    updateRow(row, file, plate, confidence, status, duplicate);
    return row;
}

function updateRow(row, file, plate, confidence, status, duplicate) {
    row.children[0].textContent = file;
    row.children[1].textContent = plate;
    row.children[2].textContent = confidence;
    row.children[3].textContent = status;
    row.children[4].textContent = duplicate;
}

function duplicateText(data) {
    const parts = [];
    if (data.duplicate_file) parts.push('same file');
    if (data.duplicate_plate) parts.push(`plate seen ${data.plate_count} times`);
    return parts.join(', ');
}
</script>
</body>
</html>
