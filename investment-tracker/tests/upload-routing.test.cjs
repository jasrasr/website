/**
 * File Name: upload-routing.test.cjs
 * Author: Jason Lamb (with help from ChatGPT)
 * Description: Verify Robinhood source CSV cannot accidentally enter normalized importer.
 * Revision: 1.0.0
 * Created Date: 2026-10-30
 * Modified Date: 2026-10-30
 * Changelog: 1.0.0 - Check BOM/CRLF CSV detection, preview handoff, and no import.
 */
'use strict';
const assert=require('node:assert/strict');
const fs=require('node:fs');
const path=require('node:path');
const vm=require('node:vm');
const src=fs.readFileSync(path.join(__dirname,'../app.js'),'utf8');
const context=vm.createContext({
 document:{addEventListener(){},querySelector(){}},
 console,
});
vm.runInContext(src,context);
const standard='Activity Date,Process Date,Settle Date,Instrument,Description,Trans Code,Quantity,Price,Amount';
function file(text) {
 return {name:'Robinhood activity.csv',size:text.length,lastModified:1,slice:()=>({text:async()=>text})};
}
(async()=>{
 for(const header of [standard, '\uFEFF'+standard+'\r\n10/9/2026,...', '"Activity Date","Process Date","Settle Date","Instrument","Description","Trans Code","Quantity","Price","Amount"\r\n']) {
  context.sample=file(header);
  assert.equal(await vm.runInContext('isOriginalRobinhoodCsv(sample)',context),true,'recognize brokerage header');
 }
 context.sample=file('date,ticker,type,quantity,amount,price,numerator,denominator,lot_id,note,external_id\n');
 assert.equal(await vm.runInContext('isOriginalRobinhoodCsv(sample)',context),false,'do not misclassify normalized CSV');
 let routed=0,scrolled=0;
 const input=file(standard+'\r\n10/9/2026,...');
 const controls={
  '#csvFile':{files:[input]},
  '#commitCsv':{disabled:false},
  '#csvPreview':{textContent:''},
  '#robinhood-upload':{scrollIntoView(){scrolled++;}},
 };
 context.document.querySelector=(selector)=>controls[selector]??null;
 context.routeBrokerage=async(_event,selected)=>{assert.equal(selected,input);routed++;};
 vm.runInContext('previewRobinhood = routeBrokerage',context);
 await vm.runInContext('csvAction("preview_csv")',context);
 assert.equal(routed,1,'Robinhood upload handed to brokerage preview');
 assert.equal(scrolled,1,'user scrolled to correct section');
 assert.equal(controls['#commitCsv'].disabled,true,'normalized import commit remains disabled');
 assert.match(controls['#csvPreview'].textContent,/No records imported/);
 console.log('PASS: Robinhood file detected and routed to read-only preview without import.');
})().catch(error=>{console.error(error);process.exitCode=1;});
