<?php declare(strict_types=1);
/**
 * Filename: collide/index.php
 * Revision : 1.1.0
 * Description : Public viewer page for CVC Collide Scoreboard.
 *               Displays live scores, auto-refreshes every 2 seconds, and shows
 *               Collide-only team mottos and walk-up song buttons.
 * Author : Jason Lamb (with help from Claude Code)
 * Created Date : 2026-04-09
 * Modified Date : 2026-09-13
 * Changelog :
 * 1.0.0 Initial release for Collide scoreboard instance
 * 1.0.1 Load the shared light/dark theme toggle
 * 1.1.0 Load Collide-only motto and walk-up song UI
 */
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>CVC Collide Scoreboard</title>
    <link rel="stylesheet" href="../public/styles.css?v=<?= filemtime(__DIR__ . '/../public/styles.css') ?>" />
    <link rel="stylesheet" href="collide.css?v=<?= filemtime(__DIR__ . '/collide.css') ?>" />
  </head>
  <body class="viewer-body" data-page-type="viewer">
    <div id="app"></div>
    <script src="../public/app.js?v=<?= filemtime(__DIR__ . '/../public/app.js') ?>" defer></script>
    <script src="collide-extras.js?v=<?= filemtime(__DIR__ . '/collide-extras.js') ?>" defer></script>
    <script src="../public/theme-toggle.js?v=<?= filemtime(__DIR__ . '/../public/theme-toggle.js') ?>" defer></script>
  </body>
</html>
