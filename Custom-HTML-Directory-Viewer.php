<?php
/*
===========================================================
 File: index.php
 Author: Jason Lamb
 Created: 2026-01-05
 Revision: 1.3
 Description:
   Secure file listing for the /private directory.
   - Reads live filesystem data (name, size, modified date)
   - Hides index.* files and non-files
   - Default sort: Last Modified (newest first)
   - Clickable column sorting
   - Download button for each file
   - Intended to be protected by .htaccess + htpasswd
===========================================================
*/

$dir = __DIR__;

/*
 Exclude:
  - dot entries
  - index.* files
  - .htaccess
*/
$files = array_filter(scandir($dir), function ($file) {
    if ($file === '.' || $file === '..') return false;
    if (preg_match('/^index\./i', $file)) return false;
    if ($file === '.htaccess') return false;
    return true;
});

function formatSize($bytes) {
    $units = ['B', 'KB', 'MB', 'GB'];
    $i = 0;
    while ($bytes >= 1024 && $i < count($units) - 1) {
        $bytes /= 1024;
        $i++;
    }
    return round($bytes, 1) . ' ' . $units[$i];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Private Files</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <style>
        body {
            font-family: system-ui, -apple-system, Segoe UI, Roboto, sans-serif;
            margin: 40px;
        }
        table {
            border-collapse: collapse;
            width: 100%;
            max-width: 1000px;
        }
        th, td {
            padding: 10px 12px;
            border-bottom: 1px solid #ddd;
            text-align: left;
        }
        th {
            cursor: pointer;
            background: #f4f4f4;
            user-select: none;
        }
        th:hover {
            background: #eaeaea;
        }
        th.nosort {
            cursor: default;
        }
        a {
            text-decoration: none;
            color: #0066cc;
        }
        a:hover {
            text-decoration: underline;
        }
        .download-btn {
            display: inline-block;
            padding: 6px 10px;
            border: 1px solid #0066cc;
            border-radius: 4px;
            background: #fff;
            color: #0066cc;
            font-size: 0.9rem;
        }
        .download-btn:hover {
            background: #0066cc;
            color: #fff;
            text-decoration: none;
        }
    </style>
</head>
<body>

<h1>Secured Resume Files</h1>
<p>Default sort: newest files first. Click a column header to change.</p>

<table id="fileTable">
    <thead>
        <tr>
            <th>File Name</th>
            <th id="dateHeader">Last Modified ▼</th>
            <th>Size</th>
            <th class="nosort">Download</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($files as $file):
            $path = $dir . '/' . $file;
            if (!is_file($path)) continue;
        ?>
        <tr>
            <td data-sort="<?= strtolower($file) ?>">
                <a href="<?= htmlspecialchars($file) ?>">
                    <?= htmlspecialchars($file) ?>
                </a>
            </td>
            <td data-sort="<?= filemtime($path) ?>">
                <?= date('Y-m-d H:i', filemtime($path)) ?>
            </td>
            <td data-sort="<?= filesize($path) ?>">
                <?= formatSize(filesize($path)) ?>
            </td>
            <td>
                <a class="download-btn"
                   href="<?= htmlspecialchars($file) ?>"
                   download>
                    Download
                </a>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<script>
const tbody = document.querySelector("#fileTable tbody");
let asc = false; // default newest first

/* ---------- DEFAULT SORT: DATE DESC ---------- */
(function defaultSortByDateDesc() {
    const rows = Array.from(tbody.querySelectorAll("tr"));
    rows.sort((a, b) => {
        const aVal = a.children[1].dataset.sort;
        const bVal = b.children[1].dataset.sort;
        return bVal - aVal;
    });
    rows.forEach(row => tbody.appendChild(row));
})();

/* ---------- CLICK SORT HANDLER ---------- */
document.querySelectorAll("th").forEach(th => {
    if (th.classList.contains("nosort")) return;

    th.addEventListener("click", () => {
        const colIndex = th.cellIndex;
        const rows = Array.from(tbody.querySelectorAll("tr"));

        rows.sort((a, b) => {
            const aVal = a.children[colIndex].dataset.sort || "";
            const bVal = b.children[colIndex].dataset.sort || "";
            return asc
                ? aVal.localeCompare(bVal, undefined, { numeric: true })
                : bVal.localeCompare(aVal, undefined, { numeric: true });
        });

        asc = !asc;
        rows.forEach(row => tbody.appendChild(row));
    });
});
</script>

</body>
</html>
