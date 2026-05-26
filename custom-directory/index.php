<?php
/*
===========================================================
 File: index.php
 Author: Jason Lamb
 Created: 2026-01-05
 Revision: 2.4
 Description:
   Generic authenticated file browser.
   - Icon + color pairing by file type
   - Dynamic, stable colors for all extensions
   - Favorites persisted via JSON
   - Download buttons
   - Default sort: newest files first
   - Clickable column sorting
   - Eastern Time (ET) display
   - Centered layout + mobile-friendly
===========================================================
*/

date_default_timezone_set('America/New_York');

$dir = __DIR__;
$favoritesFile = $dir . '/favorites.json';

/* ---------- Load favorites ---------- */
$favoritesData = file_exists($favoritesFile)
    ? json_decode(file_get_contents($favoritesFile), true)
    : ['favorites' => []];

$favorites = $favoritesData['favorites'] ?? [];

/* ---------- Scan directory ---------- */
$files = array_filter(scandir($dir), function ($file) {
    if ($file === '.' || $file === '..') return false;
    if (preg_match('/^index\./i', $file)) return false;
    if (in_array($file, ['.htaccess', 'favorites.json', 'toggle_favorite.php'])) return false;
    return true;
});

/* ---------- Helpers ---------- */
function formatSize($bytes) {
    $units = ['B','KB','MB','GB'];
    $i = 0;
    while ($bytes >= 1024 && $i < count($units) - 1) {
        $bytes /= 1024;
        $i++;
    }
    return round($bytes, 1) . ' ' . $units[$i];
}

function extensionColor($ext) {
    if (!$ext) return '#777';
    $hash = crc32($ext);
    $hue = $hash % 360;
    $light = 40 + ($hash % 20);
    return "hsl($hue, 55%, {$light}%)";
}

function fileCategory($ext) {
    return match ($ext) {
        'pdf', 'doc', 'docx'                    => 'doc',
        'jpg', 'jpeg', 'png', 'gif', 'webp'     => 'image',
        'js', 'html', 'css', 'php', 'py', 'ps1' => 'code',
        'txt', 'md', 'log'                      => 'text',
        'zip', '7z', 'rar'                      => 'archive',
        default                                 => 'other'
    };
}

function categoryIcon($category) {
    return match ($category) {
        'doc'     => '📄',
        'image'   => '🖼️',
        'code'    => '💻',
        'text'    => '📝',
        'archive' => '📦',
        default   => '📁'
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
/* ---------- Page ---------- */
body {
    font-family: system-ui, -apple-system, Segoe UI, Roboto, sans-serif;
    margin: 0;
    padding: 32px 16px;
    display: flex;
    justify-content: center;
}

/* ---------- Layout wrapper ---------- */
.page {
    width: 100%;
    max-width: 1280px;
}

/* ---------- Table container ---------- */
.table-container {
    overflow-x: auto;
}

/* ---------- Table ---------- */
table {
    border-collapse: collapse;
    width: 100%;
    min-width: 900px; /* allows horizontal scroll on mobile */
}

th, td {
    padding: 12px 14px;
    border-bottom: 1px solid #ddd;
    white-space: nowrap;
}

th {
    background: #f4f4f4;
    cursor: pointer;
}

th.nosort {
    cursor: default;
}

/* ---------- Links ---------- */
a {
    color: #0066cc;
    text-decoration: none;
}
a:hover {
    text-decoration: underline;
}

/* ---------- Extension badge ---------- */
.ext {
    display: inline-block;
    min-width: 52px;
    text-align: center;
    padding: 4px 8px;
    border-radius: 4px;
    font-weight: 700;
    font-size: 0.75rem;
    color: #fff;
}

/* ---------- File icon ---------- */
.file-icon {
    margin-right: 6px;
    font-size: 1.1rem;
    vertical-align: middle;
}

/* ---------- Download button ---------- */
.download-btn {
    padding: 8px 14px;
    border: 1px solid #0066cc;
    border-radius: 6px;
    background: #fff;
    color: #0066cc;
    font-size: 0.95rem;
}
.download-btn:hover {
    background: #0066cc;
    color: #fff;
}

/* ---------- Favorite star ---------- */
.star {
    cursor: pointer;
    font-size: 1.3rem;
    color: #ccc;
}
.star.active {
    color: gold;
}
.star:hover {
    color: orange;
}

/* ---------- Footer ---------- */
.footer {
    margin-top: 20px;
    color: #888;
    font-size: 0.85rem;
}

/* ---------- Mobile tweaks ---------- */
@media (max-width: 768px) {
    h1 {
        font-size: 1.6rem;
    }
    p {
        font-size: 0.95rem;
    }
}
</style>
</head>

<body>

<div class="page">

<h1>Files</h1>
<p>Default sort: newest files first. Click a column header to change.</p>

<div class="table-container">
<table id="fileTable">
<thead>
<tr>
    <th class="nosort">★</th>
    <th>File Name</th>
    <th>Type</th>
    <th>Last Modified ▼</th>
    <th>Size</th>
    <th class="nosort">Download</th>
</tr>
</thead>
<tbody>
<?php foreach ($files as $file):
    $path = $dir . '/' . $file;
    if (!is_file($path)) continue;

    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    $color = extensionColor($ext);
    $category = fileCategory($ext);
    $icon = categoryIcon($category);
    $isFav = in_array($file, $favorites, true);
?>
<tr data-date="<?= filemtime($path) ?>" data-favorite="<?= $isFav ? 1 : 0 ?>">
    <td>
        <span class="star <?= $isFav ? 'active' : '' ?>"
              data-file="<?= htmlspecialchars($file) ?>">★</span>
    </td>
    <td data-sort="<?= strtolower($file) ?>">
        <span class="file-icon"
              style="color: <?= $color ?>;"
              title="<?= ucfirst($category) ?> file">
            <?= $icon ?>
        </span>
        <a href="<?= htmlspecialchars($file) ?>">
            <?= htmlspecialchars($file) ?>
        </a>
    </td>
    <td data-sort="<?= $ext ?>">
        <span class="ext"
              style="background: <?= $color ?>;"
              title="<?= mime_content_type($path) ?>">
            <?= strtoupper($ext ?: 'N/A') ?>
        </span>
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
</div>

<div class="footer">
    Revision 2.4 · <?= date('Y-m-d H:i') ?> ET
</div>

</div>

<script>
const tbody = document.querySelector("#fileTable tbody");

/* Default sort: newest first */
(function () {
    const rows = [...tbody.querySelectorAll("tr")];
    rows.sort((a, b) => b.dataset.date - a.dataset.date);
    rows.forEach(r => tbody.appendChild(r));
})();

/* ⭐ Favorite toggle */
document.querySelectorAll(".star").forEach(star => {
    star.addEventListener("click", () => {
        const file = star.dataset.file;

        fetch("toggle_favorite.php", {
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded" },
            body: "file=" + encodeURIComponent(file)
        })
        .then(r => r.json())
        .then(data => {
            if (!data.success) return;
            star.classList.toggle("active", data.favorite);
            star.closest("tr").dataset.favorite = data.favorite ? 1 : 0;
        });
    });
});
</script>
<script>
const tbody = document.querySelector("#fileTable tbody");
let sortAsc = false;

/* ---------- Column sorting (including ⭐) ---------- */
document.querySelectorAll("#fileTable thead th").forEach(th => {
    if (th.classList.contains("nosort")) return;

    th.addEventListener("click", () => {
        const colIndex = th.cellIndex;
        const rows = Array.from(tbody.querySelectorAll("tr"));

        rows.sort((a, b) => {

            /* ⭐ Favorite column */
            if (colIndex === 0) {
                const aFav = parseInt(a.dataset.favorite || 0, 10);
                const bFav = parseInt(b.dataset.favorite || 0, 10);
                return sortAsc ? aFav - bFav : bFav - aFav;
            }

            const aVal = a.children[colIndex]?.dataset.sort ?? '';
            const bVal = b.children[colIndex]?.dataset.sort ?? '';

            const aNum = parseFloat(aVal);
            const bNum = parseFloat(bVal);

            if (!isNaN(aNum) && !isNaN(bNum)) {
                return sortAsc ? aNum - bNum : bNum - aNum;
            }

            return sortAsc
                ? aVal.localeCompare(bVal, undefined, { numeric: true })
                : bVal.localeCompare(aVal, undefined, { numeric: true });
        });

        sortAsc = !sortAsc;
        rows.forEach(r => tbody.appendChild(r));
    });
});
</script>



</body>
</html>
