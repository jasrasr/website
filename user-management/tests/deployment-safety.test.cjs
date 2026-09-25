// Repository guard only: Hostinger's transfer/delete settings need separate verification.
const {test}=require('node:test');
const assert=require('node:assert/strict');
const fs=require('node:fs'),path=require('node:path');
const {execFileSync}=require('node:child_process');
const root=path.resolve(__dirname,'../..');
const git=(...args)=>execFileSync('git',args,{cwd:root,encoding:'utf8'}).trim();

test('runtime data and private settings are excluded from deployment source',()=>{
 const tracked=git('ls-files','-z').split('\0');
 const runtime=tracked.filter(p=>/^(user-management|finances)\/data\//.test(p));
 assert.deepEqual(runtime.sort(),['finances/data/.htaccess','user-management/data/.htaccess']);
 for(const file of ['user-management/config.local.php','finances/config.private.php','1-Framework/config/config.php']){
   assert.ok(!tracked.includes(file),`${file} must not be tracked`);
 }
 const probes=['user-management/data/directory.json','user-management/data/attempts.json',
  'user-management/data/directory.json.lock','user-management/data/backup/example.json',
  'finances/data/existing-user.json','finances/data/existing-user.json.lock',
  'finances/data/identity/backup-example.json','finances/data/identity/links.json',
  'finances/data/future-storage/records.json','finances/config.private.php',
  'user-management/config.local.php','1-Framework/config/config.php'];
 assert.deepEqual(git('check-ignore','--no-index',...probes).split('\n').sort(),probes.sort());
 // Historical tracked configuration must not be edited OR deleted in a migration.
 // Removing it from Git can delete the deployed file; .gitignore alone cannot protect it.
 assert.equal(git('hash-object','finances/config.local.php'),'eea0d316aeb2b9cec32df9ed8d8a4cb5b80ab7a1',
  'Preserve historical tracked config; use the ignored private override on the server.');
 assert.ok(tracked.includes('finances/config.local.php'),'Do not deploy a deletion of legacy configuration.');
});

test('sample files stay outside runtime paths and contain no seeded identities',()=>{
 const example=JSON.parse(fs.readFileSync(path.join(root,'user-management/examples/directory.example.json'),'utf8'));
 assert.deepEqual(example.records.users,{});
 const budget=JSON.parse(fs.readFileSync(path.join(root,'user-management/examples/budget.example.json'),'utf8'));
 assert.deepEqual(budget.expenses,[]);
 for(const project of ['user-management','finances']){
   assert.match(fs.readFileSync(path.join(root,project,'data/.htaccess'),'utf8'),/Require all denied|Deny from all/i);
 }
});
