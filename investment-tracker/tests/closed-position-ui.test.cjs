/**
 * File Name: closed-position-ui.test.cjs
 * Author: Jason Lamb (with help from ChatGPT)
 * Description: Check closed-position rendering from existing imported holdings without UI data mutation.
 * Revision: 1.0.0
 * Created Date: 2026-11-13
 * Modified Date: 2026-11-13
 * Changelog: 1.0.0 - ABNB closed trade ROI, date, no historical quote, no false FIFO warning.
 */
'use strict';
const assert=require('node:assert/strict');
const fs=require('node:fs');
const path=require('node:path');
const vm=require('node:vm');

class FakeElement {
 constructor(tag){this.tagName=tag;this.className='';this.textContent='';this.children=[];}
 append(...items){this.children.push(...items);}
 replaceChildren(...items){this.children=[...items];}
}
function flatten(node){
 if(typeof node==='string')return node;
 return (node.textContent||'')+' '+(node.children||[]).map(flatten).join(' ');
}
const target=new FakeElement('div');
const document={
 body:{dataset:{demo:'0'}},
 createElement:tag=>new FakeElement(tag),
 querySelector:selector=>{
  if(selector==='#holdings')return target;
  throw new Error('Unexpected DOM query: '+selector);
 },
 addEventListener:()=>{}, // Never fire DOMContentLoaded; render only holdings.
};
const context=vm.createContext({document,console});
const source=fs.readFileSync(path.join(__dirname,'../app.js'),'utf8');
vm.runInContext(source,context,{filename:'app.js'});
context.examples={
 ABNB:{
  ticker:'ABNB',closed:true,simple_closed_trade:true,basis_unknown:false,
  allocation_estimated:true,sale_allocation_ambiguous:false,
  lifetime_profit:1.13,realized:1.13,closed_total_return_pct:11.3,
  first_purchased_on:'2020-12-19',closed_on:'2020-12-22',
  closed_holding_days:3,closed_tax_term:'short-term',
  shares:0,basis:0,value:0,unrealized:0,price:172.86,price_date:'2020-12-22',
  purchases:10,sales_proceeds:11.13,
  dividends_cash:0,dividends_reinvested:0,other_income:0,fees:0,xirr_pct:null
 },
 AMBIG:{
  ticker:'AMBIG',closed:false,basis_unknown:false,allocation_estimated:true,
  sale_allocation_ambiguous:true,
  shares:1.5,basis:25,value:100,unrealized:75,realized:5,
  price:66.6667,price_date:'2026-11-13',lifetime_profit:80,
  dividends_cash:0,dividends_reinvested:0,other_income:0,fees:0,xirr_pct:10
 },
 ONE:{
  ticker:'ONE',closed:false,basis_unknown:false,allocation_estimated:true,
  sale_allocation_ambiguous:false,shares:1,basis:10,value:20,unrealized:10,
  realized:5,price:20,price_date:'2026-11-13',lifetime_profit:15,
  dividends_cash:0,dividends_reinvested:0,other_income:0,fees:0,xirr_pct:20
 }
};
vm.runInContext('state.analysis.holdings=examples;renderHoldings()',context);
assert.equal(target.children.length,3,'Three positions rendered');
const a=flatten(target.children[0]);
assert.match(a,/Closed/,'Closed position label exists');
assert.match(a,/\+\$1\.13/,'Profit displayed with positive sign');
assert.match(a,/Return on purchase \+11\.30%/,'Correct unannualized return displayed');
assert.match(a,/Held 3 calendar days · Short-term/,'Historical holding period displayed');
assert.match(a,/Fully sold Dec 22, 2020/,'Historical sell date displayed');
assert.doesNotMatch(a,/ESTIMATED FIFO/,'Single purchase and sale do not get false FIFO warning');
assert.doesNotMatch(a,/172\.86/,'Historical quote is not displayed as current value');
assert.doesNotMatch(a,/XIRR/,'Closed position avoids misleading XIRR');
const amb=flatten(target.children[1]);
assert.match(amb,/ESTIMATED FIFO/,'Genuinely ambiguous lot allocations remain flagged');
const one=flatten(target.children[2]);
assert.doesNotMatch(one,/ESTIMATED FIFO/,'A partial sale from only one purchase lot is unambiguous');
console.log('PASS: Closed-position rendering, return and FIFO warning semantics.');
