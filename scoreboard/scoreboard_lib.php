<?php declare(strict_types=1);
/**
 * Filename: scoreboard_lib.php
 * Revision : 1.2.0
 * Description : Core library for the default CVC Scoreboard. Defines default team data,
 *               handles JSON file read/write with file locking, and HTTP JSON responses.
 * Author : Jason Lamb (with help from Claude Code)
 * Created Date : 2026-04-09
 * Modified Date : 2026-06-13
 * Changelog :
 * 1.0.0 Initial PHP release, converted from Node.js/Express
 * 1.1.0 Rename default scoreboard title to Live Scoreboard
 * 1.2.0 Rename default team labels to Team 1 through Team 6
 */

const SCOREBOARD_DATA_FILE = __DIR__ . '/data/scores.json';

function scoreboardDefaultData(): array
{
    return [
        'title' => 'Live Scoreboard',
        'updatedAt' => null,
        'teams' => [
            [
                'id' => 'sixth-grade-boys',
                'name' => 'Team 1',
                'color' => '#1d4ed8',
                'score' => 0,
            ],
            [
                'id' => 'sixth-grade-girls',
                'name' => 'Team 2',
                'color' => '#db2777',
                'score' => 0,
            ],
            [
                'id' => 'seventh-grade-boys',
                'name' => 'Team 3',
                'color' => '#0f766e',
                'score' => 0,
            ],
            [
                'id' => 'seventh-grade-girls',
                'name' => 'Team 4',
                'color' => '#7c3aed',
                'score' => 0,
            ],
            [
                'id' => 'eighth-grade-boys',
                'name' => 'Team 5',
                'color' => '#ea580c',
                'score' => 0,
            ],
            [
                'id' => 'eighth-grade-girls',
                'name' => 'Team 6',
                'color' => '#15803d',
                'score' => 0,
            ],
        ],
    ];
}

function ensureScoreboardDataFile(): void
{
    $directory = dirname(SCOREBOARD_DATA_FILE);
    if (!is_dir($directory)) {
        mkdir($directory, 0775, true);
    }

    if (!is_file(SCOREBOARD_DATA_FILE)) {
        file_put_contents(SCOREBOARD_DATA_FILE, json_encode(scoreboardDefaultData(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL, LOCK_EX);
    }
}

function readScoreboardData(): array
{
    ensureScoreboardDataFile();

    $raw = file_get_contents(SCOREBOARD_DATA_FILE);
    $decoded = json_decode($raw ?: '', true);

    return is_array($decoded) ? $decoded : scoreboardDefaultData();
}

function writeScoreboardData(callable $callback): array
{
    ensureScoreboardDataFile();

    $handle = fopen(SCOREBOARD_DATA_FILE, 'c+');
    if ($handle === false) {
        throw new RuntimeException('Unable to open the scoreboard data file.');
    }

    try {
        if (!flock($handle, LOCK_EX)) {
            throw new RuntimeException('Unable to lock the scoreboard data file.');
        }

        rewind($handle);
        $raw = stream_get_contents($handle);
        $current = json_decode($raw ?: '', true);
        if (!is_array($current)) {
            $current = scoreboardDefaultData();
        }

        $updated = $callback($current);
        $updated['updatedAt'] = gmdate('c');

        $encoded = json_encode($updated, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($encoded === false) {
            throw new RuntimeException('Unable to encode the scoreboard data.');
        }

        ftruncate($handle, 0);
        rewind($handle);
        fwrite($handle, $encoded . PHP_EOL);
        fflush($handle);
        flock($handle, LOCK_UN);

        return $updated;
    } finally {
        fclose($handle);
    }
}

function findTeamIndex(array $data, string $teamId): ?int
{
    foreach ($data['teams'] ?? [] as $index => $team) {
        if (($team['id'] ?? '') === $teamId) {
            return $index;
        }
    }

    return null;
}

function jsonResponse(array $payload, int $statusCode = 200): never
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

function readJsonRequestBody(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || trim($raw) === '') {
        return [];
    }

    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}
