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
    $password = bin2hex(random_bytes(12));
    $super = $people->account('', 'demo-super', 'Demo Super Admin', 'super', $password, true);
    $plan = DemoData::populate($target . '/storage', $people, $listings, $super);
    echo "Demo created with 10 businesses, 10 customers and 10 fictional reviews. Store these generated passwords privately.\n";
    echo "demo-super (super)  " . $password . "\n";
    foreach ($plan as $username => $entry) echo $username . ' (' . $entry['role'] . ')  ' . $entry['password'] . "\n";
    exit;
}
if (($argv[1] ?? '') !== 'super' || empty($argv[2])) exit("Usage: php setup.php super USERNAME < private-password-file\n       php setup.php demo NEW_DIRECTORY\n");
$auth->account('',$argv[2],'Super admin','super',rtrim((string)fgets(STDIN),"\r\n"),true);
echo "First super admin created.\n";
