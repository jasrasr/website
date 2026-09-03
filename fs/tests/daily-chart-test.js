// Run with: node fs/tests/daily-chart-test.js
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const source = fs.readFileSync(require('node:path').join(__dirname, '../index.php'), 'utf8');
const code = source.slice(source.indexOf("const chartTimezone ="), source.indexOf('\ndrawChart(); window.addEventListener'));
const labels = [], lines = [], bars = [], listeners = {};
const context2d = new Proxy({}, {get: (target, key) => target[key] || ((...args) => {
    if (key === 'fillText') labels.push(args);
    if (key === 'setLineDash') lines.push(args[0]);
    if (key === 'fillRect') bars.push({args,color:context2d.fillStyle});
}), set: (target, key, value) => { target[key] = value; return true; }});
const canvas = {
    parentElement: {clientWidth: 320, scrollWidth: 1000, scrollLeft: 0},
    style: {}, getContext: () => context2d, setAttribute: () => {},
    getBoundingClientRect: () => ({left:0, top:0}),
    addEventListener: (name, callback) => { listeners[name] = callback; }
};
const controls = ['unresolved','newTickets','completed'].map(key => ({
    dataset:{chartSeries:key}, checked:true,
    addEventListener(name, callback) { this.change=callback; }
}));
const picker = {value:'',disabled:false,options:[],
    replaceChildren(...options) { this.options=options; },
    addEventListener(name, callback) { this.change=callback; }
};
const document = {getElementById:()=>picker,querySelectorAll:()=>controls,createElement:()=>({})};
const sandbox = {canvas, note:{}, entries:[], window:{devicePixelRatio:2}, Intl, Date,document};
vm.createContext(sandbox);
vm.runInContext(code, sandbox);
const aggregate = entries => JSON.parse(JSON.stringify(sandbox.dailyChartSamples(entries)));
const entry = (capturedAt, unresolved) => ({capturedAt, unresolved});
const data = [
    entry('2026-09-02T01:00:00Z', 81), // Sep 1, 9 PM Eastern
    entry('2026-09-01T10:00:00-04:00', 90),
    entry('2026-09-02T02:00:00Z', 81), // Same day/count still one point
    entry('2026-09-02T05:00:00Z', 80), // Sep 2 Eastern
    entry('2026-09-04T09:00:00-04:00', 80), // Same count on another day stays visible
    entry('invalid', 999), entry('2026-09-03T12:00:00Z', null)
];
const original = JSON.stringify(data);
const days = aggregate(data);
assert.deepEqual(days.map(d => d.value), [81,80,80]);
assert.equal(days[0].time, Date.parse('2026-09-02T02:00:00Z'));
assert.equal(days[0].day, Date.UTC(2026,8,1));
assert.equal(JSON.stringify(data), original, 'Raw history must not change');
assert.equal(days[2].day-days[1].day, 2*86400000);
assert.deepEqual(aggregate([]), []);
assert.deepEqual(aggregate([entry('2026-09-01T12:00:00Z',0)]).map(d=>d.value), [0]);
// Eastern DST transition still yields one point per calendar date.
assert.deepEqual(aggregate([
    entry('2026-11-01T05:30:00Z',5), entry('2026-11-01T06:30:00Z',4),
    entry('2026-11-02T06:30:00Z',3)
]).map(d=>d.value), [4,3]);
sandbox.entries = data;
sandbox.drawChart();
assert.equal(canvas._trendHits.length, 3);
const [a,b,c] = canvas._trendHits;
assert.ok(Math.abs((c.x-b.x)/(b.x-a.x)-1)<0.00001, 'Only recorded days occupy equally spaced axis slots');
assert.ok(lines.some(line=>line.length===2), 'Missing days use dashed connectors');
assert.equal(labels.filter(label=>label[0]==='Sep').length,3, 'Only recorded days get ticks');
assert.deepEqual(labels.filter(label=>label[2]===272).map(label=>label[0]), ['1','2','4'], 'Missing Sep 3 is omitted; unchanged Sep 4 remains');
listeners.click({clientX:b.x,clientY:b.y});
assert.match(sandbox.note.textContent,/Net change: -1 vs previous day/);
assert.match(sandbox.note.textContent,/Sep 2, 2026, 1:00 AM/);
assert.doesNotMatch(sandbox.note.textContent,/EDT|EST|GMT|UTC/);
listeners.click({clientX:c.x,clientY:c.y});
assert.match(sandbox.note.textContent,/Net change: 0 vs previous recorded day \(2 days earlier\)/);
for (const entries of [[],[entry('2026-09-01T12:00:00Z',0)]]) {
    sandbox.entries=entries; sandbox.drawChart();
    assert.equal(canvas._trendHits.length,entries.length);
}
sandbox.entries = Array.from({length:31},(_,i)=>entry(new Date(Date.UTC(2026,8,i+1,12)).toISOString(),100-i));
sandbox.drawChart();
assert.ok(parseFloat(canvas.style.width)>320, 'Mobile chart scrolls instead of overlapping daily ticks');
assert.equal(canvas._trendHits.length,31);
// A long gap must not create empty ticks or widen the chart by elapsed days.
labels.length = 0;
sandbox.entries = [entry('2026-01-01T12:00:00Z',80),entry('2026-09-01T12:00:00Z',80)];
sandbox.drawChart();
assert.equal(canvas._trendHits.length,2);
assert.equal(parseFloat(canvas.style.width),320);
assert.equal(labels.filter(label=>label[2]===272).length,2);
// One page-wide timezone note, with suffix-free summer and winter tooltips.
assert.equal((source.match(/All times are Eastern \(EDT\/EST\)/g) || []).length,1);
for (const [timestamp, expected] of [
    ['2026-07-01T12:00:00Z', 'Jul 1, 2026, 8:00 AM'],
    ['2026-01-01T12:00:00Z', 'Jan 1, 2026, 7:00 AM']
]) {
    sandbox.entries = [entry(timestamp,80)];
    sandbox.drawChart();
    const hit = canvas._trendHits[0];
    listeners.click({clientX:hit.x,clientY:hit.y});
    assert.ok(sandbox.note.textContent.includes(expected));
    assert.doesNotMatch(sandbox.note.textContent,/EDT|EST|GMT|UTC/);
}
console.log('Daily chart tests passed: daily latest, timezone/DST, gaps, zero, history preservation, tap changes, mobile layout.');

const api = (time,value,activity,note='Automated Freshservice API snapshot.') =>
    ({capturedAt:time,unresolved:value,activity,note,source:'freshservice-api'});
const activity = (newTickets,resolved,closed) => ({newTickets,resolved,closed});
const combined = [
    entry('2026-09-01T12:00:00Z',90), // manual only: activity unknown
    api('2026-09-02T12:00:00Z',88,activity(0,0,0),'Freshservice API baseline initialized.'),
    api('2026-09-03T12:00:00Z',86,activity(2,3,1)),
    api('2026-09-04T02:00:00Z',84,activity(1,2,1)), // still Sep 3 Eastern
    entry('2026-09-04T03:00:00Z',83), // manual latest does not erase activity
    api('2026-09-04T12:00:00Z',83,activity(0,0,0)), // known zero
    api('2026-09-06T12:00:00Z',82,{newTickets:2,resolved:1}) // closed missing => unknown completed
];
const combinedBefore=JSON.stringify(combined);
const totals=aggregate(combined.slice().reverse());
assert.deepEqual(totals.map(d=>d.value),[90,88,83,83,82]);
assert.deepEqual(totals.map(d=>d.newTickets),[null,null,3,0,2]);
assert.deepEqual(totals.map(d=>d.completed),[null,null,7,0,null]);
assert.equal(totals[2].resolved,5);
assert.equal(totals[2].closed,2);
assert.equal(JSON.stringify(combined),combinedBefore);
assert.equal(aggregate([api('2026-09-03T12:00:00Z',5,activity(null,-1,2))])[0].newTickets,null);
sandbox.entries=combined; bars.length=0; labels.length=0;
sandbox.drawChart();
assert.equal(picker.options.length,5);
assert.equal(bars.filter(bar=>bar.color==='#ffad4d').length,2);
assert.equal(bars.filter(bar=>bar.color==='#3ddc84').length,1);
assert.ok(labels.some(label=>label[0]==='?'),'Unknown activity is visibly marked');
const comboHit=canvas._trendHits[2];
listeners.click({clientX:comboHit.x,clientY:220}); // tap bars, not just unresolved point
assert.match(sandbox.note.textContent,/New: 3. Completed: 7 \(5 resolved, 2 closed\)/);
picker.value=String(totals[0].day); picker.change();
assert.match(sandbox.note.textContent,/New: unknown. Completed: unknown/);
picker.value=String(totals[3].day); picker.change();
assert.match(sandbox.note.textContent,/New: 0. Completed: 0/);
controls[1].checked=false; bars.length=0; controls[1].change();
assert.equal(bars.filter(bar=>bar.color==='#ffad4d').length,0);
assert.equal(bars.filter(bar=>bar.color==='#3ddc84').length,1);
controls.forEach(control=>{control.checked=false;control.change();});
assert.match(sandbox.note.textContent,/Select a series/);
// Both bar series use a common scale and bars can exceed the unresolved total.
controls.forEach(control=>{control.checked=true;control.change();});
sandbox.entries=[api('2026-09-03T12:00:00Z',1,activity(150,4,2))];
bars.length=0; sandbox.drawChart();
assert.ok(bars.every(bar=>bar.args[1]>=30&&bar.args[3]>=0&&bar.args[1]+bar.args[3]===228));
assert.equal(bars.find(bar=>bar.color==='#ffad4d').args[1],30);
const dstTotals=aggregate([
    api('2026-11-01T05:30:00Z',5,activity(1,2,0)),
    api('2026-11-01T06:30:00Z',4,activity(2,1,0))
]);
assert.equal(dstTotals.length,1);
assert.equal(dstTotals[0].newTickets,3);
assert.equal(dstTotals[0].completed,3);
console.log('Combined chart tests passed: daily sums, manual/baseline unknown, known zeros, toggles, accessible details, common scale, DST.');
