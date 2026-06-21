<?php declare(strict_types=1);
/**
 * Filename: enter-scores.php
 * Revision : 1.5.1
 * Description : Admin score entry page for the default Live Scoreboard instance.
 *               Allows authorized users to update, reset, and rename team scores and title.
 * Author : Jason Lamb (with help from Claude Code)
 * Created Date : 2026-04-09
 * Modified Date : 2026-06-21
 * Changelog :
 * 1.0.0 Initial release; admin page split from index.php
 * 1.1.0 Added session authentication; passes username/role/urls to JS via data attrs
 * 1.2.0 Added change-password URL for signed-in users
 * 1.3.0 Added changelog and all-scoreboards navigation URLs
 * 1.4.0 Rename root/default page title to Live Scoreboard
 * 1.5.0 Server-rendered page-header block so View Source shows the page identity and signed-in user (JS still overrides on first render)
 * 1.5.1 Load the shared light/dark theme toggle
 */

require __DIR__ . '/auth.php';
$user = requireAuth('root', './login.php');
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Live Scoreboard Admin</title>
    <link rel="stylesheet" href="./public/styles.css?v=<?= filemtime(__DIR__ . '/public/styles.css') ?>" />
  </head>
  <body
    data-page-type="admin"
    data-username="<?= htmlspecialchars($user['username']) ?>"
    data-role="<?= htmlspecialchars($user['role']) ?>"
    data-logout-url="./logout.php"
    data-admin-url="./admin-users.php"
    data-password-url="./change-password.php?return=enter-scores.php"
    data-changelog-url="./changelog.php"
    data-scoreboards-url="./scoreboards.php"
  >
    <div id="app">
      <header class="page-header">
        <div>
          <p>Admin</p>
          <h1>Live Scoreboard — Score Entry</h1>
          <p class="updated-at">Signed in as <?= htmlspecialchars($user['username']) ?> (<?= htmlspecialchars($user['role']) ?>)</p>
        </div>
      </header>
      <p class="status-text">Loading score entry...</p>
    </div>
    <script src="./public/app.js?v=<?= filemtime(__DIR__ . '/public/app.js') ?>" defer></script>
    <script src="./public/theme-toggle.js?v=<?= filemtime(__DIR__ . '/public/theme-toggle.js') ?>" defer></script>
  </body>
</html>
