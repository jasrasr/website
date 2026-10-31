<?php
/* File Name: parse.test.php
Author: Jason Lamb (with help from ChatGPT)
Description: Standalone CLI regression tests for key-free workout descriptions.
Revision: 1.4.0
Created Date: 2026-10-21
Modified Date: 2026-10-22
Changelog: 1.4.0 - Test BPM, HR/pulse aliases and reject speed/duration confusion. 1.3.0 - Parse calorie totals and varying bench-press weights in one exercise. 1.2.0 - Assert explicit treadmill incline and tracking hashtag parsing. 1.1.0 - Validate workouts, multi-exercise separation, dates, and invalid input. */
declare(strict_types=1);
require dirname(__DIR__) . '/parse.php';
$cases=[
 ['1.16 miles at 3.3 on treadmill for today for 22 minutes',[['exercise'=>'treadmill','date'=>'2026-10-21','distance_miles'=>1.16,'speed_mph'=>3.3,'duration_minutes'=>22]]],
 ['Walked 1.16 miles on the treadmill for 22 minutes at 3.3 mph, then did 20 push-ups',[['exercise'=>'treadmill','distance_miles'=>1.16,'duration_minutes'=>22],['exercise'=>'push-ups','reps'=>20]]],
 ['3 sets of 10 squats and 20 lunges',[['exercise'=>'squats','reps'=>10,'sets'=>3],['exercise'=>'lunges','reps'=>20]]],
 ['Yesterday walked 3 km in 45 minutes',[['exercise'=>'walking','date'=>'2026-10-20','distance_miles'=>1.864,'duration_minutes'=>45]]],
 ['10/20/2026 ran 2 mi in 20 mins',[['exercise'=>'running','date'=>'2026-10-20','distance_miles'=>2,'duration_minutes'=>20]]],
 ['Last Monday biked 10 miles for 1 hr 15 minutes',[['exercise'=>'cycling','date'=>'2026-10-19','distance_miles'=>10,'duration_minutes'=>75]]],
 ['Did 4x12 pushups',[['exercise'=>'push-ups','sets'=>4,'reps'=>12]]],
 ['Plank for 60 seconds',[['exercise'=>'plank','duration_minutes'=>1]]],
 ['Bench press 3 sets of 8 reps at 75 lb',[['exercise'=>'bench press','sets'=>3,'reps'=>8,'weight_lb'=>75]]],
 ['Treadmill 1.16 miles 22 min 3.3 mph 5% incline #morning',[['exercise'=>'treadmill','distance_miles'=>1.16,'duration_minutes'=>22,'speed_mph'=>3.3,'incline_percent'=>5,'tag'=>'morning']]],
 ['Treadmill at 3.3 mph incline 2.5 for 30 minutes',[['exercise'=>'treadmill','incline_percent'=>2.5,'duration_minutes'=>30,'speed_mph'=>3.3]]],
 ['20 pushups #strength',[['exercise'=>'push-ups','reps'=>20,'tag'=>'strength']]],
 ['treadmill 2 miles 30 mins 5% incline 320 calories',[['exercise'=>'treadmill','distance_miles'=>2,'duration_minutes'=>30,'incline_percent'=>5,'calories_kcal'=>320]]],
 ['bench press 95 lb x 12, 115 lb x 10, 135 lb x 8, 115 lb x 10',[['exercise'=>'bench press','sets_detail'=>[
   ['weight_lb'=>95,'reps'=>12],['weight_lb'=>115,'reps'=>10],['weight_lb'=>135,'reps'=>8],['weight_lb'=>115,'reps'=>10]
 ]]]],
 ['Treadmill 1.16 miles in 22 minutes at 3.3 mph, heart rate 143 bpm',[['exercise'=>'treadmill','distance_miles'=>1.16,'duration_minutes'=>22,'speed_mph'=>3.3,'heart_rate_bpm'=>143]]],
 ['Walked 2 miles with average heart rate of 128',[['exercise'=>'walking','distance_miles'=>2,'heart_rate_bpm'=>128]]],
 ['Bench press 3 sets of 8 reps HR 150',[['exercise'=>'bench press','sets'=>3,'reps'=>8,'heart_rate_bpm'=>150]]],
 ['Treadmill 2 miles at 145 bpm for 30 minutes',[['exercise'=>'treadmill','distance_miles'=>2,'duration_minutes'=>30,'heart_rate_bpm'=>145,'speed_mph'=>null]]],
 ['Treadmill 2 miles 1 hr 15 minutes',[['exercise'=>'treadmill','distance_miles'=>2,'duration_minutes'=>75,'heart_rate_bpm'=>null]]],
 ['Running 3 miles with 150 beats per minute',[['exercise'=>'running','distance_miles'=>3,'heart_rate_bpm'=>150]]],
 ['Rowing for 10 mins, pulse 132',[['exercise'=>'rowing','duration_minutes'=>10,'heart_rate_bpm'=>132]]],
 ['Treadmill 2 miles 30 min HR 143 and 20 push-ups',[['exercise'=>'treadmill','distance_miles'=>2,'heart_rate_bpm'=>143],['exercise'=>'push-ups','reps'=>20]]]

];
foreach($cases as $i=>[$input,$expected]){
 $records=workout_parse_text($input,'2026-10-21');
 if(count($records)!==count($expected))throw new RuntimeException('Case '.$i.' count mismatch.');
 foreach($expected as $j=>$wanted)foreach($wanted as $field=>$value){
  $actual=$records[$j][$field]??null;
  if(is_array($value)){
   if(!is_array($actual)||count($actual)!==count($value)) throw new RuntimeException('Case '.$i.' '.$field.' count mismatch.');
   foreach($value as $k=>$want)foreach($want as $subkey=>$v)
    if(($actual[$k][$subkey]??null)!==$v && (!is_numeric($v)||abs((float)($actual[$k][$subkey]??0)-(float)$v)>0.002))
     throw new RuntimeException('Case '.$i.' '.$field.' set detail mismatch.');
  }elseif(is_numeric($value)?abs((float)$actual-(float)$value)>0.002:$actual!==$value)
   throw new RuntimeException('Case '.$i.' '.$field.' mismatch: '.json_encode($records[$j]));
 }
}
foreach(['','gym workout','treadmill on 2026-02-30', 'treadmill 1 mile at heart rate 301 bpm', 'walking HR 0'] as $bad){
 try{workout_parse_text($bad,'2026-10-21');throw new RuntimeException('Invalid input accepted.');}
 catch(InvalidArgumentException $e){}
}
echo 'Passed '.count($cases).' extraction examples and 5 invalid-input cases.'.PHP_EOL;
