<?php declare(strict_types=1);
/**
 * Filename: collide/team-meta.php
 * Revision : 1.2.0
 * Description : Collide-only API for per-team motto text, walk-up song uploads,
 *               existing walk-up song selection, and full-screen hurray triggers.
 * Author : Jason Lamb (with help from ChatGPT)
 * Created Date : 2026-09-13
 * Modified Date : 2026-09-14
 * Changelog :
 * 1.0.0 Initial Collide motto and walk-up song metadata endpoint
 * 1.1.0 Add authenticated hurray trigger event for the public viewer
 * 1.2.0 Add reusable uploaded-audio library listing and existing-file selection
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

function walkupAudioExtensions(): array
{
    return array_values(array_unique(array_values(WALKUP_ALLOWED_MIME_TO_EXT)));
}

function isAllowedWalkupAudioFilename(string $filename): bool
{
    $basename = basename($filename);
    if ($basename === '' || $basename !== $filename) {
        return false;
    }

    $ext = strtolower(pathinfo($basename, PATHINFO_EXTENSION));
    return $ext !== '' && in_array($ext, walkupAudioExtensions(), true);
}

function cleanAudioSlug(string $name): string
{
    $stem = pathinfo($name, PATHINFO_FILENAME);
    $slug = strtolower((string) preg_replace('/[^a-zA-Z0-9_-]+/', '-', $stem));
    $slug = trim($slug, '-_');
    return substr($slug !== '' ? $slug : 'walkup-song', 0, 60);
}

function walkupAudioUrl(string $filename, ?int $version = null): string
{
    $url = 'media/walkup/' . rawurlencode(basename($filename));
    if ($version !== null && $version > 0) {
        $url .= '?v=' . rawurlencode((string) $version);
    }
    return $url;
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

function listWalkupAudioLibrary(): array
{
    ensureWalkupAudioDir();

    $files = [];
    foreach (glob(WALKUP_AUDIO_DIR . '/*') ?: [] as $path) {
        if (!is_file($path)) {
            continue;
        }

        $filename = basename($path);
        if (!isAllowedWalkupAudioFilename($filename)) {
            continue;
        }

        $mtime = (int) (filemtime($path) ?: 0);
        $files[] = [
            'file' => $filename,
            'url' => walkupAudioUrl($filename, $mtime),
            'label' => $filename,
            'size_bytes' => (int) (filesize($path) ?: 0),
            'modified_at' => $mtime > 0 ? gmdate('c', $mtime) : '',
        ];
    }

    usort($files, static function (array $a, array $b): int {
        $timeCompare = strcmp((string) ($b['modified_at'] ?? ''), (string) ($a['modified_at'] ?? ''));
        if ($timeCompare !== 0) {
            return $timeCompare;
        }
        return strcasecmp((string) ($a['file'] ?? ''), (string) ($b['file'] ?? ''));
    });

    return $files;
}

function buildExistingWalkupSong(string $filename, string $username): array
{
    $filename = basename($filename);
    if (!isAllowedWalkupAudioFilename($filename)) {
        throw new InvalidArgumentException('Selected audio file is not a supported walk-up song type.');
    }

    $path = WALKUP_AUDIO_DIR . '/' . $filename;
    if (!is_file($path)) {
        throw new InvalidArgumentException('Selected audio file was not found.');
    }

    $mtime = (int) (filemtime($path) ?: time());

    return [
        'file' => $filename,
        'url' => walkupAudioUrl($filename, $mtime),
        'original_name' => $filename,
        'mime_type' => '',
        'size_bytes' => (int) (filesize($path) ?: 0),
        'uploaded_at' => gmdate('c', $mtime),
        'uploaded_by' => $username,
        'source' => 'existing-library-file',
    ];
}

function uniqueWalkupFilename(string $teamId, string $originalName, string $ext): string
{
    $prefix = cleanTeamId($teamId);
    $slug = cleanAudioSlug($originalName);
    $timestamp = gmdate('Ymd-His');
    $filename = $prefix . '-' . $timestamp . '-' . $slug . '.' . $ext;
    $path = WALKUP_AUDIO_DIR . '/' . $filename;

    if (!is_file($path)) {
        return $filename;
    }

    return $prefix . '-' . $timestamp . '-' . bin2hex(random_bytes(3)) . '-' . $slug . '.' . $ext;
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

    $filename = uniqueWalkupFilename($teamId, $originalName, $ext);
    $targetPath = WALKUP_AUDIO_DIR . '/' . $filename;

    if (!move_uploaded_file($tmpName, $targetPath)) {
        throw new RuntimeException('Unable to save the uploaded audio file.');
    }

    @chmod($targetPath, 0644);

    $uploadedAt = gmdate('c');

    return [
        'file' => $filename,
        'url' => walkupAudioUrl($filename, time()),
        'original_name' => $originalName,
        'mime_type' => $mime,
        'size_bytes' => $size,
        'uploaded_at' => $uploadedAt,
        'uploaded_by' => $username,
        'source' => 'upload',
    ];
}

try {
    if ($method !== 'POST') {
        jsonResponse(['error' => 'Method not allowed.'], 405);
    }

    if ($action === 'audio-library') {
        jsonResponse(['files' => listWalkupAudioLibrary()]);
    }

    if ($action === 'save') {
        $teamId = trim((string) ($_POST['team_id'] ?? ''));
        $motto = substr(trim((string) ($_POST['motto'] ?? '')), 0, 160);
        $existingWalkupFile = trim((string) ($_POST['existing_walkup_file'] ?? ''));

        if ($teamId === '') {
            jsonResponse(['error' => 'Team is required.'], 400);
        }

        $selectedSong = $existingWalkupFile !== ''
            ? buildExistingWalkupSong($existingWalkupFile, (string) $currentUser['username'])
            : null;

        $uploadedSong = null;
        if (isset($_FILES['walkup_audio'])) {
            $uploadedSong = handleWalkupUpload($teamId, $_FILES['walkup_audio'], (string) $currentUser['username']);
        }

        $nextSong = $uploadedSong ?? $selectedSong;

        $saved = writeScoreboardData(function (array $data) use ($teamId, $motto, $nextSong): array {
            $teamIndex = findTeamIndex($data, $teamId);
            if ($teamIndex === null) {
                throw new InvalidArgumentException('Team not found.');
            }

            $data['teams'][$teamIndex]['motto'] = $motto;
            if ($nextSong !== null) {
                $data['teams'][$teamIndex]['walkup_song'] = $nextSong;
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

        $auditAction = 'update-team-motto';
        if ($uploadedSong !== null) {
            $auditAction = 'update-team-motto-and-song-upload';
        } elseif ($selectedSong !== null) {
            $auditAction = 'update-team-motto-and-existing-song';
        }

        logAudit($auditFile, [
            'timestamp'  => gmdate('c'),
            'username'   => $currentUser['username'],
            'action'     => $auditAction,
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
            'action'     => 'remove-walkup-song-from-team',
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
