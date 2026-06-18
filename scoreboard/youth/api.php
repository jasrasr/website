<?php declare(strict_types=1);
/**
 * Filename: youth/api.php
 * Revision : 1.3.0
 * Description : REST API endpoint for CVC Youth Scoreboard score management.
 *               Handles reading, updating, resetting, and renaming teams and title.
 * Author : Jason Lamb (with help from Claude Code)
 * Created Date : 2026-04-09
 * Modified Date : 2026-06-18
 * Changelog :
 * 1.0.0 Initial release for Youth scoreboard instance
 * 1.1.0 Allow negative scores (removed max(0) floor)
 * 1.2.0 Added session authentication and audit logging per action
 * 1.3.0 Stamp score_changed_at on every score change (tiebreaker source); Reset All snapshots data/scores.previous.json before clearing for recovery
 */

require __DIR__ . '/scoreboard_lib.php';
require __DIR__ . '/../auth.php';

$scoreboardId = 'youth';
$auditFile    = __DIR__ . '/data/audit.json';

$action = $_GET['action'] ?? 'scores';
$teamId = $_GET['team'] ?? '';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET' && $action === 'scores') {
    jsonResponse(readScoreboardData());
}

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
            if ($team['id'] === $teamId) { $teamName = $team['name']; $newScore = $team['score']; break; }
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
            'team_id'    => null, 'team_name' => null, 'amount' => null, 'new_score' => null,
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
            'team_id'    => $teamId, 'team_name' => $name, 'amount' => null, 'new_score' => null,
            'ip'         => clientIp(),
            'user_agent' => clientUserAgent(),
        ]);

        jsonResponse($saved);
    }

    if ($action === 'reset-all') {
        $saved = writeScoreboardData(function (array $data): array {
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
            'team_id'    => null, 'team_name' => null, 'amount' => null, 'new_score' => null,
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
        $saved = writeScoreboardData(function (array $data) use ($teamId, &$removedName): array {
            $teamIndex = findTeamIndex($data, $teamId);
            if ($teamIndex === null) {
                throw new InvalidArgumentException('Team not found.');
            }
            $removedName = (string) ($data['teams'][$teamIndex]['name'] ?? '');
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

    jsonResponse(['error' => 'Unknown action.'], 404);
} catch (InvalidArgumentException $exception) {
    jsonResponse(['error' => $exception->getMessage()], 404);
} catch (Throwable $exception) {
    jsonResponse(['error' => 'Something went wrong while updating scores.'], 500);
}
