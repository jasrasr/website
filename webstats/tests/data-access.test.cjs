// Verify the shipped directory rule with Apache itself, not a PHP router simulation.
const {test,after}=require('node:test');const assert=require('node:assert/strict');
const fs=require('node:fs'),os=require('node:os'),path=require('node:path');const {spawn}=require('node:child_process');
const temp=fs.mkdtempSync(path.join(os.tmpdir(),'webstats-apache-'));const root=path.join(temp,'public');fs.mkdirSync(path.join(root,'data'),{recursive:true});
fs.copyFileSync(path.resolve(__dirname,'../data/.htaccess'),path.join(root,'data/.htaccess'));
for(const name of ['settings.json','admin.json','events-test.json','settings.json.lock','.json-temp'])fs.writeFileSync(path.join(root,'data',name),'private');
fs.writeFileSync(path.join(root,'probe.txt'),'ready');
const user=os.userInfo().username;
const config=path.join(temp,'httpd.conf');fs.writeFileSync(config,`ServerRoot "/etc/apache2"
ServerName localhost
Listen 127.0.0.1:18767
PidFile "${temp}/httpd.pid"
ErrorLog "${temp}/error.log"
LoadModule mpm_event_module /usr/lib/apache2/modules/mod_mpm_event.so
LoadModule authz_core_module /usr/lib/apache2/modules/mod_authz_core.so
User ${user}
Group ${user}
DocumentRoot "${root}"
<Directory "${root}">
AllowOverride All
Require all granted
</Directory>
`);
const server=spawn('/usr/sbin/apache2',['-f',config,'-DFOREGROUND'],{stdio:'ignore'});
after(()=>{server.kill();fs.rmSync(temp,{recursive:true,force:true});});
test('Apache denies every runtime file but serves normal public content',async()=>{
  let ready=false;for(let i=0;i<50;i++){try{const r=await fetch('http://127.0.0.1:18767/probe.txt');if(r.status===200){ready=true;break;}}catch{}await new Promise(r=>setTimeout(r,100));}assert.ok(ready,'Apache did not start');
  for(const name of ['settings.json','admin.json','events-test.json','settings.json.lock','.json-temp'])assert.equal((await fetch('http://127.0.0.1:18767/data/'+name)).status,403);
});
