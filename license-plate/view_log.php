<?php
require_once __DIR__ . '/config.php';
ensureAppFolders();
$entries = readLogEntries();
$counts = plateCounts($entries);
$duplicateFiles = array_filter($entries, fn($e) => !empty($e['duplicate_file']));
$duplicatePlates = array_filter($counts, fn($count) => $count > 1);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Plate Log</title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<main class="container">
    <nav class="nav">
        <a href="index.php">Upload</a>
        <a href="view_log.php">View Log</a>
    </nav>
    <h1>Plate Log</h1>

    <section class="stats-grid">
        <div class="card"><strong>Total entries</strong><span><?= count($entries) ?></span></div>
        <div class="card"><strong>Unique plates</strong><span><?= count($counts) ?></span></div>
        <div class="card"><strong>Duplicate files</strong><span><?= count($duplicateFiles) ?></span></div>
        <div class="card"><strong>Repeated plates</strong><span><?= count($duplicatePlates) ?></span></div>
    </section>

    <?php if (!empty($duplicatePlates)): ?>
    <section class="card">
        <h2>Repeated Plate Values</h2>
        <table>
            <thead><tr><th>Plate</th><th>Count</th></tr></thead>
            <tbody>
            <?php foreach ($duplicatePlates as $plate => $count): ?>
                <tr><td><?= h($plate) ?></td><td><?= h((string)$count) ?></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </section>
    <?php endif; ?>

    <section class="card">
        <h2>Entries</h2>
        <table>
            <thead>
                <tr>
                    <th>Processed</th>
                    <th>Plate</th>
                    <th>Confidence</th>
                    <th>Original File</th>
                    <th>Photo</th>
                    <th>Duplicate</th>
                    <th>Mode</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($entries as $entry): ?>
                <tr>
                    <td><?= h($entry['processed_at'] ?? '') ?></td>
                    <td><?= h($entry['plate'] ?? '') ?></td>
                    <td><?= h((string)($entry['confidence'] ?? '')) ?></td>
                    <td><?= h($entry['original_file'] ?? '') ?></td>
                    <td>
                        <?php if (!empty($entry['stored_file'])): ?>
                            <a href="uploads/<?= h($entry['stored_file']) ?>">photo</a>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php
                        $parts = [];
                        if (!empty($entry['duplicate_file'])) $parts[] = 'same file';
                        if (!empty($entry['duplicate_plate'])) $parts[] = 'same plate';
                        echo h(implode(', ', $parts));
                        ?>
                    </td>
                    <td><?= h($entry['scan_mode'] ?? '') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </section>
</main>
</body>
</html>
