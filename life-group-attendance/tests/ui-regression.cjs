// Dependency-free UI wiring tests. Run: node tests/ui-regression.cjs
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const source = fs.readFileSync(require('node:path').join(__dirname, '../assets/app.js'), 'utf8');
const nodes = new Map();
function node(selector) {
  if (!nodes.has(selector)) nodes.set(selector, {value:'', innerHTML:'', textContent:'', classList:{add(){},remove(){},toggle(){}}, addEventListener(){}, showModal(){this.open=true},close(){this.open=false}});
  return nodes.get(selector);
}
const clicks = []; const requests = []; const errors = [];
const context = {console, Set, Date, JSON, String, Math, Error, crypto:{randomUUID:()=> 'test-id'},
  setTimeout:()=>0, confirm:()=>true, APP:{csrf:'test-token',user:{role:'super_admin'}},
  document:{querySelector:node,querySelectorAll:()=>[],addEventListener:(type,fn)=>{if(type==='click')clicks.push(fn)}},
  fetch:async(url,options)=>{requests.push({url,options});return {ok:true,json:async()=>url.includes('bootstrap')?{groups:[],students:[],attendance:[],users:[]}:{group:{id:'g1',...JSON.parse(options.body)}}}}};
context.window=context;context.scrollTo=()=>{};context.appLoadError=message=>errors.push(message);
vm.createContext(context);vm.runInContext(source,context);
const evaluate=code=>vm.runInContext(code,context);
(async()=>{
  await new Promise(resolve=>setImmediate(resolve));
  assert.equal(context.APP_READY,true);
  assert.equal(errors.length,0);
  assert.match(node('#groupTable').innerHTML,/No life groups yet/);
  clicks[0]({target:{closest:selector=>selector==='[data-add-group]'?{}:null}});
  assert.equal(node('#modal').open,true,'Add life group must open the modal');
  assert.match(node('#modalContent').innerHTML,/Add a group/);
  node('#gName').value='Demo youth group';node('#gLeader').value='Demo leader';node('#gDay').value='Wednesday';
  await node('#saveGroup').onclick();
  assert.equal(evaluate('state.groups.length'),1);
  assert.equal(node('#modal').open,false);
  assert.equal(requests.at(-1).options.method,'POST');
  assert.equal(requests.at(-1).options.headers['X-CSRF-Token'],'test-token');
  assert.match(node('#groupTable').innerHTML,/Demo youth group/);
  evaluate("state.users=[{id:'pending',name:'Demo Leader',email:'demo@example.test',role:'attendance',active:false,pendingRegistration:true}];renderUsers();userModal(state.users[0]);");
  assert.match(node('#pendingUsers').textContent,/1 leader registration awaiting approval/);
  assert.match(node('#userTable').innerHTML,/Review/);
  assert.match(node('#modalContent').innerHTML,/Review leader registration/);
  assert.match(node('#modalContent').innerHTML,/id="uActive" type="checkbox" > Active account/);
  assert.ok(!node('#modalContent').innerHTML.includes('passwordHash'));
  const index=fs.readFileSync(require('node:path').join(__dirname,'../index.php'),'utf8');
  assert.match(index,/asset_url\('assets\/app\.js'\)/);
  assert.match(index,/asset_url\('assets\/app\.css'\)/);
  console.log('PASS: startup, group button/modal/save, pending-account review, and versioned asset wiring.');
})().catch(error=>{console.error(error);process.exitCode=1});
