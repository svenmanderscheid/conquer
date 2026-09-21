'use strict';
// Real city PHP view, scene, models and all main-app styles. No live account writes.
const fs=require('fs'),path=require('path'),assert=require('assert'),os=require('os'),{spawnSync}=require('child_process');
const root=path.resolve(__dirname,'..'),pw=require(process.env.PLAYWRIGHT_MODULE||'playwright');
const output=fs.mkdtempSync(path.join(os.tmpdir(),'conquer-village-viewport-'));
const codes=['castle','academy','barrack','archery_range','stable','hospital','storage','treasure_house','hall_of_alliance','trading_post','farm','lumber_camp','quarry','gold_mine','wall'];
const state={server_time:Date.now()/1000,player:{name:'Fixture',csrf:'fixture'},resources:{food:1000,lumber:1000,stone:1000,gold:1000},build_queue:[],research_queue:[],troop_queue:[],buildings:Object.fromEntries(codes.map(code=>[code,{name:code,level:3,power:1,next_power:2,cost:{food:10},requirements:[],reasons:[],seconds:60,can_upgrade:true}]))};
function embedded(){const script=`define('ROOT_DIR','${root.replaceAll('\\','/')}'); require ROOT_DIR.'/src/Game/Locale.php'; require ROOT_DIR.'/src/Game/World/WorldContext.php'; \\Conquer\\Game\\World\\WorldContext::bind(7); define('APP_BASE',''); $session=['username'=>'Fixture']; $_GET=['embed'=>'1']; include ROOT_DIR.'/views/city3d.php';`;const out=spawnSync(process.env.PHP_BINARY||'C:/xampp/php/php.exe',['-r',script],{encoding:'utf8'});assert.equal(out.status,0,out.stderr);return out.stdout;}
function host(){let html=fs.readFileSync(path.join(root,'views/game.php'),'utf8').replace(/<\?php[\s\S]*?\?>|<\?=[\s\S]*?\?>/g,'').replace(/<script\b[^>]*>[\s\S]*?<\/script>/g,'');html=html.replace('class="mobile-game"','class="mobile-game city-mode"').replace(/<section id="content"[\s\S]*?<\/section>/,'<section id="content"><div class="city-playfield"><iframe id="city-frame" class="city-frame" src="/embedded" title="Dorf"></iframe></div></section>');return html;}
async function assertVersionedCityModules(frame,assetVersion){
 assert.match(assetVersion,/^[a-f0-9]{16}$/,'3D diagnostics expose the current content hash');
 const modules=await frame.evaluate(()=>performance.getEntriesByType('resource').map(entry=>entry.name).filter(name=>{const url=new URL(name);return url.pathname.startsWith('/assets/city3d/')&&url.pathname.endsWith('.js');}));
 assert(modules.length>=6,'loaded 3D scene records its module resources');
 const expected='city3d-'+assetVersion;
 for(const name of modules){const url=new URL(name);assert.equal(url.searchParams.get('v'),expected,'City3D module uses the shared content version: '+url.pathname);}
 const three=modules.filter(name=>new URL(name).pathname.endsWith('/vendor/three.module.js'));
 assert.equal(three.length,1,'loads Three.js once through the import map');
 assert.equal(new Set(three).size,1,'does not request a second Three.js URL');
}
(async()=>{const browser=await pw.chromium.launch({headless:true,args:['--use-angle=swiftshader','--enable-unsafe-swiftshader'],...(process.env.BROWSER_EXECUTABLE_PATH?{executablePath:process.env.BROWSER_EXECUTABLE_PATH}:{})});let checks=0;const errors=[];
try{const page=await browser.newPage();page.on('pageerror',e=>errors.push(e.message));await page.route('https://village.fixture/**',route=>{const url=new URL(route.request().url());if(url.pathname==='/host')return route.fulfill({contentType:'text/html',body:host()});if(url.pathname==='/embedded')return route.fulfill({contentType:'text/html',body:embedded()});if(url.pathname==='/api/city3d/state')return route.fulfill({json:{ok:true,data:state}});if(url.pathname.startsWith('/api/'))return route.fulfill({json:{ok:true,data:{}}});const file=path.resolve(root,'.'+url.pathname);if(file.startsWith(root+path.sep)&&fs.existsSync(file)&&fs.statSync(file).isFile()){const types={'.css':'text/css','.js':'text/javascript','.png':'image/png','.svg':'image/svg+xml','.jpg':'image/jpeg','.webp':'image/webp'};return route.fulfill({contentType:types[path.extname(file)]||'application/octet-stream',body:fs.readFileSync(file)});}return route.fulfill({status:404,body:''});});
for(const size of [{width:1280,height:720},{width:390,height:844},{width:844,height:390},{width:320,height:700}]){await page.setViewportSize(size);await page.goto('https://village.fixture/host');const frame=page.frames().find(f=>f.url().endsWith('/embedded'));await frame.waitForFunction(()=>window.conquer3D?.getState().ready,{},{timeout:120000});await frame.waitForFunction(()=>document.querySelector('#connection').textContent.includes('Fixture'));
const outer=await page.locator('#city-frame').boundingBox();assert.equal(Math.round(outer.x),0);assert.equal(Math.round(outer.y),0);assert.equal(Math.round(outer.width),size.width);assert.equal(Math.round(outer.height),size.height);checks+=4;
const dimensions=await frame.locator('#world canvas').boundingBox();assert.equal(Math.round(dimensions.width),size.width);assert.equal(Math.round(dimensions.height),size.height);checks+=2;
for(const selector of ['.realm-hud','#resource-bar','.realm-nav','.city-footer']){assert.equal(await frame.locator(selector).isVisible(),false,selector+' duplicates parent HUD');checks++;}
let before=await frame.evaluate(()=>conquer3D.getState());assert(before.triangles>1000);assert.equal(before.buildings,16);checks+=2;
assert(before.pixelRatio<=1.45,'3D framebuffer density stays within the stable GPU budget');assert.equal(before.shadowMapSize,1024,'Village uses the reduced shadow-map budget');assert.equal(before.graphicsSuspended,false,'Graphics context is active');checks+=3;
assert.equal(before.buildingHeightScale,1.12,'Playable buildings use the approved taller silhouette');checks++;
assert.equal(before.boundary.shape,'rounded-rectangle','Village uses the spacious rounded rectangular boundary');assert.equal(before.boundary.cornerRadius,12);assert.equal(before.boundary.segments,88);checks+=3;
assert.equal(before.terrace.height,2.25,'Upper town exposes its real second terrain level');checks++;
for(const id of ['terrace-ramp','upper-avenue','plaza-west','plaza-east','academy','castle','treasury','hospital','alliance','market','storage','watch']){assert.equal(before.roadSurfaces[id],'stone',id+' uses castle paving');checks++;}
for(const id of ['gate','stable','garrison','archers','infantry','production','gold','quarry','production-south','bridge']){assert.equal(before.roadSurfaces[id],'earth',id+' keeps the warm lower-town road');checks++;}
assert.deepEqual(before.landmarks.fountain,{x:0,z:-16,radius:3.35},'Fountain occupies the protected upper-town plaza');checks++;
for(const code of ['castle','academy','hospital','trading_post','hall_of_alliance','treasure_house','watch_tower','storage']){const p=before.layout[code];assert(p.z<=5,code+' belongs to the raised upper town');checks++;}
for(const code of ['stable','archery_range','barrack','farm','gold_mine','lumber_camp','quarry']){const p=before.layout[code];assert(p.z>=10,code+' belongs to the lower town');checks++;}
assert.deepEqual(['academy','castle','treasure_house'].map(code=>before.layout[code].z),[-28,-28,-28],'Academy, castle and treasury share the back row');checks++;
assert.deepEqual(['hospital','hall_of_alliance','trading_post','storage'].map(code=>before.layout[code].z),[-9,-9,-9,-9],'Hospital, alliance hall, market and storage share the front row');checks++;
assert(before.layout.trading_post.x>0,'Market is shifted right of the central ramp');assert(before.layout.watch_tower.x>before.layout.storage.x&&before.layout.watch_tower.z<before.layout.storage.z,'Watchtower occupies the free right edge above storage');checks+=2;
assert.deepEqual([before.layout.gold_mine.x,before.layout.gold_mine.z],[13,30],'Gold mine occupies the former lumber-camp lot');
assert.deepEqual([before.layout.lumber_camp.x,before.layout.lumber_camp.z],[30,15],'Lumber camp occupies the former gold-mine lot');
assert.equal(before.layout.gold_mine.rotation,Math.PI,'Gold mine is turned 180 degrees toward its northern road');
assert.equal(before.layout.lumber_camp.rotation,0,'Lumber camp receives the second 180-degree turn');
assert.equal(before.layout.quarry.rotation,Math.PI,'Quarry faces its northern road');checks+=5;
const roundedArea=before.boundary.wallX*2*before.boundary.wallZ*2-(4-Math.PI)*before.boundary.cornerRadius**2;
const formerEllipseArea=Math.PI*before.boundary.wallX*before.boundary.wallZ;assert(roundedArea/formerEllipseArea>1.24,'Rounded rectangle adds at least 24% usable ground at the same extents');checks++;
const lots=Object.entries(before.layout).filter(([code])=>code!=='wall');
for(let i=0;i<lots.length;i++)for(let j=i+1;j<lots.length;j++){
 const [aName,a]=lots[i],[bName,b]=lots[j],clearance=Math.hypot(a.x-b.x,a.z-b.z)-a.radius-b.radius;
 assert(clearance>=1.4,`${aName} and ${bName} keep a readable lawn gap (${clearance.toFixed(2)})`);checks++;
}
if(size.width>900){
 await page.evaluate(()=>document.querySelector('#city-frame').contentWindow.postMessage({type:'conquer:preferences',city_skin:'dragon',reduced_motion:false},location.origin));
 await frame.waitForFunction(()=>conquer3D.getState().skin==='dragon');await page.screenshot({path:path.join(output,`village-${size.width}-dragon.png`)});checks++;
 await page.evaluate(()=>document.querySelector('#city-frame').contentWindow.postMessage({type:'conquer:preferences',city_skin:'default',reduced_motion:false},location.origin));
 await frame.waitForFunction(()=>conquer3D.getState().skin==='default');checks++;
 assert(before.farmLife,'farm animation controller is connected to the playable scene');
 await frame.waitForFunction(value=>conquer3D.getState().farmLife.vaneAngle!==value,before.farmLife.vaneAngle);checks+=2;
 await frame.locator('#pause').click();const frozen=await frame.evaluate(()=>conquer3D.getState().farmLife);await frame.waitForTimeout(250);assert.deepEqual(await frame.evaluate(()=>conquer3D.getState().farmLife),frozen,'pause freezes the actual farm pieces');checks++;
 await frame.locator('#pause').click();await page.emulateMedia({reducedMotion:'reduce'});await frame.waitForFunction(()=>conquer3D.getState().paused);const reducedFarm=await frame.evaluate(()=>conquer3D.getState().farmLife);await frame.waitForTimeout(200);assert.deepEqual(await frame.evaluate(()=>conquer3D.getState().farmLife),reducedFarm,'changing OS preference stops the farm');checks++;
 await page.emulateMedia({reducedMotion:'no-preference'});await frame.waitForFunction(()=>!conquer3D.getState().paused);
}
for(const selector of ['.village-plots-jump','.village-plot-labels','#building-plot-panel']){assert.equal(await frame.locator(selector).count(),0,'Additional construction plots are retired');checks++;}
for(const code of ['farm','lumber_camp','quarry','gold_mine']){const p=before.layout[code],b=before.boundary,r=b.cornerRadius,dx=Math.max(0,Math.abs(p.x)-(b.wallX-r)),dz=Math.max(0,Math.abs(p.z)-(b.wallZ-r));assert(Math.abs(p.x)<=b.wallX&&Math.abs(p.z)<=b.wallZ&&dx*dx+dz*dz<r*r,code+' is inside the rounded rectangular wall');checks++;}
await assertVersionedCityModules(frame,before.assetVersion);checks+=4;
if(size.width>900){assert(await frame.locator('#label-academy').isVisible(),'Academy name remains visible above the northern district');const academyLabel=await frame.locator('#label-academy').boundingBox();assert(academyLabel.y>=60,'Northern label stays below the app resource bar');checks+=2;}
// All zoom inputs must stop at the readable overview, including after zooming in.
await frame.locator('#zoomIn').click();await frame.evaluate(()=>{for(let n=0;n<20;n++)document.querySelector('#zoomOut').click();});
assert.deepEqual((await frame.evaluate(()=>conquer3D.getState())).viewport,before.viewport,'Zoom-out button stops at the overview');checks++;
await frame.locator('#zoomIn').click();await frame.locator('#world canvas').dispatchEvent('wheel',{deltaY:10000});
assert.equal((await frame.evaluate(()=>conquer3D.getState())).zoom,before.zoom,'Wheel cannot shrink the city past the overview');checks++;
await frame.locator('#zoomIn').click();
const touchSession=await page.context().newCDPSession(page),touchY=Math.round(size.height*.5),touchX=Math.round(size.width*.4);
await touchSession.send('Input.dispatchTouchEvent',{type:'touchStart',touchPoints:[{id:1,x:touchX,y:touchY},{id:2,x:touchX+80,y:touchY}]});
await touchSession.send('Input.dispatchTouchEvent',{type:'touchMove',touchPoints:[{id:1,x:touchX+30,y:touchY},{id:2,x:touchX+50,y:touchY}]});
await touchSession.send('Input.dispatchTouchEvent',{type:'touchEnd',touchPoints:[]});await touchSession.detach();
assert((await frame.evaluate(()=>conquer3D.getState())).zoom>=before.zoom,'Pinch cannot shrink the city past the overview');checks++;await frame.locator('#reset').click();
await page.screenshot({path:path.join(output,`village-${size.width}-overview.png`)});
await frame.evaluate(()=>window.dispatchEvent(new CustomEvent('conquer-focus-building',{detail:{code:'castle'}})));for(let n=0;n<3;n++)await frame.locator('#zoomIn').click();await frame.waitForTimeout(500);await page.screenshot({path:path.join(output,`village-${size.width}-close.png`)});const after=await frame.evaluate(()=>conquer3D.getState());assert.equal(after.selected,'keep');assert(after.viewport.worldWidth<before.viewport.worldWidth);checks+=2;await page.locator('#content').evaluate(el=>el.id='playfield-content');const preserved=await page.locator('#city-frame').boundingBox();assert.equal(Math.round(preserved.width),size.width);assert.equal(Math.round(preserved.height),size.height);checks+=2;
for(const code of ['farm','lumber_camp','gold_mine']){await frame.evaluate(code=>window.dispatchEvent(new CustomEvent('conquer-focus-building',{detail:{code}})),code);await frame.waitForTimeout(300);const exterior=await frame.evaluate(()=>conquer3D.getState());assert.equal(exterior.selected,code);checks++;await page.screenshot({path:path.join(output,`village-${size.width}-${code}.png`)});}
await frame.evaluate(()=>window.dispatchEvent(new CustomEvent('conquer-focus-building',{detail:{code:'wall'}})));const wallState=await frame.evaluate(()=>conquer3D.getState());assert.equal(wallState.selected,'wall');assert(Math.abs(wallState.viewport.target.z-wallState.boundary.wallZ)<.00001);checks+=2;
await frame.locator('#reset').click();const panStart=await frame.evaluate(()=>conquer3D.getState().viewport.target);await page.mouse.move(size.width*.6,size.height*.62);await page.mouse.down();await page.mouse.move(size.width*.75,size.height*.44,{steps:10});await page.mouse.up();const panEnd=await frame.evaluate(()=>conquer3D.getState().viewport.target);assert.notDeepEqual(panStart,panEnd,'Village drag pans camera');checks++;
const motionStart=await frame.evaluate(()=>conquer3D.getState().wheelAngle);await frame.waitForFunction(angle=>conquer3D.getState().wheelAngle!==angle,motionStart,{timeout:10000});checks++;
console.log(JSON.stringify({size,overview:before.viewport,near:after.viewport,triangles:after.triangles,drawCalls:after.drawCalls}));}
assert.deepEqual(errors,[]);console.log(`PASS ${checks} real 3D viewport/HUD checks; no browser errors; screenshots ${output}`);
}finally{await browser.close();}})().catch(e=>{console.error(e);process.exitCode=1;});
