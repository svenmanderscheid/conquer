'use strict';
const fs=require('fs'),path=require('path'),vm=require('vm'),assert=require('assert'),{execFileSync}=require('child_process');
const root=path.resolve(__dirname,'..'),context={window:{ConquerTerrainData:JSON.parse(fs.readFileSync(path.join(root,'data/world_terrain.json'),'utf8'))}};
vm.runInNewContext(fs.readFileSync(path.join(root,'assets/js/world-landscape.js'),'utf8'),context);
const php=`require 'src/Game/Map/WorldTerrain.php';for($y=0;$y<256;$y+=.5)for($x=0;$x<256;$x+=.5)echo \\Conquer\\Game\\Map\\WorldTerrain::isWater($x,$y)?'1':'0';`;
const server=execFileSync('php',['-r',php],{cwd:root,maxBuffer:1024*1024}).toString();let index=0,count=0;
for(let y=0;y<256;y+=.5)for(let x=0;x<256;x+=.5){const client=context.window.ConquerLandscape.waterAt(x,y);assert.equal(client,server[index++]==='1',`Water disagreement at ${x},${y}`);if(client)count++;}
assert(count>1000);console.log(`PASS ${index} half-tile samples: server and canvas use identical lakes, rivers and shores.`);
