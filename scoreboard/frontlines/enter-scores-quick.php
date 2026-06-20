<?php declare(strict_types=1);
/**
 * Filename: frontlines/enter-scores-quick.php
 * Revision : 1.6.0
 * Description : Compact test score entry page for CVC Frontlines Scoreboard.
 *               Provides fast team selection and quick/manual score updates.
 * Author : Jason Lamb (with help from Codex CLI)
 * Created Date : 2026-05-26
 * Modified Date : 2026-06-20
 * Changelog :
 * 1.0.0 initial release
 * 1.1.0 Added change-password URL for signed-in users
 * 1.2.0 Added scoreboards-url data attribute for footer Scoreboards link
 * 1.3.0 Added roster navigation URLs
 * 1.4.0 Added category navigation URLs (Enter Categories for all; Edit Categories for admin)
 * 1.5.0 Server-rendered quick-header block so View Source shows page identity and signed-in user
 * 1.6.0 Add an Add Category Score shortcut near the top and normalize category-link wording
 */

require __DIR__ . '/../auth.php';
$user = requireAuth('frontlines', '../login.php');
$isAdmin = ($user['role'] ?? '') === 'admin';
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>CVC Frontlines Scoreboard Quick Entry</title>
    <link rel="stylesheet" href="../public/styles.css?v=<?= filemtime(__DIR__ . '/../public/styles.css') ?>" />
    <link rel="stylesheet" href="../public/quick-entry.css?v=<?= filemtime(__DIR__ . '/../public/quick-entry.css') ?>" />
  </head>
  <body
    class="quick-entry-body"
    data-username="<?= htmlspecialchars($user['username']) ?>"
    data-role="<?= htmlspecialchars($user['role']) ?>"
    data-logout-url="../logout.php"
    data-password-url="../change-password.php?return=frontlines/enter-scores-quick.php"
    data-scoreboards-url="../scoreboards.php"
    data-roster-url="./teams.php"
    data-edit-roster-url="./edit-roster.php"
    data-category-entry-url="./enter-scores-category.php"
    data-edit-categories-url="<?= $isAdmin ? './edit-categories.php' : '' ?>"
  >
    <div id="quick-entry-app" class="quick-entry-shell">
      <header class="quick-header">
        <div class="quick-title">
          <h1>CVC Frontlines Scoreboard — Quick Entry</h1>
          <p class="updated-at">Quick entry — <?= htmlspecialchars($user['username']) ?> (<?= htmlspecialchars($user['role']) ?>)</p>
        </div>
      </header>
      <nav class="quick-links" data-category-score-nav="top" aria-label="Category score shortcut">
        <a class="au-btn" href="./enter-scores-category.php" data-category-score-link="top">Add Category Score</a>
      </nav>
      <p class="status-text">Loading quick score entry...</p>
    </div>
    <script src="../public/quick-entry.js?v=<?= filemtime(__DIR__ . '/../public/quick-entry.js') ?>" defer></script>
    <script src="./category-navigation.js?v=<?= filemtime(__DIR__ . '/category-navigation.js') ?>" defer></script>
  </body>
</html>
