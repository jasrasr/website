<?php
/*
    License Plate Photo Logger
    Revision: 1.2.5
    Description: Project changelog page rendered from changelog.md with current project revision details.
*/
require_once __DIR__ . '/config.php';
$projectRevision = readProjectRevision();
$projectModifiedAt = readProjectModifiedAt();
$changelogHtml = changelogHtml();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Changelog</title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<main class="container container-wide">
    <nav class="nav">
        <a href="index.php">Upload</a>
        <a href="view_log.php">View Log</a>
        <a href="changelog.php">Changelog</a>
    </nav>

    <header class="page-header">
        <div>
            <h1>Changelog</h1>
            <p class="small">Project revision history rendered from <code>changelog.md</code>.</p>
        </div>
        <div class="status-box">
            <strong>Project Rev:</strong> <?= h($projectRevision) ?><br>
            <strong>Modified:</strong> <?= h($projectModifiedAt) ?>
        </div>
    </header>

    <section class="card changelog-content">
        <?= $changelogHtml ?>
    </section>
</main>
</body>
</html>
