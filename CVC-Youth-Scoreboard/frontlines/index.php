<?php declare(strict_types=1);
/**
 * Filename: frontlines/index.php
 * Revision : 1.0.0
 * Description : Public viewer page for CVC Frontlines Scoreboard.
 *               Displays live scores, auto-refreshes every 2 seconds.
 * Author : Jason Lamb (with help from Claude Code)
 * Created Date : 2026-04-09
 * Modified Date : 2026-04-09
 * Changelog :
 * 1.0.0 Initial release for Frontlines scoreboard instance
 */
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>CVC Frontlines Scoreboard</title>
    <link rel="stylesheet" href="../public/styles.css?v=<?= filemtime(__DIR__ . '/../public/styles.css') ?>" />
  </head>
  <body class="viewer-body" data-page-type="viewer">
    <div id="app"></div>
    <script src="../public/app.js?v=<?= filemtime(__DIR__ . '/../public/app.js') ?>" defer></script>
  </body>
</html>
