'use strict';
// Real app, API and WebGL scene against the disposable preview database only.
// Start: php tools/preview-feature-fixture.php --port=18964
const fs=require('fs'),path=require('path'),assert=require('assert/strict');
const {spawnSync}=require('child_process');
const {chromium}=require(process.env.PLAYWRIGHT_MODULE||'playwright');
const base=process.env.SKIN_FIXTURE_URL||'http://127.0.0.1:18964';
assert(/^http:\/\/127\.0\.0\.1:\d+$/.test(base));
const output=path.resolve(__dirname,'../artifacts/city-skin-ui');fs.mkdirSync(output,{recursive:true});
(async()=>{
 const browser=await chromium.launch({headless:true,channel:process.env.PLAYWRIGHT_CHANNEL||'chrome'});
 const page=await browser.newPage({viewport:{width:1280,height:800},serviceWorkers:'block'}),errors=[],warnings=[];let checks=0;
 page.on('pageerror',e=>errors.push(e.message));
 page.on('console',e=>{if((e.type()==='warning'||e.type()==='error')&&e.text()!=='Service Worker registration blocked by Playwright')warnings.push(e.text());});
 try{
  await page.goto(base);await page.locator('[data-mode="login"]').click();
  await page.locator('[name="username"]').fill('PreviewPlayer');await page.locator('[name="password"]').fill('PreviewFixture!2026');
  await Promise.all([page.waitForURL('**/city'),page.locator('#auth-submit').click()]);
  await page.evaluate(()=>{location.hash='city';});
  const ready=async()=>{await page.locator('#city-frame').waitFor();const frame=await (await page.locator('#city-frame').elementHandle()).contentFrame();await frame.waitForURL('**/city/3d?embed=1');await frame.waitForFunction(()=>window.conquer3D?.getState().ready);return frame;};
  let frame=await ready();
  const snapshot=()=>frame.evaluate(()=>conquer3D.getState());
  const cityState=()=>page.evaluate(async()=> (await (await fetch('/api/city3d/state')).json()).data);
  assert.equal((await cityState()).player.name,'PreviewPlayer');checks++;
  async function choose(skin,filter){
   await page.evaluate(()=>window.dispatchEvent(new CustomEvent('conquer-village-menu',{detail:{kind:'home'}})));
   await page.locator('[data-action="city-skins"]').click();
   await page.locator(`[data-action="city-skin-filter"][data-id="${filter}"]`).click();
   while(!await page.locator(`.skin-option[data-id="${skin}"]`).count())await page.getByRole('button',{name:'Nächste Skin-Seite'}).click();
   await Promise.all([page.waitForResponse(r=>r.url().endsWith('/api/kingdom/action')&&r.request().method()==='POST'),page.locator(`.skin-option[data-id="${skin}"]`).click()]);
   await frame.waitForFunction(skin=>conquer3D.getState().skin===skin,skin);
   await page.locator('#game-dialog').waitFor({state:'hidden'});
   assert.equal((await cityState()).city.city_skin,skin);checks++;
  }
  await choose('ironkeep','legendary');
  assert.equal((await snapshot()).skinEffect.rarity,'legendary');checks++;
  await page.screenshot({path:path.join(output,'ironkeep-full.png')});
  await choose('dragon','mythic');
  assert.equal((await snapshot()).skinEffect.rarity,'mythic');checks++;
  const phase=(await snapshot()).skinEffect.phase;
  await frame.waitForFunction(phase=>conquer3D.getState().skinEffect.phase>phase,phase);checks++;
  // A stale building read and messages outside the parent bridge cannot reset it.
  await frame.evaluate(()=>{
   window.dispatchEvent(new CustomEvent('conquer-city-state',{detail:{level:12,building:false,city_skin:'default'}}));
   window.dispatchEvent(new MessageEvent('message',{origin:'https://foreign.invalid',source:parent,data:{type:'conquer:preferences',city_skin:'default'}}));
   window.dispatchEvent(new MessageEvent('message',{origin:location.origin,source:window,data:{type:'conquer:preferences',city_skin:'default'}}));
  });
  assert.equal((await snapshot()).skin,'dragon');checks++;
  for(const selector of ['.realm-hud','.realm-nav','#resource-bar'])assert.equal(await frame.locator(selector).isVisible(),false);checks+=3;
  await page.screenshot({path:path.join(output,'dragon-full.png')});
  await frame.evaluate(()=>window.dispatchEvent(new CustomEvent('conquer-focus-building',{detail:{code:'castle'}})));
  for(let i=0;i<5;i++)await frame.locator('#zoomIn').click();
  await frame.evaluate(()=>window.dispatchEvent(new Event('conquer-map-tap')));
  await frame.locator('#world canvas').click({position:{x:640,y:365}});
  assert.equal((await snapshot()).selected,'keep');checks++;
  await frame.evaluate(()=>window.dispatchEvent(new Event('conquer-map-tap')));
  await frame.locator('#label-castle .building-name').click();
  assert.equal(await frame.locator('#building-command').isVisible(),true);checks++;
  await page.screenshot({path:path.join(output,'dragon-close.png')});
  // Exercise every model in the actual city, including eviction/recreation.
  const ids=await page.evaluate(()=>ConquerCastleSkins.ids);
  for(const skin of [...ids,'ironkeep','dragon']){
   await page.evaluate(skin=>document.querySelector('#city-frame').contentWindow.postMessage({type:'conquer:preferences',city_skin:skin,reduced_motion:false},location.origin),skin);
   await frame.waitForFunction(skin=>conquer3D.getState().skin===skin,skin);
   assert((await snapshot()).triangles>1000);checks++;
  }
  await page.evaluate(()=>document.querySelector('#city-frame').contentWindow.postMessage({type:'conquer:preferences',city_skin:'dragon',reduced_motion:true},location.origin));
  await frame.waitForFunction(()=>conquer3D.getState().paused);
  const frozen=(await snapshot()).skinEffect.phase;await page.waitForTimeout(150);assert.equal((await snapshot()).skinEffect.phase,frozen);checks++;
  await page.evaluate(()=>document.querySelector('#city-frame').contentWindow.postMessage({type:'conquer:preferences',city_skin:'dragon',reduced_motion:false},location.origin));
  for(const [width,height] of [[390,844],[320,568],[844,390]]){
   await page.setViewportSize({width,height});await frame.locator('#reset').click();
   await frame.evaluate(()=>window.dispatchEvent(new CustomEvent('conquer-focus-building',{detail:{code:'castle'}})));
   await frame.evaluate(()=>window.dispatchEvent(new Event('conquer-map-tap')));
   await frame.locator('#label-castle .building-name').click();
   assert.equal((await snapshot()).skin,'dragon');assert.equal(await frame.locator('#building-command').isVisible(),true);checks+=2;
   await page.screenshot({path:path.join(output,`${width}x${height}.png`)});
  }
  await page.setViewportSize({width:1280,height:800});await page.reload();frame=await ready();
  await frame.waitForFunction(()=>conquer3D.getState().skin==='dragon');checks++;
  // /city/3d currently opens the app shell too. Verify that entry route.
  await page.goto(base+'/city/3d');frame=await ready();
  await frame.waitForFunction(()=>conquer3D.getState().skin==='dragon');checks++;
  // Render the real standalone PHP view on a test-only route, with the same API.
  const root=path.resolve(__dirname,'..').replaceAll('\\','/');
  const php=`require '${root}/src/Game/World/WorldContext.php'; \\Conquer\\Game\\World\\WorldContext::bind(1); define('APP_BASE',''); $session=['username'=>'PreviewPlayer']; $_GET=[]; include '${root}/views/city3d.php';`;
  const standalone=spawnSync(process.env.PHP_BINARY||'C:/xampp/php/php.exe',['-r',php],{encoding:'utf8'});assert.equal(standalone.status,0,standalone.stderr);
  await page.route(base+'/city/3d?standalone-fixture=1',route=>route.fulfill({contentType:'text/html',body:standalone.stdout}));
  await page.goto(base+'/city/3d?standalone-fixture=1');await page.waitForFunction(()=>window.conquer3D?.getState().ready&&conquer3D.getState().skin==='dragon');checks++;
  await page.screenshot({path:path.join(output,'standalone-full.png')});
  await page.evaluate(()=>window.dispatchEvent(new CustomEvent('conquer-focus-building',{detail:{code:'castle'}})));
  for(let i=0;i<5;i++)await page.locator('#zoomIn').click();
  await page.screenshot({path:path.join(output,'standalone-close.png')});
  await page.reload();await page.waitForFunction(()=>window.conquer3D?.getState().skin==='dragon');checks++;
  assert.deepEqual(errors,[]);assert.deepEqual(warnings,[]);checks+=2;
  console.log(JSON.stringify({checks,errors,warnings,output}));
 }catch(error){
  console.error(JSON.stringify({errors,warnings,state:await page.evaluate(()=>({scene:window.conquer3D?.getState(),city:window.CONQUER_CITY_STATE,loading:document.querySelector('#loading')?.textContent,connection:document.querySelector('#connection')?.textContent}))}));
  await page.screenshot({path:path.join(output,'failure.png')});throw error;
 }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1;});
