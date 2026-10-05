'use strict';
// Real shared app and world selection against a disposable legacy-world fixture.
const assert=require('assert/strict'),path=require('path'),fs=require('fs'),net=require('net'),{spawn}=require('child_process');
const {chromium}=require(process.env.PLAYWRIGHT_MODULE||'playwright');
const root=path.resolve(__dirname,'..'),output=path.join(root,'artifacts','vip-worlds');
(async()=>{
 const server=net.createServer();await new Promise(r=>server.listen(0,'127.0.0.1',r));const port=server.address().port;await new Promise(r=>server.close(r));
 const base='http://127.0.0.1:'+port;
 const fixture=spawn(process.env.PHP_BINARY||'C:/xampp/php/php.exe',['tools/preview-feature-fixture.php','--port='+port,'--vip-worlds'],{cwd:root,stdio:['pipe','pipe','pipe'],windowsHide:true});
 let log='',browser;fixture.stdout.on('data',d=>log+=d);fixture.stderr.on('data',d=>log+=d);
 try{
  await new Promise((resolve,reject)=>{const timer=setTimeout(()=>{clearInterval(poll);reject(Error(log||'Fixture timeout'));},60000),poll=setInterval(()=>{if(log.includes('Synthetic preview ready')){clearTimeout(timer);clearInterval(poll);resolve();}else if(fixture.exitCode!==null){clearTimeout(timer);clearInterval(poll);reject(Error(log));}},100);});
  browser=await chromium.launch({headless:true,executablePath:process.env.BROWSER_EXECUTABLE_PATH||'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe'});
  fs.mkdirSync(output,{recursive:true});
  const registrationContext=await browser.newContext({viewport:{width:390,height:844},hasTouch:true}),registration=await registrationContext.newPage();
  await registration.goto(base+'/?mode=register');assert.equal(await registration.locator('[name=world_id]').inputValue(),'1');
  await registration.goto(base+'/?mode=register&world_id=2');assert.equal(await registration.locator('[name=world_id]').inputValue(),'2');
  for(const locale of ['en','de','fr']){
   if(locale!=='en')await registration.evaluate(value=>ConquerLocale.setLocale(value),locale);
   const messages=JSON.parse(fs.readFileSync(path.join(root,'data/i18n',locale+'.json'),'utf8'));
   await registration.waitForFunction(({locale,text})=>ConquerLocale.locale===locale&&document.querySelector('.play-world-rule').textContent===text,{locale,text:messages['registration.village_rule']});
   for(const [width,height] of [[1280,800],[390,844],[320,568],[844,390]]){
    await registration.setViewportSize({width,height});await registration.locator('[name=world_id]').scrollIntoViewIfNeeded();
    assert.equal(await registration.locator('.play-world-rule').innerText(),messages['registration.village_rule']);
    const field=await registration.locator('[name=world_id]').boundingBox();assert(field.height>=44&&field.x>=0&&field.x+field.width<=width);
    assert(await registration.evaluate(()=>document.documentElement.scrollWidth<=innerWidth));
    await registration.screenshot({path:path.join(output,locale+'-'+width+'-registration.png')});
   }
  }
  await registrationContext.close();
  const page=await browser.newPage({viewport:{width:390,height:844},hasTouch:true}),errors=[];page.on('pageerror',e=>errors.push(e.message));
  await page.goto(base+'/?zugang=login');await page.locator('[name=identifier], [name=username]').first().fill('PreviewPlayer');await page.locator('[name=password]').fill('PreviewFixture!2026');
  await Promise.all([page.waitForURL('**/city'),page.locator('form[action$="/auth/local"] button[type=submit]').click()]);
  const ready=async()=>{await page.locator('.painted-village-scene').waitFor();await page.waitForFunction(()=>!document.querySelector('.scene-transition.is-active')&&!document.querySelector('#app-start'));};
  const close=async()=>{for(let n=0;n<5;n++){const dialog=await page.locator('#game-dialog[open]').count()?'#game-dialog[open]':'#panel-dialog[open]';const back=page.locator(dialog+' .dialog-close:visible,'+dialog+' .panel-close:visible,'+dialog+' .mobile-page-back:visible').first();if(!await back.count())break;await back.tap();await page.waitForFunction(selector=>!document.querySelector(selector),dialog);}};
  const state=async()=> (await (await page.request.get(base+'/api/kingdom/state')).json()).data;
  const worlds=async()=>{await close();await page.locator('#hud-menu').tap();await page.locator('#game-dialog [data-action=dialog-tab][data-id=worlds]').tap();await page.locator('.world-selector').waitFor();};
  await ready();await close();
  let snapshot=await state();assert.equal(snapshot.vip.world_id,1);assert.equal(snapshot.vip.points,1000);assert.equal(snapshot.vip.level,3);
  await page.locator('#hud-vip-button').tap();await page.locator('.vip-note').waitFor();
  assert.equal(await page.locator('.vip-note').innerText(),JSON.parse(fs.readFileSync(path.join(root,'data/i18n/en.json'),'utf8'))['vip.world_scope']);
  let responsePromise=page.waitForResponse(r=>r.url().endsWith('/api/kingdom/action')&&r.request().postDataJSON()?.action==='vip.daily');
  await page.locator('[data-action=vip-daily]').tap();const daily=await responsePromise;assert.equal(daily.status(),200);
  await page.locator('.vip-claimed').waitFor();
  const headers={'X-CSRF-Token':daily.request().headers()['x-csrf-token'],'X-World-ID':'1'};
  snapshot=await state();assert.equal(snapshot.vip.points,1010);assert.equal(snapshot.vip.daily_claimed,true);
  responsePromise=page.waitForResponse(r=>r.url().endsWith('/api/kingdom/action')&&r.request().postDataJSON()?.action==='inventory.use');
  await page.locator('[data-action=vip-item][data-id="10206002"]').tap();assert.equal((await responsePromise).status(),200);
  await page.locator('[data-action=vip-item][data-id="10206002"]:not([disabled])').waitFor();
  snapshot=await state();assert.equal(snapshot.vip.points,2010);assert.equal(snapshot.inventory.find(i=>i.item_code===10206002).quantity,1);
  await worlds();assert(await page.locator('[data-action=worlds-select][data-world="3"]').isDisabled());
  await Promise.all([page.waitForURL('**/city#worlds'),page.locator('[data-action=worlds-select][data-world="2"]').tap()]);await ready();
  snapshot=await state();assert.equal(snapshot.vip.world_id,2);assert.equal(snapshot.vip.points,200);assert.equal(snapshot.vip.daily_claimed,false);assert(!snapshot.inventory.some(i=>i.item_code===10206002));
  await close();await page.locator('#hud-vip-button').tap();await page.locator('.vip-note').waitFor();assert.equal(await page.locator('#hud-vip-button [data-vip-level]').textContent(),'1');
  responsePromise=page.waitForResponse(r=>r.url().endsWith('/api/kingdom/action')&&r.request().postDataJSON()?.action==='vip.daily');
  await page.locator('[data-action=vip-daily]').tap();assert.equal((await responsePromise).status(),200);await page.locator('.vip-claimed').waitFor();assert.equal((await state()).vip.points,210);
  const oldWorld=await page.request.post(base+'/api/kingdom/action',{headers,data:{action:'inventory.use',item_code:10206002,quantity:1,expected_world_id:1,operation_key:'browser-old-world-vip'}});assert.equal(oldWorld.status(),409);
  const denied=await page.request.post(base+'/api/worlds/action',{headers:{...headers,'X-World-ID':'2'},data:{action:'join',world_id:3,expected_world_id:2,request_id:'browser-no-extra-village'}});assert.equal(denied.status(),409);
  fs.mkdirSync(output,{recursive:true});
  for(const locale of ['en','de','fr']){
   if(locale!=='en'){await Promise.all([page.waitForNavigation({waitUntil:'domcontentloaded'}),page.evaluate(value=>ConquerLocale.setLocale(value),locale)]);await ready();}
   const messages=JSON.parse(fs.readFileSync(path.join(root,'data/i18n',locale+'.json'),'utf8'));
   for(const [width,height] of [[1280,800],[390,844],[320,568],[844,390]]){
    await close();await page.setViewportSize({width,height});await page.locator('#hud-vip-button').tap();await page.locator('.vip-note').waitFor();
    assert.equal(await page.locator('.vip-note').innerText(),messages['vip.world_scope']);
    const bounds=await page.locator('.vip-note').evaluate(el=>({width:el.clientWidth,scroll:el.scrollWidth,x:el.getBoundingClientRect().x,right:el.getBoundingClientRect().right}));assert(bounds.scroll<=bounds.width+1&&bounds.x>=0&&bounds.right<=width);
    await page.screenshot({path:path.join(output,locale+'-'+width+'-vip.png')});
    await worlds();assert.equal(await page.locator('.world-selector > p').innerText(),messages['worlds.account_village_rule']);assert(await page.locator('[data-action=worlds-select][data-world="3"]').isDisabled());
    const panel=await page.locator('.world-selector').evaluate(el=>({width:el.clientWidth,scroll:el.scrollWidth,scrollbar:getComputedStyle(document.querySelector('#panel-dialog .panel-content')).scrollbarWidth}));assert(panel.scroll<=panel.width+1);assert.equal(panel.scrollbar,'none');
    await page.screenshot({path:path.join(output,locale+'-'+width+'-worlds.png')});
   }
  }
  await close();await page.evaluate(()=>ConquerLocale.setLocale('en'));await ready();await worlds();
  await Promise.all([page.waitForNavigation({waitUntil:'domcontentloaded'}),page.locator('[data-action=worlds-select][data-world="1"]').tap()]);await ready();snapshot=await state();assert.equal(snapshot.vip.points,2010);assert.equal(snapshot.vip.daily_claimed,true);assert.equal(snapshot.inventory.find(i=>i.item_code===10206002).quantity,1);
  assert.deepEqual(errors,[]);console.log('PASS main-app world VIP isolation, daily claims, item use, legacy selection, denied extra village, EN/DE/FR and desktop/portrait/landscape.');
 }finally{if(browser)await browser.close();fixture.stdin.write('\n');await new Promise(resolve=>{const timer=setTimeout(()=>{fixture.kill();resolve();},10000);fixture.once('exit',()=>{clearTimeout(timer);resolve();});});}
})().catch(error=>{console.error(error);process.exitCode=1;});
