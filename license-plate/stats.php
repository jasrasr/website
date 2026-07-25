<?php
/*
    License Plate Photo Logger
    Revision: 1.2.20
    Description: Stats dashboard with visible daily upload bars, Eastern upload-date grouping, summary counts, clickable date drill-down links into the log, deleted-audit navigation, and a project revision badge.
*/
require_once __DIR__ . '/config.php';
ensureAppFolders();
$entries = readLogEntries();
$counts = plateCounts($entries);
$duplicateFiles = array_filter($entries, fn($e) => !empty($e['duplicate_file']));
$duplicatePlates = array_filter($counts, fn($count) => $count > 1);
$pendingEntries = array_filter($entries, fn($e) => ($e['scan_status'] ?? '') === 'pending');
$metadataEntries = array_filter($entries, fn($e) => !empty($e['date_taken']) || !empty($e['gps_display']) || !empty($e['plate_state']) || !empty($e['photo_state']));
$favoriteEntries = array_filter($entries, fn($e) => !empty($e['favorite']));
$rankedEntries = array_filter($entries, fn($e) => isset($e['preference_rank']) && $e['preference_rank'] !== null && $e['preference_rank'] !== '');
$projectRevision = readProjectRevision();
$projectModifiedAt = readProjectModifiedAt();

$uploadsByDay = [];
foreach ($entries as $entry) {
    $dayKey = displayEasternDate((string)($entry['processed_at'] ?? ''));
    if ($dayKey === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dayKey)) {
        $dayKey = 'Unknown';
    }
    $uploadsByDay[$dayKey] = ($uploadsByDay[$dayKey] ?? 0) + 1;
}
ksort($uploadsByDay);
$maxUploadsPerDay = empty($uploadsByDay) ? 0 : max($uploadsByDay);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Stats</title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<main class="container container-wide">
    <aside class="project-badge">
        <strong>Project Rev:</strong> <?= h($projectRevision) ?><br>
        <strong>Modified:</strong> <?= h($projectModifiedAt) ?>
    </aside>
    <nav class="nav">
        <a href="index.php">Upload</a>
        <a href="view_log.php">View Log</a>
        <a href="stats.php">Stats</a>
        <a href="deleted_audit.php">Deleted</a>
        <a href="changelog.php">Changelog</a>
    </nav>
    <h1>Stats</h1>

    <section class="stats-grid stats-grid-compact">
        <div class="card stat-card stat-card-static"><strong>Total entries</strong><span><?= count($entries) ?></span></div>
        <div class="card stat-card stat-card-static"><strong>Pending Processing</strong><span><?= count($pendingEntries) ?></span></div>
        <div class="card stat-card stat-card-static"><strong>Unique plates</strong><span><?= count($counts) ?></span></div>
        <div class="card stat-card stat-card-static"><strong>Duplicate files</strong><span><?= count($duplicateFiles) ?></span></div>
        <div class="card stat-card stat-card-static"><strong>Duplicate Plates</strong><span><?= count($duplicatePlates) ?></span></div>
        <div class="card stat-card stat-card-static"><strong>With Metadata</strong><span><?= count($metadataEntries) ?></span></div>
        <div class="card stat-card stat-card-static"><strong>Favorites</strong><span><?= count($favoriteEntries) ?></span></div>
        <div class="card stat-card stat-card-static"><strong>Ranked</strong><span><?= count($rankedEntries) ?></span></div>
    </section>

    <section class="card stats-chart-card">
        <h2>Uploads Per Day</h2>
        <p class="small">Daily count of logged plate uploads based on the `Uploaded` timestamp. Click a day to open the log filtered to that upload date.</p>
        <?php if (empty($uploadsByDay)): ?>
            <p class="small">No uploaded plate entries are available for the chart.</p>
        <?php else: ?>
            <div class="daily-chart daily-chart-compact" role="img" aria-label="Bar chart of uploaded plates per day">
                <?php foreach ($uploadsByDay as $day => $count): ?>
                    <?php
                    $barHeight = $maxUploadsPerDay > 0 ? max(18, (int)round(($count / $maxUploadsPerDay) * 150)) : 18;
                    $targetHref = $day === 'Unknown' ? 'view_log.php' : ('view_log.php?uploaded=' . rawurlencode($day));
                    ?>
                    <div class="daily-chart-item" title="<?= h($day . ': ' . $count) ?>">
                        <span class="daily-chart-count"><?= h((string)$count) ?></span>
                        <a href="<?= h($targetHref) ?>" class="daily-chart-link" aria-label="Open log for <?= h($day) ?> with <?= h((string)$count) ?> uploads">
                            <div class="daily-chart-bar-wrap daily-chart-bar-wrap-compact">
                                <div class="daily-chart-bar" style="height: <?= $barHeight ?>px"></div>
                            </div>
                        </a>
                        <span class="daily-chart-label"><?= h($day) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</main>
</body>
</html>
