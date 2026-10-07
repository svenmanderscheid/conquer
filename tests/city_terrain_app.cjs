'use strict';
// Reproduce the lower-edge regression in the main app, using a disposable realm.
const assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path'),os=require('node:os'),net=require('node:net'),{spawn}=require('node:child_process');
const {chromium}=require(process.env.PLAYWRIGHT_MODULE||'playwright');
const root=path.resolve(__dirname,'..');
const output=path.resolve(process.env.CITY_TERRAIN_OUTPUT||path.join(os.tmpdir(),'conquer-terrain-check'));
const assetRoot=process.env.CITY_TERRAIN_ASSET_ROOT?path.resolve(process.env.CITY_TERRAIN_ASSET_ROOT):null;
fs.mkdirSync(output,{recursive:true});
(async()=>{
 const listener=net.createServer();await new Promise(resolve=>listener.listen(0,'127.0.0.1',resolve));const port=listener.address().port;await new Promise(resolve=>listener.close(resolve));
 const base='http://127.0.0.1:'+port;
 const fixture=spawn(process.env.PHP_BINARY||'C:/xampp/php/php.exe',['tools/preview-feature-fixture.php','--port='+port,'--hud','--chat'],{cwd:root,stdio:['pipe','pipe','pipe'],windowsHide:true});
 let log='',browser;
 fixture.stdout.on('data',data=>log+=data);fixture.stderr.on('data',data=>log+=data);
 try{
  await new Promise((resolve,reject)=>{const timeout=setTimeout(()=>{clearInterval(poll);reject(Error(log||'Preview timeout'));},60000),poll=setInterval(()=>{if(log.includes('Synthetic preview ready')){clearTimeout(timeout);clearInterval(poll);resolve();}else if(fixture.exitCode!==null){clearTimeout(timeout);clearInterval(poll);reject(Error(log));}},100);fixture.on('error',reject);});
  browser=await chromium.launch({headless:true,channel:process.env.PLAYWRIGHT_CHANNEL||'chrome'});
  const page=await browser.newPage({viewport:{width:1280,height:800},hasTouch:true,locale:'en-GB'}),errors=[];
  page.on('pageerror',error=>errors.push(error.message));
  page.on('response',response=>{if(response.status()>=400&&new URL(response.url()).pathname.startsWith('/assets/'))errors.push(response.status()+' '+response.url());});
  if(assetRoot)for(const name of ['assets/js/city-painted.js','assets/css/village-theme.css'])await page.route('**/'+name+'*',route=>route.fulfill({path:path.join(assetRoot,name),contentType:name.endsWith('.js')?'application/javascript':'text/css'}));
  await page.goto(base+'/?zugang=login');await page.locator('[name=identifier], [name=username]').fill('PreviewPlayer');await page.locator('[name=password]').fill('PreviewFixture!2026');
  await Promise.all([page.waitForURL('**/city'),page.locator('form[action$="/auth/local"] button[type=submit]').click()]);
  const ready=async()=>{await page.locator('.painted-village-scene').waitFor();await page.waitForFunction(()=>!document.querySelector('.scene-transition.is-active'));await page.waitForFunction(()=>[...document.querySelectorAll('.painted-village img')].every(i=>i.complete&&i.naturalWidth>0));await page.evaluate(()=>document.fonts.ready);};
  const sizes=[[1280,800],[2134,1154],[390,844],[320,568],[844,390],[568,320]].filter(size=>!process.env.CITY_TERRAIN_VIEWPORT||size.join('x')===process.env.CITY_TERRAIN_VIEWPORT);
  for(const [width,height] of sizes){
   await page.setViewportSize({width,height});await page.goto(base+'/city#city');await ready();
   assert.equal(await page.locator('.painted-building-name:visible,.painted-building-level:visible').count(),0,'The city starts without unselected name plaques');
   assert.equal(await page.locator('.painted-village-building[title]').count(),0,'Building names are not exposed by hover tooltips');
   for(const status of await page.locator('.painted-village-building:is(.is-building,.is-training) .painted-build-status').all()){
    assert(await status.isVisible(),'Ongoing work remains visible without selecting the building');
    assert.equal(await status.evaluate(node=>getComputedStyle(node.parentElement).boxShadow),'none','Unselected work keeps only the existing status badge, without an extra plaque shadow');
   }
   await page.screenshot({path:path.join(output,`city-${width}x${height}.png`)});
   for(const side of ['left','center','right']){
    const geometry=await page.evaluate(side=>{
     const scroll=document.querySelector('.painted-village-scroll'),village=document.querySelector('.painted-village'),ground=document.querySelector('.painted-village-ground'),terrain=document.querySelector('.painted-village-scene>img');
     scroll.scrollTop=scroll.scrollHeight;scroll.scrollLeft=side==='left'?0:side==='right'?scroll.scrollWidth:(scroll.scrollWidth-scroll.clientWidth)/2;
     const view=scroll.getBoundingClientRect(),image=terrain.getBoundingClientRect();
     return {background:getComputedStyle(village).backgroundImage,source:terrain.getAttribute('src'),bottomGap:view.bottom-image.bottom,groundGap:ground?image.bottom-ground.getBoundingClientRect().bottom:null,scaleX:image.width/terrain.naturalWidth,scaleY:image.height/terrain.naturalHeight,documentOverflow:document.documentElement.scrollWidth>innerWidth};
    },side);
    assert.equal(geometry.background,'none',`No fixed duplicate painting: ${width}x${height} ${side}`);
    assert.match(geometry.source,/terrain-extended\.webp$/,'Approved extended painting is used');
    assert(geometry.groundGap!==null&&geometry.groundGap>=0&&geometry.groundGap<2,'Scroll ends at the real artwork edge');
    assert(geometry.bottomGap<2,'No empty strip beneath the painting');
    assert(Math.abs(geometry.scaleX/geometry.scaleY-1)<.002,'Terrain proportions remain intact');
    assert.equal(geometry.documentOverflow,false,'No document overflow');
   }
   await page.screenshot({path:path.join(output,`bottom-${width}x${height}.png`)});
   const mine=page.locator('.painted-village-building[data-id="gold_mine"]');
   await mine.evaluate(node=>{const scroll=node.closest('.painted-village-scroll'),b=node.getBoundingClientRect(),r=scroll.getBoundingClientRect();scroll.scrollLeft+=b.left+b.width/2-r.left-r.width/2;scroll.scrollTop+=b.top+b.height/2-r.top-r.height/2;});
   await mine.tap();await page.locator('.painted-building-menu:not([hidden]) .painted-building-actions').waitFor();
   assert.equal(await mine.getAttribute('aria-pressed'),'true','Touch selects a lower building');
   assert.equal(await page.locator('.painted-building-name:visible').count(),1,'Touch reveals only the selected building name');
   assert(await mine.locator('.painted-building-label').isVisible(),'The existing plaque remains at the selected building');
   const selection=await mine.evaluate(node=>{const label=node.querySelector('.painted-building-label'),name=node.dataset.name,plaque=label.getBoundingClientRect(),actions=document.querySelector('.painted-building-actions').getBoundingClientRect();const texts=[...document.querySelectorAll('.painted-village *')].filter(el=>el.checkVisibility()&&[...el.childNodes].some(n=>n.nodeType===Node.TEXT_NODE&&n.textContent.trim()===name));return{name,nameCopies:texts.length,banners:document.querySelectorAll('.painted-building-banner').length,overlap:actions.left<plaque.right&&actions.right>plaque.left&&actions.top<plaque.bottom&&actions.bottom>plaque.top,plaque:plaque.toJSON(),actions:actions.toJSON()};});
   assert.equal(selection.nameCopies,1,'Exactly one visible building name exists across the whole village: '+JSON.stringify(selection));assert.equal(selection.banners,0,'No second title card');assert.equal(selection.overlap,false,'The existing actions never cover the sole plaque: '+JSON.stringify(selection));
   await page.screenshot({path:path.join(output,`selected-building-${width}x${height}.png`)});
   const freePoint=await page.locator('.painted-village-scroll').evaluate(scroll=>{const r=scroll.getBoundingClientRect(),controls=[...document.querySelectorAll('button')].filter(node=>node.checkVisibility()).map(node=>node.getBoundingClientRect());let best=null;for(let fy=.12;fy<.9;fy+=.08)for(let fx=.08;fx<.95;fx+=.08){const x=r.left+r.width*fx,y=r.top+r.height*fy,hit=document.elementFromPoint(x,y);if(!hit||!scroll.contains(hit)||hit.closest('button'))continue;const gap=Math.min(...controls.map(b=>Math.hypot(Math.max(b.left-x,0,x-b.right),Math.max(b.top-y,0,y-b.bottom))));if(!best||gap>best.gap)best={x,y,gap};}return best;});
   assert(freePoint,'Unoccupied terrain remains touch-reachable');await page.touchscreen.tap(freePoint.x,freePoint.y);
   await page.waitForFunction(()=>!document.querySelector('.painted-village-building[aria-pressed="true"]'));
   assert.equal(await page.locator('.painted-building-name:visible').count(),0,'A tap on free terrain clears the label');
   assert.equal(await page.locator('.painted-building-menu').isVisible(),false,'Free terrain also clears the existing actions');
   await mine.tap();
   await page.locator('.painted-building-actions [data-action="building"]').tap();await page.locator('#game-dialog[open]').waitFor();
   assert.equal(await page.locator('.painted-building-name:visible').count(),0,'The existing upgrade/info action clears the selected plaque');
   await page.screenshot({path:path.join(output,`building-${width}x${height}.png`)});
   await page.locator('#game-dialog .dialog-close:visible,#game-dialog .mobile-page-back:visible').first().tap();
   await page.locator('#navigation [data-id="world"]').tap();await page.locator('.atlas-viewport').waitFor();
   await page.locator('#navigation [data-id="city"]').tap();await ready();
   assert.equal(await page.locator('.painted-village-scene>img').count(),1,'Returning from the map retains a single terrain image');
  }
  assert.deepEqual(errors,[],'No browser errors or missing assets');
  console.log('Village terrain: lower edge and three pan positions, proportions, building touch/actions and world return passed in '+sizes.length+' viewport sizes.');
 }finally{if(browser)await browser.close();fixture.stdin.write('exit\n');await new Promise(resolve=>{if(fixture.exitCode!==null)return resolve();fixture.once('exit',resolve);setTimeout(()=>{fixture.kill();resolve();},5000);});}
})().catch(error=>{console.error(error);process.exitCode=1;});
