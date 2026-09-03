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
assert.ok(Math.abs((c.x-b.x)/(b.x-a.x)-2)<0.00001, 'Missing dates must retain space');
assert.ok(lines.some(line=>line.length===2), 'Missing days use dashed connectors');
assert.equal(labels.filter(label=>label[0]==='Sep').length,4, 'Every calendar day gets a tick');
listeners.click({clientX:b.x,clientY:b.y});
assert.match(sandbox.note.textContent,/Net change: -1 vs previous day/);
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
console.log('Daily chart tests passed: daily latest, timezone/DST, gaps, zero, history preservation, tap changes, mobile layout.');
