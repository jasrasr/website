<?php declare(strict_types=1);
/**
 * Filename: frontlines/enter-scores-category.php
 * Revision : 1.1.1
 * Description : Scorer + admin page for awarding pre-defined goal categories to Frontlines teams.
 *               One-tap awarding using the same Frontlines API. Frontlines-only feature.
 * Author : Jason Lamb (with help from Claude Code)
 * Created Date : 2026-06-17
 * Modified Date : 2026-06-21
 * Changelog :
 * 1.0.0 initial release
 * 1.1.0 Server-rendered quick-header block so View Source shows page identity and signed-in user
 * 1.1.1 Load the shared light/dark theme toggle
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
    <title>CVC Frontlines Category Entry</title>
    <link rel="stylesheet" href="../public/styles.css?v=<?= filemtime(__DIR__ . '/../public/styles.css') ?>" />
    <link rel="stylesheet" href="../public/quick-entry.css?v=<?= filemtime(__DIR__ . '/../public/quick-entry.css') ?>" />
    <link rel="stylesheet" href="../public/category-entry.css?v=<?= filemtime(__DIR__ . '/../public/category-entry.css') ?>" />
  </head>
  <body
    class="quick-entry-body category-entry-body"
    data-username="<?= htmlspecialchars($user['username']) ?>"
    data-role="<?= htmlspecialchars($user['role']) ?>"
    data-api-url="./api.php"
    data-logout-url="../logout.php"
    data-password-url="../change-password.php?return=frontlines/enter-scores-category.php"
    data-scoreboards-url="../scoreboards.php"
    data-roster-url="./teams.php"
    data-edit-roster-url="./edit-roster.php"
    data-quick-entry-url="./enter-scores-quick.php"
    data-full-admin-url="./enter-scores.php"
    data-edit-categories-url="<?= $isAdmin ? './edit-categories.php' : '' ?>"
  >
    <div id="category-entry-app" class="quick-entry-shell">
      <header class="quick-header">
        <div class="quick-title">
          <h1>CVC Frontlines Scoreboard — Category Entry</h1>
          <p class="updated-at">Goal entry — <?= htmlspecialchars($user['username']) ?> (<?= htmlspecialchars($user['role']) ?>)</p>
        </div>
      </header>
      <p class="status-text">Loading goal entry...</p>
    </div>
    <script src="../public/category-entry.js?v=<?= filemtime(__DIR__ . '/../public/category-entry.js') ?>" defer></script>
    <script src="../public/theme-toggle.js?v=<?= filemtime(__DIR__ . '/../public/theme-toggle.js') ?>" defer></script>
  </body>
</html>
