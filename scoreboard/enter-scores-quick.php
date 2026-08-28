<?php declare(strict_types=1);
/**
 * Filename: enter-scores-quick.php
 * Revision : 1.4.1
 * Description : Compact test score entry page for the default Live Scoreboard.
 *               Provides fast team selection and quick/manual score updates.
 * Author : Jason Lamb (with help from Codex CLI)
 * Created Date : 2026-05-26
 * Modified Date : 2026-06-21
 * Changelog :
 * 1.0.0 initial release
 * 1.1.0 Added change-password URL for signed-in users
 * 1.2.0 Added scoreboards-url data attribute for footer Scoreboards link
 * 1.3.0 Rename root/default page title to Live Scoreboard
 * 1.4.0 Server-rendered quick-header block so View Source shows page identity and signed-in user (JS still overrides on first render)
 * 1.4.1 Load the shared light/dark theme toggle
 */

require __DIR__ . '/auth.php';
$user = requireAuth('root', './login.php');
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Live Scoreboard Quick Entry</title>
    <link rel="stylesheet" href="./public/styles.css?v=<?= filemtime(__DIR__ . '/public/styles.css') ?>" />
    <link rel="stylesheet" href="./public/quick-entry.css?v=<?= filemtime(__DIR__ . '/public/quick-entry.css') ?>" />
  </head>
  <body
    class="quick-entry-body"
    data-username="<?= htmlspecialchars($user['username']) ?>"
    data-role="<?= htmlspecialchars($user['role']) ?>"
    data-logout-url="./logout.php"
    data-password-url="./change-password.php?return=enter-scores-quick.php"
    data-scoreboards-url="./scoreboards.php"
  >
    <div id="quick-entry-app" class="quick-entry-shell">
      <header class="quick-header">
        <div class="quick-title">
          <h1>Live Scoreboard — Quick Entry</h1>
          <p class="updated-at">Quick entry — <?= htmlspecialchars($user['username']) ?> (<?= htmlspecialchars($user['role']) ?>)</p>
        </div>
      </header>
      <p class="status-text">Loading quick score entry...</p>
    </div>
    <script src="./public/quick-entry.js?v=<?= filemtime(__DIR__ . '/public/quick-entry.js') ?>" defer></script>
    <script src="./public/theme-toggle.js?v=<?= filemtime(__DIR__ . '/public/theme-toggle.js') ?>" defer></script>
  </body>
</html>
