<?php declare(strict_types=1);
/**
 * Filename: api.php
 * Revision : 1.6.0
 * Description : REST API endpoint for the default Live Scoreboard score management.
 *               Handles reading, updating, resetting, and renaming teams and title.
 * Author : Jason Lamb (with help from Claude Code)
 * Created Date : 2026-04-09
 * Modified Date : 2026-06-18
 * Changelog :
 * 1.0.0 Initial PHP release, converted from Node.js/Express
 * 1.1.0 Fixed query parameter routing to match relative URL fetch calls
 * 1.2.0 Added rename-team and rename-title actions
 * 1.3.0 Allow negative scores (removed max(0) floor)
 * 1.4.0 Added session authentication and audit logging per action
 * 1.5.0 Stamp score_changed_at on every score change (used as a tiebreaker on the viewer/admin sort: older = ranked higher). Reset All now writes a snapshot to data/scores.previous.json before clearing scores so an accidental press can be recovered.
 * 1.6.0 reset-team snapshots data/scores.previous-single.json before zeroing. remove-team appends the deleted team to data/removed-teams.json. New restore-previous-scores action (admin-only) reads scores.previous.json and writes it back. scores GET response now includes hasPreviousSnapshot flag for the UI.
 */

require __DIR__ . '/scoreboard_lib.php';
require __DIR__ . '/auth.php';

$scoreboardId = 'root';
$auditFile    = __DIR__ . '/data/audit.json';

$action = $_GET['action'] ?? 'scores';
$teamId = $_GET['team'] ?? '';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// GET scores is public (viewer page has no login)
if ($method === 'GET' && $action === 'scores') {
    $data = readScoreboardData();
    $data['hasPreviousSnapshot'] = is_file(__DIR__ . '/data/scores.previous.json');
    jsonResponse($data);
}

// Everything else requires a valid session with access to this scoreboard
$currentUser = requireAuthJson($scoreboardId);

try {
    if ($method === 'GET' && $action === 'audit') {
        $entries = [];
        if (is_file($auditFile)) {
            $data    = json_decode(file_get_contents($auditFile) ?: '', true);
            $entries = is_array($data) ? array_slice($data, 0, 50) : [];
        }
        jsonResponse(['entries' => $entries]);
    }

    if ($method !== 'POST') {
        jsonResponse(['error' => 'Method not allowed.'], 405);
    }

    if ($action === 'update') {
        $payload = readJsonRequestBody();
        $amount  = $payload['amount'] ?? null;
        if (!is_numeric($amount)) {
            jsonResponse(['error' => 'Amount must be a valid number.'], 400);
        }

        $saved = writeScoreboardData(function (array $data) use ($teamId, $amount): array {
            $teamIndex = findTeamIndex($data, $teamId);
            if ($teamIndex === null) {
                throw new InvalidArgumentException('Team not found.');
            }
            $data['teams'][$teamIndex]['score'] = (int) ($data['teams'][$teamIndex]['score'] ?? 0) + (int) $amount;
            $data['teams'][$teamIndex]['score_changed_at'] = gmdate('c');
            return $data;
        });

        $teamName = '';
        $newScore = 0;
        foreach ($saved['teams'] as $team) {
            if ($team['id'] === $teamId) {
                $teamName = $team['name'];
                $newScore = $team['score'];
                break;
            }
        }

        logAudit($auditFile, [
            'timestamp'  => gmdate('c'),
            'username'   => $currentUser['username'],
            'action'     => 'adjust',
            'team_id'    => $teamId,
            'team_name'  => $teamName,
            'amount'     => (int) $amount,
            'new_score'  => $newScore,
            'ip'         => clientIp(),
            'user_agent' => clientUserAgent(),
        ]);

        jsonResponse($saved);
    }

    if ($action === 'reset-team') {
        $saved = writeScoreboardData(function (array $data) use ($teamId): array {
            $teamIndex = findTeamIndex($data, $teamId);
            if ($teamIndex === null) {
                throw new InvalidArgumentException('Team not found.');
            }
            // Snapshot the pre-reset team record so a mistaken reset can be recovered.
            @file_put_contents(
                __DIR__ . '/data/scores.previous-single.json',
                json_encode([
                    'snapshot_at' => gmdate('c'),
                    'action'      => 'reset-team',
                    'team'        => $data['teams'][$teamIndex],
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL,
                LOCK_EX
            );
            $data['teams'][$teamIndex]['score'] = 0;
            $data['teams'][$teamIndex]['score_changed_at'] = gmdate('c');
            return $data;
        });

        $teamName = '';
        foreach ($saved['teams'] as $team) {
            if ($team['id'] === $teamId) { $teamName = $team['name']; break; }
        }

        logAudit($auditFile, [
            'timestamp'  => gmdate('c'),
            'username'   => $currentUser['username'],
            'action'     => 'reset-team',
            'team_id'    => $teamId,
            'team_name'  => $teamName,
            'amount'     => null,
            'new_score'  => 0,
            'ip'         => clientIp(),
            'user_agent' => clientUserAgent(),
        ]);

        jsonResponse($saved);
    }

    if ($action === 'rename-title') {
        $payload = readJsonRequestBody();
        $title   = trim($payload['title'] ?? '');
        if ($title === '') {
            jsonResponse(['error' => 'Title cannot be empty.'], 400);
        }

        $saved = writeScoreboardData(function (array $data) use ($title): array {
            $data['title'] = $title;
            return $data;
        });

        logAudit($auditFile, [
            'timestamp'  => gmdate('c'),
            'username'   => $currentUser['username'],
            'action'     => 'rename-title',
            'team_id'    => null,
            'team_name'  => null,
            'amount'     => null,
            'new_score'  => null,
            'ip'         => clientIp(),
            'user_agent' => clientUserAgent(),
        ]);

        jsonResponse($saved);
    }

    if ($action === 'rename-team') {
        $payload = readJsonRequestBody();
        $name    = trim($payload['name'] ?? '');
        if ($name === '') {
            jsonResponse(['error' => 'Team name cannot be empty.'], 400);
        }

        $saved = writeScoreboardData(function (array $data) use ($teamId, $name): array {
            $teamIndex = findTeamIndex($data, $teamId);
            if ($teamIndex === null) {
                throw new InvalidArgumentException('Team not found.');
            }
            $data['teams'][$teamIndex]['name'] = $name;
            return $data;
        });

        logAudit($auditFile, [
            'timestamp'  => gmdate('c'),
            'username'   => $currentUser['username'],
            'action'     => 'rename-team',
            'team_id'    => $teamId,
            'team_name'  => $name,
            'amount'     => null,
            'new_score'  => null,
            'ip'         => clientIp(),
            'user_agent' => clientUserAgent(),
        ]);

        jsonResponse($saved);
    }

    if ($action === 'reset-all') {
        $saved = writeScoreboardData(function (array $data): array {
            // Snapshot the current state to data/scores.previous.json so a Reset All
            // can be recovered by copying that file over data/scores.json.
            $snapshotPath = __DIR__ . '/data/scores.previous.json';
            @file_put_contents(
                $snapshotPath,
                json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL,
                LOCK_EX
            );
            $now = gmdate('c');
            foreach ($data['teams'] as &$team) {
                $team['score'] = 0;
                $team['score_changed_at'] = $now;
            }
            unset($team);
            return $data;
        });

        logAudit($auditFile, [
            'timestamp'  => gmdate('c'),
            'username'   => $currentUser['username'],
            'action'     => 'reset-all',
            'team_id'    => null,
            'team_name'  => null,
            'amount'     => null,
            'new_score'  => null,
            'ip'         => clientIp(),
            'user_agent' => clientUserAgent(),
        ]);

        jsonResponse($saved);
    }

    if ($action === 'add-team') {
        $payload = readJsonRequestBody();
        $name    = trim($payload['name'] ?? '');
        $color   = trim($payload['color'] ?? '');
        if ($name === '') {
            jsonResponse(['error' => 'Team name cannot be empty.'], 400);
        }
        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
            $color = '#64748b';
        }

        $newTeamId = 'team-' . bin2hex(random_bytes(4));

        $saved = writeScoreboardData(function (array $data) use ($newTeamId, $name, $color): array {
            $data['teams'][] = [
                'id'    => $newTeamId,
                'name'  => $name,
                'color' => $color,
                'score' => 0,
                'score_changed_at' => gmdate('c'),
            ];
            return $data;
        });

        logAudit($auditFile, [
            'timestamp'  => gmdate('c'),
            'username'   => $currentUser['username'],
            'action'     => 'add-team',
            'team_id'    => $newTeamId,
            'team_name'  => $name,
            'amount'     => null,
            'new_score'  => 0,
            'ip'         => clientIp(),
            'user_agent' => clientUserAgent(),
        ]);

        jsonResponse($saved);
    }

    if ($action === 'remove-team') {
        $removedName = '';
        $saved = writeScoreboardData(function (array $data) use ($teamId, $currentUser, &$removedName): array {
            $teamIndex = findTeamIndex($data, $teamId);
            if ($teamIndex === null) {
                throw new InvalidArgumentException('Team not found.');
            }
            $removedTeam = $data['teams'][$teamIndex];
            $removedName = (string) ($removedTeam['name'] ?? '');
            // Append the full team record to data/removed-teams.json so it can
            // be restored if the removal was accidental.
            $removedPath = __DIR__ . '/data/removed-teams.json';
            $existing = [];
            if (is_file($removedPath)) {
                $existingData = json_decode(file_get_contents($removedPath) ?: '', true);
                if (is_array($existingData)) $existing = $existingData;
            }
            array_unshift($existing, [
                'removed_at' => gmdate('c'),
                'removed_by' => $currentUser['username'],
                'team'       => $removedTeam,
            ]);
            $existing = array_slice($existing, 0, 100);
            @file_put_contents(
                $removedPath,
                json_encode($existing, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL,
                LOCK_EX
            );
            array_splice($data['teams'], $teamIndex, 1);
            return $data;
        });

        logAudit($auditFile, [
            'timestamp'  => gmdate('c'),
            'username'   => $currentUser['username'],
            'action'     => 'remove-team',
            'team_id'    => $teamId,
            'team_name'  => $removedName,
            'amount'     => null,
            'new_score'  => null,
            'ip'         => clientIp(),
            'user_agent' => clientUserAgent(),
        ]);

        jsonResponse($saved);
    }

    if ($action === 'restore-previous-scores') {
        if (($currentUser['role'] ?? '') !== 'admin') {
            jsonResponse(['error' => 'Admin access required.'], 403);
        }
        $snapshotPath = __DIR__ . '/data/scores.previous.json';
        if (!is_file($snapshotPath)) {
            jsonResponse(['error' => 'No Reset All snapshot available to restore.'], 404);
        }
        $snapshotRaw = file_get_contents($snapshotPath) ?: '';
        $snapshot = json_decode($snapshotRaw, true);
        if (!is_array($snapshot) || !isset($snapshot['teams'])) {
            jsonResponse(['error' => 'Snapshot file is not valid JSON.'], 500);
        }

        $saved = writeScoreboardData(function (array $data) use ($snapshot): array {
            $data['teams'] = $snapshot['teams'];
            if (isset($snapshot['title'])) $data['title'] = $snapshot['title'];
            return $data;
        });

        logAudit($auditFile, [
            'timestamp'  => gmdate('c'),
            'username'   => $currentUser['username'],
            'action'     => 'restore-previous-scores',
            'team_id'    => null, 'team_name' => null, 'amount' => null, 'new_score' => null,
            'ip'         => clientIp(),
            'user_agent' => clientUserAgent(),
        ]);

        jsonResponse($saved);
    }

    jsonResponse(['error' => 'Unknown action.'], 404);
} catch (InvalidArgumentException $exception) {
    jsonResponse(['error' => $exception->getMessage()], 404);
} catch (Throwable $exception) {
    jsonResponse(['error' => 'Something went wrong while updating scores.'], 500);
}
