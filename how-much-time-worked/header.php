<?php require_once __DIR__ . '/config.php'; ensureAppFolders(); ?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h(APP_NAME) ?></title>
    <link rel="stylesheet" href="style.css?v=<?= h(APP_REVISION) ?>">
</head>
<body>
<div class="container">
    <div class="nav">
        <a href="index.php">Upload</a>
        <a href="manual.php">Manual Entry</a>
        <a href="view.php">View Log</a>
        <a href="stats.php">Stats</a>
    </div>
