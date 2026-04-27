<?php
require_once __DIR__ . '/config.php';
$entries = readEntries();
$byWeek = [];
$byMonth = [];
$byEmployee = [];

foreach ($entries as $e) {
    $minutes = (int)($e['shift_minutes'] ?? 0);
    $dt = new DateTime($e['date'] ?? 'now');
    $week = $dt->format('o-\WW');
    $month = $dt->format('Y-m');
    $employee = $e['employee'] ?? 'Unknown';
    $byWeek[$week] = ($byWeek[$week] ?? 0) + $minutes;
    $byMonth[$month] = ($byMonth[$month] ?? 0) + $minutes;
    $byEmployee[$employee] = ($byEmployee[$employee] ?? 0) + $minutes;
}
ksort($byWeek); ksort($byMonth); ksort($byEmployee);
include __DIR__ . '/header.php';
?>
<h1>Stats</h1>
<div class="card">
<h2>By Employee</h2>
<table><tr><th>Employee</th><th>Total Hours</th></tr>
<?php foreach ($byEmployee as $k => $v): ?><tr><td><?= h($k) ?></td><td><?= h(formatMinutes($v)) ?></td></tr><?php endforeach; ?>
</table>
</div>
<div class="card">
<h2>By Week</h2>
<table><tr><th>ISO Week</th><th>Total Hours</th></tr>
<?php foreach ($byWeek as $k => $v): ?><tr><td><?= h($k) ?></td><td><?= h(formatMinutes($v)) ?></td></tr><?php endforeach; ?>
</table>
</div>
<div class="card">
<h2>By Month</h2>
<table><tr><th>Month</th><th>Total Hours</th></tr>
<?php foreach ($byMonth as $k => $v): ?><tr><td><?= h($k) ?></td><td><?= h(formatMinutes($v)) ?></td></tr><?php endforeach; ?>
</table>
</div>
<?php include __DIR__ . '/footer.php'; ?>
