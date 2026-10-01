import assert from 'node:assert/strict';
import fs from 'node:fs';
import {parseRegions,contains,buildPopulation,checkCityPlacement,findCityPlacement,resourceSite} from '../assets/world-lux-preview/play-world.mjs';
import {createHydrology} from '../assets/world-lux-preview/hydrology.mjs';
const hydro=createHydrology(JSON.parse(fs.readFileSync(new URL('../assets/world-lux-preview/game-hydrology.json',import.meta.url))));
const data=JSON.parse(fs.readFileSync(new URL('../assets/world-lux-preview/game-geography.json',import.meta.url)));
const cantons=parseRegions(data.cantons),communes=parseRegions(data.communes),{grid}=data;
assert.equal(cantons.length,12);assert.equal(communes.length,100);
assert.equal(grid.step,4);assert.equal(grid.width*grid.step,768);assert.equal(grid.height*grid.step,1100);
const cells=[];let landFields=0;
for(const row of grid.rows){const expanded=[];for(let i=0;i<row.length;i+=2)expanded.push(...Array(row[i+1]).fill(row[i]));assert.equal(expanded.length,grid.width);cells.push(expanded);}
assert.equal(cells.length,grid.height);
for(const region of [...cantons,...communes]){
 assert.ok(contains(region,...region.point),`${region.name} has a reachable navigation point`);
 for(const ring of region.rings)for(const [x,y] of ring){assert.equal(x%4,0);assert.equal(y%4,0);}
}
// Every tile has exactly one commune and one matching canton. Check the actual
// exported polygons, not only the intermediate grid used by the generator.
for(let y=0;y<grid.height;y++)for(let x=0;x<grid.width;x++){
 const owner=cells[y][x],px=x*4+2,py=y*4+2,ms=communes.filter(c=>contains(c,px,py)),cs=cantons.filter(c=>contains(c,px,py));
 assert.equal(ms.length,owner?1:0,`No gap/overlap at ${px},${py}`);assert.equal(cs.length,owner?1:0);
 if(owner){assert.equal(ms[0].id,communes[owner-1].id);assert.equal(cs[0].id,ms[0].canton);landFields+=16;}
}
assert.equal(landFields,cantons.reduce((sum,c)=>sum+c.fields,0));
assert.equal(landFields,communes.reduce((sum,c)=>sum+c.fields,0));
const alpha=buildPopulation(cantons,communes,'alpha',hydro),live=buildPopulation(cantons,communes,'live',hydro);
assert.equal(alpha.cities.length,20);assert.equal(live.cities.length,1000);assert.deepEqual(live.cities.slice(0,20),alpha.cities);
for(const c of cantons)assert.ok(alpha.cities.some(p=>contains(c,p.x,p.y)),`Alpha covers ${c.name}`);
for(const c of communes)assert.ok(live.cities.some(p=>contains(c,p.x,p.y)),`Live covers ${c.name}`);
for(const p of live.items){
 for(const dx of [-p.size/2,0,p.size/2])for(const dy of [-p.size/2,0,p.size/2]){assert.ok(live.inLand(p.x+dx,p.y+dy));assert.ok(!live.waterAt(p.x+dx,p.y+dy));}
 assert.ok(!live.waterIntersects(p.x-p.size/2,p.y-p.size/2,p.x+p.size/2,p.y+p.size/2));
 for(const q of live.nearby(p.x,p.y))if(p!==q)assert.ok(Math.abs(p.x-q.x)>=(p.size+q.size)/2||Math.abs(p.y-q.y)>=(p.size+q.size)/2);
}
const inspect=(x,y)=>checkCityPlacement(live,cantons,communes,x,y);
assert.equal(inspect(346,1019).ok,true,'The former small-stream site remains available for a complete city');
const free=findCityPlacement(live,cantons,communes,live.home.x+5,live.home.y-1);
assert.ok(free?.ok,'A nearby free site is found');assert.equal(inspect(free.x,free.y).ok,true);
assert.equal(inspect(live.home.x,live.home.y).ok,false);assert.match(inspect(live.home.x,live.home.y).reason,/Meine Stadt/);
assert.match(inspect(...hydro.visits.find(v=>v.id==='river-Alzette').point).reason,/Wasser/);
assert.match(inspect(-5,-5).reason,/Landesfläche/);
let borderTest=false,siteTest=false;
for(let y=1;y<grid.height&&!borderTest;y++)for(let x=1;x<grid.width&&!borderTest;x++){
 const a=cells[y][x],b=cells[y][x-1];
 if(a&&b&&communes[a-1].canton!==communes[b-1].canton){const p=inspect(x*4,y*4+2);if(p.reason.includes('Kantonsgrenze')){assert.equal(p.ok,false);borderTest=true;}}
}
for(let cy=65;cy<85&&!siteTest;cy++)for(let cx=30;cx<50&&!siteTest;cx++){
 const site=resourceSite(cx,cy);if(live.fits(site.x,site.y,1,2)){const p=inspect(site.x,site.y);if(p.reason.includes(site.name)){assert.equal(p.ok,false);siteTest=true;}}
}
assert.ok(borderTest,'The complete footprint cannot cross a canton border');assert.ok(siteTest,'Procedural resources and monsters also block placement');
console.log(`PASS: ${landFields} fields form a gap-free partition; all 12 cantons / 100 communes remain, city distribution and placement reasons verified.`);
