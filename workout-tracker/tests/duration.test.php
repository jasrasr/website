<?php
/* File Name: duration.test.php
Author: Jason Lamb (with help from ChatGPT)
Description: Synthetic duration mm:ss regression across parsing, saves and revision-locked edits.
Revision: 1.0.0
Created Date: 2026-10-31
Modified Date: 2026-10-31
Changelog: 1.0.0 - Keep 33:18 exact without modifying preexisting workouts or custom Seconds tags.
*/
declare(strict_types=1);
require_once dirname(__DIR__).'/parse.php';
require_once dirname(__DIR__).'/entries.php';
require_once dirname(__DIR__,2).'/1-Framework/core/JsonStore.php';
use Jasr\Framework\JsonStore;
function assertDuration(bool $passed,string $label):void {
 if(!$passed)throw new RuntimeException('FAIL: '.$label);
}
function rejectDuration(callable $fn,string $label):void {
 try{$fn();}catch(InvalidArgumentException $e){return;}
 throw new RuntimeException('FAIL: accepted invalid '.$label);
}
$test=workout_parse_text('Treadmill, 0 incline, 2.07 mile at 4 mph for 33:18 min:sec, 180 calories','2026-10-31');
assertDuration(count($test)===1,'one treadmill entry');
assertDuration($test[0]['duration_minutes']===33 && $test[0]['duration_seconds']===18,
 '33:18 is 33 whole minutes and 18 seconds, never 18 minutes');
assertDuration(abs((float)$test[0]['distance_miles']-2.07)<0.001,'distance remains 2.07 miles');
assertDuration((float)$test[0]['calories_kcal']===180.0,'calories preserved');
assertDuration(workout_advanced_fields([])===[],'missing seconds field does not backfill');
assertDuration(workout_advanced_fields(['duration_seconds'=>'18','duration_minutes'=>'33'])['duration_seconds']===18,
 'numeric seconds normalized to integer');
assertDuration(workout_advanced_fields(['duration_seconds'=>'0','duration_minutes'=>'20'])['duration_seconds']===0,
 'explicit zero seconds accepted');
assertDuration(workout_advanced_fields(['duration_seconds'=>''])['duration_seconds']===null,
 'explicit blank seconds can clear new field');
foreach(['60',-1,'1.5','no',[],true,60.0,'1e1'] as $bad)
 rejectDuration(static fn()=>workout_advanced_fields(['duration_seconds'=>$bad,'duration_minutes'=>33]),
  'seconds '.get_debug_type($bad));
rejectDuration(static fn()=>workout_advanced_fields(['duration_seconds'=>18,'duration_minutes'=>'33.3']),
 'fractional minutes with additional seconds');

$dir=sys_get_temp_dir().'/workout-duration-'.bin2hex(random_bytes(8));
if(!mkdir($dir,0700))throw new RuntimeException('Temp dir unavailable');
$file=$dir.'/test-user.json';
$legacy=[
 'id'=>'aaaaaaaaaaaaaaaaaaaaaaaa','exercise'=>'treadmill','date'=>'2026-10-21',
 'distance_miles'=>1.16,'duration_minutes'=>22,'speed_mph'=>3.3,
 'photo'=>'existing.jpg','notes'=>'Historical session','created_at'=>'2026-10-21T12:00:00Z',
 'session_id'=>'original'
];
$oct31=[
 'id'=>'bbbbbbbbbbbbbbbbbbbbbbbb','exercise'=>'treadmill','date'=>'2026-10-31',
 'distance_miles'=>2.07,'duration_minutes'=>33,'speed_mph'=>4,
 'calories_kcal'=>180,'custom_metrics'=>[['name'=>'Seconds','unit'=>'','value'=>18]],
 'notes'=>'Original 33:18 duration, saved before parser fix','created_at'=>'2026-10-31T12:00:00Z',
 'photo'=>null,'session_id'=>'oct31'
];
try {
 JsonStore::update($file,static fn(array $r):array=>[$legacy,$oct31]);
 $before=JsonStore::read($file)['records'];
 assertDuration(!array_key_exists('duration_seconds',$before[0]) &&
                !array_key_exists('duration_seconds',$before[1]),
  'historical documents do not get seconds retroactively');
 $update=workout_validate_entry_edit([
  'exercise'=>'treadmill','date'=>'2026-10-31',
  'distance_miles'=>'2.07','duration_minutes'=>'33','duration_seconds'=>'18',
  'speed_mph'=>'4','calories_kcal'=>'180',
  'custom_metrics'=>[['name'=>'Seconds','unit'=>'','value'=>18]],
  'notes'=>'33:18 min:sec'
 ]);
 $updated=JsonStore::update($file,static fn(array $r):array=>
   workout_apply_entry_edit($r,$oct31['id'],workout_record_revision($r[1]),$update));
 assertDuration($updated[0]===$legacy,'earlier October workout remains byte-for-byte equivalent');
 assertDuration($updated[1]['duration_minutes']===33.0 &&
                $updated[1]['duration_seconds']===18,'duration separated accurately');
 assertDuration($updated[1]['custom_metrics']===$oct31['custom_metrics'],
  'user-added custom Seconds remains until user explicitly changes it');
 assertDuration($updated[1]['edit_history'][0]['previous']===$oct31,
  'previous record retained for recovery');
 assertDuration($updated[1]['photo']===$oct31['photo'] &&
                $updated[1]['session_id']===$oct31['session_id'],
  'photo and session retained');

 $earlierClient=workout_validate_entry_edit([
  'exercise'=>'treadmill','date'=>'2026-10-31','duration_minutes'=>33,
  'notes'=>'Older client omitted seconds'
 ]);
 assertDuration(!array_key_exists('duration_seconds',$earlierClient),
  'older clients do not explicitly clear new seconds');
 $again=JsonStore::update($file,static fn(array $r):array=>
   workout_apply_entry_edit($r,$oct31['id'],workout_record_revision($r[1]),$earlierClient));
 assertDuration($again[1]['duration_seconds']===18,'older client preserves seconds');
 assertDuration($again[0]===$legacy,'editing later day does not affect older day');
 assertDuration(JsonStore::read($file)['schemaVersion']===1,'same framework schema version');
 echo "Passed: exact mm:ss, invalid input, historical preservation, edit audit and older-client compatibility.\n";
} finally {
 foreach(glob($dir.'/*')?:[] as $p)unlink($p);
 rmdir($dir);
}
