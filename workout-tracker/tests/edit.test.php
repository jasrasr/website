<?php
/* File Name: edit.test.php
Author: Jason Lamb (with help from ChatGPT)
Description: Regression tests proving existing JSON workouts remain intact when editing one entry.
Revision: 1.0.0
Created Date: 2026-10-22
Modified Date: 2026-10-22
Changelog: 1.0.0 - Test legacy compatibility, targeted edits, history, and stale-write protection. */
declare(strict_types=1);
require_once dirname(__DIR__) . '/entries.php';
require_once dirname(__DIR__,2) . '/1-Framework/core/JsonStore.php';
use Jasr\Framework\JsonStore;

function assertWorkout(bool $condition, string $description): void {
    if (!$condition) throw new RuntimeException('FAIL: '.$description);
}
function expectInvalid(callable $work, string $description): void {
    try { $work(); } catch (InvalidArgumentException $e) { return; }
    throw new RuntimeException('FAIL: accepted invalid '.$description);
}
$directory=sys_get_temp_dir().'/workout-edit-test-'.bin2hex(random_bytes(8));
if (!mkdir($directory,0700)) throw new RuntimeException('Unable to create isolated test directory');
$file=$directory.'/workouts.json';
$legacy=[
 'id'=>'aaaaaaaaaaaaaaaaaaaaaaaa','date'=>'2026-10-20','exercise'=>'treadmill',
 'distance_miles'=>1.16,'duration_minutes'=>22.0,'speed_mph'=>3.3,
 'reps'=>null,'sets'=>null,'weight_lb'=>null,'notes'=>'Original treadmill note',
 'created_at'=>'2026-10-20T16:00:00+00:00','photo'=>'original.jpg','session_id'=>'shared-session',
 'performed_at'=>'2026-10-20T08:30:00-04:00',
 'custom_legacy_field'=>'keep untouched',
];
$second=[
 'id'=>'bbbbbbbbbbbbbbbbbbbbbbbb','date'=>'2026-10-20','exercise'=>'push-ups',
 'reps'=>20,'created_at'=>'2026-10-20T16:05:00+00:00','session_id'=>'shared-session',
];
$third=[
 'id'=>'cccccccccccccccccccccccc','date'=>'2026-10-21','exercise'=>'walking',
 'distance_miles'=>2,'created_at'=>'2026-10-21T10:00:00+00:00',
];
try {
    // Model the already-live v1.0/1.1 format without the new optional fields.
    JsonStore::update($file,static fn(array $records):array=>[$legacy,$second,$third]);
    $before=JsonStore::read($file);
    assertWorkout(count($before['records'])===3,'all original records present');
    assertWorkout(!isset($before['records'][0]['tag']),'old entries have no tag');
    assertWorkout(!isset($before['records'][0]['incline_percent']),'old entries have no incline');
    assertWorkout($before['schemaVersion']===1,'existing storage schema unchanged');
    $version=workout_record_revision($legacy);
    $fields=workout_validate_entry_edit([
      'exercise'=>'Treadmill','date'=>'2026-10-19','notes'=>'Edited original note',
      'distance_miles'=>1.5,'duration_minutes'=>23,'speed_mph'=>3.5,
      'incline_percent'=>4.5,'tag'=>'Cardio-Day-1',
    ]);
    $afterRecords=JsonStore::update($file,
      static fn(array $records):array=>workout_apply_entry_edit($records,$legacy['id'],$version,$fields));
    assertWorkout(count($afterRecords)===3,'edit did not lose or duplicate entries');
    assertWorkout($afterRecords[1]===$second,'another exercise in same session was unchanged');
    assertWorkout($afterRecords[2]===$third,'other-day workout was unchanged');
    $edited=$afterRecords[0];
    assertWorkout($edited['tag']==='Cardio-Day-1','tracking tag stored');
    assertWorkout($edited['incline_percent']===4.5,'incline stored separately');
    assertWorkout($edited['distance_miles']===1.5,'intended metric changed');
    assertWorkout($edited['date']==='2026-10-19','intended workout date changed');
    assertWorkout($edited['performed_at']==='2026-10-19T08:30:00-04:00','time/zone preserved after date edit');
    assertWorkout($edited['created_at']===$legacy['created_at'],'original creation time preserved');
    assertWorkout($edited['photo']===$legacy['photo'],'original photo retained');
    assertWorkout($edited['session_id']===$legacy['session_id'],'original session preserved');
    assertWorkout($edited['custom_legacy_field']==='keep untouched','unknown legacy field not deleted');
    assertWorkout($edited['edit_history'][0]['previous']===$legacy,'original record available for recovery');
    assertWorkout(isset($edited['updated_at']),'updated timestamp saved');

    $saved=JsonStore::read($file)['records'];
    $staleRejected=false;
    try {
      JsonStore::update($file,static fn(array $r):array=>workout_apply_entry_edit($r,$legacy['id'],$version,$fields));
    } catch (WorkoutEditConflict $e) { $staleRejected=true; }
    assertWorkout($staleRejected,'out-of-date overwrite rejected');
    assertWorkout(JsonStore::read($file)['records']===$saved,'rejected overwrite left all records unchanged');

    $notFound=false;
    try {
      JsonStore::update($file,static fn(array $r):array=>workout_apply_entry_edit(
        $r,'dddddddddddddddddddddddd',workout_record_revision($legacy),$fields));
    } catch (WorkoutEntryNotFound $e) { $notFound=true; }
    assertWorkout($notFound,'unknown entry ID cannot overwrite a different record');
    assertWorkout(JsonStore::read($file)['records']===$saved,'not-found edit left data unchanged');
    expectInvalid(static fn()=>workout_validate_entry_edit([
        'exercise'=>'treadmill','date'=>'2026-02-31']), 'date');
    expectInvalid(static fn()=>workout_optional_fields(['incline_percent'=>105]), 'incline');
    expectInvalid(static fn()=>workout_optional_fields(['tag'=>str_repeat('X',65)]), 'tag');
    // Simulate editing an older record with no newly introduced fields: it still succeeds.
    $version2=workout_record_revision($second);
    $change2=workout_validate_entry_edit(['exercise'=>'push-ups','date'=>'2026-10-20','reps'=>25]);
    $end=JsonStore::update($file,
      static fn(array $r):array=>workout_apply_entry_edit($r,$second['id'],$version2,$change2));
    assertWorkout($end[1]['reps']===25.0,'older record without new fields is editable');
    assertWorkout($end[0]===$saved[0] && $end[2]===$third,'editing another record preserved the first and third');
    assertWorkout(is_file($file),'existing JSON filename retained');
    echo "Passed: legacy-safe edit, per-record integrity, audit history, revision conflict, tag, incline, validation.\n";
} finally {
    foreach(glob($directory.'/*') ?: [] as $path) unlink($path);
    rmdir($directory);
}
