<?php declare(strict_types=1);
/**
 * Filename: frontlines/index.php
 * Revision : 1.4.1
 * Description : Public viewer page for CVC Frontlines Scoreboard.
 *               Displays live scores, auto-refreshes every 2 seconds.
 *               Only the top 3 teams (by score) are rendered;
 *               the grid expands to fill the screen with just those cards
 *               (data-viewer-team-limit="3"; protect losing-team morale).
 * Author : Jason Lamb (with help from Claude Code)
 * Created Date : 2026-04-09
 * Modified Date : 2026-06-21
 * Changelog :
 * 1.0.0 Initial release for Frontlines scoreboard instance
 * 1.1.0 Added roster URL for viewer navigation
 * 1.2.0 Opt in to scores-hidden mode for the bottom half; server-rendered viewer header for View Source
 * 1.3.0 Switched from hide-scores to hide-teams; bottom half is now omitted entirely so the visible cards fill the viewport
 * 1.4.0 Limit the Frontlines public viewer to the top 3 scoring teams
 * 1.4.1 Load the shared light/dark theme toggle
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
  <body
    class="viewer-body"
    data-page-type="viewer"
    data-roster-url="./teams.php"
    data-viewer-team-limit="3"
  >
    <div id="app">
      <header class="page-header">
        <div>
          <h1>CVC Frontlines Scoreboard</h1>
          <p class="updated-at">Only top 3 teams are shown.</p>
        </div>
      </header>
      <p class="status-text">Loading scoreboard...</p>
    </div>
    <script src="../public/app.js?v=<?= filemtime(__DIR__ . '/../public/app.js') ?>" defer></script>
    <script src="../public/theme-toggle.js?v=<?= filemtime(__DIR__ . '/../public/theme-toggle.js') ?>" defer></script>
  </body>
</html>
