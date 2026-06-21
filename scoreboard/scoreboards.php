<?php declare(strict_types=1);
/**
 * Filename: scoreboards.php
 * Revision : 1.5.1
 * Description : Navigation page for all CVC Scoreboard instances the signed-in user can access.
 * Author : Jason Lamb (with help from Codex CLI)
 * Created Date : 2026-06-02
 * Modified Date : 2026-06-21
 * Changelog :
 * 1.0.0 initial release
 * 1.1.0 Opened to any signed-in user; filters listed instances by user's scoreboard access
 * 1.2.0 Moved header actions to a bottom footer bar; added Change Password link
 * 1.3.0 Added Frontlines team roster link
 * 1.4.0 Show notice when redirected here after attempting an off-limits scoreboard
 * 1.5.0 Rename Root scoreboard label to Default
 * 1.5.1 Load the shared light/dark theme toggle
 */

require __DIR__ . '/auth.php';
$currentUser = requireSignedIn('./login.php');

$scoreboards = [
    [
        'id' => 'root',
        'name' => 'Default',
        'viewer' => './index.php',
        'admin' => './enter-scores.php',
        'quick' => './enter-scores-quick.php',
        'teams' => null,
    ],
    [
        'id' => 'collide',
        'name' => 'Collide',
        'viewer' => './collide/index.php',
        'admin' => './collide/enter-scores.php',
        'quick' => './collide/enter-scores-quick.php',
        'teams' => null,
    ],
    [
        'id' => 'youth',
        'name' => 'Youth',
        'viewer' => './youth/index.php',
        'admin' => './youth/enter-scores.php',
        'quick' => './youth/enter-scores-quick.php',
        'teams' => null,
    ],
    [
        'id' => 'frontlines',
        'name' => 'Frontlines',
        'viewer' => './frontlines/index.php',
        'admin' => './frontlines/enter-scores.php',
        'quick' => './frontlines/enter-scores-quick.php',
        'teams' => './frontlines/teams.php',
    ],
];

$userScoreboards = $currentUser['scoreboards'] ?? [];
$visibleScoreboards = array_values(array_filter(
    $scoreboards,
    static fn(array $sb): bool => in_array($sb['id'], $userScoreboards, true)
));
$roleLabel = ($currentUser['role'] ?? '') === 'admin' ? 'Admin' : 'Scorer';

$deniedScoreboard = '';
if (isset($_GET['denied'])) {
    $candidate = strtolower(trim((string) $_GET['denied']));
    foreach ($scoreboards as $sb) {
        if ($sb['id'] === $candidate) {
            $deniedScoreboard = $sb['name'];
            break;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>CVC Scoreboards</title>
    <link rel="stylesheet" href="./public/styles.css?v=<?= filemtime(__DIR__ . '/public/styles.css') ?>" />
  </head>
  <body>
    <div class="page-shell">
      <header class="page-header">
        <div>
          <p><?= htmlspecialchars($roleLabel) ?></p>
          <h1>Scoreboards</h1>
          <p class="updated-at">Signed in as <?= htmlspecialchars($currentUser['username']) ?></p>
        </div>
      </header>

      <?php if ($deniedScoreboard !== ''): ?>
        <p class="status-text" style="color:var(--warning)">
          You do not have access to the <?= htmlspecialchars($deniedScoreboard) ?> scoreboard. Pick one of yours below.
        </p>
      <?php endif; ?>

      <main class="team-grid">
        <?php if (empty($visibleScoreboards)): ?>
          <section class="au-section">
            <p>You do not have access to any scoreboards yet. Ask an admin to grant access.</p>
          </section>
        <?php else: ?>
          <?php foreach ($visibleScoreboards as $scoreboard): ?>
            <section class="au-section scoreboard-nav-card">
              <h2><?= htmlspecialchars($scoreboard['name']) ?></h2>
              <div class="au-actions">
                <a class="au-btn" href="<?= htmlspecialchars($scoreboard['viewer']) ?>">Viewer</a>
                <a class="au-btn" href="<?= htmlspecialchars($scoreboard['admin']) ?>">Full Admin</a>
                <a class="au-btn" href="<?= htmlspecialchars($scoreboard['quick']) ?>">Quick Entry</a>
                <?php if (!empty($scoreboard['teams'])): ?>
                  <a class="au-btn" href="<?= htmlspecialchars($scoreboard['teams']) ?>">Teams</a>
                <?php endif; ?>
              </div>
            </section>
          <?php endforeach; ?>
        <?php endif; ?>
      </main>

      <section class="admin-footer-actions" aria-label="Scoreboards page actions">
        <a class="au-btn" href="./changelog.php">Changelog</a>
        <a class="au-btn" href="./change-password.php?return=scoreboards.php">Change Password</a>
        <a class="au-btn" href="./logout.php">Sign Out</a>
      </section>
    </div>
    <script src="./public/theme-toggle.js?v=<?= filemtime(__DIR__ . '/public/theme-toggle.js') ?>" defer></script>
  </body>
</html>
