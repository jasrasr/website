<?php declare(strict_types=1);
/**
 * Filename: frontlines/rankings.php
 * Revision : 1.0.0
 * Description : Protected Frontlines full rankings page for signed-in admins and scorers.
 *               Shows every team ordered from best score to lowest score.
 * Author : Jason Lamb (with help from ChatGPT)
 * Created Date : 2026-06-23
 * Modified Date : 2026-06-23
 * Changelog :
 * 1.0.0 Initial admin/scorer-only full rankings view
 */

require __DIR__ . '/../auth.php';
require __DIR__ . '/scoreboard_lib.php';

$user = requireAuth('frontlines', '../login.php');
$data = readScoreboardData();
$teams = is_array($data['teams'] ?? null) ? $data['teams'] : [];

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function ordinalPlace(int $value): string
{
    $mod100 = $value % 100;
    if ($mod100 >= 11 && $mod100 <= 13) {
        return $value . 'th';
    }

    return match ($value % 10) {
        1 => $value . 'st',
        2 => $value . 'nd',
        3 => $value . 'rd',
        default => $value . 'th',
    };
}

function scoreChangedLabel(array $team): string
{
    $changedAt = trim((string) ($team['score_changed_at'] ?? ''));
    if ($changedAt === '') {
        return 'No score change yet';
    }

    $timestamp = strtotime($changedAt);
    if ($timestamp === false) {
        return $changedAt;
    }

    return date('M j, Y g:i A', $timestamp);
}

usort($teams, static function (array $a, array $b): int {
    $scoreDifference = (int) ($b['score'] ?? 0) <=> (int) ($a['score'] ?? 0);
    if ($scoreDifference !== 0) {
        return $scoreDifference;
    }

    $aTimestamp = (string) ($a['score_changed_at'] ?? '');
    $bTimestamp = (string) ($b['score_changed_at'] ?? '');
    if ($aTimestamp !== $bTimestamp) {
        return strcmp($aTimestamp, $bTimestamp);
    }

    return strnatcasecmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''));
});

$updatedAt = (string) ($data['updatedAt'] ?? '');
$updatedDisplay = 'Waiting for first score update';
if ($updatedAt !== '') {
    $ts = strtotime($updatedAt);
    if ($ts !== false) {
        $updatedDisplay = date('M j, Y g:i A', $ts);
    }
}
$title = (string) ($data['title'] ?? 'CVC Frontlines Scoreboard');
$roleLabel = (($user['role'] ?? '') === 'admin') ? 'Admin' : 'Scorer';
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>CVC Frontlines Full Rankings</title>
    <link rel="stylesheet" href="../public/styles.css?v=<?= filemtime(__DIR__ . '/../public/styles.css') ?>" />
  </head>
  <body>
    <div class="page-shell">
      <header class="page-header">
        <div>
          <p><?= h($roleLabel) ?> Rankings</p>
          <h1><?= h($title) ?> — Full Rankings</h1>
          <p class="updated-at">Signed in as <?= h((string) ($user['username'] ?? '')) ?>. Last updated: <?= h($updatedDisplay) ?>.</p>
        </div>
        <div class="header-actions">
          <a class="au-btn" href="./enter-scores.php">Score Entry</a>
          <a class="au-btn" href="./enter-scores-quick.php">Quick Entry</a>
          <a class="au-btn" href="./enter-scores-category.php">Add Category Score</a>
          <a class="au-btn" href="./teams.php">Roster</a>
        </div>
      </header>

      <p class="sort-note">All Frontlines teams are shown from highest score to lowest score. Tied scores are ordered by who reached the score first, then alphabetically.</p>

      <section class="au-section" aria-label="Full Frontlines rankings">
        <?php if (empty($teams)): ?>
          <p class="status-text">No teams are available yet.</p>
        <?php else: ?>
          <div class="au-table-wrap">
            <table class="au-table">
              <thead>
                <tr>
                  <th>Place</th>
                  <th>Team</th>
                  <th>Score</th>
                  <th>Last Score Change</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($teams as $index => $team): ?>
                  <?php
                    $teamName = (string) ($team['name'] ?? 'Team');
                    $teamColor = (string) ($team['color'] ?? '#64748b');
                    $score = (int) ($team['score'] ?? 0);
                    $place = $index + 1;
                  ?>
                  <tr>
                    <td><strong><?= h(ordinalPlace($place)) ?></strong></td>
                    <td>
                      <span aria-hidden="true" style="display:inline-block;width:0.85rem;height:0.85rem;margin-right:0.45rem;border-radius:999px;background:<?= h($teamColor) ?>;"></span>
                      <?= h($teamName) ?>
                    </td>
                    <td><strong><?= h((string) $score) ?></strong></td>
                    <td><?= h(scoreChangedLabel($team)) ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </section>

      <nav class="admin-footer-actions" aria-label="Full rankings actions">
        <a class="au-btn" href="./enter-scores.php">Score Entry</a>
        <a class="au-btn" href="./enter-scores-quick.php">Quick Entry</a>
        <a class="au-btn" href="./enter-scores-category.php">Add Category Score</a>
        <a class="au-btn" href="./teams.php">Roster</a>
        <a class="au-btn" href="../scoreboards.php">Scoreboards</a>
        <a class="au-btn" href="../logout.php">Sign Out</a>
      </nav>
    </div>
    <script src="../public/theme-toggle.js?v=<?= filemtime(__DIR__ . '/../public/theme-toggle.js') ?>" defer></script>
  </body>
</html>
