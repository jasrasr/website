<?php declare(strict_types=1);
/**
 * Filename: frontlines/api.php
 * Revision : 1.6.0
 * Description : REST API endpoint for CVC Frontlines Scoreboard score management.
 *               Handles reading, updating, resetting, and renaming teams and title,
 *               plus goal/category definitions and one-tap goal awards.
 * Author : Jason Lamb (with help from Claude Code)
 * Created Date : 2026-04-09
 * Modified Date : 2026-06-21
 * Changelog :
 * 1.0.0 Initial release for Frontlines scoreboard instance
 * 1.1.0 Allow negative scores (removed max(0) floor)
 * 1.2.0 Added session authentication and audit logging per action
 * 1.3.0 Added category actions: list-categories, add-category, update-category,
 *       remove-category, award-category (Frontlines-only goal scoring)
 * 1.4.0 Stamp score_changed_at on every score change (adjust/reset/add-team/award-category) for tiebreaker; Reset All snapshots data/scores.previous.json before clearing for recovery
 * 1.5.0 reset-team snapshots data/scores.previous-single.json. remove-team appends to data/removed-teams.json. New restore-previous-scores action (admin-only). scores GET adds hasPreviousSnapshot flag.
 * 1.6.0 Add ranked categories with explicit 12000-to-1000 award values.
 */

require __DIR__ . '/scoreboard_lib.php';
require __DIR__ . '/../auth.php';

$scoreboardId = 'frontlines';
$auditFile    = __DIR__ . '/data/audit.json';
const RANKED_CATEGORY_POINTS = [12000, 11000, 10000, 9000, 8000, 7000, 6000, 5000, 4000, 3000, 2000, 1000];

function normalizeCategoryScoringMode($value): string
{
    return $value === 'ranked' ? 'ranked' : 'fixed';
}

function rankedCategoryAwardPoints($value): ?int
{
    if (!is_numeric($value)) {
        return null;
    }

    $points = (int) $value;
    return in_array($points, RANKED_CATEGORY_POINTS, true) ? $points : null;
}

$action = $_GET['action'] ?? 'scores';
$teamId = $_GET['team'] ?? '';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET' && $action === 'scores') {
    $data = readScoreboardData();
    $data['hasPreviousSnapshot'] = is_file(__DIR__ . '/data/scores.previous.json');
    jsonResponse($data);
}

$currentUser = requireAuthJson($scoreboardId);
$isAdmin = ($currentUser['role'] ?? '') === 'admin';

try {
    if ($method === 'GET' && $action === 'list-categories') {
        jsonResponse(readCategoriesData());
    }

    if ($method === 'GET' && $action === 'audit') {
        $entries = [];
        if (is_file($auditFile)) {
            $data    = json_decode(file_get_contents($auditFile) ?: '', true);
            $entries = is_array($data) ? array_slice($data, 0, 1000) : [];
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
            'team_id'    => $teamId, 'team_name' => $teamName, 'amount' => null, 'new_score' => 0,
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
        $saved = writeScoreboardData(function (array $data) use ($teamId, $currentUser, &$removedName): array {
            $teamIndex = findTeamIndex($data, $teamId);
            if ($teamIndex === null) {
                throw new InvalidArgumentException('Team not found.');
            }
            $removedTeam = $data['teams'][$teamIndex];
            $removedName = (string) ($removedTeam['name'] ?? '');
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

    if ($action === 'add-category') {
        if (!$isAdmin) {
            jsonResponse(['error' => 'Admin access required.'], 403);
        }
        $payload = readJsonRequestBody();
        $name    = trim((string) ($payload['name'] ?? ''));
        $points  = $payload['points'] ?? null;
        $maxRaw  = $payload['maxAwardsPerTeam'] ?? null;
        $scoringMode = normalizeCategoryScoringMode($payload['scoringMode'] ?? 'fixed');
        if ($name === '') {
            jsonResponse(['error' => 'Category name cannot be empty.'], 400);
        }
        if ($scoringMode === 'fixed' && (!is_numeric($points) || (int) $points === 0)) {
            jsonResponse(['error' => 'Points must be a non-zero number.'], 400);
        }
        if ($scoringMode === 'ranked') {
            $points = 0;
        }
        $max = null;
        if ($maxRaw !== null && $maxRaw !== '' && $maxRaw !== 'unlimited') {
            if (!is_numeric($maxRaw) || (int) $maxRaw < 1) {
                jsonResponse(['error' => 'maxAwardsPerTeam must be unlimited or a positive integer.'], 400);
            }
            $max = (int) $maxRaw;
        }

        $newId = 'cat-' . bin2hex(random_bytes(4));
        $now   = gmdate('c');

        $saved = writeCategoriesData(function (array $data) use ($newId, $name, $points, $max, $now, $scoringMode): array {
            $data['categories'][] = [
                'id'               => $newId,
                'name'             => $name,
                'points'           => (int) $points,
                'scoringMode'      => $scoringMode,
                'maxAwardsPerTeam' => $max,
                'active'           => true,
                'created_at'       => $now,
                'modified_at'      => $now,
            ];
            return $data;
        });

        logAudit($auditFile, [
            'timestamp'  => $now,
            'username'   => $currentUser['username'],
            'action'     => 'add-category',
            'category_id'   => $newId,
            'category_name' => $name,
            'points'        => (int) $points,
            'ip'         => clientIp(),
            'user_agent' => clientUserAgent(),
        ]);

        jsonResponse($saved);
    }

    if ($action === 'update-category') {
        if (!$isAdmin) {
            jsonResponse(['error' => 'Admin access required.'], 403);
        }
        $categoryId = $_GET['category'] ?? '';
        $payload    = readJsonRequestBody();
        if ($categoryId === '') {
            jsonResponse(['error' => 'Category id is required.'], 400);
        }

        $name   = isset($payload['name']) ? trim((string) $payload['name']) : null;
        $points = $payload['points'] ?? null;
        $scoringMode = array_key_exists('scoringMode', $payload) ? normalizeCategoryScoringMode($payload['scoringMode']) : null;
        $maxRaw = array_key_exists('maxAwardsPerTeam', $payload) ? $payload['maxAwardsPerTeam'] : '__unset__';
        $active = array_key_exists('active', $payload) ? (bool) $payload['active'] : null;

        if ($name !== null && $name === '') {
            jsonResponse(['error' => 'Category name cannot be empty.'], 400);
        }
        if ($scoringMode === 'ranked') {
            $points = 0;
        }
        if ($points !== null && $scoringMode !== 'ranked' && (!is_numeric($points) || (int) $points === 0)) {
            jsonResponse(['error' => 'Points must be a non-zero number.'], 400);
        }

        $maxValue = null;
        if ($maxRaw !== '__unset__') {
            if ($maxRaw === null || $maxRaw === '' || $maxRaw === 'unlimited') {
                $maxValue = null;
            } elseif (!is_numeric($maxRaw) || (int) $maxRaw < 1) {
                jsonResponse(['error' => 'maxAwardsPerTeam must be unlimited or a positive integer.'], 400);
            } else {
                $maxValue = (int) $maxRaw;
            }
        }

        $saved = writeCategoriesData(function (array $data) use ($categoryId, $name, $points, $scoringMode, $maxRaw, $maxValue, $active): array {
            $idx = findCategoryIndex($data, $categoryId);
            if ($idx === null) {
                throw new InvalidArgumentException('Category not found.');
            }
            if ($name !== null)   { $data['categories'][$idx]['name']   = $name; }
            if ($points !== null) { $data['categories'][$idx]['points'] = (int) $points; }
            if ($scoringMode !== null) { $data['categories'][$idx]['scoringMode'] = $scoringMode; }
            if ($maxRaw !== '__unset__') { $data['categories'][$idx]['maxAwardsPerTeam'] = $maxValue; }
            if ($active !== null) { $data['categories'][$idx]['active'] = $active; }
            $data['categories'][$idx]['modified_at'] = gmdate('c');
            return $data;
        });

        logAudit($auditFile, [
            'timestamp'  => gmdate('c'),
            'username'   => $currentUser['username'],
            'action'     => 'update-category',
            'category_id'   => $categoryId,
            'ip'         => clientIp(),
            'user_agent' => clientUserAgent(),
        ]);

        jsonResponse($saved);
    }

    if ($action === 'remove-category') {
        if (!$isAdmin) {
            jsonResponse(['error' => 'Admin access required.'], 403);
        }
        $categoryId = $_GET['category'] ?? '';
        if ($categoryId === '') {
            jsonResponse(['error' => 'Category id is required.'], 400);
        }

        $removedName = '';
        $saved = writeCategoriesData(function (array $data) use ($categoryId, &$removedName): array {
            $idx = findCategoryIndex($data, $categoryId);
            if ($idx === null) {
                throw new InvalidArgumentException('Category not found.');
            }
            $removedName = (string) ($data['categories'][$idx]['name'] ?? '');
            array_splice($data['categories'], $idx, 1);
            return $data;
        });

        logAudit($auditFile, [
            'timestamp'  => gmdate('c'),
            'username'   => $currentUser['username'],
            'action'     => 'remove-category',
            'category_id'   => $categoryId,
            'category_name' => $removedName,
            'ip'         => clientIp(),
            'user_agent' => clientUserAgent(),
        ]);

        jsonResponse($saved);
    }

    if ($action === 'award-category') {
        $categoryId = $_GET['category'] ?? '';
        if ($teamId === '' || $categoryId === '') {
            jsonResponse(['error' => 'Team id and category id are required.'], 400);
        }

        $categoriesData = readCategoriesData();
        $catIdx = findCategoryIndex($categoriesData, $categoryId);
        if ($catIdx === null) {
            jsonResponse(['error' => 'Category not found.'], 404);
        }
        $category = $categoriesData['categories'][$catIdx];
        if (empty($category['active'])) {
            jsonResponse(['error' => 'Category is not active.'], 400);
        }

        $scoringMode = normalizeCategoryScoringMode($category['scoringMode'] ?? 'fixed');
        $existing = countCategoryAwards($auditFile, $teamId, $categoryId);

        if ($scoringMode === 'ranked' && $existing > 0) {
            jsonResponse(['error' => 'Team already has an award for this ranked category.'], 409);
        }

        $cap = $category['maxAwardsPerTeam'] ?? null;
        if ($cap !== null) {
            if ($existing >= (int) $cap) {
                jsonResponse(['error' => 'This team has already reached the award cap for this category.'], 409);
            }
        }

        if ($scoringMode === 'ranked') {
            $pointsDelta = rankedCategoryAwardPoints($_GET['awardPoints'] ?? null);
            if ($pointsDelta === null) {
                jsonResponse(['error' => 'Ranked category award points must be one of 12000, 11000, 10000, 9000, 8000, 7000, 6000, 5000, 4000, 3000, 2000, or 1000.'], 400);
            }
        } else {
            $pointsDelta = (int) ($category['points'] ?? 0);
        }
        $categoryName = (string) ($category['name'] ?? '');

        $saved = writeScoreboardData(function (array $data) use ($teamId, $pointsDelta): array {
            $teamIndex = findTeamIndex($data, $teamId);
            if ($teamIndex === null) {
                throw new InvalidArgumentException('Team not found.');
            }
            $data['teams'][$teamIndex]['score'] = (int) ($data['teams'][$teamIndex]['score'] ?? 0) + $pointsDelta;
            $data['teams'][$teamIndex]['score_changed_at'] = gmdate('c');
            return $data;
        });

        $teamName = '';
        $newScore = 0;
        foreach ($saved['teams'] as $team) {
            if ($team['id'] === $teamId) { $teamName = $team['name']; $newScore = $team['score']; break; }
        }

        logAudit($auditFile, [
            'timestamp'     => gmdate('c'),
            'username'      => $currentUser['username'],
            'action'        => 'award-category',
            'team_id'       => $teamId,
            'team_name'     => $teamName,
            'category_id'   => $categoryId,
            'category_name' => $categoryName,
            'amount'        => $pointsDelta,
            'new_score'     => $newScore,
            'ip'            => clientIp(),
            'user_agent'    => clientUserAgent(),
        ]);

        jsonResponse($saved);
    }

    if ($action === 'restore-previous-scores') {
        if (!$isAdmin) {
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
