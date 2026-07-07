<?php
/**
 * Project: Family GPS Tracker
 * File: changelog.php
 * Revision: 1.3.2
 * Description: Browser-readable changelog page.
 * Author: Jason Lamb / ChatGPT scaffold
 * Created: 2026-07-06
 * Modified: 2026-07-06
 */

declare(strict_types=1);
require_once __DIR__ . '/includes/config.php';

$changelogPath = __DIR__ . '/CHANGELOG.md';
$changelog = is_file($changelogPath) ? (string)file_get_contents($changelogPath) : 'CHANGELOG.md not found.';
$changelog = preg_replace('/^<!--.*?-->\s*/s', '', $changelog) ?? $changelog;
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title><?= htmlspecialchars(APP_NAME, ENT_QUOTES, 'UTF-8') ?> Changelog</title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?= urlencode(APP_REVISION) ?>">
</head>
<body>
<div class="app-shell">
    <header class="hero">
        <div>
            <p class="eyebrow">Rev <?= htmlspecialchars(APP_REVISION, ENT_QUOTES, 'UTF-8') ?></p>
            <h1>Changelog</h1>
            <p class="hero-copy"><a href="index.php">Back to tracker</a></p>
        </div>
    </header>
    <section class="card">
        <pre style="white-space: pre-wrap; overflow:auto;"><?= htmlspecialchars($changelog, ENT_QUOTES, 'UTF-8') ?></pre>
    </section>
</div>
</body>
</html>
