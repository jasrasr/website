<?php declare(strict_types=1);
/**
 * Filename: scoreboards.php
 * Revision : 1.0.0
 * Description : Admin-only navigation page for all CVC Scoreboard instances.
 * Author : Jason Lamb (with help from Codex CLI)
 * Created Date : 2026-06-02
 * Modified Date : 2026-06-02
 * Changelog :
 * 1.0.0 initial release
 */

require __DIR__ . '/auth.php';
$currentUser = requireAdmin('./login.php');

$scoreboards = [
    [
        'name' => 'Root',
        'viewer' => './index.php',
        'admin' => './enter-scores.php',
        'quick' => './enter-scores-quick.php',
    ],
    [
        'name' => 'Collide',
        'viewer' => './collide/index.php',
        'admin' => './collide/enter-scores.php',
        'quick' => './collide/enter-scores-quick.php',
    ],
    [
        'name' => 'Youth',
        'viewer' => './youth/index.php',
        'admin' => './youth/enter-scores.php',
        'quick' => './youth/enter-scores-quick.php',
    ],
    [
        'name' => 'Frontlines',
        'viewer' => './frontlines/index.php',
        'admin' => './frontlines/enter-scores.php',
        'quick' => './frontlines/enter-scores-quick.php',
    ],
];
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>CVC Scoreboards</title>
    <link rel="stylesheet" href="./public/styles.css?v=<?= filemtime(__DIR__ . '/public/styles.css') ?>" />
  </head>
  <body>
    <div class="page-shell">
      <header class="page-header">
        <div>
          <p>Admin</p>
          <h1>Scoreboards</h1>
          <p class="updated-at">Signed in as <?= htmlspecialchars($currentUser['username']) ?></p>
        </div>
        <div class="header-actions">
          <a class="au-btn" href="./enter-scores.php">Scoreboard</a>
          <a class="au-btn" href="./changelog.php">Changelog</a>
          <a class="au-btn" href="./logout.php">Sign Out</a>
        </div>
      </header>

      <main class="team-grid">
        <?php foreach ($scoreboards as $scoreboard): ?>
          <section class="au-section scoreboard-nav-card">
            <h2><?= htmlspecialchars($scoreboard['name']) ?></h2>
            <div class="au-actions">
              <a class="au-btn" href="<?= htmlspecialchars($scoreboard['viewer']) ?>">Viewer</a>
              <a class="au-btn" href="<?= htmlspecialchars($scoreboard['admin']) ?>">Full Admin</a>
              <a class="au-btn" href="<?= htmlspecialchars($scoreboard['quick']) ?>">Quick Entry</a>
            </div>
          </section>
        <?php endforeach; ?>
      </main>
    </div>
  </body>
</html>

