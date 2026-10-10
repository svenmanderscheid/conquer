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
  await page.waitForFunction(()=>!document.querySelector('#app-start'));
  await page.evaluate(()=>{window.ConquerStartup.ready();window.ConquerStartup.ready();});
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
  const materialFilter=()=>page.locator('[data-action=trading-crystal-category][data-id=material]');
  await materialFilter().scrollIntoViewIfNeeded();await materialFilter().tap();
  assert.equal(await page.locator('.trading-card').count(),1);
  const badgeBuy=()=>page.locator('[data-action=trading-buy][data-id="crystal-119000002"]');
  assert.equal(await badgeBuy().locator('.trading-price strong').innerText(),'10');
  assert.equal(await page.locator('.trading-card h3').innerText(),'Alliance Badge');
  const badgeBefore=await state(),badgeQuantity=s=>Number(s.inventory.find(i=>Number(i.item_code)===119000002)?.quantity||0);
  const badgeResponsePromise=page.waitForResponse(r=>r.url().endsWith('/api/kingdom/action')&&r.request().postDataJSON()?.action==='crystal.buy');
  await badgeBuy().tap();const badgeResponse=await badgeResponsePromise,badgePurchase=await badgeResponse.json();assert.equal(badgePurchase.ok,true);
  assert.equal(badgePurchase.data.message,'1 × Alliance Badge added to your inventory.');
  const badgeAfter=await state();assert.equal(badgeBefore.profile.gems-badgeAfter.profile.gems,10);assert.equal(badgeQuantity(badgeAfter)-badgeQuantity(badgeBefore),1);
  const badgeReplay=await page.request.post(base+'/api/kingdom/action',{data:badgeResponse.request().postDataJSON(),headers:{'X-CSRF-Token':headers['x-csrf-token'],'X-World-ID':'1'}});
  assert.equal((await badgeReplay.json()).data.result.duplicate,true);
  const badgeReplayState=await state();assert.equal(badgeReplayState.profile.gems,badgeAfter.profile.gems);assert.equal(badgeQuantity(badgeReplayState),badgeQuantity(badgeAfter));
  const vipFilter=()=>page.locator('[data-action=trading-crystal-category][data-id=vip_point]');
  await vipFilter().scrollIntoViewIfNeeded();await vipFilter().tap();
  const vipOffers=before.trading.crystals.offers.filter(o=>o.item.category==='vip_point');
  assert.deepEqual(vipOffers.map(o=>o.item.vip_points),[10,100,500,1000,10000]);
  assert.equal(await page.locator('.trading-card').count(),5);assert.equal(await vipFilter().getAttribute('aria-pressed'),'true');
  assert.equal(await page.locator('.trading-summary p').innerText(),'1 VIP Point costs 1 Crystal.');
  const vipOffer=vipOffers.find(o=>o.item.vip_points===1000),vipBefore=await state();
  const vipResponsePromise=page.waitForResponse(r=>r.url().endsWith('/api/kingdom/action')&&r.request().postDataJSON()?.action==='crystal.buy');
  await page.locator('[data-action=trading-buy][data-id="'+vipOffer.id+'"]').tap();const vipResponse=await vipResponsePromise,vipPurchase=await vipResponse.json();assert.equal(vipPurchase.ok,true);assert.equal(vipPurchase.data.message,'1 × 1000 VIP Points added to your inventory.');
  const vipAfter=await state(),vipQuantity=s=>Number(s.inventory.find(i=>Number(i.item_code)===vipOffer.item_code)?.quantity||0);
  assert.equal(vipBefore.profile.gems-vipAfter.profile.gems,1000);assert.equal(vipQuantity(vipAfter)-vipQuantity(vipBefore),1);assert.equal(vipAfter.vip.points,vipBefore.vip.points);
  assert.equal(await vipFilter().getAttribute('aria-pressed'),'true');assert.equal(await page.locator('.trading-card').count(),5);
  const vipPayload=vipResponse.request().postDataJSON();
  const vipReplay=await page.request.post(base+'/api/kingdom/action',{data:vipPayload,headers:{'X-CSRF-Token':headers['x-csrf-token'],'X-World-ID':'1'}});assert.equal((await vipReplay.json()).data.result.duplicate,true);
  const vipReplayState=await state();assert.equal(vipReplayState.profile.gems,vipAfter.profile.gems);assert.equal(vipQuantity(vipReplayState),vipQuantity(vipAfter));
  assert(await page.locator('[data-action=trading-buy][data-id="crystal-10206004"]').isDisabled(),'10,000-point pack requires its full Crystal price');
  fs.mkdirSync(output,{recursive:true});
  for(const locale of ['en','de','fr']){
   if(locale!=='en'){
    await Promise.all([page.waitForNavigation({waitUntil:'domcontentloaded'}),page.evaluate(value=>ConquerLocale.setLocale(value),locale)]);
    await page.locator('.painted-village-scene').waitFor();await page.waitForFunction(()=>!document.querySelector('.scene-transition.is-active'));
    if(!await page.locator('#panel-dialog[open][data-panel=market]').count())await page.locator('#navigation [data-action=shop-open]').tap();
    await page.locator('[data-action=trading-tab][data-id=crystals]').tap();
    await vipFilter().scrollIntoViewIfNeeded();await vipFilter().tap();
   }
   for(const [width,height]of [[390,844],[320,568],[568,320],[1280,800]]){
   await page.setViewportSize({width,height});await page.locator('.trading-scroll').evaluate(e=>e.scrollTop=0);
   await materialFilter().scrollIntoViewIfNeeded();await materialFilter().tap();
   assert.equal(await page.locator('.trading-card').count(),1);
   assert.equal(await page.locator('.trading-card h3').innerText(),{en:'Alliance Badge',de:'Allianzabzeichen',fr:"Insigne d’alliance"}[locale]);
   assert.equal(await materialFilter().innerText(),{en:'Materials',de:'Materialien',fr:'Matériaux'}[locale]);
   assert.equal(await badgeBuy().locator('.trading-price strong').innerText(),'10');
   assert.equal(await page.locator('.trading-card img').first().evaluate(async i=>{i.loading='eager';await i.decode();return i.naturalWidth>0;}),true);
   await badgeBuy().scrollIntoViewIfNeeded();assert(await badgeBuy().evaluate(b=>{const r=b.getBoundingClientRect(),hit=document.elementFromPoint(r.x+r.width/2,r.y+r.height/2);return r.height>=44&&(b===hit||b.contains(hit));}));
   assert(await page.locator('#panel-dialog').evaluate(d=>d.scrollWidth<=d.clientWidth+1));
   await page.screenshot({path:path.join(output,width+'x'+height+'-'+locale+'-badges-app.png')});
   await vipFilter().scrollIntoViewIfNeeded();await vipFilter().tap();
   assert.equal(await page.locator('.trading-scroll').evaluate(e=>getComputedStyle(e).scrollbarWidth),'none');
   assert.equal(await page.locator('.trading-card').count(),5);
   for(const offer of vipOffers){
    const buy=page.locator('[data-action=trading-buy][data-id="'+offer.id+'"]'),card=buy.locator('xpath=ancestor::article');
    const expected=await page.evaluate(({points})=>({name:ConquerLocale.t('crystal_shop.vip_pack',{points:ConquerLocale.formatNumber(points)}),price:ConquerLocale.formatNumber(points)}),{points:offer.item.vip_points});
    const spaces=value=>value.replace(/\s/gu,' ');
    assert.equal(spaces(await card.locator('h3').innerText()),spaces(expected.name));assert.equal(spaces(await buy.locator('.trading-price strong').innerText()),spaces(expected.price));
    assert.equal(await card.locator('img').first().evaluate(async i=>{i.loading='eager';await i.decode();return i.naturalWidth>0;}),true);
   }
   const last=page.locator('.trading-buy').last();await last.scrollIntoViewIfNeeded();assert(await last.evaluate(b=>{const r=b.getBoundingClientRect(),hit=document.elementFromPoint(r.x+r.width/2,r.y+r.height/2);return b===hit||b.contains(hit);}));
   await vipFilter().scrollIntoViewIfNeeded();assert(await vipFilter().evaluate(b=>{const r=b.getBoundingClientRect();return r.height>=44&&r.left>=0&&r.right<=innerWidth+1;}));
   assert(await page.locator('#panel-dialog').evaluate(d=>d.scrollWidth<=d.clientWidth+1));
   await page.locator('.trading-scroll').evaluate(e=>e.scrollTop=0);await page.screenshot({path:path.join(output,width+'x'+height+'-'+locale+'-vip-app.png')});
   }
  }
  assert.deepEqual(errors,[]);console.log('PASS Crystal Shop main app: VIP packs and exact prices, inventory purchase, replay, CSRF/auth, EN/DE/FR and four viewport sizes.');
 }finally{
  if(browser)await browser.close();fixture.stdin.end('\n');await new Promise(resolve=>{if(fixture.exitCode!==null)return resolve();fixture.once('exit',resolve);});
 }
})().catch(e=>{console.error(e);process.exitCode=1;});
