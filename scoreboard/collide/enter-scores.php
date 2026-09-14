<?php declare(strict_types=1);
/**
 * Filename: collide/enter-scores.php
 * Revision : 1.5.2
 * Description : Admin score entry page for CVC Collide Scoreboard.
 *               Allows authorized users to update, reset, rename team scores/title,
 *               and maintain Collide-only team mottos and walk-up songs.
 * Author : Jason Lamb (with help from Claude Code)
 * Created Date : 2026-04-09
 * Modified Date : 2026-09-14
 * Changelog :
 * 1.0.0 Initial release for Collide scoreboard instance
 * 1.1.0 Added session authentication; passes username/role/urls to JS via data attrs
 * 1.2.0 Added change-password URL for signed-in users
 * 1.3.0 Added changelog and all-scoreboards navigation URLs
 * 1.4.0 Server-rendered page-header block so View Source shows page identity and signed-in user
 * 1.5.0 Load Collide-only motto and walk-up song admin UI
 * 1.5.1 Load Collide-only reset-score label overrides
 * 1.5.2 Load Collide-only existing uploaded-song picker
 */

require __DIR__ . '/../auth.php';
$user = requireAuth('collide', '../login.php');
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>CVC Collide Scoreboard Admin</title>
    <link rel="stylesheet" href="../public/styles.css?v=<?= filemtime(__DIR__ . '/../public/styles.css') ?>" />
    <link rel="stylesheet" href="collide.css?v=<?= filemtime(__DIR__ . '/collide.css') ?>" />
  </head>
  <body
    data-page-type="admin"
    data-username="<?= htmlspecialchars($user['username']) ?>"
    data-role="<?= htmlspecialchars($user['role']) ?>"
    data-logout-url="../logout.php"
    data-admin-url="../admin-users.php"
    data-password-url="../change-password.php?return=collide/enter-scores.php"
    data-changelog-url="../changelog.php"
    data-scoreboards-url="../scoreboards.php"
  >
    <div id="app">
      <header class="page-header">
        <div>
          <p>Admin</p>
          <h1>CVC Collide Scoreboard — Score Entry</h1>
          <p class="updated-at">Signed in as <?= htmlspecialchars($user['username']) ?> (<?= htmlspecialchars($user['role']) ?>)</p>
        </div>
      </header>
      <p class="status-text">Loading score entry...</p>
    </div>
    <script src="../public/app.js?v=<?= filemtime(__DIR__ . '/../public/app.js') ?>" defer></script>
    <script src="collide-extras.js?v=<?= filemtime(__DIR__ . '/collide-extras.js') ?>" defer></script>
    <script src="collide-admin-labels.js?v=<?= filemtime(__DIR__ . '/collide-admin-labels.js') ?>" defer></script>
    <script src="collide-audio-library.js?v=<?= filemtime(__DIR__ . '/collide-audio-library.js') ?>" defer></script>
  </body>
</html>