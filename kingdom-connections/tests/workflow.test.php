<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__).'/bootstrap.php';
function expect(bool $value): void { if (!$value) throw new RuntimeException('Workflow assertion failed.'); }
function denied(callable $fn): void { try { $fn(); } catch (InvalidArgumentException $e) { return; } throw new RuntimeException('Expected rejection.'); }
$dir=sys_get_temp_dir().'/kc-'.bin2hex(random_bytes(6));
mkdir($dir);
try {
    $accounts=new Identity($dir);
    $sup=$accounts->account('','manager','Manager','super','synthetic-password',true);
    $owner=$accounts->account('','trades','Trades','business','synthetic-password');
    $buyer=$accounts->account('','buyer','Buyer','customer','synthetic-password');
    foreach (['manager'=>'super','trades'=>'business','buyer'=>'customer'] as $username=>$role) {
        $accounts->signIn($username,'synthetic-password');
        expect($accounts->user()['role']===$role);
    }
    $d=new KingdomDirectory($dir.'/business.json');
    $fields=['name'=>'Sample','skills'=>'plumbing, electrical','testimony'=>'Sample faith statement'];
    $id=$d->save($fields,$owner,true);
    denied(fn()=>$d->save($fields+['id'=>$id],'other-owner',true));
    $d->saveCustomer($buyer,['name'=>'Buyer']);
    $d->serviceAction('claim',['id'=>$id,'reference'=>'R-001','service'=>'Plumbing'],$buyer,false);
    $payment=array_key_first($d->all()[0]['payments']);
    $input=['id'=>$id,'key'=>$payment,'stars'=>'5','comment'=>'Great work'];
    denied(fn()=>$d->serviceAction('review',$input,$buyer,false));
    denied(fn()=>$d->serviceAction('verify',$input+['evidence'=>'checked'],$buyer,false));
    $d->serviceAction('verify',$input+['evidence'=>'checked'],$sup,true);
    denied(fn()=>$d->serviceAction('review',$input,'other-buyer',false));
    $d->serviceAction('review',$input,$buyer,false);
    denied(fn()=>$d->serviceAction('review',$input,$buyer,false));
    $d->removeAccountData($buyer); $accounts->remove($buyer);
    expect(!$d->customers() && !$d->all()[0]['ratings'] && !$d->all()[0]['payments'] && $accounts->user()===null);
    $d->removeAccountData($owner); expect(!$d->all());
    denied(fn()=>$accounts->remove($sup));
} finally { foreach (glob($dir.'/*') as $file) unlink($file); rmdir($dir); }
echo "Role, ownership, payment verification and cleanup checks passed.\n";
