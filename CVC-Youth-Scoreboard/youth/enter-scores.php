<?php declare(strict_types=1);
/**
 * Filename: youth/enter-scores.php
 * Revision : 1.1.0
 * Description : Admin score entry page for CVC Youth Scoreboard.
 *               Allows authorized users to update, reset, and rename team scores and title.
 * Author : Jason Lamb (with help from Claude Code)
 * Created Date : 2026-04-09
 * Modified Date : 2026-04-13
 * Changelog :
 * 1.0.0 Initial release for Youth scoreboard instance
 * 1.1.0 Added session authentication; passes username/role/urls to JS via data attrs
 */

require __DIR__ . '/../auth.php';
$user = requireAuth('youth', '../login.php');
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>CVC Youth Scoreboard Admin</title>
    <link rel="stylesheet" href="../public/styles.css?v=<?= filemtime(__DIR__ . '/../public/styles.css') ?>" />
  </head>
  <body
    data-page-type="admin"
    data-username="<?= htmlspecialchars($user['username']) ?>"
    data-role="<?= htmlspecialchars($user['role']) ?>"
    data-logout-url="../logout.php"
    data-admin-url="../admin-users.php"
  >
    <div id="app"></div>
    <script src="../public/app.js?v=<?= filemtime(__DIR__ . '/../public/app.js') ?>" defer></script>
  </body>
</html>
