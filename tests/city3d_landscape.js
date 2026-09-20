'use strict';
// Pure geometry and input checks; optional --browser renders an isolated fixture.
// No account, game API, saved state or existing browser tab is touched.
const assert=require('assert'),fs=require('fs'),path=require('path'),os=require('os'),http=require('http');
const {pathToFileURL}=require('url');
const root=path.resolve(__dirname,'..'),asset=path.join(root,'assets/city3d');
// Geometry checks do not render the material's painted canvas.
global.document={createElement:()=>({getContext:()=>({fillRect(){},beginPath(){},ellipse(){},fill(){}})})};
let checks=0;const check=(label,run)=>{run();checks++;console.log('PASS '+label);};
(async()=>{
 const T=await import(pathToFileURL(path.join(asset,'vendor/three.module.js')));
 const {groundPan,isTapGesture}=await import(pathToFileURL(path.join(asset,'camera-gestures.js')));
 const {buildVillageLandscape}=await import(pathToFileURL(path.join(asset,'village-landscape.js')));
 const {enrichVillage,createVillageSkins}=await import(pathToFileURL(path.join(asset,'village-details.js')));
 const {storybookMaterials:M}=await import(pathToFileURL(path.join(asset,'storybook-style.js'))+'?v=storybook1');
 const input={span:21,zoom:.72,width:390};
 check('touch pan is 38% stronger than direct ground projection and stronger than mouse',()=>{
  const touch=groundPan(0,100,{...input,touch:true}),mouse=groundPan(0,100,input);
  assert(Math.hypot(touch.x,touch.z)>Math.hypot(mouse.x,mouse.z)*1.27);
  const former=Math.hypot(.5,.8)*21/.72/390*100;assert(Math.hypot(touch.x,touch.z)>former*2);
 });
 check('camera pan reverses symmetrically and adapts to zoom and viewport width',()=>{
  const a=groundPan(70,40,input),b=groundPan(-70,-40,input),zoomed=groundPan(70,40,{...input,zoom:1.44});
  assert(Math.abs(a.x+b.x)<1e-9&&Math.abs(a.z+b.z)<1e-9);assert(Math.abs(zoomed.x-a.x/2)<1e-9);
 });
 check('tap jitter remains accepted; drags, pinches and cancellations cannot become taps',()=>{
  assert(isTapGesture({distance:0,pointers:1}));assert(isTapGesture({distance:7,pointers:1}));
  assert(!isTapGesture({distance:8,pointers:1}));assert(!isTapGesture({distance:0,pointers:2}));assert(!isTapGesture({distance:0,pointers:1,cancelled:true}));
 });
 const scene=new T.Scene(),landscape=buildVillageLandscape({scene});
 check('scenery preserves playable space and batches repeated shoreline details',()=>{
  assert.equal(landscape.root.userData.layout.sceneryOnly,true);
  const batches=[];landscape.root.traverse(object=>{if(object.isInstancedMesh)batches.push(object);});
  assert(batches.length>0&&batches.length<24);
  assert(landscape.root.userData.layout.southBridge.width>=6);
  assert(landscape.root.userData.layout.outerWater[0]>landscape.root.userData.layout.innerWater[0]);
  const before=scene.children.length;landscape.update(0);landscape.update(10);assert.equal(scene.children.length,before);
 });
 check('generated terrain, bridge and instance coordinates stay finite',()=>{
  landscape.root.traverse(object=>{if(object.geometry?.attributes.position)assert([...object.geometry.attributes.position.array].every(Number.isFinite));if(object.isInstancedMesh)assert([...object.instanceMatrix.array].every(Number.isFinite));});
 });
 const codes=['castle','academy','barrack','lumber_camp','hospital','storage','treasure_house','hall_of_alliance','trading_post','farm','quarry','gold_mine','wall','watch_tower'];
 const buildings=codes.map(code=>{const building=new T.Group();building.userData.building=code;scene.add(building);building.add(new T.Mesh(new T.BoxGeometry(1,1,1),M.blue));return[code,building];});
 const details=enrichVillage({scene,buildings});
 check('courtyards and workshops add real geometry while preserving all fourteen building identities',()=>{
  assert(details.parts>0);assert.equal(details.homes,1);assert(details.batches<250);
  assert.deepEqual(buildings.map(([code,g])=>g.userData.building),codes);assert(buildings.find(([code])=>code==='academy')[1].children.length>1);
 });
 const original=M.blue.color.getHex(),unrelated=new T.Mesh(new T.BoxGeometry(1,1,1),M.blue);scene.add(unrelated);
 const skins=createVillageSkins(buildings);
 check('jadecourt and sapphire skins change building clones without recolouring shared terrain or unrelated objects',()=>{
  assert(skins.apply('jadecourt'));assert.notEqual(buildings[0][1].children[0].material.color.getHex(),original);
  assert.equal(M.blue.color.getHex(),original);assert.equal(unrelated.material.color.getHex(),original);
  const forest=buildings[0][1].children[0].material.color.getHex();assert(skins.apply('sapphire'));assert.notEqual(buildings[0][1].children[0].material.color.getHex(),forest);
  assert(skins.apply('default'));assert.equal(buildings[0][1].children[0].material.color.getHex(),original);assert(!skins.apply('untrusted-skin'));
 });
 if(process.argv.includes('--browser')){
  let playwright;try{playwright=require(process.env.PLAYWRIGHT_MODULE||'playwright');}catch{playwright=require('C:/Users/svenm/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright');}
  const output=fs.mkdtempSync(path.join(os.tmpdir(),'conquer-village-'));
  const fixture=`<!doctype html><html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="stylesheet" href="/assets/city3d/style.css"><link rel="stylesheet" href="/assets/city3d/play.css"><link rel="stylesheet" href="/assets/city3d/hud.css"><link rel="stylesheet" href="/assets/city3d/embed.css"><body class="play-city embedded-city"><main id="world"></main><div id="loading">Laden …</div><div id="resource-bar"></div><div class="controls"><button id="zoomIn">+</button><button id="zoomOut">−</button><button id="reset">⌂</button><button id="pause">Ⅱ</button><button id="villageMenu">♛</button></div><section hidden><div id="name"></div><div id="description"></div><button id="detail"></button><button id="cityMode"></button><button id="metricsToggle"></button><div id="metrics"></div></section><div id="building-command" hidden><div class="quick-actions"></div></div><div id="upgrade-dialog" hidden></div><div id="building-labels">${codes.map(code=>`<article class="building-label" id="label-${code}"><button class="building-name">${code}</button></article>`).join('')}</div><script>window.CONQUER_PLAY={base:'',embedded:true};window.selectedEvents=0;window.addEventListener('conquer-building-select',()=>selectedEvents++);window.mapTapEvents=0;window.addEventListener('conquer-map-tap',()=>mapTapEvents++);</script><script type="module" src="/assets/city3d/scene.js"></script></body></html>`;
  const server=http.createServer((req,res)=>{
   const pathname=new URL(req.url,'http://localhost').pathname;if(pathname==='/'){res.setHeader('Content-Type','text/html');res.end(fixture);return;}
   const target=path.resolve(root,'.'+decodeURIComponent(pathname));if(!target.startsWith(asset+path.sep)){res.writeHead(404);res.end();return;}
   try{res.setHeader('Content-Type',target.endsWith('.js')?'text/javascript':target.endsWith('.css')?'text/css':'application/octet-stream');res.end(fs.readFileSync(target));}catch{res.writeHead(404);res.end();}
  });
  await new Promise(resolve=>server.listen(0,'127.0.0.1',resolve));let browser;
  try{
   browser=await playwright.chromium.launch({headless:true,channel:'chrome'});const context=await browser.newContext({viewport:{width:390,height:844},hasTouch:true,isMobile:true,deviceScaleFactor:1});const page=await context.newPage(),errors=[];
   page.on('pageerror',error=>errors.push(error.message));await page.goto('http://127.0.0.1:'+server.address().port+'/');await page.waitForFunction(()=>window.conquer3D?.getState().ready,{},{timeout:60000});
   const nextFrame=async()=>{const frame=await page.evaluate(()=>conquer3D.getState().renderFrame);await page.waitForFunction(previous=>conquer3D.getState().renderFrame>previous,frame);};
   let state=await page.evaluate(()=>conquer3D.getState());check('actual 3D scene loads landscape, complete village detail and skins without a runtime error',()=>{assert.deepEqual(errors,[]);assert(state.landscape?.trees>=200);assert(state.villageDetails?.parts>800);assert(state.skin.materials>0);});
   await page.screenshot({path:path.join(output,'village-close-mobile.png')});
   const cdp=await context.newCDPSession(page),touch=async(type,points)=>cdp.send('Input.dispatchTouchEvent',{type,touchPoints:points.map(([x,y,id=1])=>({x,y,id,radiusX:2,radiusY:2,force:1}))});
   const before=await page.evaluate(()=>({state:conquer3D.getState(),events:selectedEvents+mapTapEvents}));
   assert.equal(await page.evaluate(()=>document.elementFromPoint(35,455).tagName),'CANVAS');
   await touch('touchStart',[[35,455]]);for(let i=1;i<=6;i++)await touch('touchMove',[[35,455-i*15]]);await touch('touchEnd',[]);
   const after=await page.evaluate(()=>({state:conquer3D.getState(),events:selectedEvents+mapTapEvents}));
   check('real touch swipe moves the camera substantially without opening a building',()=>{assert(Math.hypot(after.state.target.x-before.state.target.x,after.state.target.z-before.state.target.z)>14);assert.equal(after.events,before.events);});
   await page.evaluate(()=>window.dispatchEvent(new CustomEvent('conquer-focus-building',{detail:{code:'castle'}})));await nextFrame();
   const tapsBefore=await page.evaluate(()=>selectedEvents);await page.touchscreen.tap(195,373);const tapsAfter=await page.evaluate(()=>selectedEvents);
   check('a real single touch still selects the castle after camera movement',()=>assert(tapsAfter>tapsBefore));
   await touch('touchStart',[[150,390,1],[240,390,2]]);await touch('touchMove',[[120,390,1],[270,390,2]]);await touch('touchEnd',[]);
   const pinchState=await page.evaluate(()=>({state:conquer3D.getState(),events:selectedEvents}));check('two-finger pinch zooms without generating an extra tap',()=>{assert(pinchState.state.zoom>.95);assert.equal(pinchState.events,tapsAfter);});
   await page.evaluate(()=>{window.fixtureLabelClicks=0;document.querySelectorAll('.building-name').forEach(button=>button.addEventListener('click',()=>fixtureLabelClicks++));window.dispatchEvent(new CustomEvent('conquer-focus-building',{detail:{code:'castle'}}));});
   await nextFrame();let labelBox=await page.locator('#label-castle .building-name').boundingBox();
   const labelBefore=await page.evaluate(()=>conquer3D.getState().target);
   const labelX=labelBox.x+labelBox.width/2,labelY=labelBox.y+labelBox.height/2;
   await touch('touchStart',[[labelX,labelY]]);for(let i=1;i<=4;i++)await touch('touchMove',[[labelX,labelY+i*15]]);await touch('touchEnd',[]);
   const labelAfter=await page.evaluate(()=>({target:conquer3D.getState().target,clicks:fixtureLabelClicks}));
   check('swipes starting on building labels pan instead of silently blocking or opening menus',()=>{assert(Math.hypot(labelAfter.target.x-labelBefore.x,labelAfter.target.z-labelBefore.z)>5);assert.equal(labelAfter.clicks,0);});
   await nextFrame();labelBox=await page.locator('#label-castle .building-name').boundingBox();await page.touchscreen.tap(labelBox.x+labelBox.width/2,labelBox.y+labelBox.height/2);
   await page.waitForFunction(()=>fixtureLabelClicks===1,null,{timeout:5000});const labelClicks=await page.evaluate(()=>fixtureLabelClicks);check('ordinary label taps still trigger exactly one building action after swiping',()=>assert.equal(labelClicks,1));
   await page.evaluate(()=>window.postMessage({type:'conquer:preferences',city_skin:'sapphire'},location.origin));await page.waitForFunction(()=>conquer3D.getState().skin.skin==='sapphire');
   const appliedSkin=await page.evaluate(()=>conquer3D.getState().skin.skin);check('same-origin parent preferences apply the persisted cosmetic selection',()=>assert.equal(appliedSkin,'sapphire'));
   const model=await page.evaluate(()=>conquer3D.getState().skin.castle);check('persisted skin changes the castle geometry, not just the roof color',()=>assert.equal(model,'sapphire'));
   await page.evaluate(()=>window.dispatchEvent(new CustomEvent('conquer-focus-building',{detail:{code:'castle'}})));await nextFrame();await page.screenshot({path:path.join(output,'castle-sapphire-mobile.png')});
   await page.evaluate(()=>{window.dispatchEvent(new MessageEvent('message',{origin:'https://wrong.invalid',source:window,data:{type:'conquer:preferences',city_skin:'jadecourt'}}));window.dispatchEvent(new MessageEvent('message',{origin:location.origin,source:null,data:{type:'conquer:preferences',city_skin:'jadecourt'}}));});
   const protectedSkin=await page.evaluate(()=>conquer3D.getState().skin.skin);check('untrusted origins and non-parent message sources cannot change village cosmetics',()=>assert.equal(protectedSkin,'sapphire'));
   await page.evaluate(()=>{window.fixtureMenuMessages=0;window.addEventListener('message',event=>{if(event.data?.type==='conquer:village-menu'&&event.origin===location.origin)fixtureMenuMessages++;});});
   await page.locator('#villageMenu').click();await page.waitForFunction(()=>fixtureMenuMessages===1);const menuMessages=await page.evaluate(()=>fixtureMenuMessages);check('the visible kingdom crown emits exactly one same-origin village-menu request',()=>assert.equal(menuMessages,1));
   await page.evaluate(()=>window.postMessage({type:'conquer:visibility',visible:false},location.origin));await page.waitForFunction(()=>conquer3D.getState().paused);
   const frozen=await page.evaluate(()=>conquer3D.getState().renderFrame);await page.waitForTimeout(150);const hiddenFrame=await page.evaluate(()=>conquer3D.getState().renderFrame);
   check('a hidden village stops submitting WebGL frames instead of competing with the world map',()=>assert.equal(hiddenFrame,frozen));
   await page.evaluate(()=>window.postMessage({type:'conquer:visibility',visible:true},location.origin));await page.waitForFunction(frame=>conquer3D.getState().renderFrame>frame,frozen);
   await page.locator('#reset').click();await page.screenshot({path:path.join(output,'village-overview-mobile.png')});
   await page.evaluate(()=>window.postMessage({type:'conquer:preferences',city_skin:'jadecourt'},location.origin));await page.waitForFunction(()=>conquer3D.getState().skin.castle==='jadecourt');await page.evaluate(()=>window.dispatchEvent(new CustomEvent('conquer-focus-building',{detail:{code:'castle'}})));await nextFrame();await page.screenshot({path:path.join(output,'castle-forest-mobile.png')});
   await page.evaluate(()=>window.postMessage({type:'conquer:preferences',city_skin:'phoenix',reduced_motion:false},location.origin));await page.waitForFunction(()=>conquer3D.getState().skin.castle==='phoenix');await nextFrame();
   for(const skin of ['phoenix','astral','leviathan','yggdrasil','tempest','eclipse','dragon']){
    await page.evaluate(skin=>window.postMessage({type:'conquer:preferences',city_skin:skin,reduced_motion:false},location.origin),skin);await page.waitForFunction(skin=>conquer3D.getState().skin.castle===skin,skin);await nextFrame();
    const magic=await page.evaluate(()=>conquer3D.getState().skin.effect);check(skin+' renders its own model with animated runes and glow',()=>{assert.equal(magic.rarity,'mythic');assert.equal(magic.runes,12);assert(magic.glow>0);});
   }
   let fx=await page.evaluate(()=>conquer3D.getState().skin.effect);check('mythic village skin displays its two aura rings and motes',()=>{assert.equal(fx.rarity,'mythic');assert.equal(fx.rings,2);assert(fx.particles>=20);});
   await page.screenshot({path:path.join(output,'castle-eclipse-aura-mobile.png')});
   await page.evaluate(()=>window.postMessage({type:'conquer:preferences',reduced_motion:true},location.origin));await page.waitForFunction(()=>conquer3D.getState().paused);
   const stillPhase=await page.evaluate(()=>conquer3D.getState().skin.effect.phase);await page.waitForTimeout(120);
   const stoppedPhase=await page.evaluate(()=>conquer3D.getState().skin.effect.phase);check('reduced-motion preference freezes castle magic',()=>assert.equal(stillPhase,stoppedPhase));
   await page.evaluate(()=>window.postMessage({type:'conquer:preferences',city_skin:'clockwork',reduced_motion:false},location.origin));await page.waitForFunction(()=>conquer3D.getState().skin.castle==='clockwork'&&!conquer3D.getState().paused);await nextFrame();
   const spellStart=await page.evaluate(()=>conquer3D.getState().skin.effect.phase);await nextFrame();const spellEnd=await page.evaluate(()=>conquer3D.getState().skin.effect.phase);check('legendary ornament animation resumes in the village',()=>assert(spellEnd>spellStart));
   await page.setViewportSize({width:1280,height:800});await page.locator('#reset').click();await nextFrame();await page.screenshot({path:path.join(output,'village-overview-desktop.png')});
   await page.evaluate(()=>window.dispatchEvent(new CustomEvent('conquer-focus-building',{detail:{code:'academy'}})));await page.screenshot({path:path.join(output,'academy-detail.png')});
   console.log('Screenshots: '+output);console.log('Rendered metrics: '+JSON.stringify(await page.evaluate(()=>conquer3D.getState())));
   check('all interaction and rendering paths remain free of uncaught errors',()=>assert.deepEqual(errors,[]));
  }finally{if(browser)await browser.close();await new Promise(resolve=>server.close(resolve));}
 }
 console.log('ALL '+checks+' VILLAGE CHECKS PASSED');
})().catch(error=>{console.error(error);process.exitCode=1});
