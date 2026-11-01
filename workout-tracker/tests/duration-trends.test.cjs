/*
File Name: duration-trends.test.cjs
Author: Jason Lamb (with help from ChatGPT)
Description: Validate exact mm:ss values and additive versus non-additive cumulative charts.
Revision: 1.0.0
Created Date: 2026-10-31
Modified Date: 2026-10-31
Changelog: 1.0.0 - Test the four October treadmill sessions and safe totals by exercise/tag.
*/
'use strict';
const assert=require('node:assert/strict');
const fs=require('node:fs');
const path=require('node:path');
const vm=require('node:vm');
const source=fs.readFileSync(path.join(__dirname,'..','duration-trends.js'),'utf8');
const tracker=vm.runInNewContext(source+';WorkoutTrends;',{}, {filename:'duration-trends.js'});
const workouts=[
 {id:'1',exercise:'treadmill',date:'2026-10-21',distance_miles:1.16,duration_minutes:22,speed_mph:3.3},
 {id:'2',exercise:'treadmill',date:'2026-10-22',distance_miles:2.06,duration_minutes:37,speed_mph:3.5},
 {id:'3',exercise:'treadmill',date:'2026-10-27',distance_miles:2.05,duration_minutes:27,calories_kcal:268,speed_mph:5},
 {id:'4',exercise:'treadmill',date:'2026-10-31',distance_miles:2.07,duration_minutes:33,duration_seconds:18,calories_kcal:180,speed_mph:4,
  custom_metrics:[{name:'Seconds',unit:'',value:18}]},
 {id:'5',exercise:'bench press',date:'2026-10-30',distance_miles:5,weight_lb:155},
];
const before=JSON.stringify(workouts);
const value=(entry,key)=>entry[key];
assert.equal(tracker.totalMinutes(workouts[3]),33.3,'33:18 is precisely 33.3 minutes');
assert.equal(tracker.formatDuration(workouts[3]),'33:18 min:sec');
assert.equal(tracker.formatDuration(workouts[0]),'22 min','old workouts render unchanged');
assert.equal(tracker.formatDuration({duration_minutes:12,duration_seconds:8}),'12:08 min:sec');
assert.equal(tracker.formatDuration({duration_seconds:45}),'0:45 min:sec');
assert.equal(tracker.totalMinutes({duration_minutes:1.5}),1.5,'older fractional minutes preserved');
assert.equal(tracker.totalMinutes({}),null,'missing duration remains absent');

const distance=tracker.chartSeries(workouts,'treadmill','distance_miles','',value);
assert.equal(distance.additive,true);
assert.equal(distance.points.length,4,'bench press excluded');
assert.deepEqual(Array.from(distance.points,p=>p.individual),[1.16,2.06,2.05,2.07]);
assert.equal(Number(distance.total.toFixed(2)),7.34,'running mileage total of four workouts');
assert.deepEqual(Array.from(distance.points,p=>Number(p.running.toFixed(2))),[1.16,3.22,5.27,7.34]);

const duration=tracker.chartSeries(workouts,'treadmill','duration_minutes','',value);
assert.equal(duration.additive,true);
assert.equal(Number(duration.points[3].individual.toFixed(2)),33.3,'latest graph point includes seconds');
assert.equal(Number(duration.total.toFixed(2)),119.3,'running duration includes all 18 seconds');
const calories=tracker.chartSeries(workouts,'treadmill','calories_kcal','',value);
assert.deepEqual(Array.from(calories.points,p=>p.individual),[268,180],
 'missing calories on earlier workouts are never invented as zero');
assert.equal(calories.total,448);

for(const key of ['speed_mph','heart_rate_bpm','incline_percent','weight_lb','max_weight_lb','custom:Seconds|'])
 assert.equal(tracker.isAdditive(key),false,'do not sum non-additive measure '+key);
for(const key of ['distance_miles','duration_minutes','calories_kcal','set_count','total_reps','total_volume_lb'])
 assert.equal(tracker.isAdditive(key),true,'permit meaningful additive measure '+key);
const tagged=[
 {...workouts[0],tag:'Cardio'}, {...workouts[1],tag:'Home'},
 {...workouts[2],tag:'Cardio'}, {...workouts[3],tag:'Home'}
];
const cardio=tracker.chartSeries(tagged,'treadmill','distance_miles','Cardio',value);
assert.equal(Number(cardio.total.toFixed(2)),3.21,'running total respects history tag filter');
assert.deepEqual(Array.from(cardio.points,p=>p.date),['2026-10-21','2026-10-27']);
assert.equal(JSON.stringify(workouts),before,'graph calculations never change any saved record');

const reverse=[workouts[3],workouts[0],workouts[2],workouts[1]];
const sorted=tracker.chartSeries(reverse,'treadmill','distance_miles','',value);
assert.deepEqual(Array.from(sorted.points,p=>p.date),
 ['2026-10-21','2026-10-22','2026-10-27','2026-10-31']);
console.log('Passed: mm:ss precision, 7.34 mi total, 119.3 min, filtered cumulative charts and historical data.');
