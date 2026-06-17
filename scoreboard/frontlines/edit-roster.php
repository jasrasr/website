<?php declare(strict_types=1);
/**
 * Filename: frontlines/edit-roster.php
 * Revision : 1.1.0
 * Description : Authenticated editor for Frontlines team leaders, members, gender, and grade.
 * Author : Jason Lamb (with help from Codex CLI)
 * Created Date : 2026-06-09
 * Modified Date : 2026-06-17
 * Changelog :
 * 1.0.0 Initial roster editor
 * 1.0.1 Changed team sponsor label to plural wording
 * 1.1.0 Added header links to Enter Categories and Edit Categories pages
 */

require __DIR__ . '/../auth.php';
require __DIR__ . '/scoreboard_lib.php';
require __DIR__ . '/team_roster.php';

$user = requireAuth('frontlines', '../login.php');
$scoreboard = readScoreboardData();
$teams = $scoreboard['teams'] ?? scoreboardDefaultData()['teams'];
$message = '';

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $posted = $_POST['roster'] ?? [];
    $sponsors = $_POST['sponsor'] ?? [];
    $next = ['updatedAt' => null, 'teams' => []];

    foreach ($teams as $team) {
        $teamId = (string) ($team['id'] ?? '');
        $next['teams'][$teamId] = [
            'leaders' => [],
            'members' => [],
            'sponsor' => trim((string) ($sponsors[$teamId] ?? '')),
        ];

        foreach (['leaders', 'members'] as $group) {
            $rows = is_array($posted[$teamId][$group] ?? null) ? $posted[$teamId][$group] : [];
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    continue;
                }

                $name = trim((string) ($row['name'] ?? ''));
                if ($name === '') {
                    continue;
                }

                $next['teams'][$teamId][$group][] = frontlinesRosterPerson(
                    $name,
                    trim((string) ($row['gender'] ?? '')),
                    trim((string) ($row['grade'] ?? ''))
                );
            }
        }
    }

    saveFrontlinesRosterData($next);
    $message = 'Roster saved and CSV regenerated.';
}

$roster = readFrontlinesRosterData();
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Edit Frontlines Roster</title>
    <link rel="stylesheet" href="../public/styles.css?v=<?= filemtime(__DIR__ . '/../public/styles.css') ?>" />
  </head>
  <body>
    <div class="page-shell roster-editor-shell">
      <header class="page-header">
        <div>
          <p>Signed in as <?= h((string) ($user['username'] ?? '')) ?></p>
          <h1>Edit Frontlines Roster</h1>
          <p class="updated-at">Changes update the public roster page and CSV export.</p>
        </div>
        <div class="header-actions">
          <a class="au-btn" href="./teams.php">Public Roster</a>
          <a class="au-btn" href="./team-roster.csv.php">CSV</a>
          <a class="au-btn" href="./enter-scores.php">Score Entry</a>
          <a class="au-btn" href="./enter-scores-category.php">Enter Categories</a>
          <a class="au-btn" href="./edit-categories.php">Edit Categories</a>
        </div>
      </header>

      <?php if ($message !== ''): ?>
        <p class="status-text roster-save-message"><?= h($message) ?></p>
      <?php endif; ?>

      <form method="post" class="roster-editor-form">
        <?php foreach ($teams as $team): ?>
          <?php
            $teamId = (string) ($team['id'] ?? '');
            $teamName = (string) ($team['name'] ?? 'Team');
            $teamColor = (string) ($team['color'] ?? '#64748b');
            $teamRoster = $roster['teams'][$teamId] ?? ['leaders' => [], 'members' => [], 'sponsor' => ''];
          ?>
          <section class="au-section roster-editor-team" style="--team-color: <?= h($teamColor) ?>;">
            <div class="roster-editor-heading">
              <h2><?= h($teamName) ?> Team</h2>
              <label>
                <span>Team Sponsor(s)</span>
                <input name="sponsor[<?= h($teamId) ?>]" type="text" value="<?= h((string) ($teamRoster['sponsor'] ?? '')) ?>" />
              </label>
            </div>

            <?php foreach (['leaders' => 'Team Leaders', 'members' => 'Team Members'] as $group => $label): ?>
              <h3 class="au-heading"><?= h($label) ?></h3>
              <div class="roster-edit-table">
                <div class="roster-edit-row roster-edit-head">
                  <span>Name</span>
                  <span>Gender</span>
                  <span>Grade</span>
                </div>
                <?php
                  $people = $teamRoster[$group] ?? [];
                  $people[] = frontlinesRosterPerson('');
                ?>
                <?php foreach ($people as $index => $person): ?>
                  <div class="roster-edit-row">
                    <input name="roster[<?= h($teamId) ?>][<?= h($group) ?>][<?= $index ?>][name]" type="text" value="<?= h((string) ($person['name'] ?? '')) ?>" aria-label="<?= h($label) ?> name" />
                    <input name="roster[<?= h($teamId) ?>][<?= h($group) ?>][<?= $index ?>][gender]" type="text" value="<?= h((string) ($person['gender'] ?? '')) ?>" aria-label="<?= h($label) ?> gender" />
                    <input name="roster[<?= h($teamId) ?>][<?= h($group) ?>][<?= $index ?>][grade]" type="text" value="<?= h((string) ($person['grade'] ?? '')) ?>" aria-label="<?= h($label) ?> grade" />
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endforeach; ?>
          </section>
        <?php endforeach; ?>

        <section class="admin-footer-actions roster-editor-actions" aria-label="Roster editor actions">
          <button class="positive" type="submit">Save Roster</button>
          <a class="au-btn" href="./teams.php">Cancel</a>
        </section>
      </form>
    </div>
  </body>
</html>
