<?php
/*
    License Plate Photo Logger
    Revision: 1.2.5
    Description: Log viewer with wider desktop layout, active stat filters, and clearer multi-retry controls.
*/
require_once __DIR__ . '/config.php';
ensureAppFolders();
$entries = readLogEntries();
$counts = plateCounts($entries);
$duplicateFiles = array_filter($entries, fn($e) => !empty($e['duplicate_file']));
$duplicatePlates = array_filter($counts, fn($count) => $count > 1);
$pendingEntries = array_filter($entries, fn($e) => ($e['scan_status'] ?? '') === 'pending');
$failedPendingEntries = array_filter($pendingEntries, fn($e) => !empty($e['error']));
$metadataEntries = array_filter($entries, fn($e) => !empty($e['date_taken']) || !empty($e['gps_display']) || !empty($e['plate_state']) || !empty($e['photo_state']));
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
<main class="container container-wide">
    <nav class="nav">
        <a href="index.php">Upload</a>
        <a href="view_log.php">View Log</a>
        <a href="changelog.php">Changelog</a>
    </nav>
    <h1>Plate Log</h1>

    <section class="stats-grid stats-grid-compact">
        <button type="button" class="card stat-card is-active" data-filter-mode="all" onclick="setFilterMode('all')"><strong>Total entries</strong><span><?= count($entries) ?></span></button>
        <button type="button" class="card stat-card" data-filter-mode="pending" onclick="setFilterMode('pending')"><strong>Pending Processing</strong><span id="pendingCount"><?= count($pendingEntries) ?></span></button>
        <button type="button" class="card stat-card" data-filter-mode="unique" onclick="setFilterMode('unique')"><strong>Unique plates</strong><span><?= count($counts) ?></span></button>
        <button type="button" class="card stat-card" data-filter-mode="duplicate-file" onclick="setFilterMode('duplicate-file')"><strong>Duplicate files</strong><span><?= count($duplicateFiles) ?></span></button>
        <button type="button" class="card stat-card" data-filter-mode="duplicate-plate" onclick="setFilterMode('duplicate-plate')"><strong>Duplicate Plates</strong><span><?= count($duplicatePlates) ?></span></button>
        <button type="button" class="card stat-card" data-filter-mode="metadata" onclick="setFilterMode('metadata')"><strong>With Metadata</strong><span id="metadataCount"><?= count($metadataEntries) ?></span></button>
    </section>

    <?php if (!empty($pendingEntries)): ?>
    <section class="card" id="pendingSection">
        <h2>Pending Processing</h2>
        <p class="small">Use the first-column checkboxes to pick several failed rows, or click <strong>Select All Failed</strong> to retry every prior error in one click. Files are retried one at a time to avoid web-server timeouts.</p>
        <div class="actions">
            <?php if (!empty($failedPendingEntries)): ?>
                <button id="retrySelectedPending" type="button" class="secondary">Retry Selected Failed</button>
                <button id="selectAllFailedPending" type="button" class="secondary">Select All Failed</button>
            <?php endif; ?>
            <button id="processAllPending" type="button">Process All Pending</button>
        </div>
        <p id="pendingSummary" class="small"><?= count($pendingEntries) ?> file<?= count($pendingEntries) === 1 ? '' : 's' ?> waiting.<?php if (!empty($failedPendingEntries)): ?> <?= count($failedPendingEntries) ?> previously failed.<?php endif; ?></p>
    </section>
    <?php endif; ?>

    <?php if (!empty($duplicatePlates)): ?>
    <section class="card" id="duplicatePlatesSection">
        <h2>Duplicate Plates</h2>
        <p class="small">Click a plate value to filter the entry table to that plate.</p>
        <table>
            <thead><tr><th>Plate</th><th>Count</th></tr></thead>
            <tbody>
            <?php foreach ($duplicatePlates as $plate => $count): ?>
                <tr>
                    <td><button type="button" class="link-button duplicate-plate-filter" data-plate="<?= h(strtolower($plate)) ?>"><?= h($plate) ?></button></td>
                    <td><?= h((string)$count) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </section>
    <?php endif; ?>

    <section class="card">
        <h2>Entries</h2>
        <div class="filters">
            <label class="filter-field" for="entrySearch">Search Entries</label>
            <input type="search" id="entrySearch" placeholder="Search plate, state, GPS, date taken, file, message">
            <p id="entryFilterStatus" class="small filter-status">Showing all entries.</p>
        </div>
        <div class="table-wrap">
        <table id="entriesTable" class="sortable-table log-table">
            <thead>
                <tr>
                    <th class="col-select"><button type="button" class="sort-button" data-sort-type="text">Select</button></th>
                    <th class="col-uploaded"><button type="button" class="sort-button" data-sort-type="date">Uploaded</button></th>
                    <th class="col-status"><button type="button" class="sort-button" data-sort-type="text">Status</button></th>
                    <th class="col-plate"><button type="button" class="sort-button" data-sort-type="text">Plate</button></th>
                    <th class="col-confidence"><button type="button" class="sort-button" data-sort-type="number">Confidence</button></th>
                    <th class="col-clarity"><button type="button" class="sort-button" data-sort-type="number">Clarity</button></th>
                    <th class="col-state"><button type="button" class="sort-button" data-sort-type="text">State</button></th>
                    <th class="col-date"><button type="button" class="sort-button" data-sort-type="date">Date Taken</button></th>
                    <th class="col-gps"><button type="button" class="sort-button" data-sort-type="text">GPS</button></th>
                    <th class="col-file"><button type="button" class="sort-button" data-sort-type="text">Original File</button></th>
                    <th class="col-photo"><button type="button" class="sort-button" data-sort-type="text">Photo</button></th>
                    <th class="col-duplicate"><button type="button" class="sort-button" data-sort-type="text">Duplicate</button></th>
                    <th class="col-scanner"><button type="button" class="sort-button" data-sort-type="text">Scanner</button></th>
                    <th class="col-message"><button type="button" class="sort-button" data-sort-type="text">Message</button></th>
                    <th class="col-action"><button type="button" class="sort-button" data-sort-type="text">Action</button></th>
                </tr>
            </thead>
            <tbody id="entriesTableBody">
            <?php foreach ($entries as $entry): ?>
                <?php
                $status = $entry['scan_status'] ?? (empty($entry['error']) ? 'complete' : 'pending');
                $isFailedPending = $status === 'pending' && !empty($entry['error']);
                $stateDisplay = (string)($entry['plate_state'] ?? '');
                if ($stateDisplay === '') {
                    $stateDisplay = (string)($entry['photo_state'] ?? '');
                }
                $dateTakenDisplay = displayDateTime((string)($entry['date_taken'] ?? ''));
                $searchBlob = strtolower(implode(' ', array_filter([
                    (string)($entry['plate'] ?? ''),
                    $stateDisplay,
                    (string)($entry['photo_state'] ?? ''),
                    $dateTakenDisplay,
                    (string)($entry['gps_display'] ?? ''),
                    (string)($entry['original_file'] ?? ''),
                    (string)($entry['error'] ?? ''),
                    scanModeLabel((string)($entry['scan_mode'] ?? '')),
                ])));
                ?>
                <tr
                    data-entry-id="<?= h($entry['id'] ?? '') ?>"
                    data-status="<?= h($status) ?>"
                    data-failed-pending="<?= $isFailedPending ? 'true' : 'false' ?>"
                    data-duplicate-file="<?= !empty($entry['duplicate_file']) ? 'true' : 'false' ?>"
                    data-duplicate-plate="<?= !empty($entry['duplicate_plate']) ? 'true' : 'false' ?>"
                    data-has-metadata="<?= (!empty($entry['date_taken']) || !empty($entry['gps_display']) || $stateDisplay !== '') ? 'true' : 'false' ?>"
                    data-plate-value="<?= h(strtolower((string)($entry['plate'] ?? ''))) ?>"
                    data-search="<?= h($searchBlob) ?>"
                >
                    <td>
                        <?php if ($isFailedPending): ?>
                            <input type="checkbox" class="retry-select" data-id="<?= h($entry['id'] ?? '') ?>" aria-label="Select failed entry <?= h($entry['original_file'] ?? ($entry['id'] ?? '')) ?>">
                        <?php endif; ?>
                    </td>
                    <td><?= h($entry['processed_at'] ?? '') ?></td>
                    <td class="entry-status"><?= h($status === 'pending' ? 'Pending Processing' : 'Complete') ?></td>
                    <td class="entry-plate"><?= h($entry['plate'] ?? '') ?></td>
                    <td class="entry-confidence"><?= h(isset($entry['confidence']) && $entry['confidence'] !== '' ? ((string)$entry['confidence'] . '%') : '') ?></td>
                    <td class="entry-clarity"><?= h((string)($entry['clarity_score'] ?? '')) ?></td>
                    <td class="entry-state"><?= h($stateDisplay) ?></td>
                    <td class="entry-date-taken"><?= h($dateTakenDisplay) ?></td>
                    <td class="entry-gps"><?= h((string)($entry['gps_display'] ?? '')) ?></td>
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
                        if (!empty($entry['duplicate_plate']) && !empty($entry['best_plate_photo'])) $parts[] = 'clearest photo';
                        echo h(implode(', ', $parts));
                        ?>
                    </td>
                    <td><?= h(scanModeLabel((string)($entry['scan_mode'] ?? ''))) ?></td>
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
        </div>
    </section>
</main>
<script>
const retryButtons = Array.from(document.querySelectorAll('.retry-pending'));
const processAllButton = document.getElementById('processAllPending');
const retrySelectedButton = document.getElementById('retrySelectedPending');
const selectAllFailedButton = document.getElementById('selectAllFailedPending');
const pendingSummary = document.getElementById('pendingSummary');
const pendingCount = document.getElementById('pendingCount');
const metadataCount = document.getElementById('metadataCount');
const entrySearch = document.getElementById('entrySearch');
const entryFilterStatus = document.getElementById('entryFilterStatus');
const selectionBoxes = Array.from(document.querySelectorAll('.retry-select'));
const entriesTableBody = document.getElementById('entriesTableBody');
const sortButtons = Array.from(document.querySelectorAll('.sort-button'));
const statCards = Array.from(document.querySelectorAll('.stat-card'));
const duplicatePlateFilters = Array.from(document.querySelectorAll('.duplicate-plate-filter'));
let activeFilterMode = 'all';
let activePlateFilter = '';

function getSelectedFailedButtons() {
    return selectionBoxes
        .filter(box => box.checked)
        .map(box => document.querySelector(`.retry-pending[data-id="${CSS.escape(box.dataset.id)}"]`))
        .filter(Boolean);
}

function updateFailedSelectionState() {
    if (!retrySelectedButton) return;
    const selectedCount = selectionBoxes.filter(box => box.checked).length;
    retrySelectedButton.disabled = selectedCount === 0;
    retrySelectedButton.textContent = selectedCount > 0 ? `Retry Selected Failed (${selectedCount})` : 'Retry Selected Failed';

    if (selectAllFailedButton) {
        const activeBoxes = selectionBoxes.filter(box => !box.disabled);
        const allSelected = activeBoxes.length > 0 && activeBoxes.every(box => box.checked);
        selectAllFailedButton.textContent = allSelected ? 'Clear Failed Selection' : 'Select All Failed';
        selectAllFailedButton.disabled = activeBoxes.length === 0;
    }
}

function visibleRows() {
    return Array.from(entriesTableBody.querySelectorAll('tr')).filter(row => row.style.display !== 'none');
}

function updatePendingCount() {
    const rows = visibleRows();
    const remaining = rows.filter(row => row.dataset.status === 'pending').length;
    const failedRemaining = rows.filter(row => row.dataset.status === 'pending' && row.dataset.failedPending === 'true').length;
    const visibleMetadata = rows.filter(row => row.dataset.hasMetadata === 'true').length;

    if (pendingCount) pendingCount.textContent = remaining;
    if (metadataCount) metadataCount.textContent = visibleMetadata;
    if (pendingSummary) pendingSummary.textContent = `${remaining} file${remaining === 1 ? '' : 's'} waiting.${failedRemaining ? ` ${failedRemaining} previously failed.` : ''}`;
    if (processAllButton) processAllButton.disabled = remaining === 0;
}

function refreshRowSearch(row) {
    row.dataset.search = [
        row.querySelector('.entry-plate')?.textContent || '',
        row.querySelector('.entry-clarity')?.textContent || '',
        row.querySelector('.entry-state')?.textContent || '',
        row.querySelector('.entry-date-taken')?.textContent || '',
        row.querySelector('.entry-gps')?.textContent || '',
        row.cells[9]?.textContent || '',
        row.cells[12]?.textContent || '',
        row.querySelector('.entry-message')?.textContent || ''
    ].join(' ').toLowerCase();
}

function applyEntrySearch() {
    const term = (entrySearch?.value || '').trim().toLowerCase();
    Array.from(entriesTableBody.querySelectorAll('tr')).forEach(row => {
        const modeMatch =
            activeFilterMode === 'all' ||
            (activeFilterMode === 'pending' && row.dataset.status === 'pending') ||
            (activeFilterMode === 'duplicate-file' && row.dataset.duplicateFile === 'true') ||
            (activeFilterMode === 'duplicate-plate' && row.dataset.duplicatePlate === 'true') ||
            (activeFilterMode === 'metadata' && row.dataset.hasMetadata === 'true') ||
            (activeFilterMode === 'unique' && row.dataset.status === 'complete' && row.dataset.duplicatePlate !== 'true');
        const plateMatch = activePlateFilter === '' || row.dataset.plateValue === activePlateFilter;
        const searchMatch = term === '' || (row.dataset.search || '').includes(term);
        row.style.display = modeMatch && plateMatch && searchMatch ? '' : 'none';
    });

    const labels = [];
    if (activeFilterMode !== 'all') {
        labels.push(activeFilterMode === 'duplicate-plate' ? 'duplicate plates' : activeFilterMode.replace('-', ' '));
    }
    if (activePlateFilter) labels.push(`plate ${activePlateFilter.toUpperCase()}`);
    if (term) labels.push(`search "${term}"`);
    if (entryFilterStatus) entryFilterStatus.textContent = labels.length ? `Filtered by ${labels.join(', ')}.` : 'Showing all entries.';
    updatePendingCount();
}

function setFilterMode(mode) {
    activeFilterMode = mode;
    if (mode !== 'duplicate-plate') activePlateFilter = '';
    updateStatCardState();
    applyEntrySearch();
}

function updateStatCardState() {
    statCards.forEach(card => {
        const isActive = (card.dataset.filterMode || 'all') === activeFilterMode && activePlateFilter === '';
        card.classList.toggle('is-active', isActive);
        card.setAttribute('aria-pressed', isActive ? 'true' : 'false');
    });
}

async function retryEntry(id, button) {
    const row = document.querySelector(`tr[data-entry-id="${CSS.escape(id)}"]`);
    if (!row) return false;
    const selectionBox = row.querySelector('.retry-select');

    button.disabled = true;
    button.textContent = 'Processing...';
    if (selectionBox) selectionBox.disabled = true;
    row.querySelector('.entry-message').textContent = 'Processing saved photo...';

    try {
        const response = await fetch('process_pending.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({id})
        });
        const data = await response.json();

        if (!response.ok || data.error) {
            row.querySelector('.entry-status').textContent = 'Pending Processing';
            row.querySelector('.entry-message').textContent = data.error || `HTTP ${response.status}`;
            button.disabled = false;
            button.textContent = 'Retry';
            if (selectionBox) selectionBox.disabled = false;
            refreshRowSearch(row);
            updateFailedSelectionState();
            return false;
        }

        row.dataset.status = 'complete';
        row.dataset.failedPending = 'false';
        row.querySelector('.entry-status').textContent = 'Complete';
        row.querySelector('.entry-plate').textContent = data.plate || '';
        row.querySelector('.entry-confidence').textContent = data.confidence !== undefined && data.confidence !== null && data.confidence !== '' ? `${data.confidence}%` : '';
        const clarityCell = row.querySelector('.entry-clarity');
        if (clarityCell) clarityCell.textContent = data.clarity_score ?? clarityCell.textContent ?? '';
        const stateCell = row.querySelector('.entry-state');
        if (stateCell) stateCell.textContent = data.plate_state || stateCell.textContent || '';
        row.querySelector('.entry-message').textContent = '';
        row.querySelector('.entry-duplicate').textContent = data.duplicate_plate
            ? `same plate (${data.plate_count})${data.best_plate_photo ? ', clearest photo' : ''}`
            : '';
        refreshRowSearch(row);
        if (selectionBox) {
            selectionBox.checked = false;
            selectionBox.disabled = true;
        }
        button.remove();
        updatePendingCount();
        updateFailedSelectionState();
        return true;
    } catch (error) {
        row.querySelector('.entry-message').textContent = 'Request failed: ' + error.message;
        button.disabled = false;
        button.textContent = 'Retry';
        if (selectionBox) selectionBox.disabled = false;
        refreshRowSearch(row);
        updateFailedSelectionState();
        return false;
    }
}

function normalizeSortValue(value, type) {
    const trimmed = value.trim().toLowerCase();
    if (type === 'number') {
        const parsed = Number.parseFloat(trimmed);
        return Number.isNaN(parsed) ? Number.NEGATIVE_INFINITY : parsed;
    }
    if (type === 'date') {
        const parsed = Date.parse(trimmed);
        return Number.isNaN(parsed) ? Number.NEGATIVE_INFINITY : parsed;
    }
    return trimmed;
}

function getCellSortValue(row, columnIndex) {
    const cell = row.cells[columnIndex];
    if (!cell) return '';

    const checkbox = cell.querySelector('input[type="checkbox"]');
    if (checkbox) return checkbox.checked ? 'selected' : '';

    const button = cell.querySelector('button');
    if (button) return button.textContent || '';

    const link = cell.querySelector('a');
    if (link) return link.textContent || '';

    return cell.textContent || '';
}

function sortEntries(columnIndex, type, headerButton) {
    const rows = Array.from(entriesTableBody.querySelectorAll('tr'));
    const currentDirection = headerButton.dataset.sortDirection === 'asc' ? 'desc' : 'asc';

    rows.sort((rowA, rowB) => {
        const valueA = normalizeSortValue(getCellSortValue(rowA, columnIndex), type);
        const valueB = normalizeSortValue(getCellSortValue(rowB, columnIndex), type);
        if (valueA < valueB) return currentDirection === 'asc' ? -1 : 1;
        if (valueA > valueB) return currentDirection === 'asc' ? 1 : -1;
        return 0;
    });

    rows.forEach(row => entriesTableBody.appendChild(row));

    sortButtons.forEach(button => {
        button.dataset.sortDirection = '';
        button.removeAttribute('aria-sort');
        button.classList.remove('sort-asc', 'sort-desc');
    });

    headerButton.dataset.sortDirection = currentDirection;
    headerButton.setAttribute('aria-sort', currentDirection === 'asc' ? 'ascending' : 'descending');
    headerButton.classList.add(currentDirection === 'asc' ? 'sort-asc' : 'sort-desc');
}

selectionBoxes.forEach(box => {
    box.addEventListener('change', updateFailedSelectionState);
});

sortButtons.forEach(button => {
    button.addEventListener('click', () => {
        const headerCell = button.closest('th');
        if (!headerCell) return;
        sortEntries(headerCell.cellIndex, button.dataset.sortType || 'text', button);
    });
});

statCards.forEach(card => {
    card.addEventListener('click', () => setFilterMode(card.dataset.filterMode || 'all'));
});

duplicatePlateFilters.forEach(button => {
    button.addEventListener('click', () => {
        activeFilterMode = 'duplicate-plate';
        activePlateFilter = button.dataset.plate || '';
        updateStatCardState();
        applyEntrySearch();
        document.getElementById('entriesTable')?.scrollIntoView({behavior: 'smooth', block: 'start'});
    });
});

retryButtons.forEach(button => {
    button.addEventListener('click', () => retryEntry(button.dataset.id, button));
});

if (entrySearch) {
    entrySearch.addEventListener('input', applyEntrySearch);
}

if (selectAllFailedButton) {
    selectAllFailedButton.addEventListener('click', () => {
        const activeBoxes = selectionBoxes.filter(box => !box.disabled);
        const allSelected = activeBoxes.length > 0 && activeBoxes.every(box => box.checked);
        activeBoxes.forEach(box => {
            box.checked = !allSelected;
        });
        updateFailedSelectionState();
    });
}

if (retrySelectedButton) {
    retrySelectedButton.addEventListener('click', async () => {
        const buttons = getSelectedFailedButtons();
        if (!buttons.length) {
            updateFailedSelectionState();
            return;
        }

        retrySelectedButton.disabled = true;
        if (processAllButton) processAllButton.disabled = true;
        if (selectAllFailedButton) selectAllFailedButton.disabled = true;

        let completed = 0;
        let stillPending = 0;

        for (let i = 0; i < buttons.length; i++) {
            if (pendingSummary) pendingSummary.textContent = `Retrying selected failed ${i + 1} of ${buttons.length}...`;
            const success = await retryEntry(buttons[i].dataset.id, buttons[i]);
            success ? completed++ : stillPending++;
        }

        if (pendingSummary) pendingSummary.textContent = `Selected retries completed: ${completed}. Still pending: ${stillPending}.`;
        updatePendingCount();
        updateFailedSelectionState();
    });
}

if (processAllButton) {
    processAllButton.addEventListener('click', async () => {
        processAllButton.disabled = true;
        if (retrySelectedButton) retrySelectedButton.disabled = true;
        if (selectAllFailedButton) selectAllFailedButton.disabled = true;
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
        updateFailedSelectionState();
    });
}

updatePendingCount();
updateFailedSelectionState();
updateStatCardState();
applyEntrySearch();
</script>
</body>
</html>
