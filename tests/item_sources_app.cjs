'use strict';
// Actual app in an isolated preview. All feature interactions are read-only.
const fs=require('fs'),path=require('path'),assert=require('assert/strict'),net=require('net');
const {spawn}=require('child_process');
const {chromium}=require(process.env.PLAYWRIGHT_MODULE||'playwright');
const root=path.resolve(__dirname,'..'),out=path.join(root,'artifacts/item-sources');fs.mkdirSync(out,{recursive:true});
(async()=>{
 let child,browser,log='',base=process.env.ITEM_SOURCES_URL;
 try{
  if(!base){
   const port=await new Promise(resolve=>{const s=net.createServer();s.listen(0,'127.0.0.1',()=>{const p=s.address().port;s.close(()=>resolve(p));});});
   base='http://127.0.0.1:'+port;
   child=spawn(process.env.PHP_BINARY||'C:/xampp/php/php.exe',['tools/preview-feature-fixture.php','--port='+port,'--hud','--map-search','--boss-skills','--appearance'],{cwd:root,stdio:['pipe','pipe','pipe'],windowsHide:true});
   await new Promise((resolve,reject)=>{const timer=setTimeout(()=>reject(Error(log||'Fixture timeout')),60000);child.stdout.on('data',c=>{log+=c;if(log.includes('Synthetic preview ready')){clearTimeout(timer);resolve();}});child.stderr.on('data',c=>log+=c);child.on('exit',code=>{clearTimeout(timer);reject(Error('Fixture '+code+': '+log));});});
  }
  assert(/^http:\/\/127\.0\.0\.1:\d+$/.test(base),'Disposable preview required');
  browser=await chromium.launch({headless:true,channel:'chrome'});
  const page=await browser.newPage({viewport:{width:1280,height:800},hasTouch:true}),errors=[],writes=[];
  page.on('pageerror',e=>errors.push(e.message));page.setDefaultTimeout(20000);
  await page.goto(base);const csrf=await page.locator('[name=csrf]').first().inputValue();
  await page.request.post(base+'/auth/local',{form:{csrf,mode:'login',identifier:'PreviewPlayer',password:'PreviewFixture!2026'}});
  await page.goto(base+'/city#inventory');await page.locator('.inventory-shell').waitFor();
  // Opening rally details may calculate a read-only battle preview via POST.
  page.on('request',r=>{if(r.method()==='POST'&&r.url().includes('/api/')&&new URL(r.url()).pathname!=='/api/march/preview')writes.push(r.url());});
  await page.locator('[data-action=inventory-scope][data-id=all]').click();
  await page.locator('[data-action=inventory-item].is-unowned').first().click();
  const sources=page.locator('.inventory-inspector [data-action=item-sources]');
  assert.equal(await sources.isEnabled(),true,'unowned item still offers source lookup');
  await sources.click();await page.locator('.item-source-card,.item-sources .notice').first().waitFor();
  assert.equal(await page.locator('#game-dialog #dialog-title').innerText(),'Where to find it');
  for(const [width,height]of[[1280,800],[390,844],[320,568],[844,390],[568,320]]){
   await page.setViewportSize({width,height});await page.waitForTimeout(100);
   const issues=await page.evaluate(()=>{
    const errors=[],dialog=document.querySelector('#game-dialog'),scroll=dialog.querySelector('.item-sources-scroll');
    for(const [name,e]of[['dialog',dialog],['sources',scroll]])if(e.scrollWidth>e.clientWidth+2)errors.push(name+' horizontal overflow');
    const close=[...dialog.querySelectorAll('.dialog-close,.mobile-page-back')].find(e=>e.getClientRects().length);
    if(!close)errors.push('No close control');else {const r=close.getBoundingClientRect();if(r.top<0||r.bottom>innerHeight+1||r.left<0||r.right>innerWidth+1)errors.push('Close outside viewport');}
    if(scroll.clientHeight<70)errors.push('Source list too short');
    for(const b of scroll.querySelectorAll('button'))if(b.getBoundingClientRect().height<43)errors.push('Touch target too small');
    return errors;
   });assert.deepEqual(issues,[],width+'x'+height);
   await page.screenshot({path:path.join(out,width+'x'+height+'.png')});
  }
  await page.keyboard.press('Escape');await page.waitForFunction(()=>!document.querySelector('#game-dialog').open);
  for(const [width,height]of[[1280,800],[390,844],[568,320]]){
   await page.setViewportSize({width,height});
   await page.evaluate(()=>{location.hash='inventory';});await page.locator('.inventory-shell').waitFor();
   await page.locator('[data-action=inventory-scope][data-id=all]').click();
   await page.locator('[data-action=inventory-category][data-id=other]').click();
   await page.locator('[data-action=inventory-item][data-id="10105001"]').click();
   await sources.click();await page.locator('.item-source-card').first().waitFor();
   const map=page.locator('.item-source-card').filter({has:page.locator('button', {hasText:'Show on map'})}).first();
   assert(await map.count(),'a reachable monster source is available in preview');
   await map.locator('button').click();await page.waitForFunction(()=>location.hash==='#world'&&!document.querySelector('#game-dialog').open);
   await page.waitForTimeout(250);
   assert.equal(await page.evaluate(()=>location.hash),'#world','mobile history keeps chosen map destination');
   assert.equal(await page.locator('.march-command').count(),0,'source navigation does not open or dispatch an attack');
  }
  // The effective server catalogue must agree across material sources and rally loot.
  const badgeLookup=(await(await page.request.get(base+'/api/item-sources?item_code=119000002')).json()).data;
  const badgeMonsters=badgeLookup.sources.filter(source=>source.type==='monster');
  assert(badgeMonsters.length>0,'Alliance Badges have real monster sources');
  for(const source of badgeMonsters){
   const quantity=source.level<=3?2:source.level<=6?3:source.level<=9?4:5;
   assert.deepEqual(source.rewards.map(reward=>[reward.quantity,reward.chance]),[[quantity,0.5]],'Badge quantity and 50% drop chance for '+source.id);
  }
  for(const level of [1,3,4,6,7,9,10])assert(badgeMonsters.some(source=>source.level===level),'Source catalogue covers badge threshold level '+level);
  const badgeSource=badgeMonsters.find(source=>Number(source.destination?.target?.data?.monster_code)===20200501&&source.level===1);
  assert(badgeSource,'A seeded rally monster is reachable from the badge source list');
  const badgeTarget=badgeSource.destination.target.data,badgeDefinition=badgeTarget.definition;
  assert.equal(badgeDefinition.type,'rally');assert.equal(badgeDefinition.drops.length,8,'Rally has eight item rewards including the badge');
  const badgeDrop=badgeDefinition.drops.find(drop=>Number(drop.item_code)===119000002);
  assert.equal(badgeDrop?.count,2);assert.equal(badgeDrop?.probability,0.5);
  assert(badgeDefinition.gems_drop?.amount>0,'Rally also exposes its crystal drop');
  for(const [width,height]of[[1280,800],[390,844],[320,568],[844,390],[568,320]]){
   await page.setViewportSize({width,height});await page.evaluate(()=>{location.hash='inventory';});await page.locator('.inventory-shell').waitFor();
   await page.locator('[data-action=inventory-scope][data-id=all]').click();await page.locator('[data-action=inventory-category][data-id=other]').click();
   await page.locator('[data-action=inventory-item][data-id="119000002"]').click();
   const lookupResponse=page.waitForResponse(response=>response.url().includes('/api/item-sources?')&&response.url().includes('item_code=119000002'));
   await sources.click();const lookup=(await(await lookupResponse).json()).data;await page.locator('.item-source-card').first().waitFor();
   assert.equal(await page.locator('.item-sources-selected').innerText(),lookup.item.name);
   const sourceIndex=lookup.sources.findIndex(source=>source.id===badgeSource.id);assert(sourceIndex>=0);
   const sourceCard=page.locator('.item-source-card').nth(sourceIndex);await sourceCard.scrollIntoViewIfNeeded();
   assert.match(await sourceCard.locator('.item-source-reward').innerText(),/Quantity: 2\s*·\s*50% drop chance/);
   await page.screenshot({path:path.join(out,'badge-source-'+width+'x'+height+'.png')});
   await sourceCard.locator('[data-action=item-sources-go]').click();await page.waitForFunction(()=>location.hash==='#world'&&!document.querySelector('#game-dialog').open);
   const attack=page.locator(`[data-action="expedition"][data-kind="monsters"][data-id="${badgeTarget.id}"]:visible`).first();await attack.click();await page.locator('.march-command.is-monster-rally').waitFor();
   const targetTab=page.locator('[data-action="march-view"][data-id="target"]');if(await targetTab.isVisible())await targetTab.click();
   const rewards=page.locator('.march-rewards>div>span');assert.equal(await rewards.count(),9,'All eight items and crystals remain visible in the loot list');
   const badge=rewards.filter({has:page.locator('img[alt="'+lookup.item.name+'"]')}),crystals=rewards.filter({has:page.locator('img[src$="/items/gems.svg"]')});
   assert.equal(await badge.count(),1,'Badge appears once');assert.equal(await badge.locator('strong').innerText(),'2');
   assert.equal(await crystals.count(),1,'Crystal reward is not truncated');assert.equal(Number((await crystals.locator('strong').innerText()).replace(/\D/g,'')),badgeDefinition.gems_drop.amount);
   for(const reward of [badge,crystals]){await reward.scrollIntoViewIfNeeded();assert(await reward.evaluate(el=>{const r=el.getBoundingClientRect();return r.top>=0&&r.bottom<=innerHeight+1&&el.contains(document.elementFromPoint(r.x+r.width/2,r.y+r.height/2));}),'Reward is reachable by scrolling');}
   const layout=await page.locator('#game-dialog').evaluate(dialog=>{const r=dialog.getBoundingClientRect(),target=dialog.querySelector('.march-target'),send=dialog.querySelector('#march-confirm'),button=send.getBoundingClientRect();return{inside:r.left>=0&&r.top>=0&&r.right<=innerWidth+1&&r.bottom<=innerHeight+1,overflow:dialog.scrollWidth>dialog.clientWidth+2||target.scrollWidth>target.clientWidth+2,sendReachable:button.height>=44&&button.top>=0&&button.bottom<=innerHeight+1&&send.contains(document.elementFromPoint(button.x+button.width/2,button.y+button.height/2))};});
   assert.deepEqual(layout,{inside:true,overflow:false,sendReachable:true},'Complete loot list '+width+'x'+height);
   await page.locator('.march-rewards img').evaluateAll(async images=>{await Promise.all(images.map(image=>image.decode()));});
   await page.screenshot({path:path.join(out,'badge-loot-'+width+'x'+height+'.png')});
   await page.keyboard.press('Escape');await page.waitForFunction(()=>!document.querySelector('#game-dialog').open);
  }
  console.log('PASS badge source tiers with 50% drop chance and all nine rally loot rewards in five viewports.');
  await page.setViewportSize({width:390,height:844});
  await page.evaluate(()=>{location.hash='treasures';});await page.locator('.treasury-shell').waitFor();
  await page.locator('.treasury-card').filter({has:page.locator('.is-locked')}).first().click();
  const fragments=page.locator('.treasury-inspector [data-action=item-sources]');
  await fragments.click();await page.locator('.item-source-card').first().waitFor();
  assert.match(await page.locator('.item-sources-selected').innerText(),/Relic fragments/);
  await page.keyboard.press('Escape');await page.waitForFunction(()=>!document.querySelector('#game-dialog').open);
  let release;const held=new Promise(resolve=>release=resolve);let intercepted;
  const requested=new Promise(resolve=>intercepted=resolve);
  await page.route('**/api/item-sources?*',async route=>{const response=await route.fetch();intercepted();await held;await route.fulfill({response});});
  await fragments.click();await requested;await page.keyboard.press('Escape');release();await page.waitForTimeout(400);
  assert.equal(await page.locator('#game-dialog').evaluate(e=>e.open),false,'late lookup cannot reopen a dismissed dialog');
  await page.unroute('**/api/item-sources?*');
  await page.locator('[data-action=treasury-detail-close]').click();
  await page.locator('.treasury-card.grade-legendary').first().click();
  await fragments.click();await page.locator('.item-source-card').first().waitFor();
  await page.locator('.item-source-card').filter({has:page.locator('h3',{hasText:'Platinum chest'})}).getByRole('button',{name:'Open source'}).click();
  await page.locator('.inventory-inspector [data-action=item-sources][data-item-code="10105003"]').waitFor();
  await page.waitForTimeout(250);
  assert.equal(await page.evaluate(()=>location.hash),'#inventory','mobile source handoff preserves the exact chest inspector');
  assert.deepEqual(writes,[],'finding and following sources performs no gameplay writes');
  assert.deepEqual(errors,[],'no browser errors');
  console.log('ITEM SOURCE APP CHECKS PASSED');
 }finally{if(browser)await browser.close();if(child){child.stdin.end('\n');await new Promise(resolve=>child.on('exit',resolve));}}
})().catch(e=>{console.error(e);process.exitCode=1;});
