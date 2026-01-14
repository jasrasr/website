<?php
/* ---------- Script metadata ---------- */
$scriptAuthor   = 'Jason Lamb';
$scriptCreated  = '2026-01-05';
$scriptRevision = '2.7';


/*
===========================================================
 File: index.php
 Author: Jason Lamb
 Created: 2026-01-05
 Revision: 2.7

 Description:
   Generic authenticated file browser with telemetry and
   user interaction features.

   Provides a secure, live filesystem listing intended
   for directories protected via .htaccess / htpasswd.

 Features:
   - Live filesystem scan
   - Exclusion rules for filenames and extensions
   - Favorites (⭐) persisted to favorites.json
   - Views, clicks, and downloads tracking via JSON
   - Download proxy for accurate download counts
   - Sortable columns with ▲ / ▼ indicators
   - Default sort: favorites first, then newest files
   - Extension/type column with color-coded badges
   - Icon + color pairing by file type
   - Last modified date and file size
   - Download button per file
   - Mobile-friendly, centered layout
   - Eastern Time (ET) timestamps

 Notes:
   - Hides index.* files, .htaccess, and internal helpers
   - No database required
   - Designed to be generic
===========================================================
*/


date_default_timezone_set('America/New_York');

$dir = __DIR__;

/* ---------- Exclusions ---------- */
$excludedFiles = [
    '.htaccess',
    'index.php',
    'download.php',
    'toggle_favorite.php',
    'favorites.json',
    'file_stats.json'
];

$excludedExtensions = ['php', 'json', 'log', 'bak', 'txt'];

/* ---------- Data files ---------- */
$favoritesFile = $dir . '/favorites.json';
$statsFile     = $dir . '/file_stats.json';

/* ---------- Load favorites ---------- */
$favorites = file_exists($favoritesFile)
    ? (json_decode(file_get_contents($favoritesFile), true)['favorites'] ?? [])
    : [];

/* ---------- Load stats ---------- */
$stats = file_exists($statsFile)
    ? json_decode(file_get_contents($statsFile), true)
    : [];

/* ---------- Scan directory ---------- */
$files = array_filter(scandir($dir), function ($file) use ($excludedFiles, $excludedExtensions) {

    if ($file === '.' || $file === '..') return false;
    if (preg_match('/^index\./i', $file)) return false;
    if (in_array($file, $excludedFiles, true)) return false;

    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    if ($ext && in_array($ext, $excludedExtensions, true)) return false;

    return is_file(__DIR__ . '/' . $file);
});

/* ---------- Track views ---------- */
foreach ($files as $file) {
    $stats[$file] ??= ['views' => 0, 'clicks' => 0, 'downloads' => 0];
    $stats[$file]['views']++;
}
file_put_contents($statsFile, json_encode($stats, JSON_PRETTY_PRINT));

/* ---------- Helpers ---------- */
function formatSize($bytes) {
    $units = ['B','KB','MB','GB'];
    for ($i = 0; $bytes >= 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }
    return round($bytes, 1) . ' ' . $units[$i];
}

function extensionColor($ext) {
    if (!$ext) return '#777';
    $hash = crc32($ext);
    return "hsl(" . ($hash % 360) . ",55%," . (40 + ($hash % 20)) . "%)";
}

function fileIcon($ext) {
    return match ($ext) {
        'pdf','doc','docx'     => '📄',
        'jpg','jpeg','png'    => '🖼️',
        'zip','7z','rar'      => '📦',
        'txt','md','log'      => '📝',
        'js','css','html'     => '💻',
        default               => '📁'
    };
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Files</title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<style>
body {
    font-family: system-ui, sans-serif;
    padding: 32px 16px;
    display: flex;
    justify-content: center;
}
.page { max-width: 1280px; width: 100%; }
.table-container { overflow-x: auto; }

table {
    width: 100%;
    min-width: 900px;
    border-collapse: collapse;
}

th, td {
    padding: 12px 14px;
    border-bottom: 1px solid #ddd;
    white-space: nowrap;
}

th {
    background: #f4f4f4;
    cursor: pointer;
    user-select: none;
}

th.nosort { cursor: default; }

th .arrow {
    margin-left: 6px;
    font-size: .7rem;
    visibility: hidden;
}

th.sorted .arrow { visibility: visible; }

.ext {
    padding: 4px 8px;
    border-radius: 4px;
    color: #fff;
    font-weight: 700;
    font-size: .75rem;
}

.star {
    cursor: pointer;
    font-size: 1.3rem;
    color: #ccc;
}
.star.active { color: gold; }

.download-btn {
    padding: 8px 14px;
    border: 1px solid #0066cc;
    border-radius: 6px;
    color: #0066cc;
}
.download-btn:hover {
    background: #0066cc;
    color: #fff;
}
</style>
</head>

<body>
<div class="page">

<h1>Files</h1>

<div class="table-container">
<table id="fileTable">
<thead>
<tr>
    <th data-col="favorite">★<span class="arrow"></span></th>
    <th data-col="name">File<span class="arrow"></span></th>
    <th data-col="type">Type<span class="arrow"></span></th>
    <th data-col="date">Modified<span class="arrow">▼</span></th>
    <th data-col="size">Size<span class="arrow"></span></th>
    <th class="nosort">Download</th>
</tr>
</thead>
<tbody>
<?php foreach ($files as $file):
    $path = $dir . '/' . $file;
    $ext  = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    $fav  = in_array($file, $favorites, true);
    $s    = $stats[$file];
?>
<tr
    data-favorite="<?= $fav ? 1 : 0 ?>"
    data-name="<?= strtolower($file) ?>"
    data-type="<?= $ext ?>"
    data-date="<?= filemtime($path) ?>"
    data-size="<?= filesize($path) ?>"
>
<td>
    <span class="star <?= $fav ? 'active' : '' ?>"
          data-file="<?= htmlspecialchars($file) ?>">★</span>
</td>
<td>
    <?= fileIcon($ext) ?>
    <a href="<?= htmlspecialchars($file) ?>"
       class="file-link"
       data-file="<?= htmlspecialchars($file) ?>"
       title="👁 <?= $s['views'] ?> · 👆 <?= $s['clicks'] ?> · ⬇ <?= $s['downloads'] ?>">
        <?= htmlspecialchars($file) ?>
    </a>
</td>
<td>
    <span class="ext" style="background:<?= extensionColor($ext) ?>">
        <?= strtoupper($ext ?: 'N/A') ?>
    </span>
</td>
<td><?= date('Y-m-d H:i', filemtime($path)) ?></td>
<td><?= formatSize(filesize($path)) ?></td>
<td>
    <a class="download-btn" href="download.php?file=<?= urlencode($file) ?>">Download</a>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>


<p style="color:#888;font-size:.8rem">
    Rev <?= $scriptRevision ?> · <?= date('Y-m-d H:i') ?> ET ·
    Excludes: <?= implode(', ', array_map('strtoupper', $excludedExtensions)) ?>
</p>



</div>

<script>
const tbody = document.querySelector("#fileTable tbody");
const headers = document.querySelectorAll("#fileTable thead th[data-col]");
let currentCol = 'date';
let sortAsc = false;

/* ---------- Sorting ---------- */
function sortTable(col) {
    const rows = [...tbody.querySelectorAll("tr")];
    rows.sort((a, b) => {
        const av = a.dataset[col];
        const bv = b.dataset[col];
        const an = parseFloat(av), bn = parseFloat(bv);
        if (!isNaN(an) && !isNaN(bn)) return sortAsc ? an - bn : bn - an;
        return sortAsc ? av.localeCompare(bv) : bv.localeCompare(av);
    });
    rows.forEach(r => tbody.appendChild(r));
}

function updateArrows(th) {
    headers.forEach(h => {
        h.classList.remove("sorted");
        h.querySelector(".arrow").textContent = '';
    });
    th.classList.add("sorted");
    th.querySelector(".arrow").textContent = sortAsc ? '▲' : '▼';
}

headers.forEach(th => {
    th.addEventListener("click", () => {
        const col = th.dataset.col;
        sortAsc = (col === currentCol) ? !sortAsc : false;
        currentCol = col;
        sortTable(col);
        updateArrows(th);
    });
});

/* Default sort */
sortTable('date');
updateArrows(document.querySelector('th[data-col="date"]'));

/* ---------- Favorites ---------- */
document.querySelectorAll(".star").forEach(star => {
    star.addEventListener("click", e => {
        e.stopPropagation();
        const file = star.dataset.file;

        fetch("toggle_favorite.php", {
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded" },
            body: "file=" + encodeURIComponent(file)
        })
        .then(r => r.json())
        .then(d => {
            if (!d.success) return;
            star.classList.toggle("active", d.favorite);
            star.closest("tr").dataset.favorite = d.favorite ? 1 : 0;
        });
    });
});
</script>

</body>
</html>
