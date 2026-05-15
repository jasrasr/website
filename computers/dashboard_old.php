<?php
// dashboard.php - Device Heartbeat Dashboard (Revision 1.0)

// Folder where per-device JSON logs live
$logDir = __DIR__ . "/logs/";
$files = glob($logDir . "*.json");

$devices = [];

foreach ($files as $file) {
    $name = basename($file, ".json");

    $json = file_get_contents($file);
    if ($json === false) {
        continue;
    }

    $entries = json_decode($json, true);
    if (!is_array($entries) || count($entries) === 0) {
        continue;
    }

    // Latest entry = last element
    $last = end($entries);

    // Fallbacks
    $lastSeen = $last["ServerReceived"] ?? $last["TimeLocal"] ?? null;
    $lastSeenUnix = $lastSeen ? strtotime($lastSeen) : 0;

    $devices[] = [
        "name"       => $name,
        "model"      => $last["Model"] ?? "",
        "user"       => $last["UserName"] ?? "",
        "os"         => $last["OSVersion"] ?? "",
        "serial"     => $last["SerialNumber"] ?? "",
        "localip"    => $last["LocalIP"] ?? "",
        "publicip"   => $last["PublicIP"] ?? "",
        "lastSeen"   => $lastSeen ?: "unknown",
        "lastSeenTs" => $lastSeenUnix,
    ];
}

// Default sort: newest lastSeen first
usort($devices, function($a, $b) {
    return $b["lastSeenTs"] <=> $a["lastSeenTs"];
});

function h($str) {
    return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Device Heartbeat Dashboard</title>
    <style>
        body { font-family: system-ui, -apple-system, BlinkMacSystemFont, sans-serif; margin: 20px; }
        h1 { margin-bottom: 10px; }
        table { border-collapse: collapse; width: 100%; margin-top: 10px; }
        th, td { border: 1px solid #ccc; padding: 8px; font-size: 14px; }
        th { background: #eee; cursor: pointer; white-space: nowrap; }
        tr:nth-child(even) { background: #f9f9f9; }
        tr.ok { background: #e8ffe8; }
        tr.warn { background: #fff9e0; }
        tr.bad { background: #ffe8e8; }
        th.sort-asc::after { content: " ▲"; }
        th.sort-desc::after { content: " ▼"; }
        a { text-decoration: none; color: #0645ad; }
        a:hover { text-decoration: underline; }
        .small { font-size: 12px; color: #666; }
    </style>
</head>
<body>

<h1>Device Heartbeat Dashboard</h1>
<p class="small">
    Showing latest heartbeat per device. Click a column header to sort.  
    Click "View History" to see all entries for a computer.
</p>

<table id="devices-table">
    <thead>
    <tr>
        <th data-col="name" data-type="string">Computer</th>
        <th data-col="model" data-type="string">Model</th>
        <th data-col="user" data-type="string">User</th>
        <th data-col="os" data-type="string">OS Version</th>
        <th data-col="serial" data-type="string">Serial</th>
        <th data-col="localip" data-type="string">Local IP</th>
        <th data-col="publicip" data-type="string">Public IP</th>
        <th data-col="lastSeen" data-type="number">Last Seen</th>
        <th data-col="actions" data-type="string">Actions</th>
    </tr>
    </thead>
    <tbody>
    <?php foreach ($devices as $d):
        $age = $d["lastSeenTs"] > 0 ? (time() - $d["lastSeenTs"]) : PHP_INT_MAX;
        if ($age < 3600) {
            $rowclass = "ok";
        } elseif ($age < 86400) {
            $rowclass = "warn";
        } else {
            $rowclass = "bad";
        }
        ?>
        <tr class="<?= $rowclass ?>">
            <td data-value="<?= h($d["name"]) ?>"><?= h($d["name"]) ?></td>
            <td data-value="<?= h($d["model"]) ?>"><?= h($d["model"]) ?></td>
            <td data-value="<?= h($d["user"]) ?>"><?= h($d["user"]) ?></td>
            <td data-value="<?= h($d["os"]) ?>"><?= h($d["os"]) ?></td>
            <td data-value="<?= h($d["serial"]) ?>"><?= h($d["serial"]) ?></td>
            <td data-value="<?= h($d["localip"]) ?>"><?= h($d["localip"]) ?></td>
            <td data-value="<?= h($d["publicip"]) ?>"><?= h($d["publicip"]) ?></td>
            <td data-value="<?= (int)$d["lastSeenTs"] ?>"><?= h($d["lastSeen"]) ?></td>
            <td data-value="view">
                <a href="view.php?device=<?= urlencode($d["name"]) ?>">View History</a>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<script>
// Simple client-side table sorter
(function() {
    const table = document.getElementById('devices-table');
    const headers = table.querySelectorAll('th');
    let currentSort = { index: null, dir: 'asc' };

    headers.forEach((th, index) => {
        th.addEventListener('click', () => {
            const type = th.dataset.type || 'string';

            let dir = 'asc';
            if (currentSort.index === index && currentSort.dir === 'asc') {
                dir = 'desc';
            }
            currentSort = { index, dir };

            headers.forEach(h => h.classList.remove('sort-asc', 'sort-desc'));
            th.classList.add(dir === 'asc' ? 'sort-asc' : 'sort-desc');

            const tbody = table.querySelector('tbody');
            const rows = Array.from(tbody.querySelectorAll('tr'));

            rows.sort((a, b) => {
                const aCell = a.children[index];
                const bCell = b.children[index];

                const aValRaw = aCell.dataset.value ?? aCell.textContent.trim();
                const bValRaw = bCell.dataset.value ?? bCell.textContent.trim();

                let cmp = 0;
                if (type === 'number') {
                    const aNum = parseFloat(aValRaw) || 0;
                    const bNum = parseFloat(bValRaw) || 0;
                    cmp = aNum - bNum;
                } else {
                    const aStr = aValRaw.toLowerCase();
                    const bStr = bValRaw.toLowerCase();
                    if (aStr < bStr) cmp = -1;
                    else if (aStr > bStr) cmp = 1;
                    else cmp = 0;
                }

                return dir === 'asc' ? cmp : -cmp;
            });

            rows.forEach(r => tbody.appendChild(r));
        });
    });
})();
</script>

</body>
</html>
