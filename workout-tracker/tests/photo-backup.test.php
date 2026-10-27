<?php
/* File Name: photo-backup.test.php
Author: Jason Lamb (with help from ChatGPT)
Description: Synthetic tests for private photo access and non-destructive ZIP backups.
Revision: 1.0.0
Created Date: 2026-10-27
Modified Date: 2026-10-27
Changelog: 1.0.0 - Verify owner binding, traversal denial, missing-photo failure and data integrity. */
declare(strict_types=1);
require_once dirname(__DIR__) . '/photo.php';
require_once dirname(__DIR__) . '/backup.php';
require_once dirname(__DIR__, 2) . '/1-Framework/core/JsonStore.php';

use Jasr\Framework\JsonStore;

function test_assert(bool $value, string $message): void {
    if (!$value) throw new RuntimeException('FAIL: ' . $message);
}
function test_fails(callable $fn, string $message): void {
    try { $fn(); } catch (RuntimeException $e) { return; }
    throw new RuntimeException('FAIL: expected failure - ' . $message);
}

$root = sys_get_temp_dir() . '/workout-photo-test-' . bin2hex(random_bytes(8));
$photos = $root . '/photos';
$user = 'user-one@example.invalid';
$other = 'user-two@example.invalid';
$firstId = 'aaaaaaaaaaaaaaaaaaaaaaaa';
$otherId = 'bbbbbbbbbbbbbbbbbbbbbbbb';
$filename = $firstId . '.png';
$folder = $photos . '/' . hash('sha256', $user);
$otherFolder = $photos . '/' . hash('sha256', $other);
$jsonPath = $root . '/' . hash('sha256', $user) . '.json';
$fixture = base64_decode(
    'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRz8AAAAASUVORK5CYII=',
    true
);
if ($fixture === false) throw new RuntimeException('Invalid PNG fixture');
try {
    if (!mkdir($folder, 0700, true) || !mkdir($otherFolder, 0700, true)) {
        throw new RuntimeException('Unable to create test-only directories.');
    }
    file_put_contents($folder . '/' . $filename, $fixture);
    $records = [
        ['id'=>$firstId, 'exercise'=>'treadmill', 'date'=>'2026-10-21',
            'distance_miles'=>1.16, 'photo'=>$filename, 'notes'=>'Private test'],
        ['id'=>$otherId, 'exercise'=>'push-ups', 'date'=>'2026-10-22',
            'photo'=>null, 'reps'=>20]
    ];
    JsonStore::update($jsonPath, static fn(array $existing): array => $records);
    $before = file_get_contents($jsonPath);
    $found = workout_owned_photo($records, $firstId, $root, $user);
    test_assert($found !== null && $found['mime']==='image/png', 'owned referenced PNG resolves');
    test_assert($found['filename']===$filename, 'photo references its own filename');
    test_assert(workout_owned_photo($records, $otherId, $root, $user)===null,
        'unattached entry cannot request an unrelated photo');
    test_assert(workout_owned_photo($records, $firstId, $root, $other)===null,
        'different account never sees another user photo');
    test_assert(workout_owned_photo($records, '../'.$firstId, $root, $user)===null,
        'entry ID cannot traverse directories');
    test_assert(workout_owned_photo([['id'=>$firstId,'photo'=>'../../config.local.php']],
        $firstId, $root, $user)===null, 'malicious stored photo reference rejected');
    test_assert(workout_owned_photo([['id'=>$firstId,'photo'=>'evil.svg']],
        $firstId, $root, $user)===null, 'unsupported potentially active images rejected');
    test_assert(workout_photo_name_is_safe('original.jpg'), 'legacy photo names are accepted');
    test_assert(!workout_photo_name_is_safe('some/photo.png'), 'path separators rejected');
    test_assert(!workout_photo_name_is_safe('..%2Fsecret.jpg'), 'encoded path segments rejected');
    test_assert(!workout_photo_name_is_safe("test.jpg\0.php"), 'null bytes rejected');
    test_assert(!workout_photo_name_is_safe('test.jpeg.php'), 'double extensions rejected');

    $manifest=workout_owned_photo_manifest($records, $root, $user);
    test_assert(count($manifest)===1 && $manifest[0]['filename']===$filename,
        'manifest includes exactly one owned referenced photo');
    test_fails(static fn()=>workout_owned_photo_manifest([
        ['id'=>$firstId,'photo'=>'missing.png']
    ], $root, $user),'missing image makes backup fail instead of silently skipping');
    test_assert(file_get_contents($jsonPath)===$before,
        'photo reads and manifest checks never rewrite JSON');

    if (class_exists(ZipArchive::class)) {
        $archive=workout_prepare_backup($jsonPath,$root,$user);
        try {
            test_assert(is_file($archive),'a temporary backup ZIP is produced');
            test_assert(strpos(realpath($archive), realpath($root).DIRECTORY_SEPARATOR)!==0,
                'backup never stored in public workout directory');
            $zip=new ZipArchive();
            test_assert($zip->open($archive)===true,'archive opens');
            $data=json_decode((string)$zip->getFromName('workouts.json'),true);
            test_assert($data['schemaVersion']===1 && $data['records']===$records,
                'original complete JSON records are retained');
            test_assert($zip->getFromName('photos/'.$filename)===$fixture,
                'exact owned private photo is included in archive');
            test_assert($zip->getFromName('config.local.php')===false,
                'secrets are never included');
            $zip->close();
        } finally { if (is_file($archive)) unlink($archive); }
    } else {
        test_fails(static fn()=>workout_prepare_backup($jsonPath,$root,$user),
            'missing ZipArchive extension must fail explicitly');
    }
    test_assert(file_get_contents($jsonPath)===$before,
        'export preparation leaves the preexisting JSON bytes unchanged');
    echo "Passed: photo ownership, path checks, missing-photo safety, and read-only backup preparation.";
    if (!class_exists(ZipArchive::class)) echo " Archive-content assertions skipped (ZIP extension absent).";
    echo PHP_EOL;
} finally {
    foreach (glob($folder.'/*') ?: [] as $p) unlink($p);
    foreach (glob($otherFolder.'/*') ?: [] as $p) unlink($p);
    foreach (glob($root.'/*') ?: [] as $p) {
        if (is_file($p)) unlink($p);
    }
    if (is_dir($folder)) rmdir($folder);
    if (is_dir($otherFolder)) rmdir($otherFolder);
    if (is_dir($photos)) rmdir($photos);
    if (is_dir($root)) rmdir($root);
}
