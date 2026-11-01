/*
File Name: attendance.test.cjs
Author: Jason Lamb (with help from ChatGPT)
Description: Regress rolling and since-first workout attendance with weekday streaks.
Revision: 1.0.0
Created Date: 2026-10-27
Modified Date: 2026-10-27
Changelog: 1.0.0 - Assert Oct 21/22/27 example, deduplication, weekday skips, DST and read-only logic.
*/
'use strict';
const assert=require('node:assert/strict');
const fs=require('node:fs');
const path=require('node:path');
const vm=require('node:vm');

const source=fs.readFileSync(path.join(__dirname,'..','attendance.js'),'utf8');
const tracker=vm.runInNewContext(source+'\nWorkoutAttendance;',{}, {filename:'attendance.js'});
const saved=[
 {id:'a',date:'2026-10-21',exercise:'treadmill',distance_miles:1.16},
 {id:'b',date:'2026-10-21',exercise:'push-ups',reps:20},
 {id:'c',date:'2026-10-22',exercise:'treadmill',distance_miles:2.06},
 {id:'d',date:'2026-10-27',exercise:'treadmill',distance_miles:2.05},
 {id:'e',date:'2026-10-27',exercise:'bench press',sets:3}
];
const frozen=JSON.stringify(saved);
const result=tracker.calculate(saved,'2026-10-27');
assert.equal(result.hasData,true);
assert.equal(result.firstDate,'2026-10-21','start tracking at first logged date');
assert.equal(result.throughDate,'2026-10-27');
assert.deepEqual(Array.from(Object.entries(result.rollingCalendar)),[
 ['attended',3],['eligible',7],['percentage',42.9]
]);
assert.deepEqual(Array.from(Object.entries(result.rollingWeekdays)),[
 ['attended',3],['eligible',5],['percentage',60]
]);
assert.equal(result.calendar.attended,3,'overall unique attendance dates');
assert.equal(result.calendar.eligible,7,'overall calendar denominator starts Oct 21');
assert.equal(result.calendar.percentage,42.9);
assert.equal(result.weekdays.attended,3,'Wednesday, Thursday and Tuesday');
assert.equal(result.weekdays.eligible,5,'Wednesday Thursday Friday Monday Tuesday');
assert.equal(result.weekdays.percentage,60,'3/5 is 60%, not 66.7%');
assert.equal(result.calendarStreak.current,1,'current calendar streak on Oct 27 is one day');
assert.equal(result.calendarStreak.longest,2,'Oct 21-22 longest calendar streak');
assert.equal(result.weekdayStreak.current,1,'current weekday streak on Oct 27');
assert.equal(result.weekdayStreak.longest,2,'Oct 21-22 longest weekday streak');
assert.equal(result.lastSeven.length,7);
assert.equal(result.lastSeven[0].date,'2026-10-21');
assert.equal(result.lastSeven[6].date,'2026-10-27');
assert.equal(result.lastSeven.filter(day=>day.attended).length,3);
assert.equal(result.lastSeven.filter(day=>day.weekday).length,5);
assert.equal(JSON.stringify(saved),frozen,'calculations never mutate existing records');

const nextDay=tracker.calculate(saved,'2026-10-28');
assert.equal(nextDay.calendar.attended,3);
assert.equal(nextDay.calendar.eligible,8);
assert.equal(nextDay.calendar.percentage,37.5);
assert.equal(nextDay.weekdays.eligible,6);
assert.equal(nextDay.weekdays.percentage,50);
assert.equal(nextDay.rollingCalendar.attended,2,'21st falls out of seven-day window');
assert.equal(nextDay.rollingCalendar.eligible,7);
assert.equal(nextDay.rollingCalendar.percentage,28.6);
assert.equal(nextDay.rollingWeekdays.attended,2);
assert.equal(nextDay.rollingWeekdays.eligible,5);
assert.equal(nextDay.rollingWeekdays.percentage,40);
assert.equal(nextDay.calendarStreak.current,1,
 'yesterday workout preserves current streak until today ends');
assert.equal(tracker.calculate(saved,'2026-10-29').calendarStreak.current,0,
 'streak expires after a full missed day');

const weekdays=[
 {date:'2026-10-21'}, {date:'2026-10-22'}, {date:'2026-10-23'},
 {date:'2026-10-26'}, {date:'2026-10-27'}
];
const w=tracker.calculate(weekdays,'2026-10-27');
assert.equal(w.weekdayStreak.current,5,'Saturday and Sunday never break weekday streak');
assert.equal(w.weekdayStreak.longest,5);
assert.equal(w.calendarStreak.current,2,'weekend interrupts calendar streak');
assert.equal(w.calendarStreak.longest,3);
assert.equal(w.weekdays.percentage,100);
assert.equal(w.calendar.percentage,71.4);

// Friday streak is still live on Saturday, Sunday, and early Monday.
const fri=[{date:'2026-10-23'}];
for(const today of ['2026-10-24','2026-10-25','2026-10-26']){
 assert.equal(tracker.calculate(fri,today).weekdayStreak.current,1,
  'weekend or incomplete Monday must preserve Friday weekday streak: '+today);
}
assert.equal(tracker.calculate(fri,'2026-10-27').weekdayStreak.current,0);

const weekendFirst=tracker.calculate([{date:'2026-10-24'}],'2026-10-25');
assert.equal(weekendFirst.calendar.eligible,2);
assert.equal(weekendFirst.weekdays.eligible,0);
assert.equal(weekendFirst.weekdays.percentage,0,'avoid divide-by-zero on weekend start');
assert.equal(weekendFirst.rollingWeekdays.eligible,0);
assert.equal(weekendFirst.weekdayStreak.current,0);

const todayOnly=tracker.calculate([{date:'2026-10-27'}],'2026-10-27');
assert.equal(todayOnly.calendar.eligible,1,'never count time before first workout');
assert.equal(todayOnly.weekdays.eligible,1);
assert.equal(todayOnly.calendar.percentage,100);
assert.equal(todayOnly.rollingCalendar.eligible,1);
assert.equal(todayOnly.rollingWeekdays.eligible,1);
assert.equal(todayOnly.calendarStreak.current,1);

const nothing=tracker.calculate([],'2026-10-27');
assert.equal(nothing.hasData,false);
assert.equal(nothing.firstDate,null);
assert.equal(nothing.lastSeven.length,0);

const future=tracker.calculate([
 {date:'2026-10-21'}, {date:'2026-10-28'}, {date:'2026-10-29'},
 {date:'not-a-date'}, {date:'2026-02-30'}, {date:'2026-10-27T13:00:00'}
],'2026-10-27');
assert.equal(future.calendar.attended,1,'future and invalid dates do not affect attendance');
assert.equal(future.firstDate,'2026-10-21');
assert.equal(tracker.dayNumber('2026-02-29'),null,'invalid leap day rejected');
assert.notEqual(tracker.dayNumber('2028-02-29'),null,'valid leap day accepted');

// DST fall transition: day counts remain integer calendar days.
const dst=tracker.calculate([{date:'2026-10-30'},{date:'2026-11-02'}],'2026-11-02');
assert.equal(dst.calendar.eligible,4,'DST does not create 23/25-hour phantom days');
assert.equal(dst.weekdays.eligible,2,'Fri and Mon only');
assert.equal(dst.weekdayStreak.current,2,'Friday through Monday weekday streak continues');
assert.equal(dst.calendarStreak.current,1);

console.log('Passed: 3/7=42.9%; 3/5=60%; streaks; weekends; first date; seven-day rolling; DST; legacy safety.');
