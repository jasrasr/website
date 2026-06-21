<?php declare(strict_types=1);
/**
 * Filename: frontlines/teams.php
 * Revision : 1.10.0
 * Description : Public Frontlines team roster page with leaders, members, sponsors,
 *               and client-side roster search.
 * Author : Jason Lamb (with help from Codex CLI)
 * Created Date : 2026-06-09
 * Modified Date : 2026-06-21
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
 * 1.8.0 Renamed the Enter Categories link to Add Category Score
 * 1.9.0 Added roster search by team, leader, member, gender/grade, or sponsor
 * 1.10.0 Roster search now shows only matching people/sponsor rows inside matching teams
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
$teamCount = count($teams);

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
    <link rel="stylesheet" href="./roster-search.css?v=<?= filemtime(__DIR__ . '/roster-search.css') ?>" />
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

      <section class="roster-search" role="search" aria-label="Search roster">
        <label for="roster-search-input">Search roster</label>
        <div class="roster-search-row">
          <input
            id="roster-search-input"
            type="search"
            placeholder="Search team, leader, member, grade, or sponsor"
            autocomplete="off"
            autocapitalize="none"
            spellcheck="false"
            enterkeyhint="search"
            aria-controls="roster-grid"
            aria-describedby="roster-search-status"
          />
          <button id="roster-search-clear" class="secondary roster-search-clear" type="button" hidden>Clear</button>
        </div>
        <p id="roster-search-status" class="roster-search-status" aria-live="polite">
          Showing all <?= $teamCount ?> <?= $teamCount === 1 ? 'team' : 'teams' ?>.
        </p>
      </section>

      <main id="roster-grid" class="roster-grid">
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

            <div class="roster-section" data-roster-search-section>
              <h3>Team Leaders</h3>
              <ul class="roster-list roster-leaders">
                <?php foreach ($teamRoster['leaders'] as $leader): ?>
                  <li data-roster-search-item><?= h((string) ($leader['name'] ?? '')) ?></li>
                <?php endforeach; ?>
              </ul>
            </div>

            <div class="roster-section roster-members" data-roster-search-section>
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
                  <li data-roster-search-item><?= h($memberName . $memberSuffix) ?></li>
                <?php endforeach; ?>
              </ul>
            </div>

            <?php if (($teamRoster['sponsor'] ?? '') !== ''): ?>
              <p class="roster-sponsor" data-roster-search-item>Team Sponsor: <?= h((string) $teamRoster['sponsor']) ?></p>
            <?php endif; ?>
          </section>
        <?php endforeach; ?>

        <p id="roster-search-empty" class="roster-search-empty" hidden>
          No roster matches were found. Try a team name, person, grade, or sponsor.
        </p>
      </main>

      <nav class="admin-footer-actions" aria-label="Roster links">
        <a class="au-btn" href="./index.php">Scoreboard</a>
        <?php if ($isAdmin): ?>
          <a class="au-btn" href="./team-roster.csv.php">CSV</a>
          <a class="au-btn" href="./edit-roster.php">Edit Roster</a>
          <a class="au-btn" href="./enter-scores.php">Score Entry</a>
          <a class="au-btn" href="./enter-scores-category.php">Add Category Score</a>
          <a class="au-btn" href="./edit-categories.php">Edit Categories</a>
        <?php endif; ?>
      </nav>
    </div>
    <script src="./roster-search.js?v=<?= filemtime(__DIR__ . '/roster-search.js') ?>" defer></script>
  </body>
</html>
