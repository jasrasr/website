<?php declare(strict_types=1);
/**
 * Filename: collide/scoreboard_lib.php
 * Revision : 1.2.0
 * Description : Core library for CVC Collide Scoreboard. Defines 6 teams
 *               (6th-8th Boys/Girls), handles JSON file read/write with file locking,
 *               and normalizes Collide-only motto and walk-up song metadata.
 * Author : Jason Lamb (with help from Claude Code)
 * Created Date : 2026-04-09
 * Modified Date : 2026-09-13
 * Changelog :
 * 1.0.0 Initial release for Collide scoreboard instance
 * 1.1.0 Add per-team motto and walk-up song metadata defaults/normalization
 * 1.2.0 Add placeholder quick-song keys for every Collide team
 */

const SCOREBOARD_DATA_FILE = __DIR__ . '/data/scores.json';

function defaultCollidePlaceholderSong(string $teamId): string
{
    $map = [
        'sixth-boys' => 'blue-burst',
        'sixth-girls' => 'pink-spark',
        'seventh-boys' => 'teal-rise',
        'seventh-girls' => 'purple-pop',
        'eighth-boys' => 'orange-charge',
        'eighth-girls' => 'green-run',
    ];

    return $map[$teamId] ?? 'default-chime';
}

function scoreboardDefaultData(): array
{
    return [
        'title' => 'CVC Collide Scoreboard',
        'updatedAt' => null,
        'teams' => [
            [
                'id' => 'sixth-boys',
                'name' => '6th Boys',
                'color' => '#1d4ed8',
                'score' => 0,
                'motto' => '',
                'placeholder_song' => 'blue-burst',
                'walkup_song' => null,
            ],
            [
                'id' => 'sixth-girls',
                'name' => '6th Girls',
                'color' => '#db2777',
                'score' => 0,
                'motto' => '',
                'placeholder_song' => 'pink-spark',
                'walkup_song' => null,
            ],
            [
                'id' => 'seventh-boys',
                'name' => '7th Boys',
                'color' => '#0f766e',
                'score' => 0,
                'motto' => '',
                'placeholder_song' => 'teal-rise',
                'walkup_song' => null,
            ],
            [
                'id' => 'seventh-girls',
                'name' => '7th Girls',
                'color' => '#7c3aed',
                'score' => 0,
                'motto' => '',
                'placeholder_song' => 'purple-pop',
                'walkup_song' => null,
            ],
            [
                'id' => 'eighth-boys',
                'name' => '8th Boys',
                'color' => '#ea580c',
                'score' => 0,
                'motto' => '',
                'placeholder_song' => 'orange-charge',
                'walkup_song' => null,
            ],
            [
                'id' => 'eighth-girls',
                'name' => '8th Girls',
                'color' => '#15803d',
                'score' => 0,
                'motto' => '',
                'placeholder_song' => 'green-run',
                'walkup_song' => null,
            ],
        ],
    ];
}

function normalizeWalkupSong(mixed $song): ?array
{
    if (!is_array($song)) {
        return null;
    }

    $file = basename((string) ($song['file'] ?? ''));
    if ($file === '') {
        return null;
    }

    $uploadedAt = (string) ($song['uploaded_at'] ?? '');
    $url = 'media/walkup/' . rawurlencode($file);
    if ($uploadedAt !== '') {
        $url .= '?v=' . rawurlencode($uploadedAt);
    }

    return [
        'file' => $file,
        'url' => $url,
        'original_name' => (string) ($song['original_name'] ?? $file),
        'mime_type' => (string) ($song['mime_type'] ?? ''),
        'size_bytes' => (int) ($song['size_bytes'] ?? 0),
        'uploaded_at' => $uploadedAt,
        'uploaded_by' => (string) ($song['uploaded_by'] ?? ''),
    ];
}

function scoreboardNormalizeData(array $data): array
{
    $default = scoreboardDefaultData();

    $data['title'] = trim((string) ($data['title'] ?? '')) !== ''
        ? (string) $data['title']
        : $default['title'];

    if (!array_key_exists('updatedAt', $data)) {
        $data['updatedAt'] = null;
    }

    $teams = [];
    foreach (($data['teams'] ?? []) as $team) {
        if (!is_array($team)) {
            continue;
        }

        $teamId = trim((string) ($team['id'] ?? ''));
        if ($teamId === '') {
            continue;
        }

        $team['id'] = $teamId;
        $team['name'] = trim((string) ($team['name'] ?? '')) !== '' ? (string) $team['name'] : $teamId;
        $team['color'] = preg_match('/^#[0-9a-fA-F]{6}$/', (string) ($team['color'] ?? '')) ? (string) $team['color'] : '#64748b';
        $team['score'] = (int) ($team['score'] ?? 0);
        $team['motto'] = substr(trim((string) ($team['motto'] ?? '')), 0, 160);
        $placeholderSong = preg_replace('/[^a-zA-Z0-9_-]/', '-', (string) ($team['placeholder_song'] ?? '')) ?: '';
        $team['placeholder_song'] = $placeholderSong !== '' ? $placeholderSong : defaultCollidePlaceholderSong($teamId);
        $team['walkup_song'] = normalizeWalkupSong($team['walkup_song'] ?? null);
        $teams[] = $team;
    }

    $data['teams'] = $teams !== [] ? array_values($teams) : $default['teams'];

    return $data;
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

    return scoreboardNormalizeData(is_array($decoded) ? $decoded : scoreboardDefaultData());
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

        $updated = scoreboardNormalizeData($callback(scoreboardNormalizeData($current)));
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
