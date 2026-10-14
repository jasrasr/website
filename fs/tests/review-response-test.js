'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const source = fs.readFileSync(require('node:path').join(__dirname,'../review/review.js'),'utf8');
const apiSource = source.slice(source.indexOf('async function api('),source.indexOf('\nfunction renderQueue'));
async function run(response, expected) {
 const context = vm.createContext({token:'test-token',Date,fetch:async()=>response});
 vm.runInContext(apiSource,context);
 await assert.rejects(()=>context.api('analyze',123),expected);
}
(async()=>{
 await run({status:504,ok:false,json:async()=>{throw Error('<html>private content</html>');}},/Non-JSON response during analyze for ticket #123 \(HTTP 504/);
 await run({status:403,ok:false,json:async()=>{throw Error('secret');}},/host may have blocked/);
 await run({status:200,ok:true,json:async()=>null},/Invalid JSON response/);
 await run({status:502,ok:false,json:async()=>({error:'AI timed out',stage:'AI analysis',request_id:'test-id'})},/Stage: AI analysis. Reference: test-id/);
 const context=vm.createContext({token:'test',Date,fetch:async()=>{throw Error('secret');}});vm.runInContext(apiSource,context);
 await assert.rejects(()=>context.api('detail',123),/Network request failed during detail for ticket #123/);
 console.log('Review response diagnostics passed.');
})();
