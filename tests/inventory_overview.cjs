'use strict';
const fs=require('node:fs'),vm=require('node:vm'),assert=require('node:assert/strict'),path=require('node:path');
const sandbox={window:{}};
vm.runInNewContext(fs.readFileSync(path.join(__dirname,'../assets/js/inventory-overview.js'),'utf8'),sandbox);
const {summarize,formatTime}=sandbox.window.ConquerInventoryOverview;
const summary=summarize({city:{food:8750000000,lumber:19,stone:0,gold:250}}, {
 profile:{gems:1001,action_points:199,prestige_points:420},
 inventory:[
  {category:'resource_pack',resource:'food',amount:50000,quantity:108000},
  {category:'resource_pack',resource:'food',amount:1000,quantity:3},
  {category:'resource_pack',resource:'lumber',amount:5000,quantity:7},
  {category:'resource_pack',resource:'stone',amount:1e7,quantity:0},
  {category:'resource_pack',resource:'gems',amount:50,quantity:4},
  {category:'resource_box',resource:'food',amount:900000,quantity:99},
  {category:'chest',resource:'food',amount:900000,quantity:99},
  {category:'ap_refill',ap_amount:50,quantity:3},
  {category:'vip_point',vip_points:100,quantity:8},
  {category:'boost',duration_seconds:86400,quantity:99},
  {category:'speedup',subcategory:'generic',duration_seconds:86400,quantity:2},
  {category:'speedup',subcategory:'generic',duration_seconds:3600,quantity:3},
  {category:'speedup',subcategory:'generic',duration_seconds:60,quantity:4},
  {category:'speedup',subcategory:'building',duration_seconds:3600,quantity:7},
  {category:'speedup',subcategory:'healing',duration_seconds:60,quantity:0},
  {category:'resource_pack',resource:'gold',amount:1000,quantity:-4},
  {category:'resource_pack',resource:'gold',amount:Infinity,quantity:1},
 ],
 inventory_catalog:[{category:'resource_pack',resource:'food',amount:1e7,quantity:1000}],
});
const resource=key=>summary.resources.find(r=>r.key===key),speed=key=>summary.speedups.find(r=>r.key===key);
assert.equal(resource('food').items,5400003000,'Sum pack contents, not item counts or catalogue quantities');
assert.equal(resource('food').stock,8750000000,'Keep the current wallet separate from unopened packs');
assert.equal(resource('lumber').items,35000);assert.equal(resource('stone').items,0);
assert.equal(resource('gems').items,200);assert.equal(resource('gems').stock,1001);
assert.equal(resource('ap').items,150);assert.equal(resource('ap').stock,199,'Energy items retain their full nominal contents');
assert.equal(resource('prestige').items,800);assert.equal(resource('prestige').stock,420);
assert.equal(resource('gold').items,0);assert.equal(resource('gold').stock,250);
assert.equal(speed('generic').seconds,183840);assert.equal(speed('building').seconds,25200,'Do not add universal time to specialist rows');
assert.equal(speed('healing').seconds,0);assert.equal(speed('training').seconds,0);
assert.equal(formatTime(183840,'days'),'2 Tg. 3 Std. 4 Min.');
assert.equal(formatTime(183840,'hours'),'51 Std. 4 Min.');
assert.equal(formatTime(183840,'minutes'),'3.064 Min.');
assert.equal(formatTime(86461,'days'),'1 Tg. 1 Min. 1 Sek.');
assert.equal(formatTime(0),'Keine Items');assert.equal(formatTime(null),'Nicht verfügbar');
const missing=summarize(undefined,undefined);assert.equal(missing.resources[0].items,null);assert.equal(missing.resources[0].stock,null);assert.equal(missing.speedups[0].seconds,null);
const empty=summarize({city:{food:0}},{inventory:[]});assert.equal(empty.resources[0].items,0);assert.equal(empty.resources[0].stock,0);
console.log('PASS inventory overview sums, separate wallets, chest exclusions, zero/missing data and lossless time-unit conversion.');
