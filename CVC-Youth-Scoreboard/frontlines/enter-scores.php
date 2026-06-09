<?php declare(strict_types=1);
/**
 * Filename: frontlines/enter-scores.php
 * Revision : 1.4.0
 * Description : Admin score entry page for CVC Frontlines Scoreboard.
 *               Allows authorized users to update, reset, and rename team scores and title.
 * Author : Jason Lamb (with help from Claude Code)
 * Created Date : 2026-04-09
 * Modified Date : 2026-06-02
 * Changelog :
 * 1.0.0 Initial release for Frontlines scoreboard instance
 * 1.1.0 Added session authentication; passes username/role/urls to JS via data attrs
 * 1.2.0 Added change-password URL for signed-in users
 * 1.3.0 Added changelog and all-scoreboards navigation URLs
 * 1.4.0 Added roster navigation URLs
 */

require __DIR__ . '/../auth.php';
$user = requireAuth('frontlines', '../login.php');
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
  >
    <div id="app"></div>
    <script src="../public/app.js?v=<?= filemtime(__DIR__ . '/../public/app.js') ?>" defer></script>
  </body>
</html>
