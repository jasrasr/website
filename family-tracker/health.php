<?php
/**
 * Project: Family GPS Tracker
 * File: health.php
 * Revision: 1.4.2
 * Description: Signed-in health-check page for folder write permissions and deployment diagnostics.
 * Author: Jason Lamb / ChatGPT scaffold
 * Created: 2026-07-09
 * Modified: 2026-07-09
 */

declare(strict_types=1);
require_once __DIR__ . '/includes/security.php';

init_app_storage();
$user = require_user();

$folders = [
    'users',
    'families',
    'locations',
    'trails',
    'notices',
    'persistent_logins',
    'locks',
    'audit',
];

$checks = [];
foreach ($folders as $folder) {
    $path = DATA_DIR . '/' . $folder;
    $placeholder = $path . '/.placeholder';
    $checks[] = [
        'name' => 'data/' . $folder,
        'exists' => is_dir($path),
        'writable' => is_dir($path) && is_writable($path),
        'placeholder' => is_file($placeholder),
    ];
}

$dataHtaccess = DATA_DIR . '/.htaccess';
$includeHtaccess = __DIR__ . '/includes/.htaccess';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title><?= htmlspecialchars(APP_NAME, ENT_QUOTES, 'UTF-8') ?> Health Check</title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?= urlencode(APP_REVISION) ?>">
</head>
<body>
<div class="app-shell">
    <header class="hero">
        <div>
            <p class="eyebrow">Rev <?= htmlspecialchars(APP_REVISION, ENT_QUOTES, 'UTF-8') ?> • <?= htmlspecialchars(APP_BUILD_LABEL, ENT_QUOTES, 'UTF-8') ?></p>
            <h1>Health Check</h1>
            <p class="hero-copy"><a href="index.php">Back to tracker</a></p>
        </div>
    </header>

    <section class="card">
        <h2>Runtime folders</h2>
        <div class="member-list">
            <?php foreach ($checks as $check): ?>
                <article class="member-card">
                    <div>
                        <div class="member-name"><?= htmlspecialchars($check['name'], ENT_QUOTES, 'UTF-8') ?></div>
                        <div class="member-meta">
                            Exists: <?= $check['exists'] ? 'yes' : 'no' ?> •
                            Writable: <?= $check['writable'] ? 'yes' : 'no' ?> •
                            Placeholder: <?= $check['placeholder'] ? 'yes' : 'no' ?>
                        </div>
                    </div>
                    <span class="badge <?= ($check['exists'] && $check['writable'] && $check['placeholder']) ? '' : 'stale' ?>">
                        <?= ($check['exists'] && $check['writable'] && $check['placeholder']) ? 'OK' : 'Check' ?>
                    </span>
                </article>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="card">
        <h2>Protection checks</h2>
        <div class="diag-grid">
            <div class="diag-item"><strong>data/.htaccess</strong><span><?= is_file($dataHtaccess) ? 'Present' : 'Missing' ?></span></div>
            <div class="diag-item"><strong>includes/.htaccess</strong><span><?= is_file($includeHtaccess) ? 'Present' : 'Missing' ?></span></div>
            <div class="diag-item"><strong>HTTPS</strong><span><?= is_https_request() ? 'Yes' : 'No' ?></span></div>
            <div class="diag-item"><strong>Session user</strong><span><?= htmlspecialchars((string)($user['username'] ?? 'unknown'), ENT_QUOTES, 'UTF-8') ?></span></div>
            <div class="diag-item"><strong>Data dir</strong><span><?= htmlspecialchars(DATA_DIR, ENT_QUOTES, 'UTF-8') ?></span></div>
            <div class="diag-item"><strong>PHP</strong><span><?= htmlspecialchars(PHP_VERSION, ENT_QUOTES, 'UTF-8') ?></span></div>
        </div>
    </section>
</div>
</body>
</html>
