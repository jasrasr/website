<?php declare(strict_types=1);
/**
 * Filename: index.php
 * Revision : 1.2.0
 * Description : Public viewer page for the default Live Scoreboard instance.
 *               Displays live scores, auto-refreshes every 2 seconds.
 * Author : Jason Lamb (with help from Claude Code)
 * Created Date : 2026-04-09
 * Modified Date : 2026-06-13
 * Changelog :
 * 1.0.0 Initial PHP release, converted from Node.js/Express (was admin page)
 * 1.1.0 Repurposed as public viewer; admin moved to enter-scores.php
 * 1.2.0 Rename root/default page title to Live Scoreboard
 */
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Live Scoreboard</title>
    <link rel="stylesheet" href="./public/styles.css?v=<?= filemtime(__DIR__ . '/public/styles.css') ?>" />
  </head>
  <body class="viewer-body" data-page-type="viewer">
    <div id="app"></div>
    <script src="./public/app.js?v=<?= filemtime(__DIR__ . '/public/app.js') ?>" defer></script>
  </body>
</html>
