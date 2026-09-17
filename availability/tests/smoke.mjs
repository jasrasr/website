// Revision 1.1.0 | 2026-09-17 | Privacy, party planning and expiration regressions.
// History: 1.0.0 — API integration regressions; disposable local server only.
import assert from 'node:assert/strict';
const base = process.argv[2] || 'http://127.0.0.1:8090';
if (!['127.0.0.1', 'localhost', '[::1]'].includes(new URL(base).hostname)) throw Error('Use a local disposable server.');
let checks = 0;
async function post(body, status = 200) {
  const response = await fetch(`${base}/api.php`, {method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(body)});
  const data = await response.json(); assert.equal(response.status,status,JSON.stringify(data)); checks++; return data;
}
const definition = {action:'create',title:'Team dinner',adminName:'Jason',description:'Pick a day',dates:['2026-11-01','2026-11-02','2026-11-03']};
await post({...definition,adminName:' '},422);
await post({...definition,dates:['2026-02-30']},422);
await post({...definition,dates:[]},422);
const created = await post(definition), id = created.event.id;
assert.equal(created.event.adminHash,undefined);
const vote = {action:'vote',id,revision:1,name:'Hannah',answers:{'2026-11-01':'yes','2026-11-02':'no'}};
await post({...vote,name:''},422);
await post({...vote,answers:{'2026-11-04':'yes'}},422);
await post({...vote,answers:{'2026-11-01':'maybe'}},422);
const first = await post(vote);
assert.equal(first.event.responses[0].answers['2026-11-03'],undefined);
await post({...vote,name:'HANNAH'},409);
await post({...vote,responseId:first.responseId,responseToken:'wrong'},403);
const revised = await post({...vote,responseId:first.responseId,responseToken:first.responseToken,answers:{'2026-11-01':'no','2026-11-02':'yes'}});
assert.equal(revised.event.responses.length,1);
assert.equal(revised.event.responses[0].answers['2026-11-01'],'no');
await Promise.all(Array.from({length:12},(_,i)=>post({...vote,name:`Guest ${i}`})));
let publicData = await (await fetch(`${base}/api.php?id=${id}`)).json();
assert.equal(publicData.event.responses.length,13);
assert.ok(!JSON.stringify(publicData).includes('Hash'));
assert.ok(!JSON.stringify(publicData).includes(created.adminToken));
const update = {...definition,action:'update',id,revision:1,adminToken:created.adminToken,closed:false,dates:['2026-11-02','2026-11-04']};
await post({...update,adminToken:'wrong'},403);
const edited = await post(update);
assert.equal(edited.event.revision,2);
assert.equal(edited.event.responses[0].answers['2026-11-01'],undefined);
assert.equal(edited.event.responses[0].answers['2026-11-04'],undefined);
await post({...vote,name:'Stale response'},409);
await post(update,409);
await post({...update,revision:2,closed:true});
await post({...vote,revision:3,name:'Closed response',answers:{}},409);
await post({...update,revision:3,closed:false});
await post({...vote,revision:4,name:'No answers yet',answers:{}});
const other = await post({...definition,title:'Separate event'});
await post({...update,id:other.event.id,revision:1},403);
const direct = await fetch(`${base}/data/${id}.php`);
assert.equal(direct.status,404); assert.ok(!(await direct.text()).includes('adminHash'));
console.log(`${checks} API checks passed; authorization, concurrency, revisions, isolation and storage protection verified.`);

// 1.1.0: private data is excluded server-side; event-specific access and deadlines are enforced.
const planning = await post({...definition, dates:['2026-10-16T17:00','2026-10-16T19:00','2026-10-17'], location:'Community park', timezone:'America/New_York', expiresLocal:'2099-10-01T18:30'});
assert.equal(planning.event.expiresAt,'2099-10-01T22:30:00Z');
await post({...definition, timezone:'Not/AZone'},422);
await post({...definition, dates:['2026-10-16T25:00']},422);
await post({...definition, dates:['2027-03-14T02:30'],timezone:'America/New_York'},422);
await post({...definition, expiresLocal:'2027-03-14T02:30',timezone:'America/New_York'},422);
const privateVote = {action:'vote',id:planning.event.id,revision:1,name:'Public nickname',privateName:'Private person',phone:'555-0101',email:'private@example.test',adults:2,kids:3,foodType:'Side dish',foodNote:'Pasta salad',answers:{'2026-10-16T17:00':'yes','2026-10-16T19:00':'no'}};
await post({...privateVote,email:'bad-email'},422);
await post({...privateVote,adults:-1},422);
await post({...privateVote,kids:1.5},422);
await post({...privateVote,adults:'2'},422);
await post({...privateVote,foodType:'invalid'},422);
const privateSaved=await post(privateVote);
assert.equal(privateSaved.myResponse.email,privateVote.email);
assert.equal(privateSaved.event.responses[0].adults,2);
assert.equal(privateSaved.event.responses[0].foodNote,'Pasta salad');
function noSecrets(data) {
  const json=JSON.stringify(data);
  for (const value of ['privateName','phone','email','tokenHash','adminHash','Private person','private@example.test','555-0101']) assert.ok(!json.includes(value),`Leaked ${value}`);
}
noSecrets(privateSaved.event);
const anonymous=await (await fetch(`${base}/api.php?id=${planning.event.id}&adminToken=${planning.adminToken}`)).json();
noSecrets(anonymous); // Query-string tokens never authorize private data.
noSecrets(await post({action:'view',id:planning.event.id}));
const adminView=await post({action:'view',id:planning.event.id,adminToken:planning.adminToken});
assert.equal(adminView.adminResponses[0].privateName,'Private person');
assert.equal(adminView.adminResponses[0].email,'private@example.test');
noSecrets(adminView.event);
await post({action:'view',id:planning.event.id,adminToken:other.adminToken},403);
await post({action:'view',id:planning.event.id,responseId:privateSaved.responseId,responseToken:'wrong'},403);
const self=await post({action:'view',id:planning.event.id,responseId:privateSaved.responseId,responseToken:privateSaved.responseToken});
assert.equal(self.myResponse.phone,'555-0101');assert.equal(self.adminResponses,undefined);noSecrets(self.event);
const stranger=await post({...privateVote,name:'Another public name',privateName:'Someone else',phone:'',email:''});
assert.ok(!JSON.stringify(stranger).includes('private@example.test'));
assert.ok(!JSON.stringify(stranger).includes('Private person'));
const planUpdate={...definition,action:'update',id:planning.event.id,adminToken:planning.adminToken,revision:1,closed:false,dates:planning.event.dates,timezone:'America/New_York',expiresLocal:'2000-01-01T00:00'};
await post({...planUpdate,timezone:'America/Chicago'},409);
const ended=await post(planUpdate);assert.equal(ended.event.expired,true);
await post({...privateVote,name:'After deadline',revision:2},409);
await post({...privateVote,revision:2,responseId:privateSaved.responseId,responseToken:privateSaved.responseToken},409);
await post({...planUpdate,revision:2,expiresLocal:''});
const legacyEdit=await post({action:'vote',id:planning.event.id,revision:3,name:'Public nickname',answers:{},responseId:privateSaved.responseId,responseToken:privateSaved.responseToken});
assert.equal(legacyEdit.myResponse.email,'private@example.test');
const cleared=await post({...privateVote,revision:3,responseId:privateSaved.responseId,responseToken:privateSaved.responseToken,privateName:'',email:'',phone:'',adults:null,kids:0,foodType:'',foodNote:''});
assert.equal(cleared.myResponse.email,'');assert.equal(cleared.event.responses[0].adults,null);assert.equal(cleared.event.responses[0].kids,0);
console.log(`${checks} total API checks passed, including 1.1.0 privacy, cross-event access, proposed times, household counts, deadlines and legacy edits.`);
