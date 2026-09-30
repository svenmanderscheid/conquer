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
  assert.equal(await page.locator('.quick-action').count(),4);
  if(process.env.ADMIN_TABLE_ONLY==='1'){
   for(const width of [1440,390,320,568]){
    await page.setViewportSize({width,height:width===568?320:844});await page.goto(base+'/admin/rewards?type=monster');
    await page.locator('.monster-table-scroll').evaluate(async e=>{e.scrollIntoView({block:'start'});e.scrollLeft=e.scrollWidth;await new Promise(r=>requestAnimationFrame(()=>requestAnimationFrame(r)));});
    const frozen=await page.locator('.monster-reward-table tbody th').first().evaluate(e=>{const box=e.getBoundingClientRect(),area=e.closest('.monster-table-scroll').getBoundingClientRect();return {left:box.left,edge:area.left,hit:document.elementFromPoint(box.left+10,box.top+10)?.closest('th')===e};});
    assert(Math.abs(frozen.left-frozen.edge)<3&&frozen.hit,'Frozen monster column stays visible: '+JSON.stringify(frozen));
    await page.screenshot({path:path.join(out,`monster-table-verified-${width}.png`)});
   }
   assert.deepEqual(errors,[]);console.log('MONSTER TABLE CHECKS PASSED');return;
  }
  await page.goto(base+'/admin/rewards?type=monster');
  assert((await page.locator('.monster-reward-table tbody tr').count())>50,'Monster table compares the entire catalog');
  await page.locator('[data-source-level]').selectOption('3');
  assert((await page.locator('.reward-source-row:visible').count())>1,'Multiple monsters remain comparable at one level');
  assert(await page.locator('.reward-source-row:visible').evaluateAll(rows=>rows.every(row=>row.dataset.sourceLevelValue==='3')),'Level filter applies to table rows');
  await page.locator('[data-source-search]').fill('no-such-monster-fixture');assert(await page.locator('[data-source-empty]').isVisible());
  await page.locator('[data-source-search]').fill('');await page.locator('[data-source-level]').selectOption('');
  const more=page.locator('.monster-table-more').first();await more.locator('summary').click();assert(await more.evaluate(e=>e.open),'Additional drops expand inside their monster row');
  const edit=page.locator('.reward-source-row').nth(2).getByRole('link');const editUrl=await edit.getAttribute('href');
  await edit.click();await page.waitForURL(base+editUrl);assert.equal(await page.locator('#reward-editor').evaluate(e=>Math.abs(e.getBoundingClientRect().top-16)<8),true,'Edit jumps directly to selected monster editor');
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
    if(route==='/rewards?type=monster'){
     assert(await page.locator('[data-source-table]').evaluate(e=>e.open),'Comparison table remains open on all screens');
     await page.locator('.monster-reward-table img').evaluateAll(async images=>{await Promise.all(images.map(i=>{i.loading='eager';return i.decode().catch(()=>{});}));});
     await page.screenshot({path:path.join(out,`monster-table-${width}x${height}.png`)});
     if(width<700){
      await page.locator('.monster-table-scroll').evaluate(e=>e.scrollIntoView({block:'start'}));
      await page.screenshot({path:path.join(out,`monster-table-detail-${width}x${height}.png`)});
      await page.locator('.monster-table-scroll').evaluate(e=>e.scrollLeft=e.scrollWidth);assert((await page.locator('.monster-table-scroll').evaluate(e=>e.scrollLeft))>0,'Mobile table scrolls within its own region');
      await page.screenshot({path:path.join(out,`monster-table-actions-${width}x${height}.png`)});
     }
    }
    if(route===''||route.includes('dungeon')||route.includes('farm')||route==='/items'||route==='/lands'){
     await page.locator('img').evaluateAll(async images=>{await Promise.all(images.map(i=>{i.loading='eager';return i.decode().catch(()=>{});}));});
     await page.screenshot({path:path.join(out,`${route===''?'overview':route==='/items'?'items':route==='/lands'?'lands':route.includes('farm')?'farm':'dungeon'}-${width}x${height}.png`),fullPage:true});
    }
   }
   await page.goto(base+'/admin/rewards?type=dungeon');await page.locator('.drop-row [data-item-picker]').first().click();await page.locator('#item-picker-search').fill('Nahrung');
   assert((await page.locator('.picker-result').count())>0);await page.locator('.picker-result img').evaluateAll(async images=>{await Promise.all(images.map(i=>{i.loading='eager';return i.decode();}));});
   assert.equal(await page.locator('#item-picker-dialog').evaluate(e=>e.scrollWidth>e.clientWidth+2),false,'Picker does not overflow');
   await page.screenshot({path:path.join(out,`picker-${width}x${height}.png`)});await page.keyboard.press('Escape');assert.equal(await page.locator('#item-picker-dialog').evaluate(e=>e.open),false);
   if(width<850){await page.locator('.mobile-menu').click();assert.equal(await page.locator('.mobile-menu').getAttribute('aria-expanded'),'true');await page.locator('#admin-nav a').filter({hasText:'Gegenstände'}).click();await page.waitForURL('**/admin/items?world_id=1');}
  }
  await page.goto(base+'/admin/rewards?type=chest&source=gold');const rows=await page.locator('.drop-row').count();assert(rows>100,'Large chest table is complete');await page.locator('[data-drop-search]').fill('Nahrung');assert(await page.locator('.drop-row:visible').count()<rows);assert(!(await page.locator('[data-save-status]').innerText()).includes('Ungespeicherte'),'Filtering does not mark config dirty');
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
  await page.locator('[data-source-rule]').selectOption('custom');assert.equal(await page.locator('.source-choice:visible').count(),1,'Customized filter finds saved farm rule');
  await page.context().addCookies([{name:'conquer_locale',value:'en',url:base}]);
  await page.goto(base+'/admin/rewards?type=dungeon');
  assert.equal(await page.locator('.reward-category-tabs a').filter({hasText:'Farms & resources'}).count(),1,'New UI has an English version');
  const weights=page.locator('.drop-row input[name$="[weight]"]');for(const input of await weights.all())await input.fill('1');
  await page.locator('[name="config[item_chance]"]').fill('18');await page.locator('[name="config[item_quantity]"]').fill('2');
  assert.equal(await page.locator('[data-drop-expected]').innerText(),'36','Dungeon expectation includes outer chance and quantity');assert.equal(await page.locator('[data-drop-guaranteed]').innerText(),'0');
  assert((await page.locator('.drop-outcome').first().innerText()).startsWith('9%'),'Dungeon row shows actual chance, not just pool share');
  await page.locator('[name="config[item_chance]"]').fill('0');assert.equal(await page.locator('[data-drop-active]').innerText(),'0');
  page.once('dialog',dialog=>dialog.accept());
  await page.goto(base+'/admin/rewards?type=farm');
  await page.context().addCookies([{name:'conquer_locale',value:'de',url:base}]);
  await page.goto(base+'/admin/items');await page.locator('[data-catalog-search]').fill('xyz-not-an-item');assert(await page.locator('[data-catalog-empty]').isVisible());await page.locator('[data-catalog-search]').fill('10103001');assert.equal(await page.locator('.catalog-item:visible').count(),1);
  if(!await page.locator('form[action$="/admin/logout"] button').isVisible()) await page.locator('.mobile-menu').click();
  await Promise.all([page.waitForURL('**/admin/login'),page.locator('form[action$="/admin/logout"] button').click()]);await page.locator("[name=identifier], [name=username]").fill('RewardModerator');await page.locator('[name=password]').fill('Fixture-Reward-123!');await page.getByRole('button',{name:'Anmelden',exact:true}).click();await page.waitForURL(base+'/admin');await page.goto(base+'/admin/rewards?type=dungeon');assert(await page.locator('.reward-editor .admin-form button[type=submit]').isDisabled());assert(await page.locator('[data-add-drop]').isDisabled());
  assert.deepEqual(errors,[],'No browser errors');console.log('ALL ADMIN BROWSER CHECKS PASSED · '+out);
 }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1;});
