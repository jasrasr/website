<?php
/*
    License Plate Photo Logger
    Revision: 1.2.24
    Description: Project changelog page rendered from CHANGELOG.md with current project revision details, deleted-audit navigation, a corner revision badge, and cache-busted stylesheet loading.
*/
require_once __DIR__ . '/config.php';
$projectRevision = readProjectRevision();
$projectModifiedAt = readProjectModifiedAt();
$changelogHtml = changelogHtml();
$styleVersion = rawurlencode($projectRevision);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Changelog</title>
<link rel="stylesheet" href="style.css?v=<?= h($styleVersion) ?>">
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

    <header class="page-header">
        <div>
            <h1>Changelog</h1>
            <p class="small">Project revision history rendered from <code>CHANGELOG.md</code>.</p>
        </div>
    </header>

    <section class="card changelog-content">
        <?= $changelogHtml ?>
    </section>
</main>
</body>
</html>
