// Capacity-layout regression checks; no database or live game state is involved.
import assert from 'node:assert/strict';
import fs from 'node:fs';
import {parseRegions,buildPopulation,contains} from '../assets/world-lux-preview/play-world.mjs';
import {createHydrology} from '../assets/world-lux-preview/hydrology.mjs';
const hydro=createHydrology(JSON.parse(fs.readFileSync(new URL('../assets/world-lux-preview/game-hydrology.json',import.meta.url))));
const data=JSON.parse(fs.readFileSync(new URL('../assets/world-lux-preview/geography.json',import.meta.url)));
const cantons=parseRegions(data.cantons),communes=parseRegions(data.communes);
assert.equal(cantons.length,12);assert.equal(communes.length,100);
const alpha=buildPopulation(cantons,communes,'alpha',hydro),live=buildPopulation(cantons,communes,'live',hydro);
assert.equal(alpha.cities.length,20);assert.equal(live.cities.length,1000);
assert.deepEqual(live.cities.slice(0,20),alpha.cities,'Live keeps the widely distributed Alpha towns');
for(const canton of cantons)assert.ok(alpha.cities.some(p=>contains(canton,p.x,p.y)),`Alpha reaches ${canton.name}`);
for(const commune of communes)assert.ok(live.cities.some(p=>contains(commune,p.x,p.y)),`Live reaches ${commune.name}`);
// Coverage alone could still cluster every town at commune centres. Check both
// the distance between towns and the empty spaces sampled across the whole land.
for(const [model,minSpacing,maxGap] of [[alpha,100,170],[live,12,30]]){
 for(let i=0;i<model.cities.length;i++)for(let j=0;j<i;j++){
  const a=model.cities[i],b=model.cities[j];assert.ok(Math.hypot(a.x-b.x,a.y-b.y)>=minSpacing,`${a.id} clusters with ${b.id}`);
 }
 for(let x=10;x<768;x+=20)for(let y=10;y<1100;y+=20)if(model.inLand(x,y)&&!model.waterAt(x,y)){
  const distance=Math.min(...model.cities.map(p=>Math.hypot(p.x-x,p.y-y)));
  assert.ok(distance<=maxGap,`Unpopulated land near ${x},${y}: nearest city ${distance.toFixed(1)} fields away`);
 }
}
for(const model of [alpha,live]){
 assert.equal(new Set(model.items.map(p=>p.id)).size,model.items.length);
 for(const p of model.items){
  for(const dx of [-p.size/2,0,p.size/2])for(const dy of [-p.size/2,0,p.size/2]){
   assert.ok(model.inLand(p.x+dx,p.y+dy),`${p.id} stays inside Luxembourg`);
   assert.ok(!model.waterAt(p.x+dx,p.y+dy),`${p.id} has dry ground`);
  }
  assert.ok(!model.waterIntersects(p.x-p.size/2,p.y-p.size/2,p.x+p.size/2,p.y+p.size/2),`${p.id} cannot straddle a narrow stream`);
  for(const q of model.nearby(p.x,p.y))if(p.id!==q.id)assert.ok(Math.abs(p.x-q.x)>=(p.size+q.size)/2||Math.abs(p.y-q.y)>=(p.size+q.size)/2,`${p.id} overlaps ${q.id}`);
 }
}
console.log('PASS: 20 Alpha / 1000 Live cities; all 12 cantons / 100 communes populated, towns spaced across the land, stable coordinates, dry ground and non-overlapping footprints.');
