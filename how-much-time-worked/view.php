<?php
require_once __DIR__ . '/config.php';
$entries = readEntries();
$totalMinutes = array_sum(array_map(fn($e) => (int)($e['shift_minutes'] ?? 0), $entries));
include __DIR__ . '/header.php';
?>
<h1>Hours Log</h1>
<?php if (isset($_GET['saved'])): ?><div class="good">Entry saved.</div><?php endif; ?>
<?php if (isset($_GET['duplicate'])): ?><div class="notice">Duplicate shift detected. Entry was not added.</div><?php endif; ?>
<div class="card">
    <strong>Total logged hours:</strong> <?= h(formatMinutes($totalMinutes)) ?><br>
    <strong>Total entries:</strong> <?= count($entries) ?>
</div>
<table>
    <thead>
        <tr>
            <th>Date</th><th>Employee</th><th>In</th><th>Out</th><th>Calculated</th><th>Printed</th><th>Week Printed</th><th>Warning</th><th>Source</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($entries as $e): ?>
        <tr>
            <td><?= h($e['date'] ?? '') ?></td>
            <td><?= h($e['employee'] ?? '') ?></td>
            <td><?= h($e['time_in'] ?? '') ?></td>
            <td><?= h($e['time_out'] ?? '') ?></td>
            <td><?= h($e['display_shift_hours'] ?? '') ?></td>
            <td><?= h($e['printed_shift_hours'] ?? '') ?></td>
            <td><?= h($e['printed_week_hours'] ?? '') ?></td>
            <td><?= h($e['review_warning'] ?? '') ?></td>
            <td>
                <?php if (!empty($e['source_file']) && ($e['source_file'] !== 'manual-entry')): ?>
                    <a href="uploads/<?= h($e['source_file']) ?>">photo</a>
                <?php else: ?>manual<?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php include __DIR__ . '/footer.php'; ?>
