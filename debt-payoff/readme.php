<?php
/*
    Debt Payoff Planner
    Revision: 1.0.5
    Description: Project readme page rendered from README.md with shared top navigation, project metadata, and a once-per-update notice.
*/

declare(strict_types=1);

require_once __DIR__ . '/config.php';

$projectRevision = readProjectRevision();
$projectModifiedAt = readProjectModifiedAt();
$currentAccount = currentUser();
$readmeHtml = markdownFileToHtml(__DIR__ . '/README.md', 'No readme is available.');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Readme - <?= h(APP_NAME) ?></title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<main class="container">
    <div class="topbar">
        <nav class="nav">
            <a href="index.php">Dashboard</a>
            <?php if ($currentAccount !== null && ($currentAccount['role'] ?? 'user') === 'admin'): ?>
            <a href="admin.php">Admin</a>
            <?php endif; ?>
            <a href="readme.php">Readme</a>
            <a href="changelog.php">Changelog</a>
            <a href="todo.php">Todo</a>
            <?php if ($currentAccount !== null): ?>
            <a href="index.php?logout=1" class="nav-button">Logout</a>
            <?php endif; ?>
        </nav>
        <div class="top-meta">
            <span><strong>Rev:</strong> <?= h($projectRevision) ?></span>
            <span><strong>Modified:</strong> <?= h($projectModifiedAt) ?></span>
        </div>
    </div>

    <section class="card prose-block">
        <h1>Readme</h1>
        <?= renderUpdateNotice($projectRevision) ?>
        <?= $readmeHtml ?>
    </section>
</main>
</body>
</html>
