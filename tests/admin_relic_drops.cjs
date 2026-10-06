'use strict';
// Executed through reward_admin.php against its disposable HTTP fixture only.
const assert=require('assert/strict'),fs=require('fs'),path=require('path');
module.exports=async({page,base,errors})=>{
 const out=path.join(__dirname,'../output/playwright/admin-relic-drops');fs.mkdirSync(out,{recursive:true});
 page.on('dialog',dialog=>dialog.accept());
 const source=(type)=>type==='monster'?'20209901':'20100101.1';
 const group=(type,kind)=>page.locator(`[data-batch-source="${source(type)}"] [data-batch-group="${kind}"]`);
 async function saved(button){await Promise.all([page.waitForNavigation(),button.click()]);assert.equal(await page.locator('.notice.error').count(),0,'Reward save succeeds');}
 async function fit(label){
  assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth+2),false,label+' fits viewport');
  assert.equal(await page.evaluate(()=>getComputedStyle(document.documentElement).scrollbarWidth),'none',label+' hides scrollbars');
  assert.equal(await page.locator('main').innerText().then(t=>t.includes('admin.drops.relic_')),false,label+' translates labels');
 }
 await page.context().addCookies([{name:'conquer_locale',value:'en',url:base}]);
 for(const type of ['monster','farm']){
  await page.goto(base+`/admin/rewards?type=${type}`);await page.locator('[data-batch-status]').filter({hasText:'No unsaved'}).waitFor();
  const fragments=await group(type,'fragment_rows').locator('[data-field=target]').evaluateAll(es=>es.map(e=>e.value));
  await page.locator('[data-batch-kind=relic_rows]').click();
  while(await group(type,'relic_rows').locator('[data-batch-remove]').count())await group(type,'relic_rows').locator('[data-batch-remove]').last().click();
  await group(type,'relic_rows').locator('[data-batch-add]').click();
  const row=group(type,'relic_rows').locator('.batch-entry').last();
  assert.equal(await row.locator('[data-field=chance]').inputValue(),'0','Whole relic starts disabled');
  await row.locator('select').selectOption('relic:60100001');await row.locator('[data-field=quantity]').fill('2');await row.locator('[data-field=chance]').fill('12.3456');
  assert.equal(await row.locator('output').innerText(),'24.6912','Whole relic expectation');
  assert.equal(await row.locator('option[value^="treasure:"]').count(),0,'Whole relic group never disguises fragments');
  await page.locator('[data-batch-form] [name=reason]').fill('Whole relic isolated browser check');await saved(page.locator('[data-batch-save]'));
  assert.deepEqual(await group(type,'fragment_rows').locator('[data-field=target]').evaluateAll(es=>es.map(e=>e.value)),fragments,'Whole relic changes preserve fragments');
  await page.locator('[data-batch-kind=relic_rows]').click();
  assert.equal(await group(type,'relic_rows').locator('[data-field=chance]').inputValue(),'12.3456','Precision survives batch save');
  await page.goto(base+`/admin/rewards?type=${type}&source=${source(type)}`);
  assert.equal(await page.locator('.relic-row select').inputValue(),'relic:60100001','Detail opens same whole relic');
  await page.locator('.relic-row input[name$="[quantity]"]').fill('3');
  await page.locator('.reward-editor [name=reason]').fill('Whole relic detail browser check');await saved(page.locator('.reward-editor form.admin-form button[type=submit]'));
  assert.equal(await page.locator('.relic-row input[name$="[quantity]"]').inputValue(),'3','Detail whole relic save survives reload');
  // Direct rewards cannot leak into ordinary inventory-item pickers.
  await page.locator('[data-add-drop]').click();await page.locator('#item-picker-search').fill('60100001');
  assert.equal(await page.locator('[data-pick-item="relic:60100001"],[data-pick-item="treasure:60100001"]').count(),0,'Item-only picker excludes direct relic awards');
  await page.locator('[data-picker-close]').click();await page.locator('.drop-row .remove-drop').last().click();
 }
 await page.goto(base+'/admin/rewards?type=chest&source=gold');
 while(await page.locator('.drop-row .remove-drop').count())await page.locator('.drop-row .remove-drop').last().click();
 for(const [target,quantity] of [['relic:60100001','1'],['treasure:60100001','3'],['fragment:epic','2']]){
  await page.locator('[data-add-drop]').click();await page.locator('#item-picker-search').fill(target);
  const choice=page.locator(`[data-pick-item="${target}"]`);assert.equal(await choice.count(),1,'Chest can choose '+target);
  await choice.click();const row=page.locator('.drop-row').last();await row.locator('input[name$="[quantity]"]').fill(quantity);await row.locator('input[name$="[weight]"]').fill('1');
 }
 assert((await page.locator('.drop-row').nth(0).innerText()).includes('Whole relic'),'Whole relic label remains explicit');
 assert((await page.locator('.drop-row').nth(1).innerText()).includes('Fragments'),'Specific fragment label remains explicit');
 await page.locator('.reward-editor [name=reason]').fill('Chest targeted relic browser check');await saved(page.locator('.reward-editor form.admin-form button[type=submit]'));
 assert.deepEqual(await page.locator('.drop-row input[name$="[target]"]').evaluateAll(es=>es.map(e=>e.value)),['relic:60100001','treasure:60100001','fragment:epic'],'Chest retains whole relic and exact/random fragment choices');
 const pictures=page.locator('.drop-row [data-item-picker] img');await pictures.evaluateAll(async es=>{await Promise.all(es.map(e=>e.decode()));});
 for(const [locale,width,height] of [['en',1440,1000],['de',320,700],['fr',568,320],['lb',390,844]]){
  await page.context().addCookies([{name:'conquer_locale',value:locale,url:base}]);await page.setViewportSize({width,height});
  for(const type of ['monster','farm']){
   await page.goto(base+`/admin/rewards?type=${type}`);await page.locator('[data-batch-kind=relic_rows]').click();await fit(type+' table '+width);
   await page.locator('[data-batch-search]').fill(source(type));await page.screenshot({path:path.join(out,`${type}-table-${locale}-${width}.png`)});
   await page.goto(base+`/admin/rewards?type=${type}&source=${source(type)}`);await page.locator('.reward-relics').scrollIntoViewIfNeeded();await fit(type+' detail '+width);
   assert(await page.locator('.relic-row').evaluateAll(rows=>rows.every(row=>[...row.querySelectorAll('select,input,button')].every(e=>{const r=e.getBoundingClientRect();return r.width>=44&&r.height>=44;}))),'Whole relic controls remain touchable');
   await page.screenshot({path:path.join(out,`${type}-detail-${locale}-${width}.png`)});
  }
  await page.goto(base+'/admin/rewards?type=chest&source=gold');await page.locator('.reward-items').scrollIntoViewIfNeeded();await fit('chest '+width);
  await page.locator('.drop-row [data-item-picker]').first().click();await page.locator('#item-picker-search').fill('60100001');
  assert.equal(await page.locator('[data-pick-item="relic:60100001"],[data-pick-item="treasure:60100001"]').count(),2,'Both named relic forms are available');
  assert.equal(await page.locator('#item-picker-dialog').evaluate(e=>e.scrollWidth>e.clientWidth+2),false,'Chest picker fits '+width);
  await page.locator('.picker-result img').evaluateAll(async es=>{await Promise.all(es.map(e=>e.decode()));});await page.screenshot({path:path.join(out,`chest-picker-${locale}-${width}.png`)});await page.locator('[data-picker-close]').click();
 }
 assert.deepEqual(errors,[],'No browser errors');
 console.log('RELIC ADMIN PASSED: monster/farm batch and detail saves, chest whole and specific/random fragments, picker boundaries, four languages and desktop/mobile/landscape.');
};
