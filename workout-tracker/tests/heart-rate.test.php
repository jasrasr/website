<?php
/* File Name: heart-rate.test.php
Author: Jason Lamb (with help from ChatGPT)
Description: Validate BPM parsing, historic-data preservation and single-workout editing.
Revision: 1.0.0
Created Date: 2026-10-27
Modified Date: 2026-10-27
Changelog: 1.0.0 - Verify optional heart rate is not backfilled and stale edits cannot overwrite it. */
declare(strict_types=1);

require_once dirname(__DIR__) . '/parse.php';
require_once dirname(__DIR__) . '/entries.php';
require_once dirname(__DIR__, 2) . '/1-Framework/core/JsonStore.php';
use Jasr\Framework\JsonStore;

function bpmAssert(bool $result, string $message): void {
    if (!$result) throw new RuntimeException('FAIL: '.$message);
}
function bpmRejects(callable $call, string $message): void {
    try { $call(); } catch (InvalidArgumentException $e) { return; }
    throw new RuntimeException('FAIL: invalid input accepted - '.$message);
}
bpmAssert(workout_advanced_fields([]) === [], 'absent heart rate stays absent');
bpmAssert(workout_advanced_fields(['heart_rate_bpm'=>'143'])['heart_rate_bpm'] === 143,
    'string heart rate normalized to integer');
bpmAssert(workout_advanced_fields(['heart_rate_bpm'=>143.0])['heart_rate_bpm'] === 143,
    'whole-number float from AI normalized');
bpmAssert(workout_advanced_fields(['heart_rate_bpm'=>''])['heart_rate_bpm'] === null,
    'clear existing heart rate explicitly');
foreach ([0,-1,301,'143.5','test',[],true,'1e2'] as $bad) {
    bpmRejects(static fn()=>workout_advanced_fields(['heart_rate_bpm'=>$bad]), 'heart rate '.get_debug_type($bad));
}
$parsed=workout_parse_text('Treadmill 1.2 miles in 22 minutes at 3.3 mph, HR 143 bpm','2026-10-27');
bpmAssert(count($parsed)===1 && $parsed[0]['heart_rate_bpm']===143, 'HR parsed for treadmill');
bpmAssert($parsed[0]['speed_mph']===3.3, 'speed and HR extracted independently');
$onlyPulse=workout_parse_text('Treadmill for 20 minutes at 145 bpm','2026-10-27');
bpmAssert($onlyPulse[0]['heart_rate_bpm']===145 && $onlyPulse[0]['speed_mph']===null,
    'BPM must never be inferred as treadmill speed');
$duration=workout_parse_text('Treadmill 1 hr 15 minutes','2026-10-27');
bpmAssert($duration[0]['heart_rate_bpm']===null && $duration[0]['duration_minutes']===75.0,
    'hour abbreviation must not create a false pulse');

$dir=sys_get_temp_dir().'/workout-heart-test-'.bin2hex(random_bytes(8));
if (!mkdir($dir,0700)) throw new RuntimeException('Unable to prepare isolated storage');
$file=$dir.'/user.json';
$legacy=[
    'id'=>'aaaaaaaaaaaaaaaaaaaaaaaa', 'date'=>'2026-10-22',
    'exercise'=>'treadmill', 'distance_miles'=>1.16,
    'notes'=>'Existing workout before HR field','photo'=>'old-photo.jpg',
    'created_at'=>'2026-10-22T15:00:00Z', 'session_id'=>'prior-session'
];
$second=[
    'id'=>'bbbbbbbbbbbbbbbbbbbbbbbb', 'date'=>'2026-10-23',
    'exercise'=>'treadmill', 'heart_rate_bpm'=>143, 'notes'=>'Already has pulse',
    'created_at'=>'2026-10-23T15:00:00Z', 'session_id'=>'second-session'
];
try {
    JsonStore::update($file, static fn(array $rows):array=>[$legacy,$second]);
    $before=JsonStore::read($file);
    bpmAssert(!array_key_exists('heart_rate_bpm',$before['records'][0]),
        'legacy workout does not have fabricated heart rate');
    $previous=$before['records'][0];
    $revision=workout_record_revision($previous);
    $changes=workout_validate_entry_edit([
       'exercise'=>'treadmill', 'date'=>'2026-10-22',
       'notes'=>'Added recorded pulse', 'heart_rate_bpm'=>'151'
    ]);
    $after=JsonStore::update($file,
        static fn(array $rows):array=>workout_apply_entry_edit(
            $rows,$legacy['id'],$revision,$changes));
    bpmAssert($after[0]['heart_rate_bpm']===151,'edited legacy workout has chosen value');
    bpmAssert($after[1]===$second,'editing first workout preserves second exactly');
    bpmAssert($after[0]['created_at']===$legacy['created_at'] &&
        $after[0]['photo']===$legacy['photo'] &&
        $after[0]['session_id']===$legacy['session_id'],
        'original photo, session and creation date stay untouched');
    bpmAssert($after[0]['edit_history'][0]['previous']===$previous,
        'previous version retained for audit and recovery');
    $saved=JsonStore::read($file)['records'];
    $stale=false;
    try {
        JsonStore::update($file,static fn(array $rows):array=>workout_apply_entry_edit(
            $rows,$legacy['id'],$revision,$changes));
    } catch (WorkoutEditConflict $e) { $stale=true; }
    bpmAssert($stale,'stale edit is rejected');
    bpmAssert(JsonStore::read($file)['records']===$saved,
        'rejected edit does not rewrite saved records');
    $oldClient=workout_validate_entry_edit([
        'exercise'=>'treadmill','date'=>'2026-10-23',
        'notes'=>'Updated with an older form that omits heart rate'
    ]);
    bpmAssert(!array_key_exists('heart_rate_bpm',$oldClient),
        'older editors do not automatically clear heart rate');
    $updated=JsonStore::update($file,
       static fn(array $rows):array=>workout_apply_entry_edit(
           $rows,$second['id'],workout_record_revision($rows[1]),$oldClient));
    bpmAssert($updated[1]['heart_rate_bpm']===143,
        'older edit keeps existing pulse');
    bpmAssert($updated[0]===$saved[0],
        'editing second entry did not change first');
    bpmAssert(JsonStore::read($file)['schemaVersion']===1,
        'existing JSON storage schema unchanged');
    echo "Passed: BPM validation, local parsing, legacy compatibility, isolated edits and conflict protection.\n";
} finally {
    foreach(glob($dir.'/*') ?: [] as $p) unlink($p);
    if(is_dir($dir))rmdir($dir);
}
