<?php
declare(strict_types=1);
require_once __DIR__.'/../review/ReviewService.php';
require_once __DIR__.'/../review/AiReviewer.php';
function check(bool $condition,string $message): void { if (!$condition) throw new RuntimeException($message); }
function fails(callable $callback,string $message): void { try { $callback(); } catch (RuntimeException $e) { return; } throw new RuntimeException($message); }
$config=['domain'=>'example.freshservice.com','agent_id'=>10,'workspace_id'=>0,'resolved_status_ids'=>[4,5]];
$t=['id'=>1,'responder_id'=>10,'status'=>2,'priority'=>3,'created_at'=>gmdate('c',time()-86400*50),'updated_at'=>'2026-10-01T12:00:00Z','subject'=>'Printer','description_text'=>'Printer failed.','attachments'=>[]];
$calls=[];
$client=new ReviewService($config,function($path) use (&$calls,$t){
    $calls[]=$path;
    if (str_contains($path,'/filter?')) {parse_str(parse_url($path,PHP_URL_QUERY),$q);return ['total'=>2,'tickets'=>[(int)$q['page']===1?$t:array_replace($t,['id'=>2,'status'=>5])]];}
    if (str_contains($path,'/conversations?')) {parse_str(parse_url($path,PHP_URL_QUERY),$q);$page=(int)$q['page'];return ['conversations'=>$page<=2?[['id'=>$page,'private'=>$page===1,'body_text'=>$page===1?'Fixed, test requested.':'Still broken.','created_at'=>'2026-10-0'.$page.'T12:00:00Z']]:[]];}
    return ['ticket'=>$t];
});
$q=$client->queue();check(count($q)===1,'Resolved ticket must be excluded.');check(count($calls)===2,'Must paginate to total even with a short page.');
$d=$client->detail(1);check(count($d['conversations'])===2,'Must read through an empty final conversation page.');check($d['conversations'][0]['private']===true,'Private-note marker lost.');check($d['conversations'][1]['body']==='Still broken.','Latest contradictory response omitted.');
check(!$client->inScope(array_replace($t,['responder_id'=>11])),'Other agents must be rejected.');
check(!$client->inScope(array_replace($t,['deleted'=>true])),'Deleted tickets must be rejected.');
$scoped=new ReviewService(array_replace($config,['workspace_id'=>2]),fn()=>[]);check(!$scoped->inScope(array_replace($t,['workspace_id'=>3])),'Cross-workspace ticket accepted.');
$broken=new ReviewService($config,fn()=>['total'=>2,'tickets'=>[$t]]);fails(fn()=>$broken->queue(),'Repeated ticket pages accepted.');
$cap=new ReviewService($config,fn()=>['total'=>10000,'tickets'=>[$t]]);fails(fn()=>$cap->queue(),'Filter truncation accepted.');
$missing=new ReviewService($config,fn()=>['ticket'=>$t]);fails(fn()=>$missing->detail(1),'Missing conversation response accepted.');
$n=0;$changed=new ReviewService($config,function($p) use (&$n,$t){if(str_contains($p,'conversations'))return ['conversations'=>[]];$n++;return ['ticket'=>array_replace($t,['updated_at'=>(string)$n])];});fails(fn()=>$changed->detail(1),'Changed ticket accepted.');
$many=new ReviewService($config,function($path) use ($t){
    if (!str_contains($path,'conversations')) return ['ticket'=>$t];
    parse_str(parse_url($path,PHP_URL_QUERY),$q);$items=[];
    for($i=((int)$q['page']-1)*30+1;$i<=min(65,(int)$q['page']*30);$i++)$items[]=['id'=>$i,'body_text'=>'Note '.$i,'private'=>true];
    return ['conversations'=>$items];
});
check(count($many->detail(1)['conversations'])===65,'Conversation history over 30 entries was truncated.');
$r=['summary'=>'Needs investigation','reason'=>'New failure','next_step'=>'Ask for error','internal_note_draft'=>'Investigate','requester_reply_draft'=>'What error is shown?',
'recommendation'=>'investigate','confidence'=>'medium','evidence'=>[['source'=>'conversation:2','quote'=>'Still broken.']],'blockers'=>['Failure persists'],'closure_checks'=>['Check tasks']];
check(AiReviewer::validate($r,$d)['recommendation']==='investigate','Valid evidence failed.');
$bad=$r;$bad['evidence'][0]['quote']='It works now';fails(fn()=>AiReviewer::validate($bad,$d),'Fabricated evidence accepted.');
$bad=$r;$bad['evidence'][0]['source']='conversation:999';fails(fn()=>AiReviewer::validate($bad,$d),'Fabricated source accepted.');
$bad=$r;$bad['recommendation']='resolve_candidate';check(AiReviewer::validate($bad,$d)['recommendation']==='investigate','Candidate with blockers accepted.');
$bad=$r;$bad['recommendation']='resolve_candidate';$bad['blockers']=[];$bad['evidence']=[];check(AiReviewer::validate($bad,$d)['recommendation']==='investigate','Unsupported candidate accepted.');
$bad=$r;unset($bad['next_step']);fails(fn()=>AiReviewer::validate($bad,$d),'Incomplete review accepted.');
fails(fn()=>new ReviewService(array_replace($config,['domain'=>'example.com'])),'Untrusted domain accepted.');
foreach ([400,401,403,404,429,500] as $status) {
    $failure=AiReviewer::httpFailure($status);
    check($failure->getCode()===$status,'Upstream status must survive for safe logging.');
    check(str_contains($failure->getMessage(),'HTTP '.$status),'Upstream status missing from user error.');
}
echo "Ticket review regression checks passed.\n";
