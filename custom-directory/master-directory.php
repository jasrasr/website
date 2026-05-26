<?php
/*
===========================================================
 File: master-directory.php
 Author: Jason Lamb
 Created: 2026-01-05
 Modified: 2026-03-13
 Revision: 3.3
 Description:
   Master shared directory browser.
   - Path-based favorites (files + folders)
   - Safe Up navigation
   - Centralized storage
   - Folders first, newest first
   - Download counts from downloads.json
===========================================================

# Revision History
# 1.0 initial release
# 2.0 major rewrite of master shared directory browser
# 2.1 added path-based favorites with centralized favorites.json
# 2.2 improved directory navigation and relative path handling
# 2.3 added extension color coding and category icons
# 2.4 added file type categorization (doc, image, code, text, archive)
# 2.5 improved directory filtering and exclusion rules
# 2.6 added safe parent directory navigation logic
# 2.7 improved styling and table layout
# 2.8 added file size formatting helper
# 2.9 improved extension detection and icon mapping
# 3.0 centralized shared directory browser for multiple folders
# 3.1 improved directory scanning exclusions and sorting behavior
# 3.2 added download tracking integration and Downloads column from downloads.json
# 3.3 added automatic file revision timestamp and script age display in footer

*/

define('APP_REVISION', '3.3');

date_default_timezone_set('America/New_York');

$root = realpath($_SERVER['DOCUMENT_ROOT']);
$dir  = getcwd();
$currentPath = realpath($dir);

/* ===========================================================
   SAFE UP DIRECTORY LOGIC
=========================================================== */

$parentPath = realpath(dirname($currentPath));
$showUp = false;
$upLink = '';

if ($parentPath && strpos($parentPath, $root) === 0 && $parentPath !== $currentPath) {

    $relativeParent = str_replace($root, '', $parentPath);

    if ($relativeParent === '' || $relativeParent === DIRECTORY_SEPARATOR) {
        $upLink = '/directory.php';
    } else {
        $upLink = $relativeParent . '/';
    }

    $showUp = true;
}

/* ===========================================================
   CENTRALIZED FAVORITES
=========================================================== */

define('FAVORITES_FILE', $root . '/custom-directory/favorites.json');

if (!file_exists(dirname(FAVORITES_FILE))) {
    mkdir(dirname(FAVORITES_FILE), 0755, true);
}

if (!file_exists(FAVORITES_FILE)) {
    file_put_contents(
        FAVORITES_FILE,
        json_encode(['favorites' => []], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
    );
}

$data = json_decode(file_get_contents(FAVORITES_FILE), true);
$favorites = $data['favorites'] ?? [];

/* ===========================================================
   DOWNLOAD COUNTS
=========================================================== */

$downloadLog = $root . '/custom-directory/logs/downloads.json';

$downloadCounts = [];

if (file_exists($downloadLog)) {

    $downloads = json_decode(
        file_get_contents($downloadLog),
        true
    ) ?? [];

    foreach ($downloads as $entry) {

        $name = basename($entry['file']);

        if (!isset($downloadCounts[$name])) {
            $downloadCounts[$name] = 0;
        }

        $downloadCounts[$name]++;
    }
}

/* ===========================================================
   SCAN DIRECTORY
=========================================================== */

$items = array_filter(scandir($dir), function ($item) {

    if ($item === '.' || $item === '..') return false;
    if (preg_match('/^directory\./i', $item)) return false;

    $exactExcludes = [
        '.htaccess',
        '.editorconfig',
        '.well-known',
        '.htpasswd',
        '.hidden',
    ];

    if (in_array($item, $exactExcludes, true)) return false;

    $patternExcludes = ['api','log','ico','index','yourls','default','sample','json','lock'];

    foreach ($patternExcludes as $pattern) {
        if (stripos($item, $pattern) !== false) return false;
    }

    return true;
});

/* ===========================================================
   HELPERS
=========================================================== */

function formatSize($bytes) {

    $units = ['B','KB','MB','GB'];
    $i = 0;

    while ($bytes >= 1024 && $i < count($units) - 1) {
        $bytes /= 1024;
        $i++;
    }

    return round($bytes,1) . ' ' . $units[$i];
}

function extensionColor($ext) {

    if (!$ext) return '#777';

    $hash = crc32($ext);
    $hue = $hash % 360;
    $light = 40 + ($hash % 20);

    return "hsl($hue,55%,{$light}%)";
}

function fileCategory($ext,$isDir=false) {

    if ($isDir) return 'folder';

    return match ($ext) {

        'pdf','doc','docx' => 'doc',
        'jpg','jpeg','png','gif','webp' => 'image',
        'js','html','css','php','py','ps1' => 'code',
        'txt','md','log' => 'text',
        'zip','7z','rar','exe' => 'archive',

        default => 'other'
    };
}

function categoryIcon($category) {

    return match ($category) {

        'folder'  => '📁',
        'doc'     => '📄',
        'image'   => '🖼️',
        'code'    => '💻',
        'text'    => '📝',
        'archive' => '📦',

        default => '📄'
    };
}

function fileRevisionStamp($file)
{
    return date('Y-m-d H:i', filemtime($file));
}

function fileAgeDays($file)
{
    return floor((time() - filemtime($file)) / 86400);
}

$masterFile = __FILE__;

$lastModified = fileRevisionStamp($masterFile);
$ageDays = fileAgeDays($masterFile);

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">
<title>Files</title>

<meta name="viewport" content="width=device-width, initial-scale=1">

<style>

body{
font-family:system-ui,-apple-system,Segoe UI,Roboto,sans-serif;
margin:0;
padding:32px 16px;
display:flex;
justify-content:center
}

.page{
width:100%;
max-width:1280px
}

table{
border-collapse:collapse;
width:100%;
min-width:900px
}

th,td{
padding:12px 14px;
border-bottom:1px solid #ddd;
white-space:nowrap
}

th{
background:#f4f4f4;
cursor:pointer
}

th.nosort{
cursor:default
}

a{
color:#0066cc;
text-decoration:none
}

a:hover{
text-decoration:underline
}

.ext{
display:inline-block;
min-width:52px;
text-align:center;
padding:4px 8px;
border-radius:4px;
font-weight:700;
font-size:.75rem;
color:#fff
}

.file-icon{
margin-right:6px;
font-size:1.1rem
}

.download-btn{
padding:8px 14px;
border:1px solid #0066cc;
border-radius:6px;
background:#fff;
color:#0066cc
}

.download-btn:hover{
background:#0066cc;
color:#fff
}

.star{
cursor:pointer;
font-size:1.3rem;
color:#ccc
}

.star.active{
color:gold
}

.up-btn{
display:inline-block;
margin-bottom:16px;
padding:8px 14px;
border:1px solid #0066cc;
border-radius:6px;
background:#fff;
color:#0066cc;
font-weight:600
}

.up-btn:hover{
background:#0066cc;
color:#fff
}

.download-count{
text-align:right;
font-weight:600
}

.footer{
margin-top:20px;
color:#888;
font-size:.85rem
}

</style>

</head>

<body>

<div class="page">

<h1>Files</h1>

<?php if ($showUp): ?>
<a href="<?= htmlspecialchars($upLink) ?>" class="up-btn">⬆ Up</a>
<?php endif; ?>

<table id="fileTable">

<thead>
<tr>
<th class="nosort">★</th>
<th>File Name</th>
<th>Type</th>
<th>Last Modified ▼</th>
<th>Size</th>
<th>Downloads</th>
<th class="nosort">Download</th>
</tr>
</thead>

<tbody>

<?php foreach ($items as $item):

$path  = $dir . '/' . $item;
$isDir = is_dir($path);

$resolved = realpath($path);

if ($resolved && strpos($resolved,$root) === 0) {
    $relativePath = str_replace($root,'',$resolved);
} else {
    $relativePath = '/' . ltrim(str_replace($root,'',$path),'/');
}

$ext      = $isDir ? '' : strtolower(pathinfo($item, PATHINFO_EXTENSION));
$color    = extensionColor($ext);
$category = fileCategory($ext,$isDir);
$icon     = categoryIcon($category);

$isFav = in_array($relativePath,$favorites,true);

$downloadCount = $downloadCounts[$item] ?? 0;

?>

<tr data-date="<?= filemtime($path) ?>"
    data-type="<?= $isDir ? 0 : 1 ?>">

<td>
<span class="star <?= $isFav ? 'active' : '' ?>"
      data-file="<?= htmlspecialchars($relativePath) ?>">★</span>
</td>

<td>
<span class="file-icon" style="color: <?= $isDir ? '#d4a017' : $color ?>;"><?= $icon ?></span>
<a href="<?= htmlspecialchars($item) ?>"><?= htmlspecialchars($item) ?></a>
</td>

<td>
<?= $isDir ? '—' : "<span class='ext' style='background:$color;'>".strtoupper($ext ?: 'N/A')."</span>" ?>
</td>

<td><?= date('Y-m-d H:i', filemtime($path)) ?></td>

<td><?= $isDir ? '—' : formatSize(filesize($path)) ?></td>

<td class="download-count">
<?= $isDir ? '—' : $downloadCount ?>
</td>

<td>
<?= $isDir ? '' : "<a class='download-btn' href='/custom-directory/download.php?file=".urlencode($relativePath)."'>Download</a>" ?>
</td>

</tr>

<?php endforeach; ?>

</tbody>

</table>

<div class="footer">
Revision <?= APP_REVISION ?> · <?= $lastModified ?> ET · <?= $ageDays ?> days old
</div>

</div>

<script>

const tbody=document.querySelector("#fileTable tbody");

(function(){

const rows=[...tbody.querySelectorAll("tr")];

rows.sort((a,b)=>{

const typeDiff=a.dataset.type-b.dataset.type;

if(typeDiff!==0) return typeDiff;

return b.dataset.date-a.dataset.date;

});

rows.forEach(r=>tbody.appendChild(r));

})();

document.querySelectorAll(".star").forEach(star=>{

star.addEventListener("click",()=>{

const file=star.dataset.file;

fetch("/custom-directory/toggle_favorite.php",{

method:"POST",
headers:{"Content-Type":"application/x-www-form-urlencoded"},
body:"file="+encodeURIComponent(file)

})
.then(r=>r.json())
.then(data=>{

if(!data.success) return;

star.classList.toggle("active",data.favorite);

});

});

});

</script>

</body>
</html>