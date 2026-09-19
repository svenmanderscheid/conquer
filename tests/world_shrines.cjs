'use strict';
// Static local browser fixture: never requests game APIs or changes live players.
const fs=require('fs'),path=require('path'),os=require('os'),http=require('http'),assert=require('assert');
const {chromium}=require(process.env.PLAYWRIGHT_MODULE||'playwright');
const root=path.resolve(__dirname,'..'),output=fs.mkdtempSync(path.join(os.tmpdir(),'conquer-world-shrines-'));
const viewports=process.argv.includes('--landscape')?[{width:568,height:320}]:[{width:1280,height:720},{width:390,height:844},{width:320,height:740},{width:568,height:320}];
const fixture=`<!doctype html><html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="icon" href="data:,">
${['world-atlas','map-overlay','castle-skins','world-zones','world-shrines'].filter(name=>fs.existsSync(path.join(root,'assets/css',name+'.css'))).map(name=>`<link rel="stylesheet" href="/assets/css/${name}.css">`).join('')}
<style>*{box-sizing:border-box}body{margin:0;font-family:Arial,sans-serif}#map{height:100dvh}</style><body class="mobile-game playfield-mode world-mode"><div id="map"></div>
<script>window.ConquerTerrainData=${fs.readFileSync(path.join(root,'data/world_terrain.json'),'utf8')};window.actions=[];document.addEventListener('click',e=>{const b=e.target.closest('[data-action]');if(b)actions.push({...b.dataset});});</script>
${['castle-skins','world-landscape','world-map'].map(name=>`<script src="/assets/js/${name}.js"></script>`).join('')}
<script>
window.miniShrines=[];const fill=CanvasRenderingContext2D.prototype.fillRect;CanvasRenderingContext2D.prototype.fillRect=function(x,y,w,h){if(this.canvas.closest('.atlas-minimap')&&['#79bd71','#c0edf8','#f3c878','#ff8a65'].includes(this.fillStyle)){miniShrines.push([x,y,w,h,this.fillStyle]);if(miniShrines.length>16)miniShrines.shift();}return fill.call(this,x,y,w,h);};
window.options={host:document.querySelector('#map'),base:'',esc:s=>String(s).replaceAll('&','&amp;').replaceAll('<','&lt;').replaceAll('"','&quot;'),monsterArt:()=>'',now:()=>Date.now(),state:{city:{id:1,coord_x:64,coord_y:64,castle_level:3,city_skin:'default'},players:[],nodes:[],monsters:[],marches:[],congress:{id:1,coord_x:128,coord_y:128,name:'Kongress',can_attack:true},shrines:[
{id:21,element:'forest',coord_x:101,coord_y:101,name:'Schrein des Waldes',alliance_tag:'WLD',can_attack:true,event:{name:'Waldwacht',active:true,ends_at:'2030-01-02 19:00:00'}},
{id:22,art_key:'SHRINE_ICE',coord_x:155,coord_y:101,name:'Schrein des Eises',alliance_tag:null,can_attack:false,event:{name:'Frostwacht',active:false,next_starts_at:'2030-01-03 19:00:00'}},
{id:23,shrine_code:'SHRINE_SAND',coord_x:101,coord_y:155,name:'Schrein des Sandes',alliance_tag:'DUN',can_attack:true,event:{name:'Dünenwacht',active:true,ends_at:'2030-01-02 19:00:00'}},
{id:24,element:'lava',coord_x:155,coord_y:155,name:'Schrein der Lava',can_attack:false,event:{name:'Glutwacht',active:false,next_starts_at:'2030-01-03 19:00:00'}}]}};
ConquerWorld.render(options);
</script></body></html>`;
const near=(a,b,label)=>assert(Math.abs(a-b)<.2,`${label}: ${a} / ${b}`);
async function assertLandmark(page,marker,tiles){
 const geometry=await marker.evaluate(el=>{
  const ground=el.querySelector('.atlas-marker-ground'),fx=el.querySelector('.atlas-landmark-effects'),image=el.querySelector('img'),style=getComputedStyle(ground),box=ground.getBoundingClientRect(),imgStyle=getComputedStyle(image),tile=parseFloat(el.style.getPropertyValue('--tile-size'));
  const canvas=document.createElement('canvas');canvas.width=image.naturalWidth;canvas.height=image.naturalHeight;const c=canvas.getContext('2d');c.drawImage(image,0,0);const pixels=c.getImageData(0,0,canvas.width,canvas.height).data;let left=canvas.width,right=0;
  for(let y=0;y<canvas.height;y++)for(let x=0;x<canvas.width;x++)if(pixels[(y*canvas.width+x)*4+3]>32){left=Math.min(left,x);right=Math.max(right,x);}
  return {ground:{width:box.width,height:box.height,border:parseFloat(style.borderTopWidth),borderStyle:style.borderTopStyle,radius:style.borderTopLeftRadius,background:style.backgroundImage,display:style.display,visibility:style.visibility},tile,selected:el.classList.contains('is-selected'),fx:!!fx,sparks:fx?.querySelectorAll('.landmark-spark').length,pointerSafe:fx&&[fx,...fx.querySelectorAll('*')].every(n=>getComputedStyle(n).pointerEvents==='none'),running:fx?.getAnimations({subtree:true}).filter(a=>a.playState==='running').length,visibleTiles:(right-left+1)*Math.min(parseFloat(imgStyle.width)/image.naturalWidth,parseFloat(imgStyle.height)/image.naturalHeight)/tile};
 });
 near(geometry.ground.width,geometry.tile*tiles,'visible border spans entire monument width');near(geometry.ground.height,geometry.tile*tiles,'visible border spans entire monument height');assert(!geometry.selected,'footprint is visible before selection');assert(geometry.ground.border>=2&&geometry.ground.borderStyle==='solid'&&parseFloat(geometry.ground.radius)===0,'persistent square border is visibly drawn');assert(geometry.ground.background.includes('linear-gradient'),'occupied cells have an internal grid');assert(geometry.ground.display!=='none'&&geometry.ground.visibility==='visible');
 assert(geometry.visibleTiles>=(tiles===6?5.8:6.7),`actual alpha-visible sprite fills ${tiles}-tile landmark: ${geometry.visibleTiles}`);assert(geometry.fx&&geometry.sparks===6&&geometry.pointerSafe,'full aura DOM cannot intercept pointer selection');assert(geometry.running>=3,'multiple aura animations run without selecting the landmark');
 const sample=()=>marker.evaluate(el=>{const fx=el.querySelector('.atlas-landmark-effects'),halo=getComputedStyle(fx.querySelector('.landmark-halo')),orbit=getComputedStyle(fx.querySelector('.landmark-orbit'),'::before'),image=getComputedStyle(el.querySelector('img'));return [halo.transform,halo.opacity,orbit.transform,image.transform];});
 const before=await sample();assert(Number(before[1])>.4,'normal aura has clearly visible opacity');await page.waitForTimeout(420);const after=await sample();assert(Number(after[1])>.4,'normal aura remains visible while moving');assert.notDeepEqual(after,before,'visible aura/orbit transform actually advances over time');
}
async function assertReducedLandmarks(page){
 await page.waitForFunction(()=>[...document.querySelectorAll('.atlas-marker--shrine img,.atlas-marker--congress img')].every(i=>i.src.includes('.png')&&i.complete&&i.naturalWidth));
 const reduced=await page.locator('.atlas-marker--shrine,.atlas-marker--congress').evaluateAll(elements=>elements.map(el=>{const fx=el.querySelector('.atlas-landmark-effects'),nodes=[el.querySelector('img'),...fx.querySelectorAll('*')];return {running:el.getAnimations({subtree:true}).filter(a=>a.playState==='running').length,names:nodes.flatMap(n=>[getComputedStyle(n).animationName,getComputedStyle(n,'::before').animationName,getComputedStyle(n,'::after').animationName])};}));
 assert.equal(reduced.length,5);assert(reduced.every(r=>r.running===0&&r.names.every(name=>name==='none')),'system/game reduced motion disables all five landmark effects and pseudo-elements');
}
(async()=>{
 const blocked=[],server=http.createServer((req,res)=>{const name=decodeURIComponent(new URL(req.url,'http://localhost').pathname);if(name==='/'){res.setHeader('Content-Type','text/html');res.end(fixture);return;}if(!name.startsWith('/assets/')){blocked.push(name);res.writeHead(403);res.end();return;}const file=path.resolve(root,'.'+name);if(!file.startsWith(root+path.sep)||!fs.existsSync(file)){res.writeHead(404);res.end();return;}res.setHeader('Content-Type',file.endsWith('.js')?'text/javascript':file.endsWith('.css')?'text/css':file.endsWith('.webp')?'image/webp':file.endsWith('.svg')?'image/svg+xml':file.endsWith('.png')?'image/png':'application/octet-stream');res.end(fs.readFileSync(file));});
 await new Promise(resolve=>server.listen(0,'127.0.0.1',resolve));let browser;
 try{
  browser=await chromium.launch({headless:true,channel:process.env.PLAYWRIGHT_CHANNEL||'chrome'});
  for(const viewport of viewports){
   const page=await browser.newPage({viewport}),errors=[];page.on('pageerror',e=>errors.push(e.message));await page.goto(`http://127.0.0.1:${server.address().port}`);
   await page.waitForFunction(()=>[...document.querySelectorAll('.atlas-marker img')].every(i=>i.complete&&i.naturalWidth));
   assert.equal(await page.locator('.atlas-marker--shrine').count(),4,'all server shrines retained outside current radius');
   await page.locator('[data-atlas="navigation"]').click();
   const minis=await page.evaluate(()=>miniShrines.slice(-4));assert.equal(new Set(minis.map(r=>r[4])).size,4,'overview has four distinct permanent markers');
   await page.locator('#atlas-navigation-panel .map-overlay-close').click();
   for(const [index,element]of ['forest','ice','sand','lava'].entries()){
    await page.locator('[data-atlas="navigation"]').click();const nav=page.locator(`.atlas-shrine-jump[data-shrine="${element}"]`);assert(await nav.isVisible(),'shrine navigation visible');
    const bounds=await nav.boundingBox();if(bounds.x<0||bounds.y<0||bounds.x+bounds.width>viewport.width+.2||bounds.y+bounds.height>viewport.height+.2){await page.screenshot({path:path.join(output,`${viewport.width}x${viewport.height}-overflow.png`)});console.log('Unreachable navigation:',bounds,'Screenshots:',output);}assert(bounds.x>=0&&bounds.y>=0&&bounds.x+bounds.width<=viewport.width+.2&&bounds.y+bounds.height<=viewport.height+.2,'navigation button reachable without page overflow');
    await nav.click();const id=21+index,marker=page.locator(`.atlas-marker[data-atlas-target="shrine:${id}"]`),b=await marker.boundingBox();
    near(b.width,264,'six tile footprint');near(b.height,264,'six tile footprint height');near(b.x+b.width/2,viewport.width/2,'half-tile centered shrine x');near(b.y+b.height/2,viewport.height/2,'half-tile centered shrine y');
    assert((await marker.locator('img').getAttribute('src')).includes(`shrine-${element}.webp`));
    await assertLandmark(page,marker,6);
    // Isolate terrain hit-testing from fixed HUD controls, whose reachability is checked separately.
    await page.evaluate(()=>document.querySelectorAll('.map-overlay-coordinate-toggle,.map-overlay-search-toggle').forEach(el=>el.style.visibility='hidden'));
    for(let y=0;y<6;y++)for(let x=0;x<6;x++){if(x||y)await page.locator('.atlas-viewport').press('Escape');await page.mouse.click(b.x+22+44*x,b.y+22+44*y);assert.equal(await page.locator('.atlas-target-actions [data-action="shrine-open"]').getAttribute('data-id'),String(id),`shrine ${id} tile ${x},${y} selects correct target`);}
    await page.evaluate(()=>document.querySelectorAll('.map-overlay-coordinate-toggle,.map-overlay-search-toggle').forEach(el=>el.style.visibility=''));
    assert.equal(await page.locator('.atlas-target-actions [data-action="shrine-open"]').getAttribute('data-id'),String(id),'all shrine footprint clicks keep correct ID');
    assert.equal(await page.locator('.map-overlay-target-panel:not([hidden])').count(),0,'selection opens context before details');
    assert.equal(await page.locator('.atlas-target-actions [data-action="shrine-attack"]').count(),index%2===0?1:0,'attack only follows server permission');
    if(index===0){assert((await marker.locator('.atlas-marker-level').textContent()).includes('[WLD]'));await page.locator('.atlas-target-actions [data-action="shrine-attack"]').click();assert.deepEqual(await page.evaluate(()=>actions.at(-1)),{action:'shrine-attack',id:'21'});await marker.click();}
    await page.locator('.atlas-target-actions [data-action="shrine-open"]').click();assert.deepEqual(await page.evaluate(()=>actions.at(-1)),{action:'shrine-open',id:String(id)});
    await page.screenshot({path:path.join(output,`${viewport.width}x${viewport.height}-${element}.png`)});await marker.click();await page.locator('.atlas-target-actions [data-action="share-coordinates"]').click();const share=await page.evaluate(()=>actions.at(-1));assert.equal(share.x,String(index%2?155:101));assert.equal(share.y,String(index>1?155:101));
    await page.mouse.click(b.x-5,b.y+b.height/2);near((await page.locator('.atlas-cell-focus').boundingBox()).width,44,'aura overhang leaves adjacent empty tile clickable');await page.locator('.atlas-viewport').press('Escape');
   }
   await page.locator('[data-atlas="navigation"]').click();const firstNav=page.locator('.atlas-shrine-jump[data-shrine="forest"]');await firstNav.focus();await page.evaluate(()=>{options.state.shrines[0].coord_x=103;options.state.shrines[0].coord_y=102;options.state.shrines[0].alliance_tag='NEU';options.state.shrines[0].can_attack=false;options.state.shrines[0].event.active=false;ConquerWorld.render(options);});assert(await firstNav.evaluate(el=>document.activeElement===el),'poll update preserves focused navigation button');await firstNav.click();assert.deepEqual(await page.evaluate(()=>({x:ConquerWorld.getCenter().x,y:ConquerWorld.getCenter().y})),{x:104,y:103},'navigation uses updated server coordinates');
   const homeShrine=page.locator('.atlas-marker--shrine[data-shrine="forest"]');await homeShrine.click();assert.equal(await page.locator('.atlas-target-actions [data-action="shrine-attack"]').count(),0,'poll removes outdated attack permission');assert((await homeShrine.locator('.atlas-marker-level').textContent()).includes('[NEU]'));
   await page.locator('.atlas-viewport').press('Escape');await page.locator('[data-atlas="search"]').click();assert.equal(await page.locator('.atlas-nearby-row').count(),0,'object search has no result list');await page.locator('#atlas-search-panel .map-overlay-close').click();
   await page.emulateMedia({reducedMotion:'reduce'});await assertReducedLandmarks(page);await page.emulateMedia({reducedMotion:'no-preference'});await page.waitForFunction(()=>[...document.querySelectorAll('.atlas-marker--shrine img')].every(i=>i.src.includes('.webp')));await page.evaluate(()=>{document.body.classList.add('reduced-motion');ConquerWorld.render(options);});await assertReducedLandmarks(page);
   await page.evaluate(()=>{document.body.classList.remove('reduced-motion');ConquerWorld.render(options);});await page.waitForFunction(()=>document.querySelector('.atlas-marker--congress img').src.includes('.webp')&&document.querySelector('.atlas-marker--congress img').complete);
   await page.locator('[data-atlas="navigation"]').click();await page.locator('.atlas-congress-jump').click();const congress=page.locator('.atlas-marker--congress'),congressBox=await congress.boundingBox();near(congressBox.width,308,'Congress remains seven tiles wide');near(congressBox.height,308,'Congress remains seven tiles high');await assertLandmark(page,congress,7);await congress.click();assert.equal(await page.locator('.atlas-target-actions [data-action="congress-open"]').count(),1,'Congress context retained');await page.locator('.atlas-viewport').press('Escape');
   await page.emulateMedia({reducedMotion:'reduce'});await assertReducedLandmarks(page);
   await page.locator('[data-atlas="navigation"]').click();await page.screenshot({path:path.join(output,`${viewport.width}x${viewport.height}-overview.png`)});assert.deepEqual(errors,[]);await page.close();console.log(`PASS ${viewport.width}x${viewport.height}: visible6/7-cell bounds, alpha-visible art, five moving auras,144 tile hits, no pointer interception, dynamic state and system/game reduced motion.`);
  }
  assert.deepEqual(blocked,[]);console.log('Screenshots: '+output);
 }finally{if(browser)await browser.close();await new Promise(resolve=>server.close(resolve));}
})().catch(e=>{console.error(e);process.exitCode=1;});
