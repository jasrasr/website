<?php

/*
===========================================================
 File: stats.php
 Author: Jason Lamb
 Created: 2026-03-13
 Revision: 1.1
 Description:
   Displays tool download statistics
===========================================================
*/

date_default_timezone_set('America/New_York');

$root = realpath($_SERVER['DOCUMENT_ROOT']);
$logFile = $root . '/custom-directory/logs/downloads.json';

$data = [];

if (file_exists($logFile)) {
    $data = json_decode(file_get_contents($logFile), true) ?? [];
}

$totalDownloads = count($data);

$files = [];
$ips = [];
$times = [];

foreach ($data as $entry) {

    $file = basename($entry['file']);
    $ip   = $entry['ip'];
    $time = strtotime($entry['time']);

    $times[] = $time;

    if (!isset($files[$file])) {
        $files[$file] = 0;
    }

    $files[$file]++;

    $ips[$ip] = true;
}

ksort($files);

$uniqueVisitors = count($ips);

$firstEntry = null;
$daysTracked = 0;

if (!empty($times)) {

    sort($times);

    $firstEntry = date('Y-m-d H:i:s', $times[0]);

    $daysTracked = floor(
        (time() - $times[0]) / 86400
    ) + 1;
}

?>

<!DOCTYPE html>
<html>
<head>

<meta charset="UTF-8">
<title>Tool Download Stats</title>

<style>

body{
font-family:system-ui;
margin:40px;
}

table{
border-collapse:collapse;
width:600px;
}

th,td{
padding:10px;
border-bottom:1px solid #ddd;
text-align:left;
}

th{
background:#f4f4f4;
cursor:pointer;
}

.info{
margin-bottom:20px;
}

</style>

</head>

<body>

<h1>Tool Download Stats</h1>

<div class="info">

<p><b>Total Downloads:</b> <?= $totalDownloads ?></p>

<p><b>Unique Visitors:</b> <?= $uniqueVisitors ?></p>

<p><b>Log Start:</b>
<?= $firstEntry ? $firstEntry . ' ET' : 'No data' ?>
</p>

<p><b>Days Tracked:</b> <?= $daysTracked ?></p>

<p><i>Stats are calculated from downloads.json.  
If the log is reset, stats restart. Stats columns are sortable.</i></p>
</div>

<table id="statsTable">

<thead>
<tr>
<th onclick="sortTable(0)">Tool Name</th>
<th onclick="sortTable(1)">Downloads</th>
</tr>
</thead>

<tbody>

<?php foreach ($files as $tool => $count): ?>

<tr>
<td><?= htmlspecialchars($tool) ?></td>
<td><?= $count ?></td>
</tr>

<?php endforeach; ?>

</tbody>

</table>

<script>

function sortTable(col){

const table = document.getElementById("statsTable");
const tbody = table.tBodies[0];
const rows = Array.from(tbody.rows);

const asc = table.classList.toggle("asc");

rows.sort((a,b)=>{

let A = a.cells[col].innerText;
let B = b.cells[col].innerText;

if(col === 1){
return asc ? A-B : B-A;
}

return asc
? A.localeCompare(B)
: B.localeCompare(A);

});

rows.forEach(r=>tbody.appendChild(r));

}

</script>

</body>
</html>