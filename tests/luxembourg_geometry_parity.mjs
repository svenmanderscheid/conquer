import fs from 'node:fs';
import path from 'node:path';
import {fileURLToPath} from 'node:url';
import {spawnSync} from 'node:child_process';
import assert from 'node:assert/strict';
import {createHydrology} from '../assets/world-lux-preview/hydrology.mjs';
const root=path.dirname(path.dirname(fileURLToPath(import.meta.url)));
const geo=JSON.parse(fs.readFileSync(path.join(root,'assets/world-lux-preview/game-geography.json')));
const data=JSON.parse(fs.readFileSync(path.join(root,'assets/world-lux-preview/game-hydrology.json')));
const hydro=createHydrology(data),rows=geo.grid.rows.map(r=>{const a=[];for(let i=0;i<r.length;i+=2)a.push(...Array(r[i+1]).fill(r[i]));return a;});
const at=(x,y)=>{const id=x>=0&&y>=0&&x<768&&y<1100?rows[Math.floor(y/4)]?.[Math.floor(x/4)]:0;return id?geo.communes[id-1]:null;};
const dry=(l,t,r,b)=>{if(l<0||t<0||r>768||b>1100)return false;for(let y=Math.floor(t/4);y<=Math.floor((b-.000001)/4);y++)for(let x=Math.floor(l/4);x<=Math.floor((r-.000001)/4);x++)if(!at(x*4+2,y*4+2))return false;return !hydro.intersectsRect(l,t,r,b);};
let seed=918272;const rand=()=>((seed=Math.imul(seed,1664525)+1013904223>>>0)/2**32);
const cases=[];
for(let i=0;i<10000;i++){const x=rand()*776-4,y=rand()*1108-4,size=1+Math.floor(rand()*7);cases.push([x,y,x+size,y+size]);}
for(const river of data.rivers)for(let i=0;i<river.points.length;i+=25){const [x,y]=river.points[i];for(const offset of [-river.width/2,0,river.width/2])cases.push([x+offset,y,x+offset+1,y+1]);}
const result=spawnSync(process.env.PHP_BINARY||'php',[path.join(root,'tests/fixtures/luxembourg_geometry_probe.php')],{input:JSON.stringify(cases),encoding:'utf8',maxBuffer:16*1024*1024});
assert.equal(result.status,0,result.stderr);const actual=JSON.parse(result.stdout);assert.equal(actual.length,cases.length);
for(let i=0;i<cases.length;i++){const c=cases[i],region=at(c[0],c[1]);assert.equal(actual[i].at?.commune_id??null,region?.id??null,`commune ${i}`);assert.equal(actual[i].at?.canton_id??null,region?.canton??null,`canton ${i}`);assert.equal(actual[i].water,hydro.waterAt(c[0],c[1]),`water ${i}`);assert.equal(actual[i].intersects,hydro.intersectsRect(...c),`rectangle ${i}`);assert.equal(actual[i].dry,dry(...c),`dry ${i}`);}
console.log(`PASS PHP/browser geography and exact hydrology parity for ${cases.length} points and complete footprint rectangles.`);
