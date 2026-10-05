'use strict';
// Real shared app and authenticated purchases against a disposable local database.
const assert=require('assert/strict'),path=require('path'),fs=require('fs'),net=require('net'),{spawn}=require('child_process');
const {chromium}=require(process.env.PLAYWRIGHT_MODULE||'playwright');
const root=path.resolve(__dirname,'..'),output=path.join(root,'artifacts/crystal-shop');
(async()=>{
 const socket=net.createServer();await new Promise(r=>socket.listen(0,'127.0.0.1',r));const port=socket.address().port;await new Promise(r=>socket.close(r));
 const base='http://127.0.0.1:'+port;
 const fixture=spawn(process.env.PHP_BINARY||'C:/xampp/php/php.exe',['tools/preview-feature-fixture.php','--port='+port,'--crystal-shop'],{cwd:root,stdio:['pipe','pipe','pipe'],windowsHide:true});
 let log='',browser;fixture.stdout.on('data',d=>log+=d);fixture.stderr.on('data',d=>log+=d);
 try{
  await new Promise((resolve,reject)=>{const timer=setTimeout(()=>{clearInterval(poll);reject(Error(log||'Fixture timeout'));},60000),poll=setInterval(()=>{if(log.includes('Synthetic preview ready')){clearTimeout(timer);clearInterval(poll);resolve();}else if(fixture.exitCode!==null){clearTimeout(timer);clearInterval(poll);reject(Error(log));}},100);});
  browser=await chromium.launch({headless:true,executablePath:process.env.BROWSER_EXECUTABLE_PATH||'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe'});
  const page=await browser.newPage({viewport:{width:390,height:844},hasTouch:true}),errors=[];page.on('pageerror',e=>errors.push(e.message));
  await page.goto(base+'/?zugang=login');await page.locator('[name=identifier], [name=username]').fill('PreviewPlayer');await page.locator('[name=password]').fill('PreviewFixture!2026');
  await Promise.all([page.waitForURL('**/city'),page.locator('form[action$="/auth/local"] button[type=submit]').click()]);
  await page.locator('.painted-village-scene').waitFor();await page.waitForFunction(()=>!document.querySelector('.scene-transition.is-active'));
  for(let n=0;n<4;n++){const back=page.locator('dialog[open] .dialog-close:visible,dialog[open] .panel-close:visible,dialog[open] .mobile-page-back:visible').first();if(!await back.count())break;await back.tap();await page.waitForTimeout(100);}
  await page.locator('#navigation [data-action=shop-open]').tap();await page.locator('[data-action=trading-tab][data-id=crystals]').tap();
  const state=async()=> (await (await page.request.get(base+'/api/kingdom/state')).json()).data;
  const before=await state();assert(before.vip.level<=1);assert.equal(before.trading.market_level,0);assert.equal(await page.locator('.trading-vip-level').count(),0);
  const offer=before.trading.crystals.offers.find(o=>o.item_code===10201004);
  const responsePromise=page.waitForResponse(r=>r.url().endsWith('/api/kingdom/action')&&r.request().postDataJSON()?.action==='crystal.buy');
  await page.locator('[data-action=trading-buy][data-id="'+offer.id+'"]').tap();const response=await responsePromise;assert.equal(response.status(),200);const purchase=await response.json();assert.equal(purchase.ok,true);
  const after=await state();assert.equal(before.profile.gems-after.profile.gems,offer.price.amount);
  const quantity=s=>Number(s.inventory.find(i=>Number(i.item_code)===Number(offer.item_code))?.quantity||0);assert.equal(quantity(after)-quantity(before),1);
  const payload=response.request().postDataJSON(),headers=response.request().headers();
  const replay=await page.request.post(base+'/api/kingdom/action',{data:payload,headers:{'X-CSRF-Token':headers['x-csrf-token'],'X-World-ID':'1'}});assert.equal((await replay.json()).data.result.duplicate,true);
  const replayState=await state();assert.equal(replayState.profile.gems,after.profile.gems);assert.equal(quantity(replayState),quantity(after));
  const denied=await page.request.post(base+'/api/kingdom/action',{data:{...payload,request_id:'crystal-no-csrf-0001'}});assert.equal(denied.status(),403);
  const anonymous=await browser.newContext();assert.equal((await anonymous.request.post(base+'/api/kingdom/action',{data:payload})).status(),401);await anonymous.close();
  fs.mkdirSync(output,{recursive:true});
  for(const [width,height]of [[390,844],[568,320],[1280,800]]){
   await page.setViewportSize({width,height});await page.locator('.trading-scroll').evaluate(e=>e.scrollTop=0);
   assert.equal(await page.locator('.trading-scroll').evaluate(e=>getComputedStyle(e).scrollbarWidth),'none');
   const last=page.locator('.trading-buy').last();await last.scrollIntoViewIfNeeded();assert(await last.evaluate(b=>{const r=b.getBoundingClientRect(),hit=document.elementFromPoint(r.x+r.width/2,r.y+r.height/2);return b===hit||b.contains(hit);}));
   await page.locator('.trading-scroll').evaluate(e=>e.scrollTop=0);await page.screenshot({path:path.join(output,width+'x'+height+'-app.png')});
  }
  assert.deepEqual(errors,[]);console.log('PASS Crystal Shop main app: base VIP purchase without Trading Post, real currency/inventory, replay, CSRF/auth and three viewport sizes.');
 }finally{
  if(browser)await browser.close();fixture.stdin.end('\n');await new Promise(resolve=>{if(fixture.exitCode!==null)return resolve();fixture.once('exit',resolve);});
 }
})().catch(e=>{console.error(e);process.exitCode=1;});
