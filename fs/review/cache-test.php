<?php
declare(strict_types=1);
require __DIR__.'/../tests/review-test.php';
require __DIR__.'/ReviewCache.php';
$config += ['review_token'=>str_repeat('synthetic',8),'review_model'=>'test-model'];
$directory=sys_get_temp_dir().'/review-cache-test-'.bin2hex(random_bytes(8));
try {
 $cache=new ReviewCache($config,$directory);$count=0;
 $generate=function()use(&$count,$r){$count++;return $r;};
 $first=$cache->analyze($d,false,$generate);check($count===1&&!$first['review_cached'],'First review must be generated.');
 $cache=new ReviewCache($config,$directory);
 $hit=$cache->analyze($d,false,$generate);check($count===1&&$hit['review_cached'],'A new cache instance must reuse a persisted review.');
 $same=$d;$same['fetched_at']='later';$same['ticket']['age_days']=999;$same['ticket']['updated_at']='later';$same['conversations'][0]['updated_at']='later';
 check($cache->load($same)!==null,'Time/metadata-only changes must not burn AI tokens.');
 foreach (['description','conversation','status','priority','attachment'] as $change) {
  $changed=$d;
  if($change==='description')$changed['description'].=' New issue.';
  if($change==='conversation')$changed['conversations'][0]['body'].=' New reply.';
  if($change==='status')$changed['ticket']['status']++;
  if($change==='priority')$changed['ticket']['priority']++;
  if($change==='attachment')$changed['attachment_count']++;
  check($cache->load($changed)===null,'Substantial change must invalidate: '.$change);
 }
 $cache->analyze($d,true,$generate);check($count===2,'Force must generate a new review.');
 try{$cache->analyze($d,true,fn()=>throw new RuntimeException('synthetic failure'));}catch(RuntimeException $e){}
 check($cache->load($d)!==null,'Failed forced review must retain prior successful cache.');
 $raw=file_get_contents($directory.'/1.json');check(!str_contains($raw,$r['summary']),'Review text must be encrypted.');
 $other=new ReviewCache(array_replace($config,['review_token'=>'different-secret']),$directory);check($other->load($d)===null,'Other credentials must not read cache.');
 file_put_contents($directory.'/1.json','broken');check($cache->load($d)===null,'Corrupt cache must not be used.');
 echo "Persistent review cache checks passed.\n";
} finally {
 if(is_dir($directory)){foreach(scandir($directory) as $file)if($file!=='.'&&$file!=='..')unlink($directory.'/'.$file);rmdir($directory);}
}
