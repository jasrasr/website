<?php
/**
 * Filename: frontlines/index.php
 * Revision: 1.0
 * Description: Public viewer page for CVC Frontlines Scoreboard.
 *              Displays live scores, auto-refreshes every 2 seconds.
 * Author: Jason Lamb (with help from Claude)
 * Changelog
 * 1.0 Initial release for Frontlines scoreboard instance
 */
declare(strict_types=1);
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>CVC Frontlines Scoreboard</title>
    <link rel="stylesheet" href="../public/styles.css" />
  </head>
  <body class="viewer-body" data-page-type="viewer">
    <div id="app"></div>
    <script src="../public/app.js" defer></script>
  </body>
</html>
