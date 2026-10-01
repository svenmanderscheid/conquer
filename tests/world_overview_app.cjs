'use strict';
require('./fixtures/browser_locale.cjs')('de'); // This suite asserts the explicit German UI.
// tools/preview-feature-fixture.php --territory --appearance --port=18964
const assert=require('assert'),fs=require('fs'),path=require('path');
const {chromium}=require(process.env.PLAYWRIGHT_MODULE||'playwright');
const base=process.env.OVERVIEW_FIXTURE_URL||'http://127.0.0.1:18964';
assert(/^http:\/\/127\.0\.0\.1:\d+$/.test(base),'Disposable localhost fixture required');
const out=path.resolve(__dirname,'../artifacts/world-overview');fs.mkdirSync(out,{recursive:true});
(async()=>{
 const browser=await chromium.launch({headless:true,channel:'chrome'}),errors=[],failures=[];
 try{
  const page=await browser.newPage({viewport:{width:1280,height:800},hasTouch:true});page.setDefaultTimeout(12000);page.on('pageerror',e=>errors.push(e.message));
  await page.goto(base+'/?zugang=login');await page.locator('[name="identifier"],[name="username"]').fill('PreviewPlayer');await page.locator('[name="password"]').fill('PreviewFixture!2026');
  await Promise.all([page.waitForURL('**/city'),page.locator('form[action$="/auth/local"] button[type="submit"]').click()]);await page.waitForFunction(()=>document.querySelector('#player-hud-name')?.textContent.includes('PreviewPlayer'));
  await Promise.all([page.waitForEvent('load'),page.evaluate(()=>ConquerLocale.setLocale('de'))]);await page.locator('#navigation [data-id="world"]').click();await page.waitForSelector('.atlas-shell.is-luxembourg');
  const panel=page.locator('#atlas-navigation-panel'),open=async()=>{if(!await panel.isVisible())await page.locator('[data-atlas="navigation"]').click();await panel.waitFor({state:'visible'});await page.waitForSelector('.wo-canton',{state:'attached'});await page.waitForFunction(()=>document.querySelector('.wo-map canvas').width>1);};
  for(const [width,height]of [[1280,800],[390,844],[320,568],[844,390],[568,320]]){
   await page.setViewportSize({width,height});await open();await page.locator('[data-overview="fit"]').click();
   assert.equal(await page.locator('.wo-canton').count(),12);assert.equal(await page.locator('.wo-map-label').count(),12);
   const before=await page.evaluate(()=>ConquerWorld.getCenter());
   await page.locator('.wo-search input').fill('Vianden');assert.equal(await page.locator('.wo-canton:visible').count(),1);await page.locator('.wo-canton:visible').click();
   assert.equal(await page.locator('.wo-selected-name').innerText(),'Vianden');assert.equal(await page.locator('.wo-map-label[data-canton="09"]').getAttribute('aria-pressed'),'true');assert.deepEqual(await page.evaluate(()=>ConquerWorld.getCenter()),before,'Selection must not move the live map');
   await page.locator('.wo-search input').fill('zzzz');assert(await page.locator('.wo-empty').isVisible());await page.locator('.wo-search input').fill('');await page.locator('.wo-search input').press('Escape');assert(await panel.isVisible(),'Escape dismisses canton suggestions first');
   await page.locator('[data-overview="zoom-in"]').click();await page.waitForFunction(()=>!document.querySelector('[data-overview="zoom-out"]').disabled);await page.locator('[data-overview="fit"]').click();await page.waitForFunction(()=>document.querySelector('[data-overview="zoom-out"]').disabled);
   await page.locator('.wo-communes').check();await page.locator('.wo-communes').uncheck();await page.locator('[data-overview="ownership"]').click();assert.equal(await page.locator('[data-overview="ownership"]').getAttribute('aria-pressed'),'true');await page.locator('[data-overview="cantons"]').click();
   await page.waitForTimeout(250);await page.screenshot({path:path.join(out,`${width}x${height}.png`)});
   const result=await panel.evaluate(p=>{
    const reachable=selector=>{const e=p.querySelector(selector),r=e.getBoundingClientRect();return {selector,x:r.x,y:r.y,w:r.width,h:r.height,okay:r.x>=0&&r.y>=0&&r.right<=innerWidth+1&&r.bottom<=innerHeight+1&&e.contains(document.elementFromPoint(r.x+r.width/2,r.y+r.height/2))};};
    return {controls:['.map-overlay-close','.wo-search input','[data-overview="go"]','[data-overview="fit"]','[data-overview="zoom-in"]','[data-overview="ownership"]','.wo-home','.wo-coordinates summary'].map(reachable),overflow:p.scrollWidth>p.clientWidth+1||p.scrollHeight>p.clientHeight+1,pageOverflow:document.documentElement.scrollWidth>innerWidth,labels:[...p.querySelectorAll('.wo-map-label:not([hidden])')].map(b=>({id:b.dataset.canton,rect:b.getBoundingClientRect().toJSON()}))};
   });
   failures.push(...result.controls.filter(c=>!c.okay).map(c=>({width,height,...c})));if(result.overflow||result.pageOverflow)failures.push({width,height,overflow:result.overflow,pageOverflow:result.pageOverflow});
   assert.equal(result.labels.length,12,'All cantons have a visible map label');
   for(let i=0;i<result.labels.length;i++)for(let j=i+1;j<result.labels.length;j++){const a=result.labels[i],b=result.labels[j];if(a.rect.left<b.rect.right&&a.rect.right>b.rect.left&&a.rect.top<b.rect.bottom&&a.rect.bottom>b.rect.top)failures.push({width,height,overlap:[a.id,b.id]});}
   fs.writeFileSync(path.join(out,`${width}x${height}.json`),JSON.stringify(result,null,2));
   await page.locator('[data-overview="go"]').click();await panel.waitFor({state:'hidden'});const center=await page.evaluate(()=>ConquerWorld.getCenter());assert.equal(center.x,410);assert.equal(center.y,378);
   await open();await page.goBack();await panel.waitFor({state:'hidden'});assert.equal(await page.evaluate(()=>location.hash),'#world','Back closes the atlas before leaving the world');
   await open();await page.locator('.wo-home').click();await panel.waitFor({state:'hidden'});
   console.log(`PASS ${width}x${height}: search, selection, map zoom, layers, jump, history, home`);
  }
  await open();await page.locator('.wo-coordinates summary').click();await page.locator('.atlas-jump [name=x]').fill('477');await page.locator('.atlas-jump [name=y]').fill('669');await page.locator('.atlas-jump [type=submit]').click();await panel.waitFor({state:'hidden'});assert.equal((await page.evaluate(()=>ConquerWorld.getCenter())).x,477);
  await open();await page.locator('.map-overlay-close').click();await panel.waitFor({state:'hidden'});
  await page.setViewportSize({width:390,height:844});await open();
  await page.locator('.wo-map-label[data-canton="07"]').tap();assert.equal(await page.locator('.wo-selected-name').textContent(),'Redange','Touch label selects the correct canton');
  const mapImage=()=>page.locator('.wo-map canvas').evaluate(c=>c.toDataURL());
  const beforeZoom=await mapImage(),mapBox=await page.locator('.wo-map').boundingBox(),centerBefore=await page.evaluate(()=>ConquerWorld.getCenter());
  await page.locator('[data-overview="zoom-in"]').click();await page.waitForTimeout(100);assert.notEqual(await mapImage(),beforeZoom,'Overview zoom really redraws the map');
  const beforePan=await mapImage();await page.mouse.move(mapBox.x+30,mapBox.y+80);await page.mouse.down();await page.mouse.move(mapBox.x+65,mapBox.y+110,{steps:5});await page.mouse.up();await page.waitForTimeout(100);assert.notEqual(await mapImage(),beforePan,'Dragging pans the atlas');assert.deepEqual(await page.evaluate(()=>ConquerWorld.getCenter()),centerBefore,'Atlas gestures leave world camera unchanged');
  await page.locator('[data-overview="fit"]').click();await page.waitForTimeout(100);
  const touch=await page.context().newCDPSession(page),x=mapBox.x+mapBox.width/2,y=mapBox.y+mapBox.height/2;
  await touch.send('Input.dispatchTouchEvent',{type:'touchStart',touchPoints:[{x:x-20,y,id:1},{x:x+20,y,id:2}]});
  await touch.send('Input.dispatchTouchEvent',{type:'touchMove',touchPoints:[{x:x-50,y,id:1},{x:x+50,y,id:2}]});await touch.send('Input.dispatchTouchEvent',{type:'touchEnd',touchPoints:[]});await page.waitForTimeout(100);assert(await page.locator('[data-overview="zoom-out"]').isEnabled(),'Two-finger zoom works');await page.locator('[data-overview="fit"]').click();
  assert.equal(await page.locator('.wo-selected-name').textContent(),'Redange','Gestures do not change canton selection');
  // Changing language reloads the main app by design.
  for(const locale of ['en','fr','de']){await Promise.all([page.waitForEvent('load'),page.evaluate(l=>ConquerLocale.setLocale(l),locale)]);await open();await page.locator('.wo-map-label[data-canton="07"]').tap();assert(!/overview\./.test(await panel.innerText()),'No untranslated keys');assert.equal(await page.locator('.wo-selected-name').textContent(),'Redange');await page.screenshot({path:path.join(out,`390x844-${locale}.png`)});}
  await page.locator('[data-overview="territories"]').click();await panel.waitFor({state:'hidden'});await page.waitForSelector('.territory-shell');await page.locator('[data-action="territory-close"]').click();
  // The same app still opens city buildings after leaving the atlas.
  await page.locator('#navigation [data-id="city"]').click();await page.waitForFunction(()=>!document.querySelector('#scene-transition')?.classList.contains('is-active'));
  for(const [width,height]of [[1280,800],[390,844],[844,390]]){
   await page.setViewportSize({width,height});await page.screenshot({path:path.join(out,`city-${width}x${height}.png`)});
   const castle=page.locator('.painted-village-building[data-id="castle"]');await castle.evaluate(e=>e.scrollIntoView({block:'center',inline:'center'}));
   const point=await castle.evaluate(e=>{const r=e.getBoundingClientRect();for(const y of [.5,.8,.9,.3,.1])for(const x of [.5,.3,.7,.1,.9])if(e.contains(document.elementFromPoint(r.left+r.width*x,r.top+r.height*y)))return{x:r.width*x,y:r.height*y};return null;});assert(point,'Castle remains touch accessible');await castle.click({position:point});await page.waitForSelector('.painted-building-actions');await page.screenshot({path:path.join(out,`city-action-${width}x${height}.png`)});await page.locator('.painted-selection-close').click();
  }
  assert.deepEqual(errors,[],'Browser errors');assert.deepEqual(failures,[],'Reachable controls and no clipping');
  console.log('PASS world overview in the actual app. Screenshots: '+out);
 }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1;});
