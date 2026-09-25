<?php
/*
    License Plate Photo Logger
    Revision: 1.2.33
    Description: Front-page upload queue with resilient Safari/mobile uploads, explicit endpoint resolution, transient request retry, failed-file retry queue, latest-upload preview, and project status/navigation.
*/
require_once __DIR__ . '/config.php';
ensureAppFolders();
$entries = readLogEntries();
$counts = plateCounts($entries);
$pendingEntries = array_filter($entries, fn($entry) => ($entry['scan_status'] ?? '') === 'pending');
$latestUploadEntry = null;
foreach ($entries as $entry) {
    if (!empty($entry['stored_file'])) {
        $latestUploadEntry = $entry;
        break;
    }
}
$projectRevision = readProjectRevision();
$projectModifiedAt = readProjectModifiedAt();
$styleVersion = rawurlencode($projectRevision);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= h(APP_NAME) ?></title>
<link rel="stylesheet" href="style.css?v=<?= h($styleVersion) ?>">
</head>
<body>
<main class="container container-wide">
    <aside class="project-badge">
        <strong>Project Rev:</strong> <?= h($projectRevision) ?><br>
        <strong>Modified:</strong> <?= h($projectModifiedAt) ?>
    </aside>
    <nav class="nav">
        <a href="index.php">Upload</a>
        <a href="view_log.php">View Log</a>
        <a href="stats.php">Stats</a>
        <a href="deleted_audit.php">Deleted</a>
        <a href="changelog.php">Changelog</a>
    </nav>

    <header class="page-header">
        <div>
            <h1><?= h(APP_NAME) ?></h1>
            <p class="small">Batch upload plate photos. Each file is hashed, saved, scanned when available, and checked for duplicate files and repeated plate values.</p>
        </div>
        <div class="status-box">
            <strong>Mode:</strong> <?= h(scanModeLabel(SCAN_MODE)) ?><br>
            <strong>Entries:</strong> <?= count($entries) ?><br>
            <strong>Pending:</strong> <?= count($pendingEntries) ?><br>
            <strong>Unique plates:</strong> <?= count($counts) ?>
        </div>
    </header>

    <?php if ($latestUploadEntry !== null): ?>
    <section class="card latest-upload-card">
        <div class="latest-upload-preview">
            <img
                src="uploads/<?= h((string)$latestUploadEntry['stored_file']) ?>"
                alt="<?= h((string)($latestUploadEntry['original_file'] ?? 'Latest uploaded plate photo')) ?>"
            >
        </div>
        <div>
            <h2>Last Uploaded Photo</h2>
            <div class="latest-upload-meta">
                <strong>Uploaded</strong>
                <span><?= h(displayEasternDateTime((string)($latestUploadEntry['processed_at'] ?? ''))) ?></span>
                <strong>Plate</strong>
                <span><?= h((string)($latestUploadEntry['plate'] ?? '')) ?: 'Not yet read' ?></span>
                <strong>Original File</strong>
                <span><?= h((string)($latestUploadEntry['original_file'] ?? '')) ?></span>
                <strong>Scanner</strong>
                <span><?= h(entryScannerLabel($latestUploadEntry)) ?></span>
                <strong>Status</strong>
                <span><?= h((string)($latestUploadEntry['scan_status'] ?? 'complete')) ?></span>
            </div>
        </div>
    </section>
    <?php endif; ?>

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
        <div class="table-wrap">
        <table class="results-table">
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
        </div>
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
const processUploadUrl = new URL('./process_upload.php', window.location.href).href;
let queue = [];

function wait(ms) {
    return new Promise(resolve => setTimeout(resolve, ms));
}

async function uploadPhoto(form, maxAttempts = 2) {
    let lastError = null;

    for (let attempt = 1; attempt <= maxAttempts; attempt++) {
        try {
            const resp = await fetch(processUploadUrl, {
                method: 'POST',
                body: form,
                cache: 'no-store',
                credentials: 'same-origin',
            });

            const responseText = await resp.text();
            let data = {};

            if (responseText !== '') {
                try {
                    data = JSON.parse(responseText);
                } catch (parseError) {
                    const preview = responseText.replace(/\s+/g, ' ').trim().slice(0, 180);
                    throw new Error(`Server returned an invalid response (HTTP ${resp.status})${preview ? ': ' + preview : ''}`);
                }
            }

            return { resp, data };
        } catch (error) {
            lastError = error;
            if (attempt < maxAttempts) {
                await wait(750);
            }
        }
    }

    throw lastError || new Error('Upload request failed.');
}

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
    clearBtn.disabled = true;
    results.innerHTML = '';
    progress.hidden = false;
    let logged = 0;
    let pending = 0;
    let dupes = 0;
    let failed = 0;
    const failedFiles = [];
    const batchQueue = [...queue];

    for (let i = 0; i < batchQueue.length; i++) {
        const file = queue[i];
        const row = addRow(file.name, 'Scanning...', '', 'Working', '');
        const form = new FormData();
        form.append('photo', file);

        try {
            const { resp, data } = await uploadPhoto(form);
            if (!resp.ok || data.error) {
                failed++;
                updateRow(row, file.name, data.plate || '', data.confidence || '', data.error || `HTTP ${resp.status}`, '');
            } else {
                if (data.pending) {
                    pending++;
                } else {
                    logged++;
                }
                if (data.duplicate_file || data.duplicate_plate) dupes++;
                const status = data.pending && data.scan_error
                    ? `${data.status}: ${data.scan_error}`
                    : (data.status || 'Logged');
                updateRow(row, file.name, data.plate || '', data.confidence || '', status, duplicateText(data));
            }
        } catch (e) {
            failed++;
            failedFiles.push(file);
            updateRow(row, file.name, '', '', 'Upload request failed: ' + (e?.message || 'Unknown browser/network error'), '');
        }

        const pct = Math.round(((i + 1) / batchQueue.length) * 100);
        progressBar.style.width = pct + '%';
        summary.textContent = `${i + 1} of ${batchQueue.length} processed. Logged: ${logged}. Pending processing: ${pending}. Duplicates flagged: ${dupes}. Failed uploads: ${failed}.`;
    }

    clearBtn.disabled = false;

    if (failedFiles.length > 0) {
        queue = failedFiles;
        startBtn.disabled = false;
        startBtn.textContent = `Retry Failed Uploads (${failedFiles.length})`;
        summary.textContent = `Batch complete. Logged: ${logged}. Pending processing: ${pending}. Duplicates flagged: ${dupes}. Failed uploads: ${failed}. The failed files are still queued; tap Retry Failed Uploads to retry only those files.`;
    } else {
        input.value = '';
        queue = [];
        startBtn.disabled = true;
        startBtn.textContent = 'Process Selected Photos';
        summary.textContent = `Batch complete. Logged: ${logged}. Pending processing: ${pending}. Duplicates flagged: ${dupes}. Failed uploads: 0. Choose new files to process another batch.`;
    }
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
    if (data.best_plate_photo && data.duplicate_plate) parts.push('clearest photo');
    return parts.join(', ');
}
</script>
</body>
</html>
