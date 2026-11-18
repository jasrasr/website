<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__.'/bootstrap.php';

// Demo gets its own application copy, private settings, cookie path and storage.
if (($argv[1] ?? '') === 'demo') {
    $target = $argv[2] ?? '';
    if ($target === '' || file_exists($target) || !mkdir($target,0750)) exit("Choose a new, unused directory for the demo.\n");
    $target = realpath($target);
    foreach (['assets/css','lib','storage'] as $folder) mkdir($target.'/'.$folder,0750,true);
    $files = array_merge(glob(__DIR__.'/*.php'),[__DIR__.'/.htaccess',__DIR__.'/assets/css/app.css',__DIR__.'/lib/JsonStore.php',__DIR__.'/storage/.htaccess']);
    foreach ($files as $file) {
        if (in_array(basename($file),['config.local.php','setup.php'],true)) continue;
        if (!copy($file,$target.substr($file,strlen(__DIR__)))) throw new RuntimeException('Demo copy failed.');
    }
    copy(__DIR__.'/.gitignore',$target.'/.gitignore');
    file_put_contents($target.'/config.local.php', "<?php\nreturn ['demo'=>true];\n");
    $people = new Identity($target.'/storage');
    $listings = new KingdomDirectory($target.'/storage/directory.json');
    $credentials=[];
    foreach (['demo-super'=>'super','demo-trades'=>'business','demo-wood'=>'business','demo-customer'=>'customer'] as $username=>$role) {
        $password=bin2hex(random_bytes(12));
        $ids[$role === 'business' ? $username : $role]=$people->account('',$username,ucwords(str_replace('-',' ',$username)),$role,$password);
        $credentials[] = "$username ($role)  $password";
    }
    foreach ([['demo-trades','Demo Faithful Home Services','plumbing, electrical'],['demo-wood','Demo Good Neighbor Woodworking','carpentry, cabinetry']] as [$owner,$name,$skills]) {
        $businessIds[]=$listings->save(['name'=>$name,'skills'=>$skills,'about'=>'Fictional business for the proof of concept.','testimony'=>'We seek to serve neighbors with honesty and care.'],$ids[$owner],true);
    }
    $listings->saveCustomer($ids['customer'],['name'=>'Demo Customer']);
    foreach (['DEMO-SETTLED','DEMO-PENDING'] as $ref) $listings->serviceAction('claim',['id'=>$businessIds[0],'reference'=>$ref,'service'=>'Demo plumbing visit'],$ids['customer'],false);
    $key=array_key_first($listings->all()[0]['payments']);
    $listings->serviceAction('verify',['id'=>$businessIds[0],'key'=>$key,'evidence'=>'checked'],$ids['super'],true);
    echo "Isolated demo created. Fictional payments only. Store these generated passwords privately.\n",implode("\n",$credentials),"\n";
    exit;
}
if (($argv[1] ?? '') !== 'super' || empty($argv[2])) exit("Usage: php setup.php super USERNAME < private-password-file\n       php setup.php demo NEW_DIRECTORY\n");
$auth->account('',$argv[2],'Super admin','super',rtrim((string)fgets(STDIN),"\r\n"),true);
echo "First super admin created.\n";
