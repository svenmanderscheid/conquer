'use strict';
// Runs only against reward_admin.php's disposable loopback HTTP fixture.
const assert=require('assert'),fs=require('fs'),os=require('os'),path=require('path');
const {chromium}=require(process.env.PLAYWRIGHT_MODULE||'playwright');
const base=process.argv[2];
assert(/^http:\/\/127\.0\.0\.1:\d+$/.test(base),'An isolated fixture URL is required');
const out=path.join(os.tmpdir(),'conquer-admin-ui');fs.mkdirSync(out,{recursive:true});
(async()=>{
 const browser=await chromium.launch({headless:true,channel:process.env.PLAYWRIGHT_CHANNEL||'chrome'});
 const page=await browser.newPage({viewport:{width:1440,height:1000}});const errors=[];page.on('pageerror',e=>errors.push(e.message));
 try{
  await page.context().addCookies([{name:'conquer_locale',value:'de',url:base}]);
  await page.goto(base+'/admin/login');await page.locator("[name=identifier], [name=username]").fill('RewardAdmin');await page.locator('[name=password]').fill('Fixture-Reward-123!');await page.getByRole('button',{name:'Anmelden',exact:true}).click();await page.waitForURL(base+'/admin');
  assert.equal(await page.locator('[data-operations-overview]').count(),1,'Analytical overview is the administration landing page');
  assert.equal(await page.locator('.ops-metric').count(),4,'Overview exposes the four operational metrics');
  assert.equal(await page.locator('.ops-analysis .ops-tabs a').count(),3,'Activity, economy and stability are one click apart');
  if(process.env.ADMIN_WORLD_ONLY==='1'){await require('./admin_world_workspace.cjs')({page,base,errors});return;}
  if(process.env.ADMIN_RELICS_ONLY==='1'){await require('./admin_relic_drops.cjs')({page,base,errors});await browser.close();return;}
  if(process.env.ADMIN_MODERN_ONLY==='1'){await require('./admin_modern.cjs')({page,base,errors});await browser.close();return;}
  if(process.env.ADMIN_FRAGMENTS_ONLY==='1'){
   const fragmentOut=path.join(__dirname,'../output/playwright/relic-fragment-drops');fs.mkdirSync(fragmentOut,{recursive:true});
   page.on('dialog',dialog=>dialog.accept());
   for(const [locale,width,height] of [['en',1440,1000],['de',390,844],['en',320,700],['fr',568,320]]){
    await page.context().addCookies([{name:'conquer_locale',value:locale,url:base}]);await page.setViewportSize({width,height});
    for(const type of ['monster','farm']){
     const source=type==='monster'?'20209901':'20100101.1';
     await page.goto(base+`/admin/rewards?type=${type}&source=${source}`);
     while(await page.locator('[data-remove-fragment]').count())await page.locator('[data-remove-fragment]').last().click();
     await page.locator('[data-add-fragment]').click();let row=page.locator('.fragment-row').last();
     assert.equal(await row.locator('input[name$="[chance]"]').inputValue(),'0','New fragment row starts disabled');
     assert.equal(await row.locator('option[value="fragment:mythic"]').count(),0,'Retired rarity is absent');
     await row.locator('select').selectOption('treasure:60100001');await row.locator('input[name$="[quantity]"]').fill('3');await row.locator('input[name$="[chance]"]').fill('12.3456');
     assert.equal(await row.locator('option:checked').innerText(),await page.evaluate(()=>ConquerRelicPresentation.name({treasure_code:60100001})),'Admin selection uses the same current relic name as the game');
     await page.locator('[data-add-fragment]').click();row=page.locator('.fragment-row').last();
     await row.locator('select').selectOption('fragment:epic');await row.locator('input[name$="[quantity]"]').fill('1');await row.locator('input[name$="[chance]"]').fill('100');
     assert((await page.locator('[data-fragment-summary]').innerText()).includes('137'),'Live fragment expectation includes both rows');
     await page.locator('.reward-editor [name=reason]').fill('Isolated relic fragment browser check');
     await page.locator('.reward-editor form.admin-form button[type=submit]').click();await page.waitForURL('**/admin/rewards?world_id=1&type='+type+'&source='+source+'&scope=global');
     assert.equal(await page.locator('.fragment-row').count(),2,'Saving keeps both fragment rows');
     assert.equal(await page.locator('.fragment-row').first().locator('select').inputValue(),'treasure:60100001');
     assert.equal(await page.locator('.fragment-row').first().locator('input[name$="[chance]"]').inputValue(),'12.3456','Exact percentage survives save');
     await page.reload();assert.equal(await page.locator('.fragment-row').last().locator('select').inputValue(),'fragment:epic','Random rarity survives reload');
     await page.locator('.reward-fragments').evaluate(async el=>{await document.fonts.ready;el.scrollIntoView({block:'start',behavior:'instant'});await new Promise(resolve=>requestAnimationFrame(()=>requestAnimationFrame(resolve)));});
     assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth+2),false,'Fragment editor fits at '+width);
     assert(await page.locator('.fragment-row').evaluateAll(rows=>rows.every(row=>[...row.querySelectorAll('select,input,button')].every(e=>{const r=e.getBoundingClientRect();return r.width>=44&&r.height>=44;}))),'Fragment inputs and removal buttons have touch targets at '+width);
     assert.equal(await page.evaluate(()=>getComputedStyle(document.documentElement).scrollbarWidth),'none','Scrollbars stay hidden');
     await page.screenshot({path:path.join(fragmentOut,`${type}-${locale}-${width}x${height}.png`)});
    }
   }
   await page.goto(base+'/admin/rewards?type=farm&source=20100101.1&scope=world&world_id=1');
   await page.locator('.fragment-row').first().locator('input[name$="[quantity]"]').fill('7');
   await page.locator('.reward-editor [name=reason]').fill('World-specific fragment fixture');
   await page.locator('.reward-editor form.admin-form button[type=submit]').click();await page.waitForURL('**/admin/rewards?world_id=1&type=farm&source=20100101.1&scope=world');
   await page.reload();assert.equal(await page.locator('.fragment-row').first().locator('input[name$="[quantity]"]').inputValue(),'7','World-specific fragment quantity survives reload');
   await page.goto(base+'/admin/rewards?type=farm&source=20100101.1&scope=global');assert.equal(await page.locator('.fragment-row').first().locator('input[name$="[quantity]"]').inputValue(),'3','World override preserves the global fragment rule');
   assert.deepEqual(errors,[],'No browser errors in fragment editors');console.log('RELIC FRAGMENT ADMIN BROWSER CHECKS PASSED · '+fragmentOut);return;
  }
  await require('./admin_modern.cjs')({page,base,errors});
  await page.context().addCookies([{name:'conquer_locale',value:'de',url:base}]);await page.setViewportSize({width:1440,height:1000});
  await page.goto(base+'/admin/rewards?type=monster&source=20209901');
  const originalDropCount=await page.locator('.drop-row').count();
  while(await page.locator('.drop-row .remove-drop').count())await page.locator('.drop-row .remove-drop').last().click();
  await page.locator('[data-add-drop]').click();await page.locator('#item-picker-search').fill('10103003');await page.locator('[data-pick-item="10103003"]').click();
  await page.locator('[name="config[rows][0][quantity]"]').fill('8');await page.locator('[name="config[rows][0][chance]"]').fill('100');await page.locator('.reward-editor [name=reason]').fill('Browser save fixture');
  await page.getByRole('button',{name:'Beute speichern'}).click();await page.waitForURL('**/admin/rewards?world_id=1&type=monster&source=20209901&scope=global');
  assert((await page.locator('.notice.success').innerText()).includes('gespeichert'));
  assert.equal(await page.locator('[name="config[rows][0][quantity]"]').inputValue(),'8');
  await page.reload();assert.equal(await page.locator('[name="config[rows][0][target]"]').inputValue(),'10103003');
  const invalid=await page.locator('.reward-editor form.admin-form').evaluate(async f=>{const data=new FormData(f);data.set('reason','Invalid fixture percentage');data.set('config[rows][0][chance]','101');return (await fetch(f.action,{method:'POST',body:data})).text();});
  assert(invalid.includes('0 bis 100 Prozent'),'Server rejects forged percentage');
  await page.reload();assert.equal(await page.locator('[name="config[rows][0][chance]"]').inputValue(),'101','Invalid draft is retained');
  await page.goto(base+'/admin/rewards?type=monster&source=20209901&discard=1');
  await page.locator('.reset-settings summary').click();await page.locator('.reset-settings [name=reason]').fill('Restore fixture defaults');await page.locator('.reset-settings button[type=submit]').click();await page.waitForURL('**/admin/rewards?world_id=1&type=monster&source=20209901&scope=global');assert.equal(await page.locator('.drop-row').count(),originalDropCount);
  for(const [width,height] of [[1440,1000],[390,844],[320,700],[568,320]]){
   await page.setViewportSize({width,height});
   for(const route of ['','/rewards?type=monster','/rewards?type=farm','/rewards?type=dungeon','/rewards?type=chest&source=platinum','/rewards?type=expedition','/items','/world','/lands','/players','/bug-reports']){
    await page.goto(base+'/admin'+route);
    assert(!(await page.locator('main').innerText()).includes('Ansicht konnte nicht geladen'),'View renders '+route);
    const overflow=await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth+2);
    if(overflow){console.log('Overflow elements',await page.locator('main *').evaluateAll(es=>es.filter(e=>e.getBoundingClientRect().right>innerWidth+2).slice(0,10).map(e=>({tag:e.tagName,class:e.className,text:e.textContent.slice(0,65),right:e.getBoundingClientRect().right}))));await page.screenshot({path:path.join(out,'overflow.png'),fullPage:true});}
    assert.equal(overflow,false,`${route} overflow at ${width}x${height}`);
    if(route===''||route.includes('dungeon')||route.includes('farm')||route==='/items'||route==='/lands'){
     await page.locator('img').evaluateAll(async images=>{await Promise.all(images.map(i=>{i.loading='eager';return i.decode().catch(()=>{});}));});
     await page.screenshot({path:path.join(out,`${route===''?'overview':route==='/items'?'items':route==='/lands'?'lands':route.includes('farm')?'farm':'dungeon'}-${width}x${height}.png`),fullPage:true});
    }
   }
   await page.goto(base+'/admin/rewards?type=dungeon');await page.locator('.drop-row [data-item-picker]').first().click();await page.locator('#item-picker-search').fill('Nahrung');
   assert((await page.locator('.picker-result').count())>0);await page.locator('.picker-result img').evaluateAll(async images=>{await Promise.all(images.map(i=>{i.loading='eager';return i.decode();}));});
   assert.equal(await page.locator('#item-picker-dialog').evaluate(e=>e.scrollWidth>e.clientWidth+2),false,'Picker does not overflow');
   await page.screenshot({path:path.join(out,`picker-${width}x${height}.png`)});await page.keyboard.press('Escape');assert.equal(await page.locator('#item-picker-dialog').evaluate(e=>e.open),false);
   if(width<850){await page.locator('.mobile-menu').click();assert.equal(await page.locator('.mobile-menu').getAttribute('aria-expanded'),'true');await page.locator('#admin-nav a[data-admin-area="drops"]').click();await page.waitForURL(url=>url.pathname.endsWith('/admin/rewards'));await page.locator('.admin-section-nav a[href*="/admin/items"]').click();await page.waitForURL(url=>url.pathname.endsWith('/admin/items'));}
  }
  await page.goto(base+'/admin/rewards?type=chest&source=platinum');const rows=await page.locator('.drop-row').count();assert(rows>100,'Large chest table is complete');await page.locator('[data-drop-search]').fill('Nahrung');assert(await page.locator('.drop-row:visible').count()<rows);assert(!(await page.locator('[data-save-status]').innerText()).includes('Ungespeicherte'),'Filtering does not mark config dirty');
  await page.setViewportSize({width:1440,height:1000});
  await page.goto(base+'/admin/rewards?type=farm&source=20100101.1');
  await page.locator('[data-source-level]').selectOption('3');
  assert.equal(await page.locator('.source-choice:visible').count(),5,'Level filter finds all five resource families');
  await page.locator('[data-source-search]').fill('Kristall');assert.equal(await page.locator('.source-choice:visible').count(),1,'Search and level filters combine');
  assert(!(await page.locator('[data-save-status]').innerText()).includes('Ungespeicherte'),'Source filters do not dirty the form');
  while(await page.locator('.drop-row .remove-drop').count())await page.locator('.drop-row .remove-drop').last().click();
  assert.equal(await page.locator('[data-drop-active]').innerText(),'0');
  await page.locator('[data-add-drop]').click();await page.locator('#item-picker-search').fill('10103001');await page.locator('[data-pick-item="10103001"]').click();
  await page.locator('[name="config[rows][0][quantity]"]').fill('3');
  assert.equal(await page.locator('[data-drop-active]').innerText(),'1');assert.equal(await page.locator('[data-drop-guaranteed]').innerText(),'1');assert.equal(await page.locator('[data-drop-expected]').innerText(),'300');
  await page.locator('.reward-editor [name=reason]').fill('Farm browser fixture');await page.getByRole('button',{name:'Beute speichern'}).click();await page.waitForURL('**/admin/rewards?world_id=1&type=farm&source=20100101.1&scope=global');
  await page.reload();assert.equal(await page.locator('[name="config[rows][0][quantity]"]').inputValue(),'3','Farm changes survive reload');
  assert.equal(await page.locator('[data-source-level]').inputValue(),'3');assert.equal(await page.locator('[data-source-search]').inputValue(),'Kristall','Farm search survives saving and reloading');
  assert.equal(await page.locator('.source-choice:visible').count(),1,'Restored farm filters still combine');await page.locator('[data-source-reset]').click();
  await page.locator('[data-source-rule]').selectOption('custom');assert.equal(await page.locator('.source-choice:visible').count(),2,'Customized filter includes both saved batch sources');assert(await page.locator('.source-choice.is-selected').isVisible(),'Customized filter includes the saved detail source');
  await page.context().addCookies([{name:'conquer_locale',value:'en',url:base}]);
  await page.goto(base+'/admin/rewards?type=dungeon');
  assert.equal(await page.locator('.reward-category-tabs a').filter({hasText:'Mines'}).count(),1,'New UI has an English version');
  const weights=page.locator('.drop-row input[name$="[weight]"]');for(const input of await weights.all())await input.fill('1');
  await page.locator('[name="config[item_chance]"]').fill('18');await page.locator('[name="config[item_quantity]"]').fill('2');
  assert.equal(await page.locator('[data-drop-expected]').innerText(),'36','Dungeon expectation includes outer chance and quantity');assert.equal(await page.locator('[data-drop-guaranteed]').innerText(),'0');
  assert((await page.locator('.drop-outcome').first().innerText()).startsWith('9%'),'Dungeon row shows actual chance, not just pool share');
  await page.locator('[name="config[item_chance]"]').fill('0');assert.equal(await page.locator('[data-drop-active]').innerText(),'0');
  await page.goto(base+'/admin/rewards?type=farm');
  await page.context().addCookies([{name:'conquer_locale',value:'de',url:base}]);
  await page.goto(base+'/admin/items');await page.locator('[data-catalog-search]').fill('xyz-not-an-item');assert(await page.locator('[data-catalog-empty]').isVisible());await page.locator('[data-catalog-search]').fill('10103001');assert.equal(await page.locator('.catalog-item:visible').count(),1);
  if(!await page.locator('form[action$="/admin/logout"] button').isVisible()) await page.locator('.mobile-menu').click();
  await Promise.all([page.waitForURL('**/admin/login'),page.locator('form[action$="/admin/logout"] button').click()]);await page.locator("[name=identifier], [name=username]").fill('RewardModerator');await page.locator('[name=password]').fill('Fixture-Reward-123!');await page.getByRole('button',{name:'Anmelden',exact:true}).click();await page.waitForURL(base+'/admin');await page.goto(base+'/admin/rewards?type=dungeon');assert(await page.locator('.reward-editor .admin-form button[type=submit]').isDisabled());assert(await page.locator('[data-add-drop]').isDisabled());
  await page.goto(base+'/admin/rewards?type=farm');assert(await page.locator('[data-batch-form] fieldset').evaluate(e=>e.disabled));assert(await page.locator('[data-batch-add]').first().isDisabled());assert(await page.locator('[data-batch-save]').isDisabled());
  assert.deepEqual(errors,[],'No browser errors');console.log('ALL ADMIN BROWSER CHECKS PASSED · '+out);
 }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1;});
