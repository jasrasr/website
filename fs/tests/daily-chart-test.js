// Run with: node fs/tests/daily-chart-test.js
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const source = fs.readFileSync(require('node:path').join(__dirname, '../index.php'), 'utf8');
const code = source.slice(source.indexOf("const chartTimezone ="), source.indexOf('\ndrawChart(); window.addEventListener'));
const labels = [], lines = [], listeners = {};
const context2d = new Proxy({}, {get: (target, key) => target[key] || ((...args) => {
    if (key === 'fillText') labels.push(args);
    if (key === 'setLineDash') lines.push(args[0]);
}), set: (target, key, value) => { target[key] = value; return true; }});
const canvas = {
    parentElement: {clientWidth: 320, scrollWidth: 1000, scrollLeft: 0},
    style: {}, getContext: () => context2d, setAttribute: () => {},
    getBoundingClientRect: () => ({left:0, top:0}),
    addEventListener: (name, callback) => { listeners[name] = callback; }
};
const sandbox = {canvas, note:{}, entries:[], window:{devicePixelRatio:2}, Intl, Date};
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
