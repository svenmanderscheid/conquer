'use strict';
// Real app in a disposable database: navigation and layout only, no gameplay writes.
const fs=require('fs'),path=require('path'),assert=require('assert/strict'),net=require('net');
const {spawn}=require('child_process');
const {chromium}=require(process.env.PLAYWRIGHT_MODULE||'playwright');
const root=path.resolve(__dirname,'..'),out=path.join(root,'artifacts/mobile-pages');
fs.mkdirSync(out,{recursive:true});
(async()=>{
 const port=await new Promise(resolve=>{const s=net.createServer();s.listen(0,'127.0.0.1',()=>{const p=s.address().port;s.close(()=>resolve(p));});});
 const child=spawn(process.env.PHP_BINARY||'C:/xampp/php/php.exe',['tools/preview-feature-fixture.php','--port='+port,'--hud','--training','--mailbox','--chat'],{cwd:root,stdio:['pipe','pipe','pipe'],windowsHide:true});
 let browser,log='';const base='http://127.0.0.1:'+port;
 try{
  await new Promise((resolve,reject)=>{const timer=setTimeout(()=>reject(Error(log||'Fixture timeout')),60000);child.stdout.on('data',c=>{log+=c;if(log.includes('Synthetic preview ready')){clearTimeout(timer);resolve();}});child.stderr.on('data',c=>log+=c);child.on('exit',code=>{clearTimeout(timer);reject(Error('Fixture '+code+': '+log));});});
  browser=await chromium.launch({headless:true,channel:'chrome'});
  const page=await browser.newPage({viewport:{width:390,height:844},hasTouch:true});
  const errors=[];page.on('pageerror',e=>errors.push(e.message));page.setDefaultTimeout(15000);page.setDefaultNavigationTimeout(45000);
  await page.goto(base);const csrf=await page.locator('[name=csrf]').first().inputValue();
  await page.request.post(base+'/auth/local',{form:{csrf,mode:'login',identifier:'PreviewPlayer',password:'PreviewFixture!2026'}});
  await page.goto(base+'/city#city');await page.locator('#hud-research:not([data-job-state=loading])').waitFor();
  const route=async tab=>{await page.evaluate(tab=>{location.hash=tab;},tab);await page.locator(`#panel-dialog[open][data-panel="${tab}"]`).waitFor();await page.waitForTimeout(160);};
  const frame=async selector=>page.locator(selector).evaluate(e=>{const r=e.getBoundingClientRect();return {x:r.x,y:r.y,w:r.width,h:r.height,overflow:e.scrollWidth>e.clientWidth+2};});
  for(const [width,height] of (process.env.MOBILE_PAGES_NAV_ONLY?[]:[[390,844],[320,568],[844,390],[568,320],[1280,800]])){
   await page.setViewportSize({width,height});
   for(const tab of ['army','research','treasures','market','inventory','reports','quests','mastery','alliance','community','defense','events','dungeons','expeditions','land','rankings','arena','profile','settings','account','help','worlds','bugreport']){
    await route(tab);const r=await frame('#panel-dialog');
    if(width!==1280)assert(r.x===0&&r.y===0&&Math.abs(r.w-width)<2&&Math.abs(r.h-height)<2,JSON.stringify({tab,width,height,r}));
    else assert(r.w<width&&r.h<height,'Desktop keeps its window');
    assert(!r.overflow,JSON.stringify({tab,width,height,r}));
    if(tab==='army'){
     await page.locator('[data-action=training-school][data-id=archery_range]').click();
     const confirm=await frame('#train-confirm');
     assert(confirm.y>=0&&confirm.y+confirm.h<=height+1&&confirm.h>=40,JSON.stringify({width,height,confirm}));
    }
    if(['army','research','treasures','market'].includes(tab))await page.screenshot({path:path.join(out,`${tab}-${width}x${height}.png`)});
   }
   console.log('Layout checked:',width,height);
  }
  await page.setViewportSize({width:390,height:844});
  await page.goto(base+'/city#city');await page.locator('#hud-research:not([data-job-state=loading])').waitFor();
  await page.locator('#hud-research').click();await page.locator('.rt-scroll').waitFor();
  await page.locator('.rt-scroll').evaluate(e=>e.scrollTop=200);
  await page.locator('.rt-node').first().click();await page.locator('#game-dialog.mobile-detail-page[open]').waitFor();
  const before=await page.locator('.rt-scroll').evaluate(e=>e.scrollTop);
  assert.equal((await frame('#game-dialog')).w,390);
  await page.goBack();await page.locator('#game-dialog').waitFor({state:'hidden'});
  assert.equal(await page.locator('#panel-dialog').getAttribute('data-panel'),'research');
  assert.equal(await page.locator('.rt-scroll').evaluate(e=>e.scrollTop),before);
  await page.locator('#panel-dialog .mobile-page-back').click();await page.locator('#panel-dialog').waitFor({state:'hidden'});
  assert.equal(new URL(page.url()).hash,'#city');
  await page.locator('#navigation [data-id=chat]').click();await page.locator('.world-chat.is-open').waitFor();
  const chat=await frame('.world-chat-window');assert.equal(chat.w,390);assert.equal(chat.h,844);
  await page.screenshot({path:path.join(out,'chat-390x844.png')});
  // The real chat shell uses the same overlay, fonts and touch targets as isolated fixtures.
  await page.locator('.world-chat-message').first().waitFor();await page.evaluate(()=>document.fonts.ready);
  for(const [width,height] of [[1280,800],[390,844],[320,568],[844,390],[568,320]]){
   await page.setViewportSize({width,height});await page.waitForTimeout(80);
   assert.equal(await page.locator('.world-chat-compose [type="submit"]').isDisabled(),true,'empty message cannot be sent');
   const logBefore=await frame('.world-chat-log');
   const menu=page.locator('.world-chat-log [data-chat-menu]').last();await menu.click();
   assert.equal(await page.locator('.world-chat-log').evaluate(el=>el.inert),true);
   const logAfter=await frame('.world-chat-log');assert(Math.abs(logBefore.h-logAfter.h)<1,'message menu overlays rather than shrinking the history');
   for(const selector of ['.world-chat-tools [data-chat-reply]','[data-chat-panel-close]','#world-chat-message','.world-chat-window-heading [data-chat-close]:visible']){
    const control=page.locator(selector).first();await control.scrollIntoViewIfNeeded();const r=await control.boundingBox();
    assert(r&&r.height>=44&&r.y>=0&&r.y+r.height<=height+1,selector+' reachable at '+width+'x'+height);
   }
   assert.equal((await frame('.world-chat-window')).overflow,false);
   await page.screenshot({path:path.join(out,`chat-actions-${width}x${height}.png`)});
   await page.locator('[data-chat-panel-close]').click();assert.equal(await page.locator('.world-chat-log').evaluate(el=>el.inert),false);
   await page.locator('[data-chat-mute]').click();await page.locator('[data-chat-mode=world]').waitFor();
   await page.screenshot({path:path.join(out,`chat-settings-${width}x${height}.png`)});
   await page.locator('[data-chat-panel-close]').click();
   await page.screenshot({path:path.join(out,`chat-messages-${width}x${height}.png`)});
  }
  await page.setViewportSize({width:390,height:844});

  await page.goBack();await page.locator('.world-chat.is-open').waitFor({state:'detached'});
  assert.equal(new URL(page.url()).hash,'#city');
  // The closed ribbon must keep useful text width and clear the dock in both scenes.
  for(const scene of ['city','world']){
   await page.evaluate(scene=>location.hash=scene,scene);
   await page.locator('body.'+(scene==='city'?'city-mode':'world-mode')).waitFor();
   await page.locator('#scene-transition').waitFor({state:'hidden'});
   for(const [width,height] of [[1280,800],[390,844],[320,568],[844,390],[568,320]]){
    await page.setViewportSize({width,height});await page.waitForTimeout(100);
    const ribbon=await page.locator('.world-chat-preview').boundingBox(),dock=await page.locator('#navigation').boundingBox();
    const text=await page.locator('.world-chat-preview-messages').boundingBox();
    await page.screenshot({path:path.join(out,`chat-ribbon-${scene}-${width}x${height}.png`)});
    assert(ribbon&&ribbon.x>=-1&&ribbon.x+ribbon.width<=width+1&&ribbon.y>=0,'closed chat fits '+scene+' '+width+'x'+height+' '+JSON.stringify({ribbon,dock}));
    const overlaps=await page.locator('#navigation button,#navigation .dock-icon,#navigation .dock-label').evaluateAll((nodes,ribbon)=>nodes.filter(node=>{const r=node.getBoundingClientRect();return r.width&&r.height&&ribbon.x<r.right-1&&ribbon.x+ribbon.width>r.left+1&&ribbon.y<r.bottom-1&&ribbon.y+ribbon.height>r.top+1;}).map(node=>node.dataset.id||node.className),ribbon);
    assert.deepEqual(overlaps,[],'closed chat clears actual dock targets and protruding artwork at '+scene+' '+width+'x'+height);
    if(scene==='world'){
     for(const selector of ['.map-overlay-search-toggle','.map-overlay-coordinate-toggle']){
      const control=page.locator(selector);const r=await control.boundingBox();
      assert(r&&!(ribbon.x<r.x+r.width-1&&ribbon.x+ribbon.width>r.x+1&&ribbon.y<r.y+r.height-1&&ribbon.y+ribbon.height>r.y+1),selector+' clears the ribbon at '+width+'x'+height);
      assert.equal(await control.evaluate(el=>{const r=el.getBoundingClientRect(),hit=document.elementFromPoint(r.x+r.width/2,r.y+r.height/2);return hit===el||el.contains(hit);}),true,selector+' remains reachable');
     }
    }
    assert(text&&text.width>=160&&text.height>=40,'two preview lines have usable space at '+scene+' '+width+'x'+height);
    const lines=await page.locator('.world-chat-preview-message').evaluateAll(elements=>elements.map(el=>{const r=el.getBoundingClientRect();return {x:r.x,y:r.y,w:r.width,h:r.height};}));
    assert.equal(lines.length,2,'two preview messages');
    assert(lines[0].y+lines[0].h<=lines[1].y+1,'preview lines do not overlap');
   }
  }
  await page.evaluate(()=>location.hash='city');await page.locator('body.city-mode').waitFor();
  await page.setViewportSize({width:390,height:844});
  await page.locator('#navigation [data-id=chat]').click();
  await page.setViewportSize({width:390,height:420});
  const input=await frame('#world-chat-message');assert(input.y+input.h<=420&&input.h>=44,'Chat input stays inside the reduced viewport');
  await page.locator('.world-chat-back').click();await page.locator('.world-chat.is-open').waitFor({state:'detached'});
  await page.setViewportSize({width:390,height:844});
  await page.waitForTimeout(120);
  await page.locator('#hud-menu').click();await page.locator('#game-dialog.mobile-detail-page[open]').waitFor();
  await page.locator('[data-action=dialog-tab][data-id=army]').click();await page.locator('#panel-dialog[data-panel=army][open]').waitFor();
  await page.locator('#panel-dialog .mobile-page-back').click();await page.locator('#panel-dialog').waitFor({state:'hidden'});
  await page.locator('#hud-build').click();await page.locator('#game-dialog[open]').waitFor();
  assert.equal(await page.locator('#game-dialog').evaluate(e=>e.classList.contains('mobile-detail-page')),false);
  const compact=await frame('#game-dialog');assert(compact.h<820&&compact.w<390,'Building remains a compact sheet');
  await page.screenshot({path:path.join(out,'building-sheet.png')});
  await page.locator("#game-dialog .dialog-close:visible, #game-dialog .mobile-page-back:visible").first().click();await page.waitForTimeout(150);
  await route('treasures');await page.locator('.treasury-card').first().click();
  await page.locator('.treasury-detail').waitFor();const relic=await frame('.treasury-detail');
  assert(relic.w>360&&relic.h>700,'Relic detail uses the content page');
  await page.screenshot({path:path.join(out,'relic-detail.png')});
  await page.goBack();await page.locator('.treasury-detail').waitFor({state:'detached'});
  assert.equal(await page.locator('#panel-dialog').getAttribute('data-panel'),'treasures');
  await page.locator('.treasury-card').first().click();
  await page.locator('[data-action=treasury-exchange-open]').first().click();
  await page.locator('.treasury-exchange').waitFor();
  await page.goBack();await page.locator('.treasury-exchange').waitFor({state:'detached'});
  assert(await page.locator('.treasury-detail').isVisible());
  await page.locator('#panel-dialog .mobile-page-back').click();await page.locator('.treasury-detail').waitFor({state:'detached'});
  await route('army');await page.locator('#panel-dialog [data-action=panel-tab][data-group=army][data-id=hospital]').click();
  assert.equal(await page.locator('#panel-dialog').getAttribute('data-hospital'),'true');
  await page.screenshot({path:path.join(out,'hospital.png')});
  await route('profile');await page.locator('[data-action=edit-profile]:visible').first().click();
  await page.locator('#game-dialog.mobile-detail-page[open]').waitFor();
  await page.goBack();await page.locator('#game-dialog').waitFor({state:'hidden'});
  await route('reports');await page.locator('[data-action=mailbox-open]').first().click();
  await page.locator('#game-dialog.mobile-detail-page[open]').waitFor();
  await page.locator('#game-dialog .mobile-page-back').click();await page.locator('#game-dialog').waitFor({state:'hidden'});
  await page.evaluate(()=>location.hash='city');await page.locator('#panel-dialog').waitFor({state:'hidden'});
  await page.locator('#hud-vip-button').click();await page.locator('#game-dialog.mobile-detail-page[open]').waitFor();
  await page.goBack();await page.locator('#game-dialog').waitFor({state:'hidden'});
  assert.deepEqual(errors,[]);console.log('PASS: '+(process.env.MOBILE_PAGES_NAV_ONLY?'Navigation checks':'115 menu layouts')+', research/browser back, scroll retention, five chat menu/settings layouts, reduced chat viewport, relics, hospital, profile, mail and VIP.');
 }finally{await browser?.close();child.stdin.write('\n');await new Promise(resolve=>{child.once('exit',resolve);setTimeout(resolve,10000).unref();});}
})().catch(e=>{console.error(e);process.exitCode=1;});

