import assert from 'node:assert/strict';
import fs from 'node:fs';
import {createHydrology} from '../assets/world-lux-preview/hydrology.mjs';
const data=JSON.parse(fs.readFileSync(new URL('../assets/world-lux-preview/hydrology.json',import.meta.url)));
const water=createHydrology(data);
assert.equal(data.sourceCRS,'EPSG:3035 (northing/easting)');
assert.equal(new Set(data.rivers.map(r=>r.sourceName)).size,15);
assert.ok(data.rivers.every(r=>r.major),'No minor tributaries in the playable dataset');
assert.ok(data.lakes.length>0&&data.lakes.every(l=>l.areaM2>=50000),'Only large lakes remain');
for(const name of ['Mosel','Sauer','Our','Alzette','Attert','Eisch','Mamer','Wiltz','Clerve','Woltz','Syre','Ernz Blanche','Ernz Noire','Chiers','Wark'])assert.ok(data.rivers.some(r=>r.sourceName===name&&r.major),name);
assert.ok(data.lakes.some(l=>l.name==='Obersauer-Stausee'&&l.areaM2>3000000));
for(const visit of water.visits)assert.ok(water.waterAt(...visit.point),`${visit.name} jump follows its actual watercourse or lake`);
for(let i=0;i<data.rivers.length;i++){
 const r=data.rivers[i];for(const p of [r.points[0],r.points.at(-1)]){assert.ok(water.waterAt(...p));assert.ok(water.visible({left:p[0]-.1,top:p[1]-.1,right:p[0]+.1,bottom:p[1]+.1}).rivers.includes(r));}
}
// Previously blocked by Diddelengerbaach and a small basin: neither may remain
// as an invisible water collision after the minor-water layer is removed.
for(const [x,y] of [[346,1019],[342,552]])assert.equal(water.intersectsRect(x-2,y-2,x+2,y+2),false);
// Regression: checking only a city's centre and corners misses a narrow stream
// passing through the footprint. Rectangle/segment intersection must catch it.
const sample=createHydrology({rivers:[{points:[[0,1],[10,1]],width:.2}],lakes:[],visits:[]});
for(const p of [[2,0],[6,0],[2,4],[6,4],[4,2]])assert.equal(sample.waterAt(...p),false);
assert.equal(sample.intersectsRect(2,0,6,4),true);
assert.equal(sample.intersectsRect(2,1.101,6,4),false);
assert.equal(sample.intersectsRect(10.05,.9,11,1.1),true,'Round river cap');
const lake=createHydrology({rivers:[],visits:[],lakes:[{bounds:[[0,0],[10,10]],rings:[[[0,0],[10,0],[10,10],[0,10]],[[4,4],[6,4],[6,6],[4,6]]]}]});
assert.equal(lake.waterAt(2,2),true);assert.equal(lake.waterAt(5,5),false,'Island remains dry');
assert.equal(lake.intersectsRect(-1,-1,11,11),true,'Lake inside footprint');
assert.equal(lake.intersectsRect(4.2,4.2,5.8,5.8),false,'Footprint on island');
assert.equal(lake.intersectsRect(3.5,4.2,5.8,5.8),true,'Footprint crosses shore');
console.log('PASS: main rivers and large lakes only; removed streams/ponds no longer block footprints; navigation and shore/island collision checks.');
