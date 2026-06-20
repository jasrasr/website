<?php declare(strict_types=1);
/**
 * Filename: frontlines/teams.php
 * Revision : 1.7.0
 * Description : Public Frontlines team roster page with leaders, members, and sponsors.
 * Author : Jason Lamb (with help from Codex CLI)
 * Created Date : 2026-06-09
 * Modified Date : 2026-06-20
 * Changelog :
 * 1.0.0 Initial Frontlines-only team roster page
 * 1.1.0 Load editable roster data and link CSV export
 * 1.2.0 Added authenticated roster editor link
 * 1.3.0 Randomized team card order and added intro blurb
 * 1.3.1 Moved random-order blurb into roster header copy
 * 1.3.2 Show roster header action buttons only for signed-in admins
 * 1.3.3 Keep public Scoreboard link visible while admin links stay gated
 * 1.4.0 Append gender/grade suffix to team member rows (e.g., "Alex Lamb - M/12")
 * 1.5.0 Show roster last-updated timestamp in header
 * 1.5.1 Combine roster header copy into one paragraph, italicize random-order note
 * 1.6.0 Added admin header links to Enter Categories and Edit Categories pages
 * 1.7.0 Moved roster navigation and admin links below all team cards
 */

require __DIR__ . '/../auth.php';
require __DIR__ . '/scoreboard_lib.php';
require __DIR__ . '/team_roster.php';

$currentUser = authUser();
$isAdmin = ($currentUser['role'] ?? '') === 'admin';
$scoreboard = readScoreboardData();
$roster = readFrontlinesRosterData();
$teams = $scoreboard['teams'] ?? scoreboardDefaultData()['teams'];
shuffle($teams);

$rosterUpdatedAt = (string) ($roster['updatedAt'] ?? '');
$rosterUpdatedDisplay = '';
if ($rosterUpdatedAt !== '') {
    $ts = strtotime($rosterUpdatedAt);
    if ($ts !== false) {
        $rosterUpdatedDisplay = date('M j, Y \\a\\t g:i A', $ts);
    }
}

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>CVC Frontlines Teams</title>
    <link rel="stylesheet" href="../public/styles.css?v=<?= filemtime(__DIR__ . '/../public/styles.css') ?>" />
  </head>
  <body>
    <div class="page-shell roster-shell">
      <header class="page-header roster-header">
        <div>
          <p>Frontlines 2026</p>
          <h1>Team Leaders &amp; Members</h1>
          <p class="updated-at">
            Roster for the Frontlines scoreboard teams.
            <?php if ($rosterUpdatedDisplay !== ''): ?>
              Roster last updated: <?= h($rosterUpdatedDisplay) ?>.
            <?php endif; ?>
            <em>Teams are shown in a fresh random order each time this roster loads.</em>
          </p>
        </div>
      </header>

      <main class="roster-grid">
        <?php foreach ($teams as $team): ?>
          <?php
            $teamId = (string) ($team['id'] ?? '');
            $teamName = (string) ($team['name'] ?? 'Team');
            $teamColor = (string) ($team['color'] ?? '#64748b');
            $teamRoster = $roster['teams'][$teamId] ?? ['leaders' => [], 'members' => [], 'sponsor' => ''];
          ?>
          <section class="roster-card" style="--team-color: <?= h($teamColor) ?>;">
            <div class="roster-mark" aria-hidden="true">
              <span>Frontlines</span>
              <strong>2026</strong>
            </div>

            <div class="roster-title">
              <h2><?= h($teamName) ?> Team</h2>
            </div>

            <div class="roster-section">
              <h3>Team Leaders</h3>
              <ul class="roster-list roster-leaders">
                <?php foreach ($teamRoster['leaders'] as $leader): ?>
                  <li><?= h((string) ($leader['name'] ?? '')) ?></li>
                <?php endforeach; ?>
              </ul>
            </div>

            <div class="roster-section roster-members">
              <h3>Team Members</h3>
              <ul class="roster-list">
                <?php foreach ($teamRoster['members'] as $member): ?>
                  <?php
                    $memberName = (string) ($member['name'] ?? '');
                    $memberGender = trim((string) ($member['gender'] ?? ''));
                    $memberGrade = trim((string) ($member['grade'] ?? ''));
                    $memberSuffix = '';
                    if ($memberGender !== '' && $memberGrade !== '') {
                        $memberSuffix = ' - ' . $memberGender . '/' . $memberGrade;
                    } elseif ($memberGender !== '') {
                        $memberSuffix = ' - ' . $memberGender;
                    } elseif ($memberGrade !== '') {
                        $memberSuffix = ' - ' . $memberGrade;
                    }
                  ?>
                  <li><?= h($memberName . $memberSuffix) ?></li>
                <?php endforeach; ?>
              </ul>
            </div>

            <?php if (($teamRoster['sponsor'] ?? '') !== ''): ?>
              <p class="roster-sponsor">Team Sponsor: <?= h((string) $teamRoster['sponsor']) ?></p>
            <?php endif; ?>
          </section>
        <?php endforeach; ?>
      </main>

      <nav class="admin-footer-actions" aria-label="Roster links">
        <a class="au-btn" href="./index.php">Scoreboard</a>
        <?php if ($isAdmin): ?>
          <a class="au-btn" href="./team-roster.csv.php">CSV</a>
          <a class="au-btn" href="./edit-roster.php">Edit Roster</a>
          <a class="au-btn" href="./enter-scores.php">Score Entry</a>
          <a class="au-btn" href="./enter-scores-category.php">Enter Categories</a>
          <a class="au-btn" href="./edit-categories.php">Edit Categories</a>
        <?php endif; ?>
      </nav>
    </div>
  </body>
</html>
