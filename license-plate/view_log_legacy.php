<?php
/*
    License Plate Photo Logger
    Revision: 1.2.30
    Description: Log viewer with immediate live search updates, a denser desktop table, `More Info` metadata overlay, live duplicate summary refresh, bulk reprocessing, visible stat filters, Eastern timestamp display, uploaded-date deep links, project revision badge, cache-busted stylesheet loading, stable overlay behavior, manual correction tools, quick delete reasons, multi-delete actions, favorites/ranking, and reliable photo preview behavior.
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
$uploadedDateFilter = trim((string)($_GET['uploaded'] ?? ''));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $uploadedDateFilter)) {
    $uploadedDateFilter = '';
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
<title>Plate Log</title>
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
    <h1>Plate Log</h1>

    <section class="stats-grid stats-grid-compact">
        <button type="button" class="card stat-card is-active" data-filter-mode="all" onclick="setFilterMode('all')"><strong>Total entries</strong><span id="totalEntriesCount"><?= count($entries) ?></span></button>
        <button type="button" class="card stat-card" data-filter-mode="pending" onclick="setFilterMode('pending')"><strong>Pending Processing</strong><span id="pendingCount"><?= count($pendingEntries) ?></span></button>
        <button type="button" class="card stat-card" data-filter-mode="unique" onclick="setFilterMode('unique')"><strong>Unique plates</strong><span id="uniquePlatesCount"><?= count($counts) ?></span></button>
        <button type="button" class="card stat-card" data-filter-mode="duplicate-file" onclick="setFilterMode('duplicate-file')"><strong>Duplicate files</strong><span id="duplicateFilesCount"><?= count($duplicateFiles) ?></span></button>
        <button type="button" class="card stat-card" data-filter-mode="duplicate-plate" onclick="setFilterMode('duplicate-plate')"><strong>Duplicate Plates</strong><span id="duplicatePlatesCount"><?= count($duplicatePlates) ?></span></button>
        <button type="button" class="card stat-card" data-filter-mode="metadata" onclick="setFilterMode('metadata')"><strong>With Metadata</strong><span id="metadataCount"><?= count($metadataEntries) ?></span></button>
    </section>

    <section class="card" id="pendingSection"<?= empty($pendingEntries) ? ' hidden' : '' ?>>
        <h2>Pending Processing</h2>
        <p class="small">Use the first-column checkboxes to pick several failed rows, or click <strong>Select All Failed</strong> to retry every prior error in one click. Files are retried one at a time to avoid web-server timeouts.</p>
        <div class="actions">
            <button id="retrySelectedPending" type="button" class="secondary">Retry Selected Failed</button>
            <button id="selectAllFailedPending" type="button" class="secondary">Select All Failed</button>
            <button id="processAllPending" type="button">Process All Pending</button>
        </div>
        <p id="pendingSummary" class="small"><?= count($pendingEntries) ?> file<?= count($pendingEntries) === 1 ? '' : 's' ?> waiting.<?php if (!empty($failedPendingEntries)): ?> <?= count($failedPendingEntries) ?> previously failed.<?php endif; ?></p>
    </section>

    <section class="card" id="duplicatePlatesSection"<?= empty($duplicatePlates) ? ' hidden' : '' ?>>
        <h2>Duplicate Plates</h2>
        <p class="small">Click a plate value to filter the entry table to that plate.</p>
        <table>
            <thead><tr><th>Plate</th><th>Count</th></tr></thead>
            <tbody id="duplicatePlatesTableBody">
            <?php foreach ($duplicatePlates as $plate => $count): ?>
                <tr>
                    <td><button type="button" class="link-button duplicate-plate-filter" data-plate="<?= h(strtolower($plate)) ?>"><?= h($plate) ?></button></td>
                    <td><?= h((string)$count) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </section>

    <section class="card">
        <h2>Entries</h2>
        <div class="filters">
            <label class="filter-field" for="entrySearch">Search Entries</label>
            <input type="search" id="entrySearch" placeholder="Search plate, state, GPS, date taken, file, message">
            <?php if ($uploadedDateFilter !== ''): ?>
                <div class="filter-chip-row">
                    <span class="filter-chip">Uploaded date: <?= h($uploadedDateFilter) ?></span>
                    <a href="view_log.php" class="chip-link">Clear Date Filter</a>
                </div>
            <?php endif; ?>
            <p id="entryFilterStatus" class="small filter-status">Showing all entries.</p>
            <div class="bulk-actions">
                <button type="button" id="selectVisibleDeletes" class="secondary">Select Visible</button>
                <button type="button" id="selectMissingPlate" class="secondary">Select Missing Plate</button>
                <button type="button" id="clearDeleteSelection" class="secondary">Clear Selection</button>
                <button type="button" id="reprocessSelectedEntries" class="secondary" disabled>Re-process Selected</button>
                <button type="button" id="deleteSelectedEntries" class="danger" disabled>Delete Selected</button>
            </div>
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
                    <th class="col-favorite"><button type="button" class="sort-button" data-sort-type="text">Fav</button></th>
                    <th class="col-rank"><button type="button" class="sort-button" data-sort-type="number">Rank</button></th>
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
                $processedAtDisplay = displayEasternDateTime((string)($entry['processed_at'] ?? ''));
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
                    data-stored-file="<?= h((string)($entry['stored_file'] ?? '')) ?>"
                    data-best-plate-photo="<?= !empty($entry['best_plate_photo']) ? 'true' : 'false' ?>"
                    data-favorite="<?= !empty($entry['favorite']) ? 'true' : 'false' ?>"
                    data-rank="<?= h((string)($entry['preference_rank'] ?? '')) ?>"
                    data-uploaded-date="<?= h(displayEasternDate((string)($entry['processed_at'] ?? ''))) ?>"
                    data-scanner-label="<?= h(entryScannerLabel($entry)) ?>"
                    data-original-file="<?= h((string)($entry['original_file'] ?? '')) ?>"
                    data-gps-display="<?= h((string)($entry['gps_display'] ?? '')) ?>"
                    data-date-taken-display="<?= h($dateTakenDisplay) ?>"
                    data-confidence-display="<?= h(isset($entry['confidence']) && $entry['confidence'] !== '' ? ((string)$entry['confidence'] . '%') : '') ?>"
                    data-clarity-display="<?= h((string)($entry['clarity_score'] ?? '')) ?>"
                    data-duplicate-display="<?= h(implode(', ', array_filter([
                        !empty($entry['duplicate_file']) ? 'same file' : '',
                        !empty($entry['duplicate_plate']) ? 'same plate' : '',
                        !empty($entry['duplicate_plate']) && !empty($entry['best_plate_photo']) ? 'clearest photo' : '',
                    ]))) ?>"
                    data-search="<?= h($searchBlob) ?>"
                >
                    <td>
                        <label class="row-select-option">
                            <input type="checkbox" class="delete-select" data-id="<?= h($entry['id'] ?? '') ?>" aria-label="Select <?= h($entry['original_file'] ?? ($entry['id'] ?? 'entry')) ?>">
                            <span>Select</span>
                        </label>
                        <?php if ($isFailedPending): ?>
                            <label class="row-select-option">
                                <input type="checkbox" class="retry-select" data-id="<?= h($entry['id'] ?? '') ?>" aria-label="Select failed entry <?= h($entry['original_file'] ?? ($entry['id'] ?? '')) ?>">
                                <span>Retry</span>
                            </label>
                        <?php endif; ?>
                    </td>
                    <td><?= h($processedAtDisplay) ?></td>
                    <td class="entry-status"><?= h($status === 'pending' ? 'Pending Processing' : 'Complete') ?></td>
                    <td class="entry-plate"><?= h($entry['plate'] ?? '') ?></td>
                    <td class="entry-confidence"><?= h(isset($entry['confidence']) && $entry['confidence'] !== '' ? ((string)$entry['confidence'] . '%') : '') ?></td>
                    <td class="entry-favorite"><?= !empty($entry['favorite']) ? '★' : '' ?></td>
                    <td class="entry-rank"><?= h(isset($entry['preference_rank']) && $entry['preference_rank'] !== null ? (string)$entry['preference_rank'] : '') ?></td>
                    <td class="entry-clarity"><?= h((string)($entry['clarity_score'] ?? '')) ?></td>
                    <td class="entry-state"><?= h($stateDisplay) ?></td>
                    <td class="entry-date-taken"><?= h($dateTakenDisplay) ?></td>
                    <td class="entry-gps"><?= !empty($entry['gps_display']) ? 'Yes' : '' ?></td>
                    <td class="entry-file" title="<?= h((string)($entry['original_file'] ?? '')) ?>"><?= h($entry['original_file'] ?? '') ?></td>
                    <td>
                        <?php if (!empty($entry['stored_file'])): ?>
                            <a
                                href="uploads/<?= h($entry['stored_file']) ?>"
                                class="photo-preview-link"
                                data-photo-src="uploads/<?= h($entry['stored_file']) ?>"
                                data-photo-label="<?= h($entry['original_file'] ?? ($entry['plate'] ?? 'Photo preview')) ?>"
                                target="_blank"
                                rel="noopener"
                            >photo</a>
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
                    <td class="entry-scanner"><?= h(entryScannerLabel($entry)) ?></td>
                    <td class="entry-message"><?= h($entry['error'] ?? '') ?></td>
                    <td>
                        <div class="inline-actions">
                            <button type="button" class="secondary more-info-entry" data-id="<?= h($entry['id'] ?? '') ?>">More Info</button>
                            <button type="button" class="secondary reprocess-entry" data-id="<?= h($entry['id'] ?? '') ?>">Re-process</button>
                            <?php if ($status === 'pending'): ?>
                                <button type="button" class="retry-pending" data-id="<?= h($entry['id'] ?? '') ?>">Retry</button>
                            <?php endif; ?>
                            <button
                                type="button"
                                class="secondary edit-entry"
                                data-id="<?= h($entry['id'] ?? '') ?>"
                                data-plate="<?= h((string)($entry['plate'] ?? '')) ?>"
                                data-state="<?= h($stateDisplay) ?>"
                                data-favorite="<?= !empty($entry['favorite']) ? 'true' : 'false' ?>"
                                data-rank="<?= h((string)($entry['preference_rank'] ?? '')) ?>"
                            >Edit</button>
                            <button
                                type="button"
                                class="danger delete-entry"
                                data-id="<?= h($entry['id'] ?? '') ?>"
                                data-file="<?= h((string)($entry['original_file'] ?? '')) ?>"
                                data-plate="<?= h((string)($entry['plate'] ?? '')) ?>"
                            >Delete</button>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </section>
</main>
<div id="photoOverlay" class="photo-overlay" hidden>
    <div class="photo-overlay-backdrop" data-close-photo-overlay="true"></div>
    <div class="photo-overlay-panel" role="dialog" aria-modal="true" aria-labelledby="photoOverlayTitle">
        <div class="photo-overlay-header">
            <strong id="photoOverlayTitle">Photo Preview</strong>
            <button type="button" id="closePhotoOverlay" class="photo-overlay-close" aria-label="Close photo preview">X</button>
        </div>
        <div class="photo-overlay-body">
            <img id="photoOverlayImage" src="" alt="Photo preview">
        </div>
    </div>
</div>
<div id="entryEditorOverlay" class="photo-overlay" hidden>
    <div class="photo-overlay-backdrop" data-close-entry-editor="true"></div>
    <div class="photo-overlay-panel entry-editor-panel" role="dialog" aria-modal="true" aria-labelledby="entryEditorTitle">
        <form id="entryEditorForm">
            <div class="photo-overlay-header">
                <strong id="entryEditorTitle">Edit Plate Entry</strong>
                <button type="button" id="closeEntryEditor" class="photo-overlay-close" aria-label="Close entry editor">X</button>
            </div>
            <div class="entry-editor-body">
                <input type="hidden" id="editorEntryId">
                <label for="editorPlate">Plate</label>
                <input type="text" id="editorPlate" maxlength="16" placeholder="Enter or correct plate text">
                <label for="editorState">State</label>
                <input type="text" id="editorState" maxlength="32" placeholder="State name or abbreviation">
                <label class="entry-editor-check">
                    <input type="checkbox" id="editorFavorite">
                    <span>Favorite this plate</span>
                </label>
                <label for="editorRank">Preference Rank</label>
                <select id="editorRank">
                    <option value="">None</option>
                    <?php for ($rank = 1; $rank <= 10; $rank++): ?>
                        <option value="<?= $rank ?>"><?= $rank ?></option>
                    <?php endfor; ?>
                </select>
                <p class="small">Use this editor to manually enter a missed plate, correct a wrong parse, or tag favorites and personal rankings.</p>
                <p id="entryEditorStatus" class="small filter-status"></p>
            </div>
            <div class="entry-editor-actions">
                <button type="submit" id="saveEntryEditor">Save Changes</button>
                <button type="button" id="cancelEntryEditor" class="secondary">Cancel</button>
            </div>
        </form>
    </div>
</div>
<div id="deleteEntryOverlay" class="photo-overlay" hidden>
    <div class="photo-overlay-backdrop" data-close-delete-entry="true"></div>
    <div class="photo-overlay-panel entry-editor-panel" role="dialog" aria-modal="true" aria-labelledby="deleteEntryTitle">
        <form id="deleteEntryForm">
            <div class="photo-overlay-header">
                <strong id="deleteEntryTitle">Delete Log Entry</strong>
                <button type="button" id="closeDeleteEntry" class="photo-overlay-close" aria-label="Close delete dialog">X</button>
            </div>
            <div class="entry-editor-body">
                <input type="hidden" id="deleteEntryId">
                <p id="deleteEntrySummary" class="small"></p>
                <label for="deleteEntryReason">Reason For Deletion</label>
                <div class="quick-reasons" aria-label="Quick delete reasons">
                    <button type="button" class="secondary quick-reason" data-reason="Duplicate upload">Duplicate</button>
                    <button type="button" class="secondary quick-reason" data-reason="Wrong upload">Wrong Upload</button>
                    <button type="button" class="secondary quick-reason" data-reason="Bad plate read">Bad Read</button>
                </div>
                <textarea id="deleteEntryReason" rows="4" placeholder="Why is this item being deleted?" required></textarea>
                <p class="small">The log entry will be removed from the active list. Its photo will be moved into `deleted/` when possible and the action will be written to the deleted audit log.</p>
                <p id="deleteEntryStatus" class="small filter-status"></p>
            </div>
            <div class="entry-editor-actions">
                <button type="submit" id="confirmDeleteEntry" class="danger">Delete Entry</button>
                <button type="button" id="cancelDeleteEntry" class="secondary">Cancel</button>
            </div>
        </form>
    </div>
</div>
<div id="entryDetailsOverlay" class="photo-overlay" hidden>
    <div class="photo-overlay-backdrop" data-close-entry-details="true"></div>
    <div class="photo-overlay-panel entry-editor-panel" role="dialog" aria-modal="true" aria-labelledby="entryDetailsTitle">
        <div class="photo-overlay-header">
            <strong id="entryDetailsTitle">Plate Details</strong>
            <button type="button" id="closeEntryDetails" class="photo-overlay-close" aria-label="Close plate details">X</button>
        </div>
        <div class="entry-editor-body">
            <div id="entryDetailsGrid" class="entry-details-grid"></div>
        </div>
    </div>
</div>
<script>
const processAllButton = document.getElementById('processAllPending');
const retrySelectedButton = document.getElementById('retrySelectedPending');
const selectAllFailedButton = document.getElementById('selectAllFailedPending');
const pendingSummary = document.getElementById('pendingSummary');
const pendingSection = document.getElementById('pendingSection');
const totalEntriesCount = document.getElementById('totalEntriesCount');
const pendingCount = document.getElementById('pendingCount');
const uniquePlatesCount = document.getElementById('uniquePlatesCount');
const duplicateFilesCount = document.getElementById('duplicateFilesCount');
const duplicatePlatesCount = document.getElementById('duplicatePlatesCount');
const metadataCount = document.getElementById('metadataCount');
const entrySearch = document.getElementById('entrySearch');
const entryFilterStatus = document.getElementById('entryFilterStatus');
const entriesTableBody = document.getElementById('entriesTableBody');
const sortButtons = Array.from(document.querySelectorAll('.sort-button'));
const statCards = Array.from(document.querySelectorAll('.stat-card'));
const duplicatePlatesSection = document.getElementById('duplicatePlatesSection');
const duplicatePlatesTableBody = document.getElementById('duplicatePlatesTableBody');
const quickReasonButtons = Array.from(document.querySelectorAll('.quick-reason'));
const photoOverlay = document.getElementById('photoOverlay');
const photoOverlayImage = document.getElementById('photoOverlayImage');
const photoOverlayTitle = document.getElementById('photoOverlayTitle');
const closePhotoOverlayButton = document.getElementById('closePhotoOverlay');
const entryDetailsOverlay = document.getElementById('entryDetailsOverlay');
const entryDetailsGrid = document.getElementById('entryDetailsGrid');
const closeEntryDetailsButton = document.getElementById('closeEntryDetails');
const entryEditorOverlay = document.getElementById('entryEditorOverlay');
const entryEditorForm = document.getElementById('entryEditorForm');
const entryEditorId = document.getElementById('editorEntryId');
const entryEditorPlate = document.getElementById('editorPlate');
const entryEditorState = document.getElementById('editorState');
const entryEditorFavorite = document.getElementById('editorFavorite');
const entryEditorRank = document.getElementById('editorRank');
const entryEditorStatus = document.getElementById('entryEditorStatus');
const saveEntryEditorButton = document.getElementById('saveEntryEditor');
const closeEntryEditorButton = document.getElementById('closeEntryEditor');
const cancelEntryEditorButton = document.getElementById('cancelEntryEditor');
const deleteEntryOverlay = document.getElementById('deleteEntryOverlay');
const deleteEntryForm = document.getElementById('deleteEntryForm');
const deleteEntryId = document.getElementById('deleteEntryId');
const deleteEntryReason = document.getElementById('deleteEntryReason');
const deleteEntrySummary = document.getElementById('deleteEntrySummary');
const deleteEntryStatus = document.getElementById('deleteEntryStatus');
const closeDeleteEntryButton = document.getElementById('closeDeleteEntry');
const cancelDeleteEntryButton = document.getElementById('cancelDeleteEntry');
const confirmDeleteEntryButton = document.getElementById('confirmDeleteEntry');
const selectVisibleDeletesButton = document.getElementById('selectVisibleDeletes');
const selectMissingPlateButton = document.getElementById('selectMissingPlate');
const clearDeleteSelectionButton = document.getElementById('clearDeleteSelection');
const reprocessSelectedEntriesButton = document.getElementById('reprocessSelectedEntries');
const deleteSelectedEntriesButton = document.getElementById('deleteSelectedEntries');
let activeFilterMode = 'all';
let activePlateFilter = '';
let activeUploadedDateFilter = <?= json_encode($uploadedDateFilter, JSON_UNESCAPED_SLASHES) ?>;
let activeEditRow = null;
let activeDeleteRow = null;
let activeDeleteRows = [];

function allRows() {
    return Array.from(entriesTableBody.querySelectorAll('tr'));
}

function getRetrySelectionBoxes() {
    return Array.from(document.querySelectorAll('.retry-select'));
}

function getDeleteSelectionBoxes() {
    return Array.from(document.querySelectorAll('.delete-select'));
}

function closeAllOverlays() {
    if (photoOverlay && !photoOverlay.hidden) {
        closePhotoOverlay();
    }
    if (entryDetailsOverlay && !entryDetailsOverlay.hidden) {
        closeEntryDetails();
    }
    if (entryEditorOverlay && !entryEditorOverlay.hidden) {
        closeEntryEditor();
    }
    if (deleteEntryOverlay && !deleteEntryOverlay.hidden) {
        closeDeleteEntry();
    }
}

function openPhotoOverlay(src, label) {
    if (!photoOverlay || !photoOverlayImage) return;
    closeAllOverlays();
    photoOverlayImage.src = src;
    photoOverlayImage.alt = label || 'Photo preview';
    if (photoOverlayTitle) photoOverlayTitle.textContent = label || 'Photo Preview';
    photoOverlay.hidden = false;
    document.body.classList.add('overlay-open');
}

function closePhotoOverlay() {
    if (!photoOverlay || !photoOverlayImage) return;
    photoOverlay.hidden = true;
    photoOverlayImage.src = '';
    photoOverlayImage.alt = 'Photo preview';
    if (photoOverlayTitle) photoOverlayTitle.textContent = 'Photo Preview';
    document.body.classList.remove('overlay-open');
}

function openPhotoPreviewFromLink(link) {
    if (!link || !photoOverlay || !photoOverlayImage) {
        return true;
    }
    const src = (link.dataset && link.dataset.photoSrc) || link.getAttribute('href') || '';
    const label = (link.dataset && link.dataset.photoLabel) || 'Photo Preview';
    if (!src) return true;

    openPhotoOverlay(src, label);
    return false;
}

function openEntryDetails(button) {
    const row = button.closest('tr');
    if (!row || !entryDetailsOverlay || !entryDetailsGrid) return;
    closeAllOverlays();

    const details = [
        ['Plate', row.querySelector('.entry-plate')?.textContent || ''],
        ['Original File', row.dataset.originalFile || ''],
        ['Uploaded', row.cells[1]?.textContent || ''],
        ['Status', row.querySelector('.entry-status')?.textContent || ''],
        ['Scanner', row.querySelector('.entry-scanner')?.textContent || row.dataset.scannerLabel || ''],
        ['Confidence', row.dataset.confidenceDisplay || row.querySelector('.entry-confidence')?.textContent || ''],
        ['Clarity', row.dataset.clarityDisplay || row.querySelector('.entry-clarity')?.textContent || ''],
        ['State', row.querySelector('.entry-state')?.textContent || ''],
        ['Date Taken', row.dataset.dateTakenDisplay || row.querySelector('.entry-date-taken')?.textContent || ''],
        ['GPS', row.dataset.gpsDisplay || ''],
        ['Duplicate', row.dataset.duplicateDisplay || row.querySelector('.entry-duplicate')?.textContent || ''],
        ['Message', row.querySelector('.entry-message')?.textContent || ''],
    ];

    entryDetailsGrid.innerHTML = '';
    details.forEach(([label, value]) => {
        const labelNode = document.createElement('strong');
        labelNode.textContent = label;
        const valueNode = document.createElement('span');
        valueNode.textContent = value || '';
        entryDetailsGrid.appendChild(labelNode);
        entryDetailsGrid.appendChild(valueNode);
    });
    entryDetailsOverlay.hidden = false;
    document.body.classList.add('overlay-open');
}

function closeEntryDetails() {
    if (!entryDetailsOverlay || !entryDetailsGrid) return;
    entryDetailsOverlay.hidden = true;
    entryDetailsGrid.innerHTML = '';
    document.body.classList.remove('overlay-open');
}

function openEntryEditor(button) {
    const row = button.closest('tr');
    if (!row || !entryEditorOverlay || !entryEditorId || !entryEditorPlate || !entryEditorState || !entryEditorFavorite || !entryEditorRank) return;
    closeAllOverlays();
    activeEditRow = row;
    entryEditorId.value = button.dataset.id || '';
    entryEditorPlate.value = button.dataset.plate || '';
    entryEditorState.value = button.dataset.state || '';
    entryEditorFavorite.checked = (button.dataset.favorite || '') === 'true';
    entryEditorRank.value = button.dataset.rank || '';
    if (entryEditorStatus) entryEditorStatus.textContent = '';
    entryEditorOverlay.hidden = false;
    document.body.classList.add('overlay-open');
    entryEditorPlate.focus();
}

function closeEntryEditor() {
    if (!entryEditorOverlay || !entryEditorForm) return;
    entryEditorOverlay.hidden = true;
    entryEditorForm.reset();
    if (entryEditorStatus) entryEditorStatus.textContent = '';
    activeEditRow = null;
    document.body.classList.remove('overlay-open');
}

function openDeleteEntry(button) {
    const row = button.closest('tr');
    if (!row || !deleteEntryOverlay || !deleteEntryId || !deleteEntryReason) return;
    closeAllOverlays();
    activeDeleteRow = row;
    activeDeleteRows = [row];
    deleteEntryId.value = button.dataset.id || '';
    deleteEntryReason.value = '';
    if (deleteEntrySummary) {
        const parts = [];
        if (button.dataset.file) parts.push(`File: ${button.dataset.file}`);
        if (button.dataset.plate) parts.push(`Plate: ${button.dataset.plate}`);
        deleteEntrySummary.textContent = parts.join(' | ');
    }
    if (deleteEntryStatus) deleteEntryStatus.textContent = '';
    if (confirmDeleteEntryButton) confirmDeleteEntryButton.textContent = 'Delete Entry';
    deleteEntryOverlay.hidden = false;
    document.body.classList.add('overlay-open');
    deleteEntryReason.focus();
}

function openBulkDeleteEntries(rows) {
    if (!rows.length || !deleteEntryOverlay || !deleteEntryId || !deleteEntryReason) return;
    closeAllOverlays();
    activeDeleteRow = rows[0];
    activeDeleteRows = rows;
    deleteEntryId.value = rows.map(row => row.dataset.entryId || '').filter(Boolean).join(',');
    deleteEntryReason.value = '';
    if (deleteEntrySummary) {
        deleteEntrySummary.textContent = `${rows.length} selected entr${rows.length === 1 ? 'y' : 'ies'} will be deleted with the same reason.`;
    }
    if (deleteEntryStatus) deleteEntryStatus.textContent = '';
    if (confirmDeleteEntryButton) confirmDeleteEntryButton.textContent = `Delete ${rows.length} Entr${rows.length === 1 ? 'y' : 'ies'}`;
    deleteEntryOverlay.hidden = false;
    document.body.classList.add('overlay-open');
    deleteEntryReason.focus();
}

function closeDeleteEntry() {
    if (!deleteEntryOverlay || !deleteEntryForm) return;
    deleteEntryOverlay.hidden = true;
    deleteEntryForm.reset();
    if (deleteEntryStatus) deleteEntryStatus.textContent = '';
    activeDeleteRow = null;
    activeDeleteRows = [];
    if (confirmDeleteEntryButton) {
        confirmDeleteEntryButton.disabled = false;
        confirmDeleteEntryButton.textContent = 'Delete Entry';
    }
    document.body.classList.remove('overlay-open');
}

function getSelectedFailedButtons() {
    return getRetrySelectionBoxes()
        .filter(box => box.checked)
        .map(box => document.querySelector(`.retry-pending[data-id="${CSS.escape(box.dataset.id)}"]`))
        .filter(Boolean);
}

function updateFailedSelectionState() {
    if (!retrySelectedButton) return;
    const selectionBoxes = getRetrySelectionBoxes();
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

function selectedDeleteRows() {
    return getDeleteSelectionBoxes()
        .filter(box => box.checked && !box.disabled)
        .map(box => box.closest('tr'))
        .filter(row => row && row.isConnected);
}

function updateDeleteSelectionState() {
    const selectedCount = selectedDeleteRows().length;
    if (deleteSelectedEntriesButton) {
        deleteSelectedEntriesButton.disabled = selectedCount === 0;
        deleteSelectedEntriesButton.textContent = selectedCount > 0 ? `Delete Selected (${selectedCount})` : 'Delete Selected';
    }
    if (reprocessSelectedEntriesButton) {
        reprocessSelectedEntriesButton.disabled = selectedCount === 0;
        reprocessSelectedEntriesButton.textContent = selectedCount > 0 ? `Re-process Selected (${selectedCount})` : 'Re-process Selected';
    }
}

function visibleRows() {
    return allRows().filter(row => row.style.display !== 'none');
}

function rowPlateValue(row) {
    return (row.dataset.plateValue || '').trim().toUpperCase();
}

function computeTableStats() {
    const rows = allRows();
    const plateCounts = new Map();

    let pending = 0;
    let failedPending = 0;
    let metadata = 0;
    let duplicateFiles = 0;

    rows.forEach(row => {
        const plate = rowPlateValue(row);
        if (plate !== '') {
            plateCounts.set(plate, (plateCounts.get(plate) || 0) + 1);
        }
        if (row.dataset.status === 'pending') pending++;
        if (row.dataset.status === 'pending' && row.dataset.failedPending === 'true') failedPending++;
        if (row.dataset.hasMetadata === 'true') metadata++;
        if (row.dataset.duplicateFile === 'true') duplicateFiles++;
    });

    const duplicatePlateGroups = Array.from(plateCounts.entries())
        .filter(([, count]) => count > 1)
        .sort((a, b) => b[1] - a[1] || a[0].localeCompare(b[0]));

    return {
        rows,
        pending,
        failedPending,
        metadata,
        duplicateFiles,
        uniquePlates: plateCounts.size,
        duplicatePlateGroups,
    };
}

function recalculateRowDuplicateFlags() {
    const rows = allRows();
    const plateGroups = new Map();
    const fileGroups = new Map();

    rows.forEach(row => {
        row.dataset.duplicatePlate = 'false';
        row.dataset.duplicateFile = 'false';
        row.dataset.bestPlatePhoto = 'false';

        const plate = rowPlateValue(row);
        if (plate !== '') {
            const group = plateGroups.get(plate) || [];
            group.push(row);
            plateGroups.set(plate, group);
        }

        const storedFile = (row.dataset.storedFile || '').trim();
        if (storedFile !== '') {
            const group = fileGroups.get(storedFile) || [];
            group.push(row);
            fileGroups.set(storedFile, group);
        }
    });

    fileGroups.forEach(group => {
        if (group.length < 2) return;
        group.forEach(row => {
            row.dataset.duplicateFile = 'true';
        });
    });

    plateGroups.forEach(group => {
        if (group.length < 2) return;
        let bestScore = Number.NEGATIVE_INFINITY;
        group.forEach(row => {
            row.dataset.duplicatePlate = 'true';
            const score = Number.parseFloat(row.querySelector('.entry-clarity')?.textContent || '');
            if (!Number.isNaN(score) && score > bestScore) {
                bestScore = score;
            }
        });
        group.forEach(row => {
            const score = Number.parseFloat(row.querySelector('.entry-clarity')?.textContent || '');
            row.dataset.bestPlatePhoto = !Number.isNaN(score) && score === bestScore ? 'true' : 'false';
        });
    });

    rows.forEach(row => {
        const duplicateCell = row.querySelector('.entry-duplicate');
        if (!duplicateCell) return;
        const duplicateText = rowDuplicateText({
            duplicate_file: row.dataset.duplicateFile === 'true',
            duplicate_plate: row.dataset.duplicatePlate === 'true',
            best_plate_photo: row.dataset.bestPlatePhoto === 'true',
            plate_count: plateGroups.get(rowPlateValue(row))?.length || 0,
        });
        duplicateCell.textContent = duplicateText;
        row.dataset.duplicateDisplay = duplicateText;
    });
}

function renderDuplicatePlateSection(duplicatePlateGroups) {
    if (!duplicatePlatesSection || !duplicatePlatesTableBody) return;

    duplicatePlatesTableBody.innerHTML = '';
    duplicatePlateGroups.forEach(([plate, count]) => {
        const row = document.createElement('tr');
        row.innerHTML = `<td><button type="button" class="link-button duplicate-plate-filter" data-plate="${plate.toLowerCase()}">${plate}</button></td><td>${count}</td>`;
        duplicatePlatesTableBody.appendChild(row);
    });

    duplicatePlatesSection.hidden = duplicatePlateGroups.length === 0;
}

function updateSummaryCards() {
    recalculateRowDuplicateFlags();
    const stats = computeTableStats();
    if (totalEntriesCount) totalEntriesCount.textContent = String(stats.rows.length);
    if (pendingCount) pendingCount.textContent = String(stats.pending);
    if (uniquePlatesCount) uniquePlatesCount.textContent = String(stats.uniquePlates);
    if (duplicateFilesCount) duplicateFilesCount.textContent = String(stats.duplicateFiles);
    if (duplicatePlatesCount) duplicatePlatesCount.textContent = String(stats.duplicatePlateGroups.length);
    if (metadataCount) metadataCount.textContent = String(stats.metadata);
    if (pendingSummary) pendingSummary.textContent = `${stats.pending} file${stats.pending === 1 ? '' : 's'} waiting.${stats.failedPending ? ` ${stats.failedPending} previously failed.` : ''}`;
    if (processAllButton) processAllButton.disabled = stats.pending === 0;
    if (retrySelectedButton) retrySelectedButton.disabled = getRetrySelectionBoxes().filter(box => box.checked).length === 0;
    if (pendingSection) pendingSection.hidden = stats.pending === 0;
    renderDuplicatePlateSection(stats.duplicatePlateGroups);
}

function updatePendingCount() {
    updateSummaryCards();
}

function refreshVisibleSelectionControls() {
    updateDeleteSelectionState();
    updateFailedSelectionState();
}

function refreshRowSearch(row) {
    row.dataset.search = [
        row.querySelector('.entry-plate')?.textContent || '',
        row.querySelector('.entry-favorite')?.textContent || '',
        row.querySelector('.entry-rank')?.textContent || '',
        row.querySelector('.entry-clarity')?.textContent || '',
        row.querySelector('.entry-state')?.textContent || '',
        row.querySelector('.entry-date-taken')?.textContent || '',
        row.dataset.gpsDisplay || '',
        row.cells[11]?.textContent || '',
        row.cells[14]?.textContent || '',
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
        const uploadedDateMatch = activeUploadedDateFilter === '' || row.dataset.uploadedDate === activeUploadedDateFilter;
        const searchMatch = term === '' || (row.dataset.search || '').includes(term);
        row.style.display = modeMatch && plateMatch && uploadedDateMatch && searchMatch ? '' : 'none';
    });

    const labels = [];
    if (activeFilterMode !== 'all') {
        labels.push(activeFilterMode === 'duplicate-plate' ? 'duplicate plates' : activeFilterMode.replace('-', ' '));
    }
    if (activePlateFilter) labels.push(`plate ${activePlateFilter.toUpperCase()}`);
    if (activeUploadedDateFilter) labels.push(`uploaded ${activeUploadedDateFilter}`);
    if (term) labels.push(`search "${term}"`);
    if (entryFilterStatus) entryFilterStatus.textContent = labels.length ? `Filtered by ${labels.join(', ')}.` : 'Showing all entries.';
    updatePendingCount();
    updateDeleteSelectionState();
}

function setFilterMode(mode) {
    activeFilterMode = mode;
    if (mode !== 'duplicate-plate') activePlateFilter = '';
    updateStatCardState();
    applyEntrySearch();
    document.getElementById('entriesTable')?.scrollIntoView({behavior: 'smooth', block: 'start'});
}

function updateStatCardState() {
    statCards.forEach(card => {
        const isActive = (card.dataset.filterMode || 'all') === activeFilterMode && activePlateFilter === '';
        card.classList.toggle('is-active', isActive);
        card.setAttribute('aria-pressed', isActive ? 'true' : 'false');
    });
}

function syncEditButtonFromRow(row) {
    const editButton = row.querySelector('.edit-entry');
    if (!editButton) return;
    editButton.dataset.plate = row.querySelector('.entry-plate')?.textContent || '';
    editButton.dataset.state = row.querySelector('.entry-state')?.textContent || '';
    editButton.dataset.favorite = row.dataset.favorite === 'true' ? 'true' : 'false';
    editButton.dataset.rank = row.dataset.rank || '';
}

function rowDuplicateText(data) {
    const duplicateParts = [];
    if (data.duplicate_file) duplicateParts.push('same file');
    if (data.duplicate_plate) duplicateParts.push(data.plate_count ? `same plate (${data.plate_count})` : 'same plate');
    if (data.duplicate_plate && data.best_plate_photo) duplicateParts.push('clearest photo');
    return duplicateParts.join(', ');
}

function ensureRetrySelection(row) {
    const firstCell = row.cells[0];
    if (!firstCell || row.querySelector('.retry-select')) return;

    const label = document.createElement('label');
    label.className = 'row-select-option';
    label.innerHTML = `<input type="checkbox" class="retry-select" data-id="${row.dataset.entryId || ''}"><span>Retry</span>`;
    firstCell.appendChild(label);
    label.querySelector('.retry-select')?.addEventListener('change', updateFailedSelectionState);
}

function ensureRetryButton(row) {
    const actions = row.querySelector('.inline-actions');
    if (!actions || row.querySelector('.retry-pending')) return;

    const reprocessButton = row.querySelector('.reprocess-entry');
    const retryButton = document.createElement('button');
    retryButton.type = 'button';
    retryButton.className = 'retry-pending';
    retryButton.dataset.id = row.dataset.entryId || '';
    retryButton.textContent = 'Retry';
    retryButton.addEventListener('click', () => retryEntry(retryButton.dataset.id || '', retryButton));
    if (reprocessButton && reprocessButton.nextSibling) {
        actions.insertBefore(retryButton, reprocessButton.nextSibling);
    } else {
        actions.appendChild(retryButton);
    }
}

function clearRetryControls(row) {
    const retrySelect = row.querySelector('.retry-select');
    if (retrySelect) {
        retrySelect.closest('.row-select-option')?.remove();
    }
    row.querySelector('.retry-pending')?.remove();
}

function applyProcessedEntryToRow(row, data) {
    row.dataset.status = data.status || 'complete';
    row.dataset.failedPending = data.status === 'pending' && data.error ? 'true' : 'false';
    row.dataset.duplicatePlate = data.duplicate_plate ? 'true' : 'false';
    row.dataset.duplicateFile = data.duplicate_file ? 'true' : row.dataset.duplicateFile || 'false';
    row.dataset.bestPlatePhoto = data.best_plate_photo ? 'true' : 'false';
    row.dataset.plateValue = (data.plate || '').toLowerCase();
    row.dataset.scannerLabel = data.scanner_label || row.dataset.scannerLabel || '';
    row.dataset.confidenceDisplay = data.confidence !== undefined && data.confidence !== null && data.confidence !== '' ? `${data.confidence}%` : '';
    row.dataset.clarityDisplay = data.clarity_score !== undefined && data.clarity_score !== null ? String(data.clarity_score) : row.dataset.clarityDisplay || '';

    const statusCell = row.querySelector('.entry-status');
    const plateCell = row.querySelector('.entry-plate');
    const confidenceCell = row.querySelector('.entry-confidence');
    const clarityCell = row.querySelector('.entry-clarity');
    const stateCell = row.querySelector('.entry-state');
    const duplicateCell = row.querySelector('.entry-duplicate');
    const scannerCell = row.querySelector('.entry-scanner');
    const messageCell = row.querySelector('.entry-message');

    if (statusCell) statusCell.textContent = data.status === 'pending' ? 'Pending Processing' : 'Complete';
    if (plateCell) plateCell.textContent = data.plate || '';
    if (confidenceCell) confidenceCell.textContent = data.confidence !== undefined && data.confidence !== null && data.confidence !== '' ? `${data.confidence}%` : '';
    if (clarityCell) clarityCell.textContent = data.clarity_score ?? clarityCell.textContent ?? '';
    if (stateCell) stateCell.textContent = data.plate_state || '';
    if (duplicateCell) {
        const duplicateText = rowDuplicateText(data);
        duplicateCell.textContent = duplicateText;
        row.dataset.duplicateDisplay = duplicateText;
    }
    if (scannerCell && data.scanner_label) scannerCell.textContent = data.scanner_label;
    if (messageCell) messageCell.textContent = data.error || '';

    if (data.status === 'pending' && data.error) {
        ensureRetrySelection(row);
        ensureRetryButton(row);
    } else {
        clearRetryControls(row);
    }

    refreshRowSearch(row);
    syncEditButtonFromRow(row);
}

async function saveEntryEditor(event) {
    event.preventDefault();
    if (!activeEditRow || !entryEditorId || !saveEntryEditorButton) return;

    saveEntryEditorButton.disabled = true;
    if (entryEditorStatus) entryEditorStatus.textContent = 'Saving changes...';

    try {
        const response = await fetch('update_entry.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                id: entryEditorId.value,
                plate: entryEditorPlate?.value || '',
                plate_state: entryEditorState?.value || '',
                favorite: !!entryEditorFavorite?.checked,
                preference_rank: entryEditorRank?.value || ''
            })
        });
        const data = await response.json();

        if (!response.ok || data.error) {
            if (entryEditorStatus) entryEditorStatus.textContent = data.error || `HTTP ${response.status}`;
            saveEntryEditorButton.disabled = false;
            return;
        }

        activeEditRow.dataset.status = data.status || activeEditRow.dataset.status;
        activeEditRow.dataset.failedPending = data.status === 'pending' && data.error ? 'true' : 'false';
        activeEditRow.dataset.duplicatePlate = data.duplicate_plate ? 'true' : 'false';
        activeEditRow.dataset.favorite = data.favorite ? 'true' : 'false';
        activeEditRow.dataset.rank = data.preference_rank ? String(data.preference_rank) : '';
        activeEditRow.dataset.plateValue = (data.plate || '').toLowerCase();

        const plateCell = activeEditRow.querySelector('.entry-plate');
        const stateCell = activeEditRow.querySelector('.entry-state');
        const favoriteCell = activeEditRow.querySelector('.entry-favorite');
        const rankCell = activeEditRow.querySelector('.entry-rank');
        const statusCell = activeEditRow.querySelector('.entry-status');
        const duplicateCell = activeEditRow.querySelector('.entry-duplicate');
        const scannerCell = activeEditRow.querySelector('.entry-scanner');
        const messageCell = activeEditRow.querySelector('.entry-message');

        if (plateCell) plateCell.textContent = data.plate || '';
        if (stateCell) stateCell.textContent = data.plate_state || '';
        if (favoriteCell) favoriteCell.textContent = data.favorite ? '★' : '';
        if (rankCell) rankCell.textContent = data.preference_rank ? String(data.preference_rank) : '';
        if (statusCell) statusCell.textContent = data.status === 'pending' ? 'Pending Processing' : 'Complete';
        if (scannerCell) scannerCell.textContent = data.scanner_label || scannerCell.textContent || '';
        if (messageCell) messageCell.textContent = data.error || '';
        if (duplicateCell) duplicateCell.textContent = rowDuplicateText(data);

        applyProcessedEntryToRow(activeEditRow, {
            ...data,
            status: data.status || 'complete',
            error: data.error || '',
        });
        updatePendingCount();
        updateFailedSelectionState();
        updateDeleteSelectionState();
        applyEntrySearch();
        closeEntryEditor();
        saveEntryEditorButton.disabled = false;
    } catch (error) {
        if (entryEditorStatus) entryEditorStatus.textContent = 'Request failed: ' + error.message;
        saveEntryEditorButton.disabled = false;
    }
}

async function submitDeleteEntry(event) {
    event.preventDefault();
    if (!activeDeleteRows.length || !deleteEntryReason || !confirmDeleteEntryButton) return;

    const reason = deleteEntryReason.value.trim();
    if (reason === '') {
        if (deleteEntryStatus) deleteEntryStatus.textContent = 'Delete reason is required.';
        return;
    }

    confirmDeleteEntryButton.disabled = true;
    const rowsToDelete = [...activeDeleteRows];
    if (deleteEntryStatus) deleteEntryStatus.textContent = rowsToDelete.length === 1 ? 'Deleting entry...' : `Deleting 1 of ${rowsToDelete.length}...`;
    confirmDeleteEntryButton.textContent = 'Deleting...';

    try {
        let deletedCount = 0;
        for (let i = 0; i < rowsToDelete.length; i++) {
            const row = rowsToDelete[i];
            const id = row.dataset.entryId || '';
            if (!id) continue;

            if (deleteEntryStatus && rowsToDelete.length > 1) {
                deleteEntryStatus.textContent = `Deleting ${i + 1} of ${rowsToDelete.length}...`;
            }

            const response = await fetch('delete_entry.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    id,
                    reason
                })
            });
            const data = await response.json();
            if (!response.ok || data.error) {
                if (deleteEntryStatus) deleteEntryStatus.textContent = data.error || `HTTP ${response.status}`;
                confirmDeleteEntryButton.disabled = false;
                confirmDeleteEntryButton.textContent = 'Delete Entry';
                return;
            }

            row.remove();
            deletedCount++;
        }

        closeDeleteEntry();
        applyEntrySearch();
        refreshVisibleSelectionControls();
        updateSummaryCards();
        if (entryFilterStatus) {
            entryFilterStatus.textContent += ` Deleted ${deletedCount} entr${deletedCount === 1 ? 'y' : 'ies'}.`;
        }
    } catch (error) {
        if (deleteEntryStatus) deleteEntryStatus.textContent = 'Request failed: ' + error.message;
        confirmDeleteEntryButton.disabled = false;
        confirmDeleteEntryButton.textContent = 'Delete Entry';
    }
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
            applyProcessedEntryToRow(row, {
                status: data.status || 'pending',
                plate: row.querySelector('.entry-plate')?.textContent || '',
                plate_state: row.querySelector('.entry-state')?.textContent || '',
                confidence: row.querySelector('.entry-confidence')?.textContent.replace('%', '') || '',
                clarity_score: row.querySelector('.entry-clarity')?.textContent || '',
                duplicate_plate: row.dataset.duplicatePlate === 'true',
                duplicate_file: row.dataset.duplicateFile === 'true',
                best_plate_photo: row.querySelector('.entry-duplicate')?.textContent.includes('clearest photo') || false,
                plate_count: 0,
                scanner_label: data.scanner_label || row.querySelector('.entry-scanner')?.textContent || '',
                error: data.error || `HTTP ${response.status}`,
            });
            button.disabled = false;
            button.textContent = 'Retry';
            if (selectionBox) selectionBox.disabled = false;
            updateSummaryCards();
            updateFailedSelectionState();
            return false;
        }

        applyProcessedEntryToRow(row, data);
        if (selectionBox) {
            selectionBox.checked = false;
        }
        updatePendingCount();
        updateFailedSelectionState();
        updateDeleteSelectionState();
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

async function reprocessEntry(id, button) {
    const row = document.querySelector(`tr[data-entry-id="${CSS.escape(id)}"]`);
    if (!row) return false;

    const originalText = button.textContent;
    button.disabled = true;
    button.textContent = 'Re-processing...';
    row.querySelector('.entry-message').textContent = 'Re-processing saved photo...';

    try {
        const response = await fetch('reprocess_entry.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({id})
        });
        const data = await response.json();

        if (!response.ok || data.error) {
            if (data.status === 'pending') {
                applyProcessedEntryToRow(row, {
                    status: 'pending',
                    plate: row.querySelector('.entry-plate')?.textContent || '',
                    plate_state: row.querySelector('.entry-state')?.textContent || '',
                    confidence: row.querySelector('.entry-confidence')?.textContent.replace('%', '') || '',
                    clarity_score: row.querySelector('.entry-clarity')?.textContent || '',
                    duplicate_plate: row.dataset.duplicatePlate === 'true',
                    duplicate_file: row.dataset.duplicateFile === 'true',
                    best_plate_photo: row.querySelector('.entry-duplicate')?.textContent.includes('clearest photo') || false,
                    plate_count: 0,
                    scanner_label: data.scanner_label || row.querySelector('.entry-scanner')?.textContent || '',
                    error: data.error || `HTTP ${response.status}`,
                });
            } else {
                row.querySelector('.entry-message').textContent = data.error || `HTTP ${response.status}`;
            }
            button.disabled = false;
            button.textContent = originalText;
            updateSummaryCards();
            updateFailedSelectionState();
            return false;
        }

        applyProcessedEntryToRow(row, data);
        button.disabled = false;
        button.textContent = originalText;
        updateSummaryCards();
        updateFailedSelectionState();
        updateDeleteSelectionState();
        return true;
    } catch (error) {
        row.querySelector('.entry-message').textContent = 'Request failed: ' + error.message;
        button.disabled = false;
        button.textContent = originalText;
        refreshRowSearch(row);
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

getRetrySelectionBoxes().forEach(box => {
    box.addEventListener('change', updateFailedSelectionState);
});

getDeleteSelectionBoxes().forEach(box => {
    box.addEventListener('change', updateDeleteSelectionState);
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

document.addEventListener('click', event => {
    const target = event.target;
    if (!(target instanceof HTMLElement)) return;

    const duplicateButton = target.closest('.duplicate-plate-filter');
    if (duplicateButton instanceof HTMLElement) {
        activeFilterMode = 'duplicate-plate';
        activePlateFilter = duplicateButton.dataset.plate || '';
        updateStatCardState();
        applyEntrySearch();
        document.getElementById('entriesTable')?.scrollIntoView({behavior: 'smooth', block: 'start'});
        return;
    }

    const photoLink = target.closest('.photo-preview-link');
    if (photoLink instanceof HTMLAnchorElement) {
        event.stopPropagation();
        if (openPhotoPreviewFromLink(photoLink) === false) {
            event.preventDefault();
        }
        return;
    }

    const editButton = target.closest('.edit-entry');
    if (editButton instanceof HTMLButtonElement) {
        event.stopPropagation();
        openEntryEditor(editButton);
        return;
    }

    const deleteButton = target.closest('.delete-entry');
    if (deleteButton instanceof HTMLButtonElement) {
        event.stopPropagation();
        openDeleteEntry(deleteButton);
        return;
    }

    const detailsButton = target.closest('.more-info-entry');
    if (detailsButton instanceof HTMLButtonElement) {
        event.stopPropagation();
        openEntryDetails(detailsButton);
        return;
    }

    const retryButton = target.closest('.retry-pending');
    if (retryButton instanceof HTMLButtonElement) {
        event.stopPropagation();
        retryEntry(retryButton.dataset.id || '', retryButton);
        return;
    }

    const reprocessButton = target.closest('.reprocess-entry');
    if (reprocessButton instanceof HTMLButtonElement) {
        event.stopPropagation();
        reprocessEntry(reprocessButton.dataset.id || '', reprocessButton);
    }
});

if (selectVisibleDeletesButton) {
    selectVisibleDeletesButton.addEventListener('click', () => {
        visibleRows().forEach(row => {
            const box = row.querySelector('.delete-select');
            if (box && !box.disabled) box.checked = true;
        });
        updateDeleteSelectionState();
    });
}

if (selectMissingPlateButton) {
    selectMissingPlateButton.addEventListener('click', () => {
        allRows().forEach(row => {
            const box = row.querySelector('.delete-select');
            const hasNoPlate = rowPlateValue(row) === '';
            if (box && !box.disabled) {
                box.checked = hasNoPlate;
            }
        });
        updateDeleteSelectionState();
    });
}

if (clearDeleteSelectionButton) {
    clearDeleteSelectionButton.addEventListener('click', () => {
        getDeleteSelectionBoxes().forEach(box => {
            box.checked = false;
        });
        updateDeleteSelectionState();
    });
}

if (deleteSelectedEntriesButton) {
    deleteSelectedEntriesButton.addEventListener('click', () => {
        openBulkDeleteEntries(selectedDeleteRows());
    });
}

if (reprocessSelectedEntriesButton) {
    reprocessSelectedEntriesButton.addEventListener('click', async () => {
        const rows = selectedDeleteRows();
        if (!rows.length) {
            updateDeleteSelectionState();
            return;
        }

        reprocessSelectedEntriesButton.disabled = true;
        let completed = 0;
        let stillPending = 0;

        for (let i = 0; i < rows.length; i++) {
            const row = rows[i];
            const button = row.querySelector('.reprocess-entry');
            if (!(button instanceof HTMLButtonElement)) continue;
            if (entryFilterStatus) {
                entryFilterStatus.textContent = `Re-processing ${i + 1} of ${rows.length} selected entries...`;
            }
            const success = await reprocessEntry(button.dataset.id || '', button);
            success ? completed++ : stillPending++;
        }

        if (entryFilterStatus) {
            entryFilterStatus.textContent = `Re-processed ${completed} selected entr${completed === 1 ? 'y' : 'ies'}. ${stillPending} still need attention.`;
        }
        updateDeleteSelectionState();
        updateFailedSelectionState();
        applyEntrySearch();
    });
}

quickReasonButtons.forEach(button => {
    button.addEventListener('click', () => {
        if (!deleteEntryReason) return;
        deleteEntryReason.value = button.dataset.reason || '';
        deleteEntryReason.focus();
    });
});

if (closePhotoOverlayButton) {
    closePhotoOverlayButton.addEventListener('click', closePhotoOverlay);
}

if (closeEntryDetailsButton) {
    closeEntryDetailsButton.addEventListener('click', closeEntryDetails);
}

if (closeEntryEditorButton) {
    closeEntryEditorButton.addEventListener('click', closeEntryEditor);
}

if (cancelEntryEditorButton) {
    cancelEntryEditorButton.addEventListener('click', closeEntryEditor);
}

if (closeDeleteEntryButton) {
    closeDeleteEntryButton.addEventListener('click', closeDeleteEntry);
}

if (cancelDeleteEntryButton) {
    cancelDeleteEntryButton.addEventListener('click', closeDeleteEntry);
}

if (photoOverlay) {
    photoOverlay.addEventListener('click', event => {
        const target = event.target;
        if (target instanceof HTMLElement && target.dataset.closePhotoOverlay === 'true') {
            closePhotoOverlay();
        }
    });
}

if (entryEditorOverlay) {
    entryEditorOverlay.addEventListener('click', event => {
        const target = event.target;
        if (target instanceof HTMLElement && target.dataset.closeEntryEditor === 'true') {
            closeEntryEditor();
        }
    });
}

if (entryDetailsOverlay) {
    entryDetailsOverlay.addEventListener('click', event => {
        const target = event.target;
        if (target instanceof HTMLElement && target.dataset.closeEntryDetails === 'true') {
            closeEntryDetails();
        }
    });
}

if (deleteEntryOverlay) {
    deleteEntryOverlay.addEventListener('click', event => {
        const target = event.target;
        if (target instanceof HTMLElement && target.dataset.closeDeleteEntry === 'true') {
            closeDeleteEntry();
        }
    });
}

if (entryEditorForm) {
    entryEditorForm.addEventListener('submit', saveEntryEditor);
}

if (deleteEntryForm) {
    deleteEntryForm.addEventListener('submit', submitDeleteEntry);
}

document.addEventListener('keydown', event => {
    if (event.key === 'Escape' && photoOverlay && !photoOverlay.hidden) {
        closePhotoOverlay();
        return;
    }
    if (event.key === 'Escape' && entryEditorOverlay && !entryEditorOverlay.hidden) {
        closeEntryEditor();
        return;
    }
    if (event.key === 'Escape' && entryDetailsOverlay && !entryDetailsOverlay.hidden) {
        closeEntryDetails();
        return;
    }
    if (event.key === 'Escape' && deleteEntryOverlay && !deleteEntryOverlay.hidden) {
        closeDeleteEntry();
    }
});

if (entrySearch) {
    entrySearch.addEventListener('input', applyEntrySearch);
    entrySearch.addEventListener('search', applyEntrySearch);
    entrySearch.addEventListener('keyup', applyEntrySearch);
    entrySearch.addEventListener('change', applyEntrySearch);
}

if (selectAllFailedButton) {
    selectAllFailedButton.addEventListener('click', () => {
        const activeBoxes = getRetrySelectionBoxes().filter(box => !box.disabled);
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
