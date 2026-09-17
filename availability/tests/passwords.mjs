// Revision 1.2.0 | 2026-09-17 | Optional password protection regression checks.
import assert from 'node:assert/strict';
const base = process.argv[2] || 'http://127.0.0.1:8090';
if (!['127.0.0.1', 'localhost', '[::1]'].includes(new URL(base).hostname)) throw Error('Use a local disposable server.');
async function post(body, status = 200) {
  const response = await fetch(`${base}/api.php`, {method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(body)});
  const data = await response.json();
  assert.equal(response.status,status,JSON.stringify(data));
  return data;
}
const definition = {action:'create',title:'Protected dinner',adminName:'Jason',description:'Password test',dates:['2026-11-01'],adminPassword:'admin-pass',eventPassword:'guest-pass'};
const created = await post(definition);
const id = created.event.id;
assert.equal(created.event.adminPasswordRequired,true);
assert.equal(created.event.eventPasswordRequired,true);
assert.ok(!JSON.stringify(created).includes('adminPasswordHash'));
assert.ok(!JSON.stringify(created).includes('eventPasswordHash'));
let response = await fetch(`${base}/api.php?id=${id}`);
assert.equal(response.status,401);
assert.equal((await response.json()).passwordRequired,'event');
await post({action:'view',id,eventPassword:'wrong'},401);
const publicView = await post({action:'view',id,eventPassword:'guest-pass'});
assert.equal(publicView.event.id,id);
await post({action:'view',id,adminToken:created.adminToken},401);
await post({action:'view',id,adminToken:created.adminToken,adminPassword:'wrong'},401);
const adminView = await post({action:'view',id,adminToken:created.adminToken,adminPassword:'admin-pass'});
assert.ok(Array.isArray(adminView.adminResponses));
await post({action:'vote',id,revision:1,name:'Guest',answers:{'2026-11-01':'yes'}},401);
await post({action:'vote',id,revision:1,name:'Guest',answers:{'2026-11-01':'yes'},eventPassword:'guest-pass'});
const updated = await post({action:'update',id,revision:1,adminToken:created.adminToken,adminPassword:'admin-pass',title:'Protected dinner',adminName:'Jason',description:'Password test',dates:['2026-11-01'],closed:false,location:'',timezone:'America/New_York',expiresLocal:'',newAdminPassword:'new-admin',newEventPassword:'new-guest'});
assert.equal(updated.event.revision,2);
await post({action:'view',id,adminToken:created.adminToken,adminPassword:'admin-pass'},401);
await post({action:'view',id,adminToken:created.adminToken,adminPassword:'new-admin'});
await post({action:'view',id,eventPassword:'guest-pass'},401);
await post({action:'view',id,eventPassword:'new-guest'});
console.log('Password protection checks passed.');
