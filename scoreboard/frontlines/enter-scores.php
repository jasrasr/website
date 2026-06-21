<?php declare(strict_types=1);
/**
 * Filename: frontlines/enter-scores.php
 * Revision : 1.7.1
 * Description : Admin score entry page for CVC Frontlines Scoreboard.
 *               Allows authorized users to update, reset, and rename team scores and title.
 * Author : Jason Lamb (with help from Claude Code)
 * Created Date : 2026-04-09
 * Modified Date : 2026-06-21
 * Changelog :
 * 1.0.0 Initial release for Frontlines scoreboard instance
 * 1.1.0 Added session authentication; passes username/role/urls to JS via data attrs
 * 1.2.0 Added change-password URL for signed-in users
 * 1.3.0 Added changelog and all-scoreboards navigation URLs
 * 1.4.0 Added roster navigation URLs
 * 1.5.0 Added category navigation URLs (Enter Categories for all; Edit Categories for admin)
 * 1.6.0 Server-rendered page-header block so View Source shows page identity and signed-in user
 * 1.7.0 Add an Add Category Score shortcut to the top banner and normalize category-link wording
 * 1.7.1 Load the shared light/dark theme toggle
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
    <title>CVC Frontlines Scoreboard Admin</title>
    <link rel="stylesheet" href="../public/styles.css?v=<?= filemtime(__DIR__ . '/../public/styles.css') ?>" />
  </head>
  <body
    data-page-type="admin"
    data-username="<?= htmlspecialchars($user['username']) ?>"
    data-role="<?= htmlspecialchars($user['role']) ?>"
    data-logout-url="../logout.php"
    data-admin-url="../admin-users.php"
    data-password-url="../change-password.php?return=frontlines/enter-scores.php"
    data-changelog-url="../changelog.php"
    data-scoreboards-url="../scoreboards.php"
    data-roster-url="./teams.php"
    data-edit-roster-url="./edit-roster.php"
    data-category-entry-url="./enter-scores-category.php"
    data-edit-categories-url="<?= $isAdmin ? './edit-categories.php' : '' ?>"
  >
    <div id="app">
      <header class="page-header">
        <div>
          <p>Admin</p>
          <h1>CVC Frontlines Scoreboard — Score Entry</h1>
          <p class="updated-at">Signed in as <?= htmlspecialchars($user['username']) ?> (<?= htmlspecialchars($user['role']) ?>)</p>
        </div>
        <div class="header-actions">
          <a class="au-btn" href="./enter-scores-category.php" data-category-score-link="top">Add Category Score</a>
        </div>
      </header>
      <p class="status-text">Loading score entry...</p>
    </div>
    <script src="../public/app.js?v=<?= filemtime(__DIR__ . '/../public/app.js') ?>" defer></script>
    <script src="./category-navigation.js?v=<?= filemtime(__DIR__ . '/category-navigation.js') ?>" defer></script>
    <script src="../public/theme-toggle.js?v=<?= filemtime(__DIR__ . '/../public/theme-toggle.js') ?>" defer></script>
  </body>
</html>
