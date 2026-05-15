<?php
// ============================================================================
// File: manage_entries.php
// Purpose: View, edit, and delete individual fuel log entries for a plate
// Revision: 2.1
// Author: Jason Lamb
// ============================================================================

error_reporting(E_ALL);
ini_set('display_errors', 1);

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/device_init.php';

if (!$isAdminTrusted) {
    die("<h2>Access denied.</h2>");
}

$plate = preg_replace('/[^A-Z0-9]/', '', strtoupper(trim($_GET['plate'] ?? $_POST['plate'] ?? '')));
if ($plate === '') die("<h2>No license plate specified.</h2>");

$logFile = __DIR__ . "/logs/{$plate}.json";
if (!file_exists($logFile)) die("<h2>No log file found for: " . htmlspecialchars($plate) . "</h2>");

// ─────────────────────────────────────────
// Recalculate miles and MPG for one entry
// (based on the previous entry's odometer)
// ─────────────────────────────────────────
function recalcEntry(&$entries, $i) {
    if (!isset($entries[$i])) return;
    $prevOdo = ($i > 0) ? (float)($entries[$i - 1]['odometer'] ?? 0) : 0;
    $odo     = (float)($entries[$i]['odometer'] ?? 0);
    $gallons = (float)($entries[$i]['gallons']  ?? 0);
    $miles   = ($prevOdo > 0 && $odo > $prevOdo) ? round($odo - $prevOdo, 1) : 0;
    $entries[$i]['miles'] = $miles;
    $entries[$i]['mpg']   = ($gallons > 0 && $miles > 0) ? round($miles / $gallons, 2) : 0;
}

function loadEntries($logFile) {
    $decoded = json_decode(file_get_contents($logFile), true);
    return is_array($decoded) ? array_values($decoded) : [];
}

function saveEntries($logFile, $entries) {
    file_put_contents($logFile, json_encode(array_values($entries), JSON_PRETTY_PRINT), LOCK_EX);
}

$message = '';

// ─────────────────────────────────────────
// Handle POST actions
// ─────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $index  = isset($_POST['index']) ? (int)$_POST['index'] : -1;
    $entries = loadEntries($logFile);

    if ($action === 'delete' && isset($entries[$index])) {
        array_splice($entries, $index, 1);
        // Recalculate the entry that now sits at $index (was the one after deleted)
        if (isset($entries[$index])) recalcEntry($entries, $index);
        saveEntries($logFile, $entries);
        $message = "Entry #{$index} deleted and miles recalculated.";

    } elseif ($action === 'save' && isset($entries[$index])) {
        $entries[$index]['date']            = trim($_POST['date'] ?? $entries[$index]['date']);
        $entries[$index]['odometer']        = (float)($_POST['odometer']        ?? $entries[$index]['odometer']);
        $entries[$index]['gallons']         = (float)($_POST['gallons']         ?? $entries[$index]['gallons']);
        $entries[$index]['price_per_gallon']= (float)($_POST['price_per_gallon']?? $entries[$index]['price_per_gallon']);
        $entries[$index]['total_cost']      = (float)($_POST['total_cost']      ?? $entries[$index]['total_cost']);
        $entries[$index]['verified']        = ($_POST['verified'] ?? 'no') === 'yes' ? 'yes' : 'no';

        // Recalculate this entry and the one after it
        recalcEntry($entries, $index);
        if (isset($entries[$index + 1])) recalcEntry($entries, $index + 1);

        saveEntries($logFile, $entries);
        $message = "Entry #{$index} saved. Miles and MPG recalculated.";

    } elseif ($action === 'save_json') {
        $raw = $_POST['raw_json'] ?? '';
        $parsed = json_decode($raw, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $message = "ERROR: Invalid JSON — " . json_last_error_msg() . ". Nothing was saved.";
        } elseif (!is_array($parsed)) {
            $message = "ERROR: JSON must be an array of entries.";
        } else {
            file_put_contents($logFile, json_encode(array_values($parsed), JSON_PRETTY_PRINT), LOCK_EX);
            $message = "Raw JSON saved successfully (" . count($parsed) . " entries).";
        }
    }

    header("Location: manage_entries.php?plate={$plate}&msg=" . urlencode($message));
    exit;
}

$entries = loadEntries($logFile);

// Sort display by date (preserves original numeric keys for edit/delete)
$sortDir = ($_GET['sort'] ?? 'asc') === 'desc' ? 'desc' : 'asc';
uasort($entries, function($a, $b) use ($sortDir) {
    $cmp = strcmp($a['date'] ?? '', $b['date'] ?? '');
    return $sortDir === 'desc' ? -$cmp : $cmp;
});

$editIndex = isset($_GET['edit']) ? (int)$_GET['edit'] : -1;
$msgDisplay = htmlspecialchars($_GET['msg'] ?? '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Manage Entries – <?php echo htmlspecialchars($plate); ?></title>
<style>
body{font-family:sans-serif;max-width:1200px;margin:auto;padding:1.5rem;}
table{width:100%;border-collapse:collapse;margin-top:1.5rem;font-size:0.88rem;}
th,td{border:1px solid #ccc;padding:0.45rem 0.5rem;text-align:center;}
th{background:#f2f2f2;}
.badge-yes{color:green;font-weight:bold;}
.badge-no{color:#c00;font-weight:bold;}
.btn{border:none;padding:5px 10px;border-radius:4px;cursor:pointer;font-size:0.82rem;}
.btn-verify{background:#28a745;color:white;}
.btn-edit  {background:#007bff;color:white;}
.btn-delete{background:#dc3545;color:white;}
.btn:hover{opacity:0.85;}
a{color:#007bff;text-decoration:none;}
.msg{background:#d4edda;color:#155724;padding:0.6rem 1rem;border-radius:6px;margin-bottom:1rem;}
.edit-card{background:#fff8e1;border:1px solid #ffc107;border-radius:8px;padding:1.2rem;margin:1.5rem 0;}
.edit-card h3{margin:0 0 1rem 0;}
.edit-grid{display:grid;grid-template-columns:1fr 1fr;gap:0.7rem 1.2rem;}
.edit-grid label{font-size:0.88rem;color:#444;}
.edit-grid input, .edit-grid select{width:100%;padding:0.35rem 0.5rem;border:1px solid #ccc;border-radius:4px;font-size:0.92rem;}
.edit-actions{margin-top:1rem;display:flex;gap:0.7rem;}
.btn-save{background:#28a745;color:white;padding:0.5rem 1.2rem;font-size:0.95rem;}
.btn-cancel{background:#6c757d;color:white;padding:0.5rem 1rem;font-size:0.95rem;text-decoration:none;display:inline-block;border-radius:4px;}
.note{font-size:0.8rem;color:#666;margin-top:0.4rem;}
</style>
</head>
<body>

<h2>Manage Entries — <?php echo htmlspecialchars($plate); ?></h2>
<p><a href="admin.php">← Back to Admin Panel</a></p>

<?php if ($msgDisplay): ?>
<div class="msg">✓ <?php echo $msgDisplay; ?></div>
<?php endif; ?>

<?php
// ─────────────────────────────────────────
// Edit form (shown inline when ?edit=N)
// ─────────────────────────────────────────
if ($editIndex >= 0 && isset($entries[$editIndex])):
    $e = $entries[$editIndex];
?>
<div class="edit-card">
    <h3>Edit Entry #<?php echo $editIndex; ?></h3>
    <form method="post" action="manage_entries.php">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="plate"  value="<?php echo htmlspecialchars($plate); ?>">
        <input type="hidden" name="index"  value="<?php echo $editIndex; ?>">
        <div class="edit-grid">
            <div>
                <label>Date</label>
                <input type="date" name="date" value="<?php echo htmlspecialchars($e['date'] ?? ''); ?>">
            </div>
            <div>
                <label>Odometer</label>
                <input type="number" name="odometer" step="0.1" value="<?php echo $e['odometer'] ?? ''; ?>">
            </div>
            <div>
                <label>Gallons</label>
                <input type="number" name="gallons" step="0.001" value="<?php echo $e['gallons'] ?? ''; ?>">
            </div>
            <div>
                <label>Price per Gallon ($)</label>
                <input type="number" name="price_per_gallon" step="0.001" value="<?php echo $e['price_per_gallon'] ?? ''; ?>">
            </div>
            <div>
                <label>Total Cost ($)</label>
                <input type="number" name="total_cost" step="0.01" value="<?php echo $e['total_cost'] ?? ''; ?>">
            </div>
            <div>
                <label>Verified</label>
                <select name="verified">
                    <option value="no"  <?php echo ($e['verified'] ?? 'no') === 'no'  ? 'selected' : ''; ?>>No</option>
                    <option value="yes" <?php echo ($e['verified'] ?? 'no') === 'yes' ? 'selected' : ''; ?>>Yes</option>
                </select>
            </div>
        </div>
        <p class="note">Miles and MPG are recalculated automatically on save. If you change the odometer, the next entry's miles/MPG will also be updated.</p>
        <div class="edit-actions">
            <button type="submit" class="btn btn-save">💾 Save Changes</button>
            <a href="manage_entries.php?plate=<?php echo urlencode($plate); ?>" class="btn-cancel">Cancel</a>
        </div>
    </form>
</div>
<?php endif; ?>

<table>
<thead>
<tr>
    <th>#</th>
    <th><a href="?plate=<?php echo urlencode($plate); ?>&sort=<?php echo $sortDir === 'asc' ? 'desc' : 'asc'; ?>" style="color:inherit;text-decoration:none;">Date <?php echo $sortDir === 'asc' ? '▲' : '▼'; ?></a></th>
    <th>Odometer</th>
    <th>Miles</th>
    <th>Gallons</th>
    <th>Price/Gal</th>
    <th>Total</th>
    <th>MPG</th>
    <th>Submitted (ET)</th>
    <th>Source</th>
    <th>Verified</th>
    <th>Actions</th>
</tr>
</thead>
<tbody>
<?php foreach ($entries as $i => $entry):
    $verified   = strtolower($entry['verified'] ?? 'no');
    $isVerified = ($verified === 'yes');
    $rowStyle   = ($i === $editIndex) ? ' style="background:#fff8e1;"' : '';
?>
<tr<?php echo $rowStyle; ?>>
    <td><?php echo $i; ?></td>
    <td><?php echo htmlspecialchars($entry['date'] ?? '—'); ?></td>
    <td><?php echo htmlspecialchars($entry['odometer'] ?? '—'); ?></td>
    <td><?php echo htmlspecialchars($entry['miles'] ?? '—'); ?></td>
    <td><?php echo htmlspecialchars($entry['gallons'] ?? '—'); ?></td>
    <td><?php echo isset($entry['price_per_gallon']) ? '$' . number_format((float)$entry['price_per_gallon'], 3) : '—'; ?></td>
    <td><?php echo isset($entry['total_cost']) ? '$' . number_format((float)$entry['total_cost'], 2) : '—'; ?></td>
    <td><?php echo htmlspecialchars($entry['mpg'] ?? '—'); ?></td>
    <td style="font-size:0.8rem;"><?php echo htmlspecialchars($entry['submitted_et'] ?? '—'); ?></td>
    <td><?php $src = $entry['source'] ?? 'manual'; echo $src === 'scan' ? '📷' : '⌨️'; ?></td>
    <td><?php echo $isVerified ? '<span class="badge-yes">Yes</span>' : '<span class="badge-no">No</span>'; ?></td>
    <td style="white-space:nowrap;">
        <?php if (!$isVerified): ?>
        <form method="post" action="verify_entry.php" style="display:inline;">
            <input type="hidden" name="plate" value="<?php echo htmlspecialchars($plate); ?>">
            <input type="hidden" name="index" value="<?php echo $i; ?>">
            <button type="submit" class="btn btn-verify">✔ Verify</button>
        </form>
        <?php endif; ?>
        <a href="manage_entries.php?plate=<?php echo urlencode($plate); ?>&edit=<?php echo $i; ?>" class="btn btn-edit">✏️ Edit</a>
        <form method="post" action="manage_entries.php" style="display:inline;"
              onsubmit="return confirm('Delete entry #<?php echo $i; ?> (<?php echo htmlspecialchars($entry['date'] ?? ''); ?>)? Miles for the next entry will be recalculated.');">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="plate"  value="<?php echo htmlspecialchars($plate); ?>">
            <input type="hidden" name="index"  value="<?php echo $i; ?>">
            <button type="submit" class="btn btn-delete">🗑 Delete</button>
        </form>
    </td>
</tr>
<?php endforeach; ?>
</tbody>
<tfoot>
<tr>
    <th>#</th>
    <th>Date</th>
    <th>Odometer</th>
    <th>Miles</th>
    <th>Gallons</th>
    <th>Price/Gal</th>
    <th>Total</th>
    <th>MPG</th>
    <th>Submitted (ET)</th>
    <th>Source</th>
    <th>Verified</th>
    <th>Actions</th>
</tr>
</tfoot>
</table>

<!-- Raw JSON Editor -->
<div style="margin-top:2.5rem;">
    <h3 style="cursor:pointer;user-select:none;" onclick="toggleJson()" id="jsonToggleHeader">
        ▶ Raw JSON Editor <span style="font-size:0.8rem;color:#888;font-weight:normal;">(click to expand)</span>
    </h3>
    <div id="jsonEditor" style="display:none;">
        <p style="font-size:0.85rem;color:#666;margin-top:0;">
            ⚠️ Edit with care — no automatic recalculation here. Saves directly to the JSON file.
        </p>
        <div id="jsonError" style="display:none;background:#f8d7da;color:#721c24;padding:0.6rem;border-radius:6px;margin-bottom:0.6rem;font-size:0.88rem;"></div>
        <textarea id="jsonTextarea" spellcheck="false" style="width:100%;height:420px;font-family:monospace;font-size:0.82rem;border:1px solid #ccc;border-radius:6px;padding:0.7rem;resize:vertical;"><?php echo htmlspecialchars(file_get_contents($logFile)); ?></textarea>
        <div style="margin-top:0.6rem;display:flex;gap:0.7rem;align-items:center;">
            <button onclick="saveJson()" style="background:#dc3545;color:white;border:none;padding:0.55rem 1.2rem;border-radius:6px;cursor:pointer;font-size:0.95rem;">💾 Save Raw JSON</button>
            <button onclick="formatJson()" style="background:#6c757d;color:white;border:none;padding:0.55rem 1rem;border-radius:6px;cursor:pointer;font-size:0.95rem;">{ } Format</button>
            <span id="jsonStatus" style="font-size:0.85rem;color:#28a745;"></span>
        </div>
    </div>
</div>

<form id="jsonSaveForm" method="post" action="manage_entries.php" style="display:none;">
    <input type="hidden" name="action" value="save_json">
    <input type="hidden" name="plate" value="<?php echo htmlspecialchars($plate); ?>">
    <input type="hidden" name="raw_json" id="jsonHidden">
</form>

<script>
function toggleJson() {
    const el = document.getElementById('jsonEditor');
    const hdr = document.getElementById('jsonToggleHeader');
    const open = el.style.display === 'none';
    el.style.display = open ? 'block' : 'none';
    hdr.innerHTML = (open ? '▼' : '▶') + ' Raw JSON Editor <span style="font-size:0.8rem;color:#888;font-weight:normal;">(click to ' + (open ? 'collapse' : 'expand') + ')</span>';
}

function formatJson() {
    const ta = document.getElementById('jsonTextarea');
    try {
        const parsed = JSON.parse(ta.value);
        ta.value = JSON.stringify(parsed, null, 4);
        document.getElementById('jsonStatus').textContent = '✓ Formatted';
        document.getElementById('jsonError').style.display = 'none';
    } catch (e) {
        showJsonError('Invalid JSON: ' + e.message);
    }
}

function saveJson() {
    const ta = document.getElementById('jsonTextarea');
    try {
        const parsed = JSON.parse(ta.value);
        if (!Array.isArray(parsed)) { showJsonError('JSON must be an array [ ... ]'); return; }
        if (!confirm('Save raw JSON? This overwrites all ' + parsed.length + ' entries directly.')) return;
        document.getElementById('jsonHidden').value = JSON.stringify(parsed);
        document.getElementById('jsonSaveForm').submit();
    } catch (e) {
        showJsonError('Invalid JSON: ' + e.message);
    }
}

function showJsonError(msg) {
    const el = document.getElementById('jsonError');
    el.textContent = '⚠️ ' + msg;
    el.style.display = 'block';
}

// Live JSON validation as you type
document.getElementById('jsonTextarea').addEventListener('input', () => {
    const ta = document.getElementById('jsonTextarea');
    const status = document.getElementById('jsonStatus');
    try {
        JSON.parse(ta.value);
        status.textContent = '✓ Valid JSON';
        status.style.color = '#28a745';
        document.getElementById('jsonError').style.display = 'none';
    } catch (e) {
        status.textContent = '✗ Invalid JSON';
        status.style.color = '#dc3545';
    }
});
</script>

<?php include 'menu.php'; ?>
</body>
</html>
