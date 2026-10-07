<?php
// Runs separate PHP processes against the same room; exercises the real file lock.
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/lib.php';
if (($argv[1] ?? '') === 'worker') {
    $config=['data_dir'=>$argv[2]];
    gb_room($config,$argv[3],fn(&$r)=>gb_apply($r,'buzz',['device'=>$argv[4],'round'=>1]));
    exit;
}
$config=['data_dir'=>sys_get_temp_dir().'/gb-race-'.bin2hex(random_bytes(6))];
$r=gb_new(['title'=>'Concurrency']);$tokens=[];
foreach(range(0,5) as $team) foreach(range(1,2) as $phone) {
    $token=gb_token();$tokens[]=$token;
    gb_apply($r,'join',['key'=>$r['teams'][$team]['join'],'device'=>$token]);
}
$r['round']=1;$r['openAt']=microtime(true)-1;$r['closeAt']=microtime(true)+60;
gb_room($config,$r['id'],fn(&$stored)=>[], $r);
$processes=[];
foreach($tokens as $token) $processes[]=proc_open([PHP_BINARY,__FILE__,'worker',$config['data_dir'],$r['id'],$token],[1=>['pipe','w'],2=>['pipe','w']],$pipes);
foreach($processes as $process) if(proc_close($process)!==0) throw new Exception('Worker failed.');
$result=gb_room($config,$r['id'],fn(&$stored)=>gb_view($stored,[]));
if(count($result['ranking'])!==6 || count(array_unique(array_column($result['ranking'],'team')))!==6) throw new Exception('Expected exactly six team positions from twelve simultaneous phones.');
$previous=-1;
foreach($result['ranking'] as $entry) {if($entry['gapMs']<$previous)throw new Exception('Order is not monotonic.');$previous=$entry['gapMs'];}
foreach(glob($config['data_dir'].'/*') as $file)unlink($file);rmdir($config['data_dir']);
echo "Passed: 12 concurrent phone presses produce exactly 6 ranked teams.\n";
