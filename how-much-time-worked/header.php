<?php
/*
    Filename    : header.php
    Revision    : 1.1.0
    Description : Shared HTML header and navigation included at the top of every page
    Author      : Jason Lamb (with help from Claude Code CLI)
    Created     : 2026-04-27
    Modified    : 2026-04-27
    Changelog   :
    1.0.0 initial release
    1.1.0 removed Raw JSON nav link
*/
require_once __DIR__ . '/config.php'; ensureAppFolders(); ?>
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
