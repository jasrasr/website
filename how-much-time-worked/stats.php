<?php
/*
    Filename    : stats.php
    Revision    : 1.3.0
    Description : Displays hours by pay period, employee, ISO week, and month
    Author      : Jason Lamb (with help from Claude Code CLI)
    Created     : 2026-04-27
    Modified    : 2026-04-27
    Changelog   :
    1.0.0 initial release
    1.1.0 reads from per-employee JSON files
    1.3.0 add current pay period summary per employee
*/
require_once __DIR__ . '/config.php';
$entries     = readEntries();
$pp          = getCurrentPayPeriod();
$ppStart     = $pp['start'];
$ppEnd       = $pp['end'];
$byPP        = [];
$byWeek      = [];
$byMonth     = [];
$byEmployee  = [];

foreach ($entries as $e) {
    $minutes  = (int)($e['shift_minutes'] ?? 0);
    $date     = $e['date'] ?? '';
    $employee = $e['employee'] ?? 'Unknown';
    $dt       = new DateTime($date ?: 'now');
    $byWeek[$dt->format('o-\WW')]  = ($byWeek[$dt->format('o-\WW')] ?? 0) + $minutes;
    $byMonth[$dt->format('Y-m')]   = ($byMonth[$dt->format('Y-m')] ?? 0) + $minutes;
    $byEmployee[$employee]         = ($byEmployee[$employee] ?? 0) + $minutes;
    if ($date >= $ppStart && $date <= $ppEnd) {
        $byPP[$employee] = ($byPP[$employee] ?? 0) + $minutes;
    }
}
ksort($byWeek); ksort($byMonth); ksort($byEmployee); ksort($byPP);
include __DIR__ . '/header.php';
?>
<h1>Stats</h1>
<div class="card">
<h2>Current Pay Period</h2>
<p><?= h(date('M j, Y', strtotime($ppStart))) ?> &ndash; <?= h(date('M j, Y', strtotime($ppEnd))) ?></p>
<?php if (empty($byPP)): ?>
<p>No entries recorded for this pay period yet.</p>
<?php else: ?>
<table><tr><th>Employee</th><th>Hours This Period</th></tr>
<?php foreach ($byPP as $k => $v): ?><tr><td><?= h($k) ?></td><td><?= h(formatMinutes($v)) ?></td></tr><?php endforeach; ?>
</table>
<?php endif; ?>
</div>
<div class="card">
<h2>By Employee (All Time)</h2>
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
