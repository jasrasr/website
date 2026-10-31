<?php
/* File Name: backup.php
Author: Jason Lamb (with help from ChatGPT)
Description: Read-only, authenticated per-user ZIP export of original JSON and owned photos.
Revision: 1.0.0
Created Date: 2026-10-27
Modified Date: 2026-10-27
Changelog: 1.0.0 - Create verified temporary user-only backup without touching live storage. */
declare(strict_types=1);

/**
 * ZIP export includes the original JsonStore document, unchanged, plus ONLY
 * image files associated with the current authenticated account.
 *
 * On missing photos or ZipArchive support, fail instead of claiming a partial
 * backup. Never put private archives into a public web directory.
 */
function workout_prepare_backup(string $jsonPath, string $storageRoot, string $userId): string
{
    if (!class_exists(ZipArchive::class)) {
        throw new RuntimeException('ZIP support is unavailable on this PHP installation.');
    }
    $data = \Jasr\Framework\JsonStore::read($jsonPath);
    $records = $data['records'];
    $photos = workout_owned_photo_manifest($records, $storageRoot, $userId);
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";

    $tmp = tempnam(sys_get_temp_dir(), 'workout-export-');
    if ($tmp === false) throw new RuntimeException('Unable to prepare a private backup.');
    $zip = new ZipArchive();
    $opened = false;
    try {
        if ($zip->open($tmp, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Unable to create a backup archive.');
        }
        $opened = true;
        $instructions =
            "Workout Tracker private backup\n"
            . "Created: " . gmdate(DATE_ATOM) . "\n"
            . "Records: " . count($records) . "\n"
            . "Photos: " . count($photos) . "\n"
            . "Source: Your authenticated workout account only.\n\n"
            . "workouts.json is a copy of the original data, including edit history.\n"
            . "Photos are preserved under photos/.\n"
            . "Store this ZIP securely. It contains sensitive personal information.\n"
            . "Restoration is a deliberate administrator action, never automatic.\n"
            . "Before restoring, save a separate backup of current live storage.\n";
        if (!$zip->addFromString('workouts.json', $json)
            || !$zip->addFromString('README.txt', $instructions)) {
            throw new RuntimeException('Unable to add records to the backup.');
        }
        foreach ($photos as $photo) {
            if (!$zip->addFile($photo['path'], 'photos/' . $photo['filename'])) {
                throw new RuntimeException('Unable to include every personal workout photo.');
            }
        }
        $complete = $zip->close();
        $opened = false;
        if (!$complete) throw new RuntimeException('Unable to finalize the backup archive.');
        return $tmp;
    } catch (Throwable $e) {
        if ($opened) $zip->close();
        unlink($tmp);
        throw $e;
    }
}
