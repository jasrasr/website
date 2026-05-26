<?php declare(strict_types=1);
/**
 * Filename: collide/enter-scores-quick.php
 * Revision : 1.0.0
 * Description : Compact test score entry page for CVC Collide Scoreboard.
 *               Provides fast team selection and quick/manual score updates.
 * Author : Jason Lamb (with help from Codex CLI)
 * Created Date : 2026-05-26
 * Modified Date : 2026-05-26
 * Changelog :
 * 1.0.0 initial release
 */

require __DIR__ . '/../auth.php';
$user = requireAuth('collide', '../login.php');
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>CVC Collide Scoreboard Quick Entry</title>
    <link rel="stylesheet" href="../public/styles.css?v=<?= filemtime(__DIR__ . '/../public/styles.css') ?>" />
    <link rel="stylesheet" href="../public/quick-entry.css?v=<?= filemtime(__DIR__ . '/../public/quick-entry.css') ?>" />
  </head>
  <body
    class="quick-entry-body"
    data-username="<?= htmlspecialchars($user['username']) ?>"
    data-role="<?= htmlspecialchars($user['role']) ?>"
    data-logout-url="../logout.php"
  >
    <div id="quick-entry-app" class="quick-entry-shell">
      <p class="status-text">Loading quick score entry...</p>
    </div>
    <script src="../public/quick-entry.js?v=<?= filemtime(__DIR__ . '/../public/quick-entry.js') ?>" defer></script>
  </body>
</html>
