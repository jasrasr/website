<?php
/* File Name: photo.php
Author: Jason Lamb (with help from ChatGPT)
Description: Read-only, per-user photo lookup for authenticated Workout Tracker media.
Revision: 1.1.0
Created Date: 2026-10-27
Modified Date: 2026-10-27
Changelog: 1.1.0 - Support indexed legacy and multi-photo references with strict per-user isolation. 1.0.0 - Resolve only owned referenced images within protected user storage. */
declare(strict_types=1);

/** Do not accept filenames containing path separators, escape sequences or other file types. */
function workout_photo_name_is_safe(mixed $filename): bool
{
    return is_string($filename)
        && preg_match('/\A[a-zA-Z0-9_-]{1,100}\.(?:jpg|jpeg|png|webp)\z/D', $filename) === 1;
}

/** Keep legacy photo intact; all new attachments use the optional photos array. */
function workout_photo_names(array $record): array
{
    $names = [];
    if (($record['photo'] ?? null) !== null && ($record['photo'] ?? '') !== '') {
        $names[] = $record['photo'];
    }
    $additional = $record['photos'] ?? [];
    if (!is_array($additional) || !array_is_list($additional)) return $names;
    foreach ($additional as $filename) $names[] = $filename;
    return $names;
}

/**
 * Return a verified image path for a photo referenced by this account's own
 * saved workout record. Never accept a client-supplied path or arbitrary name.
 * Null means no safe/readable photo. This function has NO write side effects.
 */
function workout_photo_for_record(array $record, string $storageRoot, string $userId, int $index = 0): ?array
{
    $names = workout_photo_names($record);
    $name = $index >= 0 && $index < count($names) ? $names[$index] : null;
    if (!workout_photo_name_is_safe($name)) return null;

    $rootReal = realpath($storageRoot);
    if ($rootReal === false || !is_dir($rootReal)) return null;
    $photoRoot = $rootReal . DIRECTORY_SEPARATOR . 'photos';
    $folder = $photoRoot . DIRECTORY_SEPARATOR . hash('sha256', $userId);
    if (is_link($folder) || !is_dir($folder)) return null;
    $folderReal = realpath($folder);
    if ($folderReal === false || $folderReal !== $folder || dirname($folderReal) !== $photoRoot) return null;

    $candidate = $folder . DIRECTORY_SEPARATOR . $name;
    if (is_link($candidate) || !is_file($candidate) || !is_readable($candidate)) return null;
    $actual = realpath($candidate);
    if ($actual === false || dirname($actual) !== $folderReal) return null;

    $extension = strtolower((string)pathinfo($name, PATHINFO_EXTENSION));
    $expected = match ($extension) {
        'jpg', 'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'webp' => 'image/webp',
        default => null
    };
    if ($expected === null) return null;
    $detected = (new finfo(FILEINFO_MIME_TYPE))->file($actual);
    if ($detected !== $expected || getimagesize($actual) === false) return null;
    return ['path' => $actual, 'filename' => $name, 'mime' => $expected];
}

/** Identify a photo strictly by an entry ID already in the authenticated user's JSON file. */
function workout_owned_photo(array $records, string $entryId, string $storageRoot, string $userId, int $index = 0): ?array
{
    if ($index < 0 || $index > 32 || preg_match('/\A[a-f0-9]{24}\z/D', $entryId) !== 1) return null;
    $record = null;
    foreach ($records as $item) {
        if (!is_array($item) || ($item['id'] ?? null) !== $entryId) continue;
        if ($record !== null) return null; // Fail closed for corrupted duplicate IDs.
        $record = $item;
    }
    if ($record === null) return null;
    return workout_photo_for_record($record, $storageRoot, $userId, $index);
}

/**
 * A backup is only complete if every referenced personal photo is accessible.
 * The manifest includes only photos listed by the user's own records.
 */
function workout_owned_photo_manifest(array $records, string $storageRoot, string $userId): array
{
    $found = [];
    $manifest = [];
    foreach ($records as $record) {
        if (!is_array($record)) continue;
        $names = workout_photo_names($record);
        if (!$names) continue;
        $entryId = $record['id'] ?? null;
        if (!is_string($entryId) || preg_match('/\A[a-f0-9]{24}\z/D', $entryId) !== 1) {
            throw new RuntimeException('A saved photo has an invalid exercise reference.');
        }
        if (count($names) > 33) throw new RuntimeException('A workout has too many photo references.');
        foreach ($names as $index => $name) {
            if (!workout_photo_name_is_safe($name) || isset($found[$name])) {
                throw new RuntimeException('A duplicate or unsafe workout photo was referenced.');
            }
            $photo = workout_owned_photo($records, $entryId, $storageRoot, $userId, $index);
            if ($photo === null) {
                throw new RuntimeException('A saved workout photo is missing or invalid; backup would be incomplete.');
            }
            $found[$name] = true;
            $manifest[] = $photo;
        }
    }
    return $manifest;
}
