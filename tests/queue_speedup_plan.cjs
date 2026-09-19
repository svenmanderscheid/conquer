'use strict';
const assert=require('node:assert/strict');
global.window={};
require('../assets/js/queue-speedups.js');

const item=(item_code,duration_seconds,quantity,subcategory='training')=>({item_code,duration_seconds,quantity,subcategory});
const compact=plan=>Object.fromEntries(plan.map(entry=>[entry.duration_seconds/60,entry.quantity]));

assert.deepEqual(compact(window.ConquerPlanSpeedups([item(1,60,3),item(2,300,2)],13*60)),{1:3,5:2});
assert.deepEqual(compact(window.ConquerPlanSpeedups([item(2,300,3)],13*60)),{5:3});
assert.deepEqual(compact(window.ConquerPlanSpeedups([item(1,60,3),item(3,600,1)],13*60)),{1:3,10:1});
assert.deepEqual(compact(window.ConquerPlanSpeedups([item(2,300,1),item(3,600,1)],13*60)),{5:1,10:1});
assert.deepEqual(compact(window.ConquerPlanSpeedups([item(3,600,2)],13*60)),{10:2});
assert.deepEqual(compact(window.ConquerPlanSpeedups([item(4,900,1)],13*60)),{15:1});
assert.deepEqual(window.ConquerPlanSpeedups([item(1,60,12)],13*60),[],'QuickUse stays disabled when the stock cannot finish the job');
assert.deepEqual(compact(window.ConquerPlanSpeedups([item(5,300,1,'generic'),item(6,300,1),item(7,600,1)],15*60)),{5:1,10:1},'Dedicated speedups are preferred when the overhang is equal');
console.log('PASS QuickUse planner: exact combinations, bounded fallbacks, insufficient stock and dedicated-item preference.');
