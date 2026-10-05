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
  for(const [width,height] of [[1280,800],[2134,1154],[390,844],[320,568],[844,390],[568,320]]){
   await page.setViewportSize({width,height});await page.goto(base+'/city#city');await ready();
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
   await mine.tap();await page.locator('.painted-building-banner').waitFor();
   assert.equal(await mine.getAttribute('aria-pressed'),'true','Touch selects a lower building');
   await page.locator('.painted-building-actions [data-action="building"]').tap();await page.locator('#game-dialog[open]').waitFor();
   await page.screenshot({path:path.join(output,`building-${width}x${height}.png`)});
   await page.locator('#game-dialog .dialog-close:visible,#game-dialog .mobile-page-back:visible').first().tap();
   await page.locator('#navigation [data-id="world"]').tap();await page.locator('.atlas-viewport').waitFor();
   await page.locator('#navigation [data-id="city"]').tap();await ready();
   assert.equal(await page.locator('.painted-village-scene>img').count(),1,'Returning from the map retains a single terrain image');
  }
  assert.deepEqual(errors,[],'No browser errors or missing assets');
  console.log('Village terrain: lower edge and three pan positions, proportions, building touch/actions and world return passed in six viewport sizes.');
 }finally{if(browser)await browser.close();fixture.stdin.write('exit\n');await new Promise(resolve=>{if(fixture.exitCode!==null)return resolve();fixture.once('exit',resolve);setTimeout(()=>{fixture.kill();resolve();},5000);});}
})().catch(error=>{console.error(error);process.exitCode=1;});
