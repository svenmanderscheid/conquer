import assert from 'node:assert/strict';
import fs from 'node:fs';
import {createHydrology} from '../assets/world-lux-preview/hydrology.mjs';
import {parseRegions,contains} from '../assets/world-lux-preview/play-world.mjs';
const read=name=>JSON.parse(fs.readFileSync(new URL(`../assets/world-lux-preview/${name}`,import.meta.url)));
const original=read('hydrology.json'),data=read('game-hydrology.json');
const before=createHydrology(original),after=createHydrology(data);
const cantons=parseRegions(read('game-geography.json').cantons);
assert.deepEqual(data.lakes,original.lakes,'Keep all large lake shorelines');
assert.deepEqual(new Set(data.rivers.map(r=>r.sourceName)),new Set(['Mosel','Sauer','Our','Alzette','Attert','Eisch','Wiltz','Clerve','Woltz']));
for(const r of data.rivers){
 assert.equal(r.width,2,'Every rendered/collidable river is two fields wide');
 for(let i=1;i<r.points.length;i++){
  const a=r.points[i-1],b=r.points[i];
  assert.ok(Math.hypot(b[0]-a[0],b[1]-a[1])>0,'No degenerate curve segments');
  assert.ok(after.waterAt((a[0]+b[0])/2,(a[1]+b[1])/2),'Drawn segment is also blocked water');
  if(i<r.points.length-1){
   const c=r.points[i+1],u=[b[0]-a[0],b[1]-a[1]],v=[c[0]-b[0],c[1]-b[1]];
   const cosine=(u[0]*v[0]+u[1]*v[1])/(Math.hypot(...u)*Math.hypot(...v));
   assert.ok(cosine>.5,'No right-angle corners or reversing hairpins');
  }
 }
 const source=original.rivers.find(s=>s.id===r.id);
 for(const p of [source.points[0],source.points.at(-1)]){
  assert.ok(after.waterAt(...p),'Retained river mouths and shared junctions stay connected');
 }
}
for(const v of after.visits)assert.ok(after.waterAt(...v.point),`${v.name}: navigation follows the new river`);
// The actual construction grid must gain space, not just render thinner lines.
let oldBlocked=0,newBlocked=0;
for(let y=2;y<1100;y+=4)for(let x=2;x<768;x+=4)if(cantons.some(c=>contains(c,x,y))){
 oldBlocked+=before.intersectsRect(x-2,y-2,x+2,y+2);
 newBlocked+=after.intersectsRect(x-2,y-2,x+2,y+2);
}
assert.ok(newBlocked<oldBlocked*.85,'At least 15% fewer water-blocked 4x4 sites in the land-centre grid');
// Full cities fit on both banks of the two-field channel, without hidden banks.
const channel=createHydrology({rivers:[{points:[[5.5,.5],[5.5,20.5]],width:2}],lakes:[],visits:[]});
assert.equal(channel.intersectsRect(0,4,4,8),false);
assert.equal(channel.intersectsRect(7,4,11,8),false);
assert.equal(channel.intersectsRect(4,4,8,8),true);
console.log(`PASS: smooth two-field rivers, preserved lakes/junctions, valid visits and bank-side cities; blocked 4x4 grid sites ${oldBlocked} -> ${newBlocked}.`);
