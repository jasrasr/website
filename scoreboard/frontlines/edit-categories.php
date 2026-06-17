<?php declare(strict_types=1);
/**
 * Filename: frontlines/edit-categories.php
 * Revision : 1.0.0
 * Description : Admin-only editor for Frontlines goal categories (name, points, max awards, active flag).
 *               Reads/writes via the REST API endpoints in frontlines/api.php.
 * Author : Jason Lamb (with help from Claude Code)
 * Created Date : 2026-06-17
 * Modified Date : 2026-06-17
 * Changelog :
 * 1.0.0 initial release
 */

require __DIR__ . '/../auth.php';
require __DIR__ . '/scoreboard_lib.php';

$user = requireAuth('frontlines', '../login.php');
if (($user['role'] ?? '') !== 'admin') {
    http_response_code(403);
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"/><title>Access Denied</title>'
        . '<link rel="stylesheet" href="../public/styles.css?v=' . filemtime(__DIR__ . '/../public/styles.css') . '"/></head>'
        . '<body><div class="page-shell"><header class="page-header"><h1>Admin access required</h1>'
        . '<p>You need an admin role to edit categories.</p>'
        . '<p><a class="au-btn" href="./enter-scores-quick.php">Back to Quick Entry</a></p>'
        . '</header></div></body></html>';
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>CVC Frontlines Categories Editor</title>
    <link rel="stylesheet" href="../public/styles.css?v=<?= filemtime(__DIR__ . '/../public/styles.css') ?>" />
    <link rel="stylesheet" href="../public/edit-categories.css?v=<?= filemtime(__DIR__ . '/../public/edit-categories.css') ?>" />
  </head>
  <body
    class="edit-categories-body"
    data-username="<?= htmlspecialchars($user['username']) ?>"
    data-role="<?= htmlspecialchars($user['role']) ?>"
    data-api-url="./api.php"
    data-logout-url="../logout.php"
    data-password-url="../change-password.php?return=frontlines/edit-categories.php"
    data-scoreboards-url="../scoreboards.php"
    data-back-url="./enter-scores-quick.php"
    data-enter-categories-url="./enter-scores-category.php"
    data-roster-url="./teams.php"
    data-edit-roster-url="./edit-roster.php"
  >
    <div id="edit-categories-app" class="page-shell">
      <p class="status-text">Loading categories editor...</p>
    </div>
    <script src="../public/edit-categories.js?v=<?= filemtime(__DIR__ . '/../public/edit-categories.js') ?>" defer></script>
  </body>
</html>
