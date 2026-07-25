<?php
/*
    License Plate Photo Logger
    Revision: 1.2.19
    Description: Deleted-item audit page with Eastern timestamp display, retention age, permanent delete actions, purge logging, purge controls, and a project revision badge.
*/
require_once __DIR__ . '/config.php';
ensureAppFolders();
$projectRevision = readProjectRevision();
$projectModifiedAt = readProjectModifiedAt();

$flash = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');
    if ($action === 'permanent_selected') {
        $auditIds = array_values(array_filter(array_map('strval', $_POST['audit_ids'] ?? []), fn($id) => trim($id) !== ''));
        $result = permanentlyDeleteAuditItems($auditIds);
        $flash = 'Permanently deleted ' . $result['deleted'] . ' audit item' . ($result['deleted'] === 1 ? '' : 's') . '.';
    } elseif ($action === 'permanent_one') {
        $auditId = trim((string)($_POST['audit_id'] ?? ''));
        $result = permanentlyDeleteAuditItems([$auditId]);
        $flash = 'Permanently deleted ' . $result['deleted'] . ' audit item' . ($result['deleted'] === 1 ? '' : 's') . '.';
    } elseif ($action === 'purge_all') {
        $result = purgeDeletedAudit('manual');
        $flash = 'Purged ' . $result['purged'] . ' deleted audit item' . ($result['purged'] === 1 ? '' : 's') . '.';
    }
}

$auditEntries = readDeletedAuditEntries();
$purgeLogEntries = readDeletedPurgeLogEntries();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Deleted Audit</title>
<link rel="stylesheet" href="style.css">
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
    <h1>Deleted Audit</h1>

    <?php if ($flash !== ''): ?>
    <section class="card">
        <p class="small audit-flash"><?= h($flash) ?></p>
    </section>
    <?php endif; ?>

    <section class="card">
        <h2>Retention</h2>
        <p class="small">Deleted items are moved into `deleted/` when possible, kept in this audit trail with the reason entered at delete time, and can be permanently removed individually, in bulk, or through a full purge.</p>
        <form method="post" onsubmit="return confirmPurge(event);">
            <input type="hidden" name="action" value="purge_all">
            <button type="submit" class="danger">Purge Deleted Folder And Audit</button>
        </form>
    </section>

    <section class="card">
        <h2>Purge Log</h2>
        <?php if (empty($purgeLogEntries)): ?>
            <p class="small">No deleted-audit purges have been logged.</p>
        <?php else: ?>
            <div class="table-wrap">
            <table class="sortable-table purge-log-table">
                <thead>
                    <tr>
                        <th>Purged</th>
                        <th>Trigger</th>
                        <th>Items</th>
                        <th>Archived Photos Removed</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($purgeLogEntries as $entry): ?>
                    <tr>
                        <td><?= h(displayEasternDateTime((string)($entry['purged_at'] ?? ''))) ?></td>
                        <td><?= h((string)($entry['trigger'] ?? '')) ?></td>
                        <td><?= h((string)($entry['purged_items'] ?? '')) ?></td>
                        <td><?= h((string)($entry['removed_files_count'] ?? '')) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        <?php endif; ?>
    </section>

    <section class="card">
        <h2>Deleted Items</h2>
        <?php if (empty($auditEntries)): ?>
            <p class="small">No deleted items are in the audit log.</p>
        <?php else: ?>
            <form method="post" id="deletedAuditForm" onsubmit="return confirmPermanentDelete(event);">
                <input type="hidden" name="action" value="permanent_selected">
                <div class="actions">
                    <button type="button" id="selectAllDeletedAudit" class="secondary">Select All</button>
                    <button type="submit" class="danger">Permanently Delete Selected</button>
                </div>
                <div class="table-wrap">
                <table class="sortable-table audit-table">
                    <thead>
                        <tr>
                            <th>Select</th>
                            <th>Deleted</th>
                            <th>Days</th>
                            <th>Reason</th>
                            <th>Original File</th>
                            <th>Plate</th>
                            <th>State</th>
                            <th>Photo</th>
                            <th>Audit Note</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($auditEntries as $entry): ?>
                        <?php
                        $daysDeleted = daysSinceIsoTimestamp((string)($entry['deleted_at'] ?? ''));
                        $deletedAtDisplay = displayEasternDateTime((string)($entry['deleted_at'] ?? ''));
                        $deletedPath = (string)($entry['deleted_relative_path'] ?? '');
                        ?>
                        <tr>
                            <td><input type="checkbox" name="audit_ids[]" value="<?= h((string)($entry['audit_id'] ?? '')) ?>" class="deleted-audit-select"></td>
                            <td><?= h($deletedAtDisplay) ?></td>
                            <td><?= h($daysDeleted === null ? '' : (string)$daysDeleted) ?></td>
                            <td><?= h((string)($entry['deleted_reason'] ?? '')) ?></td>
                            <td><?= h((string)($entry['original_file'] ?? '')) ?></td>
                            <td><?= h((string)($entry['plate'] ?? '')) ?></td>
                            <td><?= h((string)($entry['plate_state'] ?? '')) ?></td>
                            <td>
                                <?php if ($deletedPath !== ''): ?>
                                    <a href="<?= h($deletedPath) ?>" target="_blank" rel="noopener">photo</a>
                                <?php endif; ?>
                            </td>
                            <td><?= h((string)($entry['file_note'] ?? '')) ?></td>
                            <td>
                                <button
                                    type="submit"
                                    class="danger"
                                    name="audit_id"
                                    value="<?= h((string)($entry['audit_id'] ?? '')) ?>"
                                    onclick="document.querySelector('#deletedAuditForm input[name=action]').value='permanent_one'; return confirm('Permanently delete this deleted item and remove its archived photo if present?');"
                                >Permanent Delete</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
            </form>
        <?php endif; ?>
    </section>
</main>
<script>
const selectAllDeletedAuditButton = document.getElementById('selectAllDeletedAudit');
const deletedAuditCheckboxes = Array.from(document.querySelectorAll('.deleted-audit-select'));
const deletedAuditForm = document.getElementById('deletedAuditForm');

if (selectAllDeletedAuditButton) {
    selectAllDeletedAuditButton.addEventListener('click', () => {
        const allSelected = deletedAuditCheckboxes.length > 0 && deletedAuditCheckboxes.every(box => box.checked);
        deletedAuditCheckboxes.forEach(box => {
            box.checked = !allSelected;
        });
        selectAllDeletedAuditButton.textContent = allSelected ? 'Select All' : 'Clear Selection';
    });
}

function confirmPermanentDelete(event) {
    if (!deletedAuditForm) return true;
    const actionInput = deletedAuditForm.querySelector('input[name="action"]');
    if (!actionInput || actionInput.value !== 'permanent_selected') {
        return true;
    }
    const selected = deletedAuditCheckboxes.filter(box => box.checked).length;
    if (selected === 0) {
        event.preventDefault();
        return false;
    }
    return confirm(`Permanently delete ${selected} selected deleted item${selected === 1 ? '' : 's'}?`);
}

function confirmPurge(event) {
    return confirm('Purge all deleted audit items and remove all archived deleted photos?');
}
</script>
</body>
</html>
