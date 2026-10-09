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
  const frame=()=>page.evaluate(()=>new Promise(resolve=>requestAnimationFrame(()=>requestAnimationFrame(resolve))));
  const camera=()=>page.locator('.painted-village-scroll').evaluate(scroll=>({left:scroll.scrollLeft,top:scroll.scrollTop,zoom:Number(scroll.closest('.painted-village').dataset.zoom),dragging:scroll.classList.contains('is-dragging')}));
  const gesturePoint=async(radius=0)=>{
   const point=await page.locator('.painted-village-scroll').evaluate((scroll,radius)=>{
    const r=scroll.getBoundingClientRect();
    for(let fy=.35;fy<.72;fy+=.06)for(let fx=.25;fx<.8;fx+=.08){
     const x=r.left+r.width*fx,y=r.top+r.height*fy;
     if([x-radius,x,x+radius].every(px=>{const hit=document.elementFromPoint(px,y);return hit&&scroll.contains(hit)&&!hit.closest('button');}))return{x,y};
    }
    return null;
   },radius);
   assert(point,'Unoccupied village terrain remains available for gestures');return point;
  };
  const wheel=async delta=>{const point=await gesturePoint();await page.mouse.move(point.x,point.y);await page.mouse.wheel(0,delta);await frame();};
  const touch=await page.context().newCDPSession(page);
  const sizes=[[1280,800],[2134,1154],[390,844],[320,568],[844,390],[568,320]].filter(size=>!process.env.CITY_TERRAIN_VIEWPORT||size.join('x')===process.env.CITY_TERRAIN_VIEWPORT);
  for(const [width,height] of sizes){
   await page.setViewportSize({width,height});await page.goto(base+'/city#city');await ready();
   assert.equal(await page.locator('.painted-building-name:visible,.painted-building-level:visible').count(),0,'The city starts without unselected name plaques');
   assert.equal(await page.locator('.painted-village-building[title]').count(),0,'Building names are not exposed by hover tooltips');
   for(const status of await page.locator('.painted-village-building:is(.is-building,.is-training) .painted-build-status').all()){
    assert(await status.isVisible(),'Ongoing work remains visible without selecting the building');
    assert.equal(await status.evaluate(node=>getComputedStyle(node.parentElement).boxShadow),'none','Unselected work keeps only the existing status badge, without an extra plaque shadow');
   }
   assert.equal(await page.locator('.painted-village-zoom,[data-city-zoom]').count(),0,'The city has no zoom buttons or percentage display');
   await page.locator('.painted-village-scroll').evaluate(scroll=>{scroll.scrollLeft=(scroll.scrollWidth-scroll.clientWidth)/2;scroll.scrollTop=(scroll.scrollHeight-scroll.clientHeight)/2;});
   await frame();
   const point=await gesturePoint(),initial=await camera();
   await page.mouse.move(point.x,point.y);await page.mouse.move(point.x+25,point.y+20);await frame();
   assert.deepEqual(await camera(),initial,'Moving the mouse without a pressed button cannot pan the village');
   await page.mouse.move(point.x,point.y);await page.mouse.down({button:'right'});await page.mouse.move(point.x-30,point.y-20,{steps:3});await page.mouse.up({button:'right'});await frame();
   assert.deepEqual(await camera(),initial,'The right mouse button cannot pan the village');
   await page.keyboard.press('Escape');
   await page.mouse.move(point.x,point.y);await page.mouse.down({button:'left'});await page.mouse.move(point.x-35,point.y-25,{steps:4});await page.mouse.up({button:'left'});await frame();
   const dragged=await camera();assert.equal(dragged.left,initial.left+35,'Holding the left mouse button pans horizontally');assert.equal(dragged.top,initial.top+25,'Holding the left mouse button pans vertically');assert.equal(dragged.dragging,false,'Releasing the button ends the drag');
   await page.mouse.move(point.x-60,point.y-45);await frame();assert.deepEqual(await camera(),dragged,'The village stops following the mouse when the left button is released');
   assert.equal(await page.locator('.painted-building-menu:visible').count(),0,'A drag does not select a building');
   let previousWidth=Infinity;
   for(const level of ['normal','middle','wide']){
    if(level!=='normal')await wheel(level==='middle'?90:400);
    const state=await camera();
    if(level==='normal')assert.equal(state.zoom,1,'The original city scale is retained');
    else if(level==='middle')assert(state.zoom>.8&&state.zoom<1,'The mouse wheel zooms continuously');
    else assert.equal(state.zoom,.8,'The expanded overview limit remains reachable');
    const sceneWidth=await page.locator('.painted-village-scene').evaluate(node=>node.offsetWidth);
    assert(sceneWidth<previousWidth,'Each step really reveals more of the city');previousWidth=sceneWidth;
    await page.screenshot({path:path.join(output,`city-${level}-${width}x${height}.png`)});
    for(const side of ['left','center','right']){
    const geometry=await page.evaluate(side=>{
     const scroll=document.querySelector('.painted-village-scroll'),village=document.querySelector('.painted-village'),ground=document.querySelector('.painted-village-ground'),terrain=document.querySelector('.painted-village-scene>img');
     scroll.scrollTop=scroll.scrollHeight;scroll.scrollLeft=side==='left'?0:side==='right'?scroll.scrollWidth:(scroll.scrollWidth-scroll.clientWidth)/2;
     const view=scroll.getBoundingClientRect(),image=terrain.getBoundingClientRect();
     return {background:getComputedStyle(village).backgroundImage,source:terrain.getAttribute('src'),bottomGap:view.bottom-image.bottom,leftGap:image.left-view.left,rightGap:view.right-image.right,groundGap:ground?image.bottom-ground.getBoundingClientRect().bottom:null,scaleX:image.width/terrain.naturalWidth,scaleY:image.height/terrain.naturalHeight,documentOverflow:document.documentElement.scrollWidth>innerWidth,scrollbar:getComputedStyle(scroll).scrollbarWidth};
    },side);
    assert.equal(geometry.background,'none',`No fixed duplicate painting: ${width}x${height} ${side}`);
    assert.match(geometry.source,/terrain-extended\.webp$/,'Approved extended painting is used');
    assert(geometry.groundGap!==null&&geometry.groundGap>=0&&geometry.groundGap<2,'Scroll ends at the real artwork edge');
    assert(geometry.bottomGap<2,'No empty strip beneath the painting');
    assert(geometry.leftGap<2&&geometry.rightGap<2,'Zoomed-out terrain still fills both sides of the screen');
    assert(Math.abs(geometry.scaleX/geometry.scaleY-1)<.002,'Terrain proportions remain intact');
    assert.equal(geometry.documentOverflow,false,'No document overflow');
    assert.equal(geometry.scrollbar,'none','Village scrollbars stay hidden at every zoom level');
    }
    await page.screenshot({path:path.join(output,`bottom-${level}-${width}x${height}.png`)});
   }
   await page.locator('.painted-village-scroll').evaluate(scroll=>{scroll.scrollLeft=(scroll.scrollWidth-scroll.clientWidth)/2;scroll.scrollTop=(scroll.scrollHeight-scroll.clientHeight)/2;});
   const pair=await gesturePoint(40),points=(half)=>[{id:10,x:pair.x-half,y:pair.y},{id:11,x:pair.x+half,y:pair.y}];
   await touch.send('Input.dispatchTouchEvent',{type:'touchStart',touchPoints:points(28)});
   await touch.send('Input.dispatchTouchEvent',{type:'touchMove',touchPoints:points(40)});await frame();
   assert.equal((await camera()).zoom,1,'Spreading two fingers zooms in to the original scale');
   const afterPinch=await camera();
   // A partial touchEnd names the lifted finger, leaving the other one down.
   await touch.send('Input.dispatchTouchEvent',{type:'touchEnd',touchPoints:[points(40)[0]]});
   await touch.send('Input.dispatchTouchEvent',{type:'touchMove',touchPoints:[{id:11,x:pair.x+10,y:pair.y-20}]});await frame();
   const continued=await camera();assert(continued.left!==afterPinch.left||continued.top!==afterPinch.top,'One remaining finger continues panning after a pinch: '+JSON.stringify({width,height,afterPinch,continued}));
   await touch.send('Input.dispatchTouchEvent',{type:'touchEnd',touchPoints:[]});await frame();assert.equal((await camera()).dragging,false,'Lifting both fingers clears the gesture');
   const finger=await gesturePoint(),beforeSwipe=await camera();
   await touch.send('Input.dispatchTouchEvent',{type:'touchStart',touchPoints:[{id:12,...finger}]});
   await touch.send('Input.dispatchTouchEvent',{type:'touchMove',touchPoints:[{id:12,x:finger.x-30,y:finger.y-20}]});
   await touch.send('Input.dispatchTouchEvent',{type:'touchCancel',touchPoints:[]});await frame();
   const afterSwipe=await camera();assert(afterSwipe.left!==beforeSwipe.left||afterSwipe.top!==beforeSwipe.top,'One finger pans the village');assert.equal(afterSwipe.dragging,false,'Cancelling a touch releases the drag');
   const inward=await gesturePoint(40),inwardPoints=half=>[{id:13,x:inward.x-half,y:inward.y},{id:14,x:inward.x+half,y:inward.y}];
   await touch.send('Input.dispatchTouchEvent',{type:'touchStart',touchPoints:inwardPoints(40)});
   await touch.send('Input.dispatchTouchEvent',{type:'touchMove',touchPoints:inwardPoints(20)});
   await touch.send('Input.dispatchTouchEvent',{type:'touchEnd',touchPoints:[]});await frame();
   assert.equal((await camera()).zoom,.8,'Pinching inward zooms out to the expanded overview');
   assert.equal((await camera()).dragging,false,'Pinching never leaves a stuck drag');
   assert.equal(await page.evaluate(()=>visualViewport.scale),1,'Two-finger gestures zoom the village without magnifying the app');
   assert.equal(await page.locator('.painted-building-menu:visible').count(),0,'Touch gestures never open building actions');
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
   assert.equal((await camera()).zoom,.8,'Returning from the world map preserves the village zoom');
   await page.locator('.painted-village-scroll').press('+');assert((await camera()).zoom>.8,'Keyboard zoom remains available');
   await wheel(-400);assert.equal((await camera()).zoom,1,'Scrolling up zooms back in');
   await wheel(-400);assert.equal((await camera()).zoom,1,'Further scrolling cannot exceed the original scale');
   await page.locator('.painted-village-scroll').evaluate(scroll=>scroll.scrollTop=0);await page.locator('.painted-village-scroll').press('ArrowDown');
   await page.waitForFunction(()=>document.querySelector('.painted-village-scroll').scrollTop>0);
   // Let native keyboard scrolling finish before changing the viewport.
   await page.waitForTimeout(250);
  }
  assert.deepEqual(errors,[],'No browser errors or missing assets');
  console.log('Village terrain: wheel and two-finger zoom, one-finger pan, held-left-button pan, release/cancel recovery, no zoom toolbar, hidden scrollbars, keyboard access, lower/side edges, building actions and zoom persistence passed in '+sizes.length+' viewport sizes.');
 }finally{if(browser)await browser.close();fixture.stdin.write('exit\n');await new Promise(resolve=>{if(fixture.exitCode!==null)return resolve();fixture.once('exit',resolve);setTimeout(()=>{fixture.kill();resolve();},5000);});}
})().catch(error=>{console.error(error);process.exitCode=1;});
