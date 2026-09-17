// Revision 1.3.0 | 2026-09-17 | Attendee-added date option regressions.
import assert from 'node:assert/strict';
const base = process.argv[2] || 'http://127.0.0.1:8090';
if (!['127.0.0.1','localhost','[::1]'].includes(new URL(base).hostname)) throw Error('Use a local disposable server.');
async function post(body, status=200) {
  const response = await fetch(`${base}/api.php`, {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify(body)});
  const data = await response.json();
  assert.equal(response.status, status, JSON.stringify(data));
  return data;
}
const definition = {action:'create',title:'Suggestion test',adminName:'Admin',description:'',dates:['2026-11-01'],location:'',timezone:'America/New_York',expiresLocal:'',allowAttendeeDates:false};
const disabled = await post(definition);
assert.equal(disabled.event.allowAttendeeDates,false);
await post({action:'suggest_date',id:disabled.event.id,revision:1,date:'2026-11-02'},403);
const enabled = await post({...definition,title:'Enabled suggestions',allowAttendeeDates:true,eventPassword:'eventpass'});
assert.equal(enabled.event.allowAttendeeDates,true);
await post({action:'suggest_date',id:enabled.event.id,revision:1,date:'2026-11-02'},401);
await post({action:'suggest_date',id:enabled.event.id,revision:1,date:'2026-11-02',eventPassword:'wrong'},401);
const added = await post({action:'suggest_date',id:enabled.event.id,revision:1,date:'2026-11-02',eventPassword:'eventpass'});
assert.equal(added.event.revision,2);
assert.deepEqual(added.event.dates,['2026-11-01','2026-11-02']);
await post({action:'suggest_date',id:enabled.event.id,revision:2,date:'2026-11-02',eventPassword:'eventpass'},409);
await post({action:'suggest_date',id:enabled.event.id,revision:1,date:'2026-11-03',eventPassword:'eventpass'},409);
const timed = await post({action:'suggest_date',id:enabled.event.id,revision:2,date:'2026-11-03T18:30',eventPassword:'eventpass'});
assert.ok(timed.event.dates.includes('2026-11-03T18:30'));
const updated = await post({action:'update',id:enabled.event.id,revision:3,adminToken:enabled.adminToken,title:'Enabled suggestions',adminName:'Admin',description:'',dates:timed.event.dates,location:'',timezone:'America/New_York',expiresLocal:'',closed:false,allowAttendeeDates:false,eventPassword:'eventpass'});
assert.equal(updated.event.allowAttendeeDates,false);
await post({action:'suggest_date',id:enabled.event.id,revision:4,date:'2026-11-04',eventPassword:'eventpass'},403);
console.log('Attendee-added date option checks passed.');
