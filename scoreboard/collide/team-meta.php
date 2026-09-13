<?php declare(strict_types=1);
/**
 * Filename: collide/team-meta.php
 * Revision : 1.1.0
 * Description : Collide-only API for per-team motto text, walk-up song uploads,
 *               and full-screen hurray triggers.
 * Author : Jason Lamb (with help from ChatGPT)
 * Created Date : 2026-09-13
 * Modified Date : 2026-09-13
 * Changelog :
 * 1.0.0 Initial Collide motto and walk-up song metadata endpoint
 * 1.1.0 Add authenticated hurray trigger event for the public viewer
 */

require __DIR__ . '/scoreboard_lib.php';
require __DIR__ . '/../auth.php';

const WALKUP_AUDIO_DIR = __DIR__ . '/media/walkup';
const WALKUP_MAX_BYTES = 15728640; // 15 MB
const WALKUP_ALLOWED_MIME_TO_EXT = [
    'audio/mpeg' => 'mp3',
    'audio/mp3' => 'mp3',
    'audio/wav' => 'wav',
    'audio/wave' => 'wav',
    'audio/x-wav' => 'wav',
    'audio/ogg' => 'ogg',
    'application/ogg' => 'ogg',
    'audio/mp4' => 'm4a',
    'audio/x-m4a' => 'm4a',
    'audio/aac' => 'aac',
    'audio/webm' => 'webm',
];

$scoreboardId = 'collide';
$auditFile    = __DIR__ . '/data/audit.json';
$action       = $_GET['action'] ?? 'save';
$method       = $_SERVER['REQUEST_METHOD'] ?? 'GET';

$currentUser = requireAuthJson($scoreboardId);

function cleanTeamId(string $teamId): string
{
    return preg_replace('/[^a-zA-Z0-9_-]/', '-', $teamId) ?: 'team';
}

function ensureWalkupAudioDir(): void
{
    if (!is_dir(WALKUP_AUDIO_DIR)) {
        mkdir(WALKUP_AUDIO_DIR, 0775, true);
    }
}

function detectUploadedAudioMime(string $tmpName): string
{
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo !== false) {
            $mime = finfo_file($finfo, $tmpName);
            finfo_close($finfo);
            if (is_string($mime) && $mime !== '') {
                return strtolower($mime);
            }
        }
    }

    if (function_exists('mime_content_type')) {
        $mime = mime_content_type($tmpName);
        if (is_string($mime) && $mime !== '') {
            return strtolower($mime);
        }
    }

    return '';
}

function removeExistingWalkupFiles(string $teamId): void
{
    $safeTeamId = cleanTeamId($teamId);
    foreach (array_unique(array_values(WALKUP_ALLOWED_MIME_TO_EXT)) as $ext) {
        $path = WALKUP_AUDIO_DIR . '/' . $safeTeamId . '.' . $ext;
        if (is_file($path)) {
            @unlink($path);
        }
    }
}

function handleWalkupUpload(string $teamId, array $file, string $username): ?array
{
    $error = $file['error'] ?? UPLOAD_ERR_NO_FILE;
    if (is_array($error)) {
        throw new InvalidArgumentException('Only one audio file can be uploaded at a time.');
    }

    if ($error === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if ($error !== UPLOAD_ERR_OK) {
        throw new InvalidArgumentException('Audio upload failed. Try a smaller file or a different audio format.');
    }

    $tmpName = (string) ($file['tmp_name'] ?? '');
    $size = (int) ($file['size'] ?? 0);
    $originalName = basename((string) ($file['name'] ?? 'walkup-audio'));

    if ($tmpName === '' || !is_uploaded_file($tmpName)) {
        throw new InvalidArgumentException('Uploaded audio file was not accepted by PHP.');
    }

    if ($size <= 0 || $size > WALKUP_MAX_BYTES) {
        throw new InvalidArgumentException('Audio file must be greater than 0 bytes and no larger than 15 MB.');
    }

    $mime = detectUploadedAudioMime($tmpName);
    $ext = WALKUP_ALLOWED_MIME_TO_EXT[$mime] ?? null;
    if ($ext === null) {
        throw new InvalidArgumentException('Unsupported audio type. Use MP3, M4A/AAC, WAV, OGG, or WEBM.');
    }

    ensureWalkupAudioDir();
    removeExistingWalkupFiles($teamId);

    $filename = cleanTeamId($teamId) . '.' . $ext;
    $targetPath = WALKUP_AUDIO_DIR . '/' . $filename;

    if (!move_uploaded_file($tmpName, $targetPath)) {
        throw new RuntimeException('Unable to save the uploaded audio file.');
    }

    @chmod($targetPath, 0644);

    $uploadedAt = gmdate('c');

    return [
        'file' => $filename,
        'url' => 'media/walkup/' . rawurlencode($filename) . '?v=' . rawurlencode($uploadedAt),
        'original_name' => $originalName,
        'mime_type' => $mime,
        'size_bytes' => $size,
        'uploaded_at' => $uploadedAt,
        'uploaded_by' => $username,
    ];
}

try {
    if ($method !== 'POST') {
        jsonResponse(['error' => 'Method not allowed.'], 405);
    }

    if ($action === 'save') {
        $teamId = trim((string) ($_POST['team_id'] ?? ''));
        $motto = substr(trim((string) ($_POST['motto'] ?? '')), 0, 160);

        if ($teamId === '') {
            jsonResponse(['error' => 'Team is required.'], 400);
        }

        $uploadedSong = null;
        if (isset($_FILES['walkup_audio'])) {
            $uploadedSong = handleWalkupUpload($teamId, $_FILES['walkup_audio'], (string) $currentUser['username']);
        }

        $saved = writeScoreboardData(function (array $data) use ($teamId, $motto, $uploadedSong): array {
            $teamIndex = findTeamIndex($data, $teamId);
            if ($teamIndex === null) {
                throw new InvalidArgumentException('Team not found.');
            }

            $data['teams'][$teamIndex]['motto'] = $motto;
            if ($uploadedSong !== null) {
                $data['teams'][$teamIndex]['walkup_song'] = $uploadedSong;
            }

            return $data;
        });

        $teamName = '';
        foreach ($saved['teams'] as $team) {
            if (($team['id'] ?? '') === $teamId) {
                $teamName = (string) ($team['name'] ?? '');
                break;
            }
        }

        logAudit($auditFile, [
            'timestamp'  => gmdate('c'),
            'username'   => $currentUser['username'],
            'action'     => $uploadedSong === null ? 'update-team-motto' : 'update-team-motto-and-song',
            'team_id'    => $teamId,
            'team_name'  => $teamName,
            'amount'     => null,
            'new_score'  => null,
            'ip'         => clientIp(),
            'user_agent' => clientUserAgent(),
        ]);

        jsonResponse($saved);
    }

    if ($action === 'delete-song') {
        $payload = readJsonRequestBody();
        $teamId = trim((string) ($payload['team_id'] ?? ''));
        if ($teamId === '') {
            jsonResponse(['error' => 'Team is required.'], 400);
        }

        ensureWalkupAudioDir();
        removeExistingWalkupFiles($teamId);

        $saved = writeScoreboardData(function (array $data) use ($teamId): array {
            $teamIndex = findTeamIndex($data, $teamId);
            if ($teamIndex === null) {
                throw new InvalidArgumentException('Team not found.');
            }

            $data['teams'][$teamIndex]['walkup_song'] = null;
            return $data;
        });

        $teamName = '';
        foreach ($saved['teams'] as $team) {
            if (($team['id'] ?? '') === $teamId) {
                $teamName = (string) ($team['name'] ?? '');
                break;
            }
        }

        logAudit($auditFile, [
            'timestamp'  => gmdate('c'),
            'username'   => $currentUser['username'],
            'action'     => 'delete-walkup-song',
            'team_id'    => $teamId,
            'team_name'  => $teamName,
            'amount'     => null,
            'new_score'  => null,
            'ip'         => clientIp(),
            'user_agent' => clientUserAgent(),
        ]);

        jsonResponse($saved);
    }

    if ($action === 'hurray') {
        $payload = readJsonRequestBody();
        $teamId = trim((string) ($payload['team_id'] ?? ''));
        if ($teamId === '') {
            jsonResponse(['error' => 'Team is required.'], 400);
        }

        $eventId = bin2hex(random_bytes(8));
        $createdAt = gmdate('c');
        $event = null;

        $saved = writeScoreboardData(function (array $data) use ($teamId, $eventId, $createdAt, $currentUser, &$event): array {
            $teamIndex = findTeamIndex($data, $teamId);
            if ($teamIndex === null) {
                throw new InvalidArgumentException('Team not found.');
            }

            $team = $data['teams'][$teamIndex];
            $event = [
                'id' => $eventId,
                'team_id' => $teamId,
                'team_name' => (string) ($team['name'] ?? 'Team'),
                'team_color' => (string) ($team['color'] ?? '#38bdf8'),
                'message' => 'HURRAY!',
                'created_at' => $createdAt,
                'created_by' => (string) ($currentUser['username'] ?? ''),
            ];

            $data['hurray_event'] = $event;
            return $data;
        });

        logAudit($auditFile, [
            'timestamp'  => gmdate('c'),
            'username'   => $currentUser['username'],
            'action'     => 'trigger-hurray',
            'team_id'    => $teamId,
            'team_name'  => (string) ($event['team_name'] ?? ''),
            'amount'     => null,
            'new_score'  => null,
            'ip'         => clientIp(),
            'user_agent' => clientUserAgent(),
        ]);

        jsonResponse($saved);
    }

    jsonResponse(['error' => 'Unknown action.'], 404);
} catch (InvalidArgumentException $exception) {
    jsonResponse(['error' => $exception->getMessage()], 400);
} catch (Throwable $exception) {
    jsonResponse(['error' => 'Unable to update Collide team metadata.'], 500);
}
