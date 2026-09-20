'use strict';
// Actual app and HTTP handlers on automatically disposed synthetic storage.
const assert=require('assert/strict'),fs=require('fs'),path=require('path'),net=require('net');
const {spawn}=require('child_process');
const {chromium}=require(process.env.PLAYWRIGHT_MODULE||'playwright');
const textContrast=require('./fixtures/menu_text_contrast.cjs');
const root=path.resolve(__dirname,'..'),out=path.join(root,'artifacts/alpha-final-2026-09-20/app');
fs.mkdirSync(out,{recursive:true});
(async()=>{
 const port=await new Promise(resolve=>{const s=net.createServer();s.listen(0,'127.0.0.1',()=>{const p=s.address().port;s.close(()=>resolve(p));});});
 const fixture=spawn(process.env.PHP_BINARY||'C:/xampp/php/php.exe',[root+'/tools/preview-feature-fixture.php','--port='+port,'--gathering','--army-receipts','--march-skin-world','--map-privacy'],{cwd:root,stdio:['pipe','pipe','pipe'],windowsHide:true});
 let browser,log='';const errors=[],badAssets=[],report=[];
 try{
  await new Promise((resolve,reject)=>{const timeout=setTimeout(()=>reject(Error(log||'Preview startup timed out')),60000);fixture.stdout.on('data',d=>{log+=d;if(log.includes('Synthetic preview ready')){clearTimeout(timeout);resolve();}});fixture.stderr.on('data',d=>log+=d);fixture.on('error',reject);fixture.on('exit',()=>{clearTimeout(timeout);reject(Error(log));});});
  browser=await chromium.launch({headless:true,executablePath:process.env.BROWSER_EXECUTABLE_PATH||'C:/Program Files/Google/Chrome/Application/chrome.exe'});
  const context=await browser.newContext({viewport:{width:390,height:844},hasTouch:true,deviceScaleFactor:2});
  const page=await context.newPage(),base='http://127.0.0.1:'+port;
  page.setDefaultTimeout(25000);page.on('pageerror',e=>errors.push(e.message));
  page.on('response',r=>{if(r.url().includes('/assets/')&&r.status()>=400)badAssets.push(r.url());});
  await page.goto(base+'/?zugang=login');
  await page.locator('[name=username]').fill('PreviewPlayer');await page.locator('[name=password]').fill('PreviewFixture!2026');
  await Promise.all([page.waitForURL('**/city'),page.locator('#auth-submit').click()]);
  await page.locator('#hud-menu').waitFor();
  await page.locator('#city-frame').waitFor();
  const frame=await (await page.locator('#city-frame').elementHandle()).contentFrame();
  assert(frame,'embedded city loaded');await frame.waitForFunction(()=>window.conquer3D?.getState().ready);
  const performanceState=await frame.evaluate(()=>conquer3D.getState());
  assert.equal(performanceState.frameLimit,30);assert(performanceState.pixelRatio<=1.2);
  for(const [width,height]of [[1280,800],[390,844],[320,568],[844,390],[568,320]]){
   await page.setViewportSize({width,height});
   await page.locator('#hud-menu').click();await page.locator('.menu-grid').first().waitFor();
   assert.equal(await page.locator('.menu-link .nav-symbol').count(),0,'every menu uses a defined vector icon');
   await frame.waitForFunction(()=>!conquer3D.getState().framePending);
   await page.screenshot({path:path.join(out,`menu-${width}x${height}.png`)});
   await page.keyboard.press('Escape');await frame.waitForFunction(()=>conquer3D.getState().framePending);
   for(const selector of ['.realm-hud','#resource-bar','.realm-nav','.city-footer'])assert.equal(await frame.locator(selector).isVisible(),false,'no duplicate embedded HUD '+selector);
   assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1));
   await page.screenshot({path:path.join(out,`city-${width}x${height}.png`)});
  }
  await page.setViewportSize({width:1280,height:800});
  await frame.evaluate(()=>{window.dispatchEvent(new CustomEvent('conquer-focus-building',{detail:{code:'farm'}}));document.querySelector('#zoomIn').click();document.querySelector('#zoomIn').click();});
  const a=await frame.evaluate(()=>conquer3D.getState().wheelAngle);await page.waitForTimeout(400);const b=await frame.evaluate(()=>conquer3D.getState().wheelAngle);assert.notEqual(a,b,'scene animation remains live');
  await page.screenshot({path:path.join(out,'city-close.png')});
  let getCalls=0;page.on('request',r=>{if(r.method()==='GET'&&r.url().includes('/api/'))getCalls++;});
  await page.evaluate(()=>{Object.defineProperty(document,'hidden',{configurable:true,get:()=>true});document.dispatchEvent(new Event('visibilitychange'));});
  await frame.waitForFunction(()=>!conquer3D.getState().framePending);await page.waitForTimeout(1000);
  const hiddenCalls=getCalls;await page.waitForTimeout(5600);assert.equal(getCalls,hiddenCalls,'no background API polling');
  await page.evaluate(()=>{delete document.hidden;document.dispatchEvent(new Event('visibilitychange'));});
  await page.waitForResponse(r=>r.url().includes('/api/game/state')&&r.status()===200);
  await frame.waitForFunction(()=>conquer3D.getState().framePending);
  const read=async()=>{const r=await context.request.get(base+'/api/game/state?map_x=90&map_y=65&map_radius=40');assert.equal(r.status(),200);return(await r.json()).data;};
  const state=await read(),monster=state.monsters.find(m=>m.definition?.type==='solo');assert(monster);
  assert.deepEqual(state.players.map(p=>Number(p.id)).sort(),[2,3],'map includes visible neighbours only, excluding own/hidden/foreign/outside cities');
  assert(state.players.every(p=>Object.keys(p).every(k=>['id','username','display_name','coord_x','coord_y','castle_level','city_skin','name_frame','alliance_id','alliance_tag'].includes(k))),'map does not expose private city or account values');
  const center=Math.floor(state.land_progression.map_size/2);
  const locked=await context.request.get(base+`/api/game/state?map_x=${center}&map_y=${center}&map_radius=12`);assert.equal(locked.status(),200);assert.deepEqual((await locked.json()).data.players,[],'locked land hides cities');
  const body={kind:'monsters',target_id:Number(monster.id),target_x:Number(monster.coord_x),target_y:Number(monster.coord_y),troops:{50100101:100}};
  const post=(data,csrf=state.player.csrf)=>context.request.post(base+'/api/march/preview',{data,headers:{'X-CSRF-Token':csrf,'X-World-ID':'1'}});
  assert.equal((await post(body,'wrong')).status(),403,'preview enforces CSRF');
  const preview=await post(body);assert.equal(preview.status(),200);const expected=(await preview.json()).data;
  assert.equal((await post({...body,troops:{50100101:500001}})).status(),422,'server rejects over-capacity armies');
  assert.equal((await post({...body,expected_world_id:2})).status(),409,'preview rejects stale world');
  const anonymous=await browser.newContext();assert.equal((await anonymous.request.post(base+'/api/march/preview',{data:body})).status(),401);await anonymous.close();
  await page.locator('#navigation [data-id=world]').click();await page.locator('.atlas-shell').waitFor();await page.waitForFunction(()=>!document.querySelector('.scene-transition.is-active'));
  const openMonster=async()=>{
   await page.evaluate(({x,y})=>ConquerWorld.focus(x,y),{x:body.target_x,y:body.target_y});
   const marker=page.locator(`[data-atlas-target="monsters:${monster.id}"]`);await marker.waitFor();
   const pos=await marker.evaluate(el=>{const r=el.getBoundingClientRect();for(const [fx,fy]of [[.5,.5],[.5,.9],[.1,.9],[.9,.9],[.1,.1]]){const x=r.width*fx,y=r.height*fy;if(el.contains(document.elementFromPoint(r.x+x,r.y+y)))return{x,y};}return null;});
   assert(pos,'monster reachable by touch');await marker.click({position:pos});await page.locator('[data-action=march-preview]').waitFor();
  };
  await openMonster();
  await page.locator('[data-action=march-clear]').click();await page.locator('#march-unit-50100101').fill('100');
  for(const [width,height]of [[1280,800],[390,844],[320,568],[844,390],[568,320]]){
   await page.setViewportSize({width,height});await page.locator('[data-action=march-preview]').click();
   await page.locator('.battle-preview-result table').waitFor();
   assert.deepEqual((await textContrast(page,'.battle-preview-dialog')).failures,[],'calculator text remains readable');
   assert.equal(await page.locator('.battle-preview-result tbody td').first().textContent(),new Intl.NumberFormat('de-DE').format(expected.attacker.survivors));
   const geometry=await page.locator('.battle-preview-dialog').evaluate(el=>{const r=el.getBoundingClientRect(),close=el.querySelector('[data-preview-close]').getBoundingClientRect();return{fits:r.left>=0&&r.top>=0&&r.right<=innerWidth+1&&r.bottom<=innerHeight+1,overflow:el.scrollWidth-el.clientWidth,close:close.height>=44&&close.bottom<=innerHeight};});
   assert(geometry.fits&&geometry.overflow<=1&&geometry.close);report.push({width,height,...geometry});
   await page.screenshot({path:path.join(out,`battle-${width}x${height}.png`)});
   if(width===390)await page.goBack();else await page.keyboard.press('Escape');await page.waitForFunction(()=>!history.state?.conquerBattlePreview);assert(await page.locator('#game-dialog').isVisible(),'closing calculation retains march selection');assert.equal(await page.locator('#march-unit-50100101').inputValue(),'100');
  }
  await page.keyboard.press('Escape');
  const after=await read();assert.deepEqual(after.troops,state.troops);assert.equal(after.marches.length,state.marches.length);
  const pvp=await post({...body,kind:'players',defender_troops:{50100101:100},defender_bonus:0,wall_bonus:0});assert.equal(pvp.status(),200);
  assert((await pvp.json()).data.assumptions.includes('Keine echten Gegnerdaten'));
  // Open the actual PvP planner without discovering private enemy garrison data.
  await page.setViewportSize({width:320,height:568});
  await page.evaluate(()=>ConquerWorld.focus(80,65));await page.locator('[data-atlas-target="players:2"]').waitFor();
  await page.evaluate(()=>window.dispatchEvent(new CustomEvent('conquer-village-menu',{detail:{kind:'players',id:2}})));
  await page.locator('[data-action=village-attack]').click();
  await page.locator('[data-action=march-clear]').click();await page.locator('#march-unit-50100101').fill('100');
  await page.locator('[data-action=march-preview]').click();
  assert.deepEqual((await textContrast(page,'.battle-preview-dialog')).failures,[],'PvP inputs remain readable');
  await page.locator('#preview-count-0').fill('100');await page.locator('.battle-preview-body [type=submit]').click();
  await page.locator('.battle-preview-result table').waitFor();
  assert.deepEqual((await textContrast(page,'.battle-preview-dialog')).failures,[],'PvP result remains readable');
  assert.match(await page.locator('.battle-preview-result').textContent(),/Niederlage in diesem Beispiel/);
  assert(await page.locator('.battle-preview-dialog').evaluate(el=>el.scrollWidth<=el.clientWidth+1));
  await page.screenshot({path:path.join(out,'battle-pvp-320.png')});
  await page.keyboard.press('Escape');await page.waitForFunction(()=>!history.state?.conquerBattlePreview);
  const waitContext=await browser.newContext(),waitPage=await waitContext.newPage();
  await waitPage.goto(base+'/?zugang=waitlist');
  await waitPage.locator('input[name=first_name]').fill('Alpha');await waitPage.locator('input[name=last_name]').fill('Tester');
  await waitPage.locator('[data-access-panel=waitlist] input[name=email]').fill('alpha-final@tests.invalid');
  await waitPage.locator('input[name=consent]').check();
  await waitPage.locator('[data-access-panel=waitlist] button[type=submit]').click();
  await waitPage.locator('[data-i18n="waitlist.success"]').waitFor();
  await waitContext.close();
  assert.deepEqual(errors,[]);assert.deepEqual(badAssets,[]);
  fs.writeFileSync(path.join(out,'report.json'),JSON.stringify({performanceState,report,errors,badAssets},null,2));
  console.log('PASS actual app: calculator + authenticated HTTP, 5 viewports, menu icons, mobile GPU limits, hidden polling, resume, animated overview/close-up and no duplicate HUD');
 }catch(error){console.error({errors,badAssets});if(browser)for(const c of browser.contexts())for(const [i,p]of c.pages().entries()){console.error(await p.locator('dialog').allTextContents().catch(()=>[]));await p.screenshot({path:path.join(out,`failure-${i}.png`)}).catch(()=>{});}throw error;}
 finally{if(browser)await browser.close();if(fixture.exitCode===null){fixture.stdin.end('\n');await new Promise(resolve=>fixture.once('exit',resolve));}}
})().catch(e=>{console.error(e);process.exitCode=1;});
