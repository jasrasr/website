<?php
/* 
===========================================================
 dashboard.php – Device Heartbeat Dashboard
 Revision: 2.5
 Updated: 2025-11-13
 Author: Jason Lamb + ChatGPT

 CHANGE HISTORY
 ----------------------------------------------------------
 Rev 2.5 — 2025-11-13
 • Added "Last User Seen" summary bar
 • Added relative + EST timestamp formatting
 • Added search box
 • Centralized time conversion helpers

 Rev 2.4 — 2025-11-13
 • Restored sortable column headers
 • Polished UI adjustments

 Rev 2.3 — 2025-11-13
 • Added status dots (green/yellow/red)
 • Added Entry Count column
 • Added model + serial + username

 Rev 2.2 — 2025-11-13
 • Improved data loading reliability

 Rev 2.1 — 2025-11-13
 • Fixed username display issue

 Rev 1.0 — 2025-11-12
 • Initial dashboard version
===========================================================
*/

// ---- Time Conversion Helpers ----
function convertUtcToEst($utcString) {
    if (!$utcString) return "";
    $dt = new DateTime($utcString, new DateTimeZone("UTC"));
    $dt->setTimezone(new DateTimeZone("America/New_York"));
    return $dt->format("Y-m-d H:i:s T"); // 24-hour + timezone
}

function relativeTime($utcString) {
    if (!$utcString) return "";
    $timestamp = strtotime($utcString);
    $diff = time() - $timestamp;

    if ($diff < 60) return $diff . " seconds ago";
    if ($diff < 3600) return floor($diff / 60) . " minutes ago";
    if ($diff < 86400) return floor($diff / 3600) . " hours ago";

    return floor($diff / 86400) . " days ago";
}

function formatTimestamp($utcString) {
    return relativeTime($utcString) . " — " . convertUtcToEst($utcString);
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Device Heartbeat Dashboard</title>
<style>
    body { font-family: Arial; margin: 20px; }
    h1 { text-align: center; }

    table { width: 100%; border-collapse: collapse; margin-top: 10px; }

    th, td {
        border-bottom: 1px solid #ddd;
        padding: 10px;
        text-align: left;
    }

    th.sortable { cursor: pointer; background-color: #f5f5f5; }
    th.sortable:hover { background-color: #eaeaea; }

    .status-dot { font-size: 20px; }
    .badge {
        background-color: #007bff;
        color: white;
        padding: 2px 8px;
        border-radius: 10px;
        font-size: 12px;
    }

    tr:hover { background-color: #fafafa; }

    #searchBox {
        width: 300px;
        padding: 8px;
        margin-bottom: 15px;
        font-size: 14px;
    }
</style>
</head>
<body>

<h1>Device Heartbeat Dashboard</h1>

<input type="text" id="searchBox" placeholder="Search devices…">

<?php
$logDir = __DIR__ . "/logs/";
$files = glob($logDir . "*.json");

$rows = [];

// --- Load all devices ---
foreach ($files as $file) {
    $device = basename($file, ".json");
    $json = json_decode(file_get_contents($file), true);
    if (!$json || count($json) === 0) continue;

    $last = end($json);
    $count = count($json);

    // Determine status
    $lastSeenUTC = strtotime($last["ServerReceived"]);
    $age = time() - $lastSeenUTC;

    if ($age < 120)       $status = "🟢"; // online
    elseif ($age < 900)   $status = "🟡"; // warning
    else                  $status = "🔴"; // offline

    $rows[] = [
        "status"     => $status,
        "device"     => $device,
        "model"      => $last["Model"] ?? "",
        "user"       => $last["UserName"] ?? "",
        "os"         => $last["OSVersion"] ?? "",
        "serial"     => $last["SerialNumber"] ?? "",
        "entries"    => $count,
        "lastseen"   => $last["ServerReceived"] ?? ""
    ];
}

// ==================================================================
// LAST USER SEEN SUMMARY
// ==================================================================
$lastUser = "";
$lastDevice = "";
$lastTime = "";
$newestTimestamp = 0;

foreach ($files as $file) {
    $device = basename($file, ".json");
    $json = json_decode(file_get_contents($file), true);
    if (!$json) continue;

    foreach ($json as $entry) {
        if (!isset($entry["ServerReceived"])) continue;

        $ts = strtotime($entry["ServerReceived"]);
        if ($ts > $newestTimestamp) {
            $newestTimestamp = $ts;
            $lastUser = $entry["UserName"] ?? "";
            $lastDevice = $device;
            $lastTime = $entry["ServerReceived"];
        }
    }
}

if ($lastDevice):
?>
    <div style="
        padding: 10px;
        background: #f0f7ff;
        border: 1px solid #cce0ff;
        margin-bottom: 10px;
        border-radius: 6px;
        font-size: 15px;">
        <strong>Last User Seen:</strong>
        <?= htmlspecialchars($lastUser) ?>
        on <strong><?= htmlspecialchars($lastDevice) ?></strong>
        — <?= formatTimestamp($lastTime) ?>
        &nbsp;
        <a href="view.php?device=<?= urlencode($lastDevice) ?>">(View History)</a>
    </div>
<?php endif; ?>

<table>
    <thead>
        <tr>
            <th class="sortable">Status</th>
            <th class="sortable">Computer</th>
            <th class="sortable">Model</th>
            <th class="sortable">User</th>
            <th class="sortable">OS</th>
            <th class="sortable">Serial</th>
            <th class="sortable">Entries</th>
            <th class="sortable">Last Seen</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($rows as $r): ?>
        <tr>
            <td class="status-dot"><?= $r["status"] ?></td>
            <td><?= htmlspecialchars($r["device"]) ?></td>
            <td><?= htmlspecialchars($r["model"]) ?></td>
            <td><?= htmlspecialchars($r["user"]) ?></td>
            <td><?= htmlspecialchars($r["os"]) ?></td>
            <td><?= htmlspecialchars($r["serial"]) ?></td>
            <td><span class="badge"><?= $r["entries"] ?></span></td>
            <td><?= formatTimestamp($r["lastseen"]) ?></td>
            <td><a href="view.php?device=<?= urlencode($r["device"]) ?>">View History</a></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<script>
// --- Search filter ---
document.getElementById("searchBox").addEventListener("input", function() {
    const filter = this.value.toLowerCase();
    const rows = document.querySelectorAll("tbody tr");
    rows.forEach(row => {
        const text = row.innerText.toLowerCase();
        row.style.display = text.includes(filter) ? "" : "none";
    });
});

// --- Sorting ---
document.querySelectorAll("th.sortable").forEach(th => {
    th.addEventListener("click", () => {
        const table = th.closest("table");
        const tbody = table.querySelector("tbody");
        const index = Array.from(th.parentNode.children).indexOf(th);
        const ascending = th.classList.toggle("asc");

        const rows = Array.from(tbody.querySelectorAll("tr"));

        rows.sort((a, b) => {
            const A = a.children[index].innerText.trim();
            const B = b.children[index].innerText.trim();

            const numA = parseFloat(A);
            const numB = parseFloat(B);

            if (!isNaN(numA) && !isNaN(numB)) return ascending ? numA - numB : numB - numA;
            if (!isNaN(Date.parse(A)) && !isNaN(Date.parse(B))) return ascending ? new Date(A) - new Date(B) : new Date(B) - new Date(A);

            return ascending ? A.localeCompare(B) : B.localeCompare(A);
        });

        rows.forEach(r => tbody.appendChild(r));
    });
});
</script>

</body>
</html>
