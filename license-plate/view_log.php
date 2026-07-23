<?php
require_once __DIR__ . '/config.php';
ensureAppFolders();
$entries = readLogEntries();
$counts = plateCounts($entries);
$duplicateFiles = array_filter($entries, fn($e) => !empty($e['duplicate_file']));
$duplicatePlates = array_filter($counts, fn($count) => $count > 1);
$pendingEntries = array_filter($entries, fn($e) => ($e['scan_status'] ?? '') === 'pending');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Plate Log</title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<main class="container">
    <nav class="nav">
        <a href="index.php">Upload</a>
        <a href="view_log.php">View Log</a>
    </nav>
    <h1>Plate Log</h1>

    <section class="stats-grid">
        <div class="card"><strong>Total entries</strong><span><?= count($entries) ?></span></div>
        <div class="card"><strong>Pending AI</strong><span id="pendingCount"><?= count($pendingEntries) ?></span></div>
        <div class="card"><strong>Unique plates</strong><span><?= count($counts) ?></span></div>
        <div class="card"><strong>Duplicate files</strong><span><?= count($duplicateFiles) ?></span></div>
        <div class="card"><strong>Repeated plates</strong><span><?= count($duplicatePlates) ?></span></div>
    </section>

    <?php if (!empty($pendingEntries)): ?>
    <section class="card">
        <h2>Pending Processing</h2>
        <p class="small">Retry saved photos after the configured scanner and API key are available. Files are processed one at a time to avoid web-server timeouts.</p>
        <div class="actions">
            <button id="processAllPending" type="button">Process All Pending</button>
        </div>
        <p id="pendingSummary" class="small"><?= count($pendingEntries) ?> file<?= count($pendingEntries) === 1 ? '' : 's' ?> waiting.</p>
    </section>
    <?php endif; ?>

    <?php if (!empty($duplicatePlates)): ?>
    <section class="card">
        <h2>Repeated Plate Values</h2>
        <table>
            <thead><tr><th>Plate</th><th>Count</th></tr></thead>
            <tbody>
            <?php foreach ($duplicatePlates as $plate => $count): ?>
                <tr><td><?= h($plate) ?></td><td><?= h((string)$count) ?></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </section>
    <?php endif; ?>

    <section class="card">
        <h2>Entries</h2>
        <table>
            <thead>
                <tr>
                    <th>Uploaded</th>
                    <th>Status</th>
                    <th>Plate</th>
                    <th>Confidence</th>
                    <th>Original File</th>
                    <th>Photo</th>
                    <th>Duplicate</th>
                    <th>Mode</th>
                    <th>Message</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($entries as $entry): ?>
                <?php $status = $entry['scan_status'] ?? (empty($entry['error']) ? 'complete' : 'pending'); ?>
                <tr data-entry-id="<?= h($entry['id'] ?? '') ?>" data-status="<?= h($status) ?>">
                    <td><?= h($entry['processed_at'] ?? '') ?></td>
                    <td class="entry-status"><?= h($status === 'pending' ? 'Pending AI' : 'Complete') ?></td>
                    <td class="entry-plate"><?= h($entry['plate'] ?? '') ?></td>
                    <td class="entry-confidence"><?= h((string)($entry['confidence'] ?? '')) ?></td>
                    <td><?= h($entry['original_file'] ?? '') ?></td>
                    <td>
                        <?php if (!empty($entry['stored_file'])): ?>
                            <a href="uploads/<?= h($entry['stored_file']) ?>">photo</a>
                        <?php endif; ?>
                    </td>
                    <td class="entry-duplicate">
                        <?php
                        $parts = [];
                        if (!empty($entry['duplicate_file'])) $parts[] = 'same file';
                        if (!empty($entry['duplicate_plate'])) $parts[] = 'same plate';
                        echo h(implode(', ', $parts));
                        ?>
                    </td>
                    <td><?= h($entry['scan_mode'] ?? '') ?></td>
                    <td class="entry-message"><?= h($entry['error'] ?? '') ?></td>
                    <td>
                        <?php if ($status === 'pending'): ?>
                            <button type="button" class="retry-pending" data-id="<?= h($entry['id'] ?? '') ?>">Retry</button>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </section>
</main>
<script>
const retryButtons = Array.from(document.querySelectorAll('.retry-pending'));
const processAllButton = document.getElementById('processAllPending');
const pendingSummary = document.getElementById('pendingSummary');
const pendingCount = document.getElementById('pendingCount');

async function retryEntry(id, button) {
    const row = document.querySelector(`tr[data-entry-id="${CSS.escape(id)}"]`);
    if (!row) return false;

    button.disabled = true;
    button.textContent = 'Processing...';
    row.querySelector('.entry-message').textContent = 'Processing saved photo...';

    try {
        const response = await fetch('process_pending.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({id})
        });
        const data = await response.json();

        if (!response.ok || data.error) {
            row.querySelector('.entry-status').textContent = 'Pending AI';
            row.querySelector('.entry-message').textContent = data.error || `HTTP ${response.status}`;
            button.disabled = false;
            button.textContent = 'Retry';
            return false;
        }

        row.dataset.status = 'complete';
        row.querySelector('.entry-status').textContent = 'Complete';
        row.querySelector('.entry-plate').textContent = data.plate || '';
        row.querySelector('.entry-confidence').textContent = data.confidence ?? '';
        row.querySelector('.entry-message').textContent = '';
        row.querySelector('.entry-duplicate').textContent = data.duplicate_plate ? `same plate (${data.plate_count})` : '';
        button.remove();
        updatePendingCount();
        return true;
    } catch (error) {
        row.querySelector('.entry-message').textContent = 'Request failed: ' + error.message;
        button.disabled = false;
        button.textContent = 'Retry';
        return false;
    }
}

function updatePendingCount() {
    const remaining = document.querySelectorAll('tr[data-status="pending"]').length;
    if (pendingCount) pendingCount.textContent = remaining;
    if (pendingSummary) pendingSummary.textContent = `${remaining} file${remaining === 1 ? '' : 's'} waiting.`;
    if (processAllButton) processAllButton.disabled = remaining === 0;
}

retryButtons.forEach(button => {
    button.addEventListener('click', () => retryEntry(button.dataset.id, button));
});

if (processAllButton) {
    processAllButton.addEventListener('click', async () => {
        processAllButton.disabled = true;
        const buttons = Array.from(document.querySelectorAll('.retry-pending'));
        let completed = 0;
        let stillPending = 0;

        for (let i = 0; i < buttons.length; i++) {
            if (pendingSummary) pendingSummary.textContent = `${i + 1} of ${buttons.length} processing...`;
            const success = await retryEntry(buttons[i].dataset.id, buttons[i]);
            success ? completed++ : stillPending++;
        }

        if (pendingSummary) pendingSummary.textContent = `Completed: ${completed}. Still pending: ${stillPending}.`;
        processAllButton.disabled = stillPending === 0;
    });
}
</script>
</body>
</html>
