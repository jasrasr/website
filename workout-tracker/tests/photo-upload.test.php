<?php
/* File Name: photo-upload.test.php
Author: Jason Lamb (with help from ChatGPT)
Description: Synthetic legacy-safe multi-photo tests without live accounts or HTTP uploads.
Revision: 1.0.0
Created Date: 2026-10-27
Modified Date: 2026-10-27
Changelog: 1.0.0 - Verify append-only private files, legacy photo preservation, and backup contents. */
declare(strict_types=1);
require_once dirname(__DIR__).'/entries.php';
require_once dirname(__DIR__).'/photo.php';
require_once dirname(__DIR__).'/photo-upload.php';
require_once dirname(__DIR__).'/backup.php';
require_once dirname(__DIR__,2).'/1-Framework/core/JsonStore.php';
use Jasr\Framework\JsonStore;

function photoAssert(bool $ok,string $message): void {
    if(!$ok)throw new RuntimeException('FAIL: '.$message);
}
function photoRejects(callable $cb,string $message): void {
    try{$cb();}catch(InvalidArgumentException|RuntimeException $e){return;}
    throw new RuntimeException('FAIL: expected rejection - '.$message);
}
$root=sys_get_temp_dir().'/workout-photoappend-'.bin2hex(random_bytes(8));
if(!mkdir($root,0700))throw new RuntimeException('No temp directory');
$user='sample-user-1';
$other='sample-user-2';
$id='aaaaaaaaaaaaaaaaaaaaaaaa';
$secondId='bbbbbbbbbbbbbbbbbbbbbbbb';
$folder=$root.'/photos/'.hash('sha256',$user);
$json=$root.'/'.hash('sha256',$user).'.json';
$fixture=base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRz8AAAAASUVORK5CYII=',true);
if(!$fixture)throw new RuntimeException('Bad fixture');
try {
    if(!mkdir($folder,0700,true))throw new RuntimeException('No photo dir');
    file_put_contents($folder.'/'.$id.'.png',$fixture);
    file_put_contents($root.'/new-1.png',$fixture);
    file_put_contents($root.'/new-2.png',$fixture);
    $beforeRecord=[
        'id'=>$id,'exercise'=>'treadmill','date'=>'2026-10-21',
        'distance_miles'=>1.16,'photo'=>$id.'.png','notes'=>'Existing saved workout',
        'created_at'=>'2026-10-21T14:00:00Z','session_id'=>'original-session',
        'legacy_metadata'=>['preserve'=>true]
    ];
    $otherRecord=['id'=>$secondId,'exercise'=>'push-ups','date'=>'2026-10-22','reps'=>20];
    JsonStore::update($json,static fn(array $r):array=>[$beforeRecord,$otherRecord]);
    $before=JsonStore::read($json)['records'];
    $uploads=[
        'name'=>['new-1.png','new-2.png'],
        'tmp_name'=>[$root.'/new-1.png',$root.'/new-2.png'],
        'size'=>[strlen($fixture),strlen($fixture)],
        'error'=>[UPLOAD_ERR_OK,UPLOAD_ERR_OK]
    ];
    photoAssert(count(workout_photo_upload_items($uploads))===2,'two new valid uploads detected');
    photoRejects(fn()=>workout_photo_upload_items([
        'name'=>array_fill(0,6,'x.png'),'tmp_name'=>array_fill(0,6,'temp'),
        'size'=>array_fill(0,6,100),'error'=>array_fill(0,6,UPLOAD_ERR_OK)
    ]),'reject more than five photos');
    photoRejects(fn()=>workout_photo_upload_items([
        'name'=>['bad.png'],'tmp_name'=>['dummy'],
        'size'=>[100],'error'=>[UPLOAD_ERR_INI_SIZE]
    ]),'upload error');
    file_put_contents($root.'/fake.svg','<svg/>');
    photoRejects(fn()=>workout_photo_upload_mime($root.'/fake.svg'),'unsupported unsafe media');

    $staged=workout_stage_photo_uploads(
        $uploads,$root,$user,$id,static fn(string $src,string $dst):bool=>copy($src,$dst)
    );
    photoAssert(count($staged)===2,'two new immutable files staged');
    photoAssert($staged[0]['filename']!==$staged[1]['filename'],'unique photo names generated');
    photoAssert(str_starts_with($staged[0]['filename'],$id.'-'),'entry ID bound filename');
    photoAssert(file_get_contents($folder.'/'.$id.'.png')===$fixture,'old photo bytes unchanged');

    $rev=workout_record_revision($before[0]);
    $names=array_column($staged,'filename');
    $updated=JsonStore::update($json,static fn(array $r):array=>
        workout_apply_photo_append($r,$id,$rev,$names));
    photoAssert(count($updated)===2,'photos do not create new workout entries');
    photoAssert($updated[1]===$otherRecord,'unrelated workout untouched');
    photoAssert($updated[0]['photo']===$beforeRecord['photo'],'original single-photo reference remains');
    photoAssert($updated[0]['photos']===$names,'new images appended to optional array');
    photoAssert($updated[0]['created_at']===$beforeRecord['created_at'] &&
        $updated[0]['session_id']===$beforeRecord['session_id'] &&
        $updated[0]['legacy_metadata']===$beforeRecord['legacy_metadata'],
        'all historical metadata remains');
    photoAssert($updated[0]['edit_history'][0]['previous']===$before[0],
        'audit snapshot preserves previous original record');
    photoAssert(count(workout_photo_names($updated[0]))===3,'legacy and new photos coexist');

    for($i=0;$i<3;$i++){
        $item=workout_owned_photo($updated,$id,$root,$user,$i);
        photoAssert($item!==null&&is_file($item['path']),'each numbered private photo resolves');
        photoAssert(workout_owned_photo($updated,$id,$root,$other,$i)===null,
            'other account never sees photo at index '.$i);
    }
    photoAssert(workout_owned_photo($updated,$id,$root,$user,3)===null,'nonexistent index denied');
    photoAssert(workout_owned_photo($updated,'../'.$id,$root,$user)===null,'photo traversal denied');
    $manifest=workout_owned_photo_manifest($updated,$root,$user);
    photoAssert(count($manifest)===3,'backup manifest includes legacy and both new photos');
    $stale=false;
    try{
        JsonStore::update($json,static fn(array $r):array=>
            workout_apply_photo_append($r,$id,$rev,$names));
    }catch(WorkoutEditConflict $e){$stale=true;}
    photoAssert($stale,'stale upload cannot overwrite concurrent entry changes');
    photoAssert(count(JsonStore::read($json)['records'][0]['photos'])===2,'failed update did not duplicate images');
    photoRejects(fn()=>workout_apply_photo_append(
        $updated,$id,workout_record_revision($updated[0]),array_fill(0,5,$names[0])),
        'duplicate attachments refused');
    if(class_exists(ZipArchive::class)){
        $zipPath=workout_prepare_backup($json,$root,$user);
        try{
            $zip=new ZipArchive();photoAssert($zip->open($zipPath)===true,'ZIP created');
            photoAssert(count(json_decode((string)$zip->getFromName('workouts.json'),true)['records'])===2,
                'complete historical workouts backed up');
            foreach(array_merge([$id.'.png'],$names) as $name)
                photoAssert($zip->getFromName('photos/'.$name)===$fixture,
                    'legacy/new photo appears with exact original bytes');
            $zip->close();
        }finally{if(is_file($zipPath))unlink($zipPath);}
    }
    photoAssert(JsonStore::read($json)['records'][1]===$otherRecord,'other record unchanged after backup');
    echo "Passed: multi-photo appends, indexed image access, backup coverage, revision safety and legacy data.";
} finally {
    foreach(glob($folder.'/*')?:[] as $file)unlink($file);
    if(is_dir($folder))rmdir($folder);
    if(is_dir($root.'/photos'))rmdir($root.'/photos');
    foreach(glob($root.'/*')?:[] as $file)if(is_file($file))unlink($file);
    if(is_dir($root))rmdir($root);
}
