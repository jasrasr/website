<?php
/* File Name: measurements.test.php
Author: Jason Lamb (with help from ChatGPT)
Description: Synthetic data regression for calories, custom metrics and per-set weights.
Revision: 1.0.0
Created Date: 2026-10-27
Modified Date: 2026-10-27
Changelog: 1.0.0 - Preserve original JSON and concurrency safety while testing metrics and set volume. */
declare(strict_types=1);
require_once dirname(__DIR__) . '/measurements.php';
require_once dirname(__DIR__) . '/entries.php';
require_once dirname(__DIR__,2) . '/1-Framework/core/JsonStore.php';
use Jasr\Framework\JsonStore;

function checkMetric(bool $ok, string $message): void {
    if (!$ok) throw new RuntimeException('FAIL: '.$message);
}
function rejectsMetric(callable $fn, string $message): void {
    try{$fn();}catch(InvalidArgumentException $e){return;}
    throw new RuntimeException('Accepted invalid '.$message);
}
$root=sys_get_temp_dir().'/workout-metrics-'.bin2hex(random_bytes(8));
if(!mkdir($root,0700))throw new RuntimeException('Temp dir unavailable');
$file=$root.'/existing-user.json';
$legacy=[
 'id'=>'aaaaaaaaaaaaaaaaaaaaaaaa','exercise'=>'treadmill','date'=>'2026-10-21',
 'distance_miles'=>1.16,'duration_minutes'=>22,'speed_mph'=>3.3,
 'tag'=>'Cardio','notes'=>'Original private workout','photo'=>'original.jpg',
 'created_at'=>'2026-10-21T12:00:00Z','session_id'=>'original-session',
 'other_historical_data'=>['stay'=>true]
];
$legacy2=[
 'id'=>'bbbbbbbbbbbbbbbbbbbbbbbb','exercise'=>'bench press','date'=>'2026-10-21',
 'sets'=>3,'reps'=>8,'weight_lb'=>95,'notes'=>'Before per-set feature',
 'created_at'=>'2026-10-21T15:00:00Z'
];
$sets=[
 ['weight_lb'=>95,'reps'=>12,'note'=>'warmup'],
 ['weight_lb'=>115,'reps'=>10,'note'=>''],
 ['weight_lb'=>135,'reps'=>8,'note'=>''],
 ['weight_lb'=>115,'reps'=>10,'note'=>'backoff']
];
$metrics=[
 ['name'=>'Elevation gain','unit'=>'ft','value'=>220],
 ['name'=>'Resistance level','unit'=>'','value'=>7.5]
];
try {
 JsonStore::update($file,static fn(array $r):array=>[$legacy,$legacy2]);
 $before=JsonStore::read($file);
 checkMetric($before['schemaVersion']===1,'unchanged data envelope');
 $legacySaved=$before['records'];
 checkMetric(!isset($legacySaved[0]['sets_detail'])&&!isset($legacySaved[0]['custom_metrics']),
    'old records remain without new keys');
 $advanced=workout_advanced_fields(['calories_kcal'=>340,'sets_detail'=>$sets,'custom_metrics'=>$metrics]);
 checkMetric($advanced['sets_detail'][2]['weight_lb']===135.0,'third set retains its own load');
 checkMetric($advanced['custom_metrics'][0]['name']==='Elevation gain','custom metric preserved');
 $strength=[
  'id'=>'cccccccccccccccccccccccc','exercise'=>'bench press','date'=>'2026-10-22',
  'notes'=>'95 x12, 115 x10, 135 x8, 115 x10','tag'=>'Strength',
  'created_at'=>'2026-10-22T13:00:00Z','session_id'=>'strength-session','photo'=>null
 ]+$advanced;
 JsonStore::update($file,static fn(array $records):array=>array_merge($records,[$strength]));
 $read=JsonStore::read($file);
 checkMetric(count($read['records'])===3,'new workout appended without replacing previous workouts');
 checkMetric($read['records'][0]===$legacySaved[0]&&$read['records'][1]===$legacySaved[1],
   'legacy exercises unchanged during new advanced save');
 checkMetric($read['schemaVersion']===1,'JSON schema envelope stays version 1');
 $totals=workout_set_stats($read['records'][2]);
 checkMetric($totals['set_count']===4,'four sets recorded');
 checkMetric($totals['total_reps']===40,'actual reps across four sets');
 checkMetric($totals['max_weight_lb']===135.0,'heaviest set is 135lb');
 checkMetric($totals['total_volume_lb']===4520.0,'volume is sum of each set weight times reps');

 $originalRecord=$read['records'][2];
 $rev=workout_record_revision($originalRecord);
 $change=workout_validate_entry_edit([
    'exercise'=>'bench press','date'=>'2026-10-22','notes'=>'adjust final set',
    'tag'=>'Strength','sets_detail'=>[
      ['weight_lb'=>95,'reps'=>12],
      ['weight_lb'=>115,'reps'=>10],
      ['weight_lb'=>135,'reps'=>8],
      ['weight_lb'=>120,'reps'=>10]
    ],'calories_kcal'=>350,'custom_metrics'=>$metrics
 ]);
 $updated=JsonStore::update($file,
  static fn(array $records):array=>workout_apply_entry_edit(
   $records,$originalRecord['id'],$rev,$change));
 checkMetric($updated[0]===$legacySaved[0]&&$updated[1]===$legacySaved[1],
    'edits touch only the selected workout');
 checkMetric($updated[2]['sets_detail'][3]['weight_lb']===120.0,'fourth set updated independently');
 checkMetric($updated[2]['sets_detail'][2]['weight_lb']===135.0,'third set unchanged');
 checkMetric($updated[2]['calories_kcal']===350.0,'calories updated independently');
 checkMetric($updated[2]['photo']===null&&$updated[2]['created_at']===$strength['created_at'],
    'photo and created_at preserved');
 checkMetric($updated[2]['edit_history'][0]['previous']===$originalRecord,
    'previous workout details preserved in audit history');

 $stale=false;
 try{
  JsonStore::update($file,static fn(array $r):array=>workout_apply_entry_edit(
   $r,$originalRecord['id'],$rev,$change));
 }catch(WorkoutEditConflict $e){$stale=true;}
 checkMetric($stale,'revision conflict prevents stale overwrite');
 $newBase=JsonStore::read($file)['records'];
 checkMetric($newBase===$updated,'rejected concurrent edit made no changes');

 // Old editor clients don't send sets_detail/custom_metrics; they must survive.
 $legacyClientChanges=workout_validate_entry_edit([
  'exercise'=>'bench press','date'=>'2026-10-22','notes'=>'edited through old form'
 ]);
 checkMetric(!array_key_exists('sets_detail',$legacyClientChanges)
  && !array_key_exists('custom_metrics',$legacyClientChanges),
  'missing advanced keys are not interpreted as clear requests');
 $oldClientEdited=JsonStore::update($file,static fn(array $r):array=>workout_apply_entry_edit(
   $r,$originalRecord['id'],workout_record_revision($r[2]),$legacyClientChanges));
 checkMetric($oldClientEdited[2]['sets_detail']===$updated[2]['sets_detail'],
   'older client leaves advanced sets intact');
 checkMetric($oldClientEdited[2]['custom_metrics']===$updated[2]['custom_metrics'],
   'older client leaves custom metrics intact');
 checkMetric($oldClientEdited[2]['calories_kcal']===$updated[2]['calories_kcal'],
   'older client leaves calories intact');

 rejectsMetric(fn()=>workout_advanced_fields(['sets_detail'=>[['weight_lb'=>95,'reps'=>0]]]),'zero reps');
 rejectsMetric(fn()=>workout_advanced_fields(['sets_detail'=>[['weight_lb'=>-10,'reps'=>5]]]),'negative weight');
 rejectsMetric(fn()=>workout_advanced_fields(['sets_detail'=>array_fill(0,41,['weight_lb'=>5,'reps'=>5])]),'too many sets');
 rejectsMetric(fn()=>workout_advanced_fields(['custom_metrics'=>[
  ['name'=>'Elevation','unit'=>'ft','value'=>5],['name'=>'elevation','unit'=>'FT','value'=>10]
 ]]),'duplicate measurement');
 rejectsMetric(fn()=>workout_advanced_fields(['custom_metrics'=>[['name'=>'Bad<script>','unit'=>'ft','value'=>10]]]),'markup metric');
 rejectsMetric(fn()=>workout_advanced_fields(['calories_kcal'=>-1]),'negative calories');
 rejectsMetric(fn()=>workout_advanced_fields(['custom_metrics'=>[['name'=>'Workout','unit'=>'ft','value'=>'oops']]]),'bad numeric value');
 checkMetric(workout_advanced_fields([])===[],'missing values never backfill old entries');
 checkMetric(workout_set_stats($legacySaved[0])===null,'legacy treadmill need no set migration');
 echo "Passed: per-set volume, custom numeric fields, calories, legacy storage preservation, audit and conflicts.\n";
} finally {
 foreach(glob($root.'/*')?:[] as $p)unlink($p);
 rmdir($root);
}
