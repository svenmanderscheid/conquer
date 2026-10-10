'use strict';
const assert=require('assert/strict'),fs=require('fs'),path=require('path');
module.exports=async({page,base,errors})=>{
 const out=path.join(__dirname,'../output/playwright/admin-modern');fs.mkdirSync(out,{recursive:true});
 page.on('dialog',dialog=>dialog.accept());
 const source=key=>page.locator(`[data-batch-source="${key}"]`);
 const group=(key,kind='fragment_rows')=>source(key).locator(`[data-batch-group="${kind}"]`);
 async function save(){await page.locator('[data-batch-form] [name=reason]').fill('Isolated modern admin browser check');await Promise.all([page.waitForNavigation(),page.locator('[data-batch-save]').click()]);await page.locator('[data-batch-editor]').waitFor();assert.equal(await page.locator('.notice.error').count(),0);}
 async function fit(label){const metrics=await page.evaluate(()=>({overflow:document.documentElement.scrollWidth-innerWidth,font:getComputedStyle(document.body).fontFamily,background:getComputedStyle(document.body).backgroundColor,scrollbars:getComputedStyle(document.body).scrollbarWidth}));assert(metrics.overflow<=1,label+JSON.stringify(metrics));assert(metrics.font.includes('system-ui'),label+' uses a practical administration font');assert.equal(metrics.scrollbars,'none');assert.equal(await page.locator('.notice.error').count(),0,label+' rendered');assert.equal(await page.locator('.sidebar nav a:not(.active)').first().evaluate(e=>getComputedStyle(e).color),'rgb(28, 39, 56)','Unselected navigation has readable contrast');assert.equal(await page.locator('.brand').evaluate(e=>getComputedStyle(e).color),'rgb(28, 39, 56)','Wordmark has readable contrast');return metrics;}
 await page.screenshot({path:path.join(out,'overview-desktop.png'),fullPage:true});
 assert.equal(await page.locator('[data-admin-area]').count(),8);
 for(const route of ['/admin/players','/admin/activity','/admin/cases','/admin/technical','/admin/alliances','/admin/world','/admin/world-create','/admin/lands','/admin/items','/admin/alpha-keys','/admin/alpha-waitlist','/admin/chat','/admin/bug-reports','/admin/audit','/admin/analytics','/admin/layout']){
  const response=await page.goto(base+route+'?world_id=1');assert.equal(response.status(),200,route);await fit(route);
 }
 await page.goto(base+'/admin/rewards?type=farm&world_id=1');await page.locator('[data-batch-count]').filter({hasText:/\d/}).waitFor();
 const scroll=page.locator('.batch-table-wrap');assert(await scroll.evaluate(e=>e.scrollHeight>e.clientHeight&&e.clientHeight<=innerHeight*.6+2),'Long drop lists keep save controls within reach');
 await scroll.focus();assert(await scroll.evaluate(e=>document.activeElement===e),'Drop list receives keyboard focus');await page.keyboard.press('PageDown');await page.waitForFunction(()=>document.querySelector('.batch-table-wrap').scrollTop>0,null,{timeout:3000});await scroll.evaluate(e=>e.scrollTop=0);
 await group('20100101.1').locator('[data-field=quantity]').first().fill('5');
 await group('20100101.1').locator('[data-field=chance]').first().fill('7.5');
 await group('20100101.2').locator('[data-field=quantity]').first().fill('3');
 await group('20100101.2').locator('[data-field=chance]').first().fill('9.25');
 assert((await group('20100101.1').locator('output').first().innerText()).includes('37,5'));
 assert((await page.locator('[data-batch-status]').innerText()).startsWith('2'));
 await save();
 assert.equal(await group('20100101.1').locator('[data-field=chance]').first().inputValue(),'7.5');
 assert.equal(await group('20100101.2').locator('[data-field=chance]').first().inputValue(),'9.25');
 assert.equal(await page.locator('[data-batch-save]').isDisabled(),true);
 await page.locator('[data-batch-category=speedups]').click();
 await group('20100101.1','rows').locator('[data-batch-add]').click();
 let added=group('20100101.1','rows').locator('.batch-entry').last();
 await added.locator('select').selectOption('10103002');await added.locator('[data-field=quantity]').fill('2');await added.locator('[data-field=chance]').fill('15');
 await save();await page.locator('[data-batch-category=speedups]').click();
 assert.equal(await group('20100101.1','rows').locator('[data-field=target]').last().inputValue(),'10103002');
 assert.equal(await group('20100101.1').locator('[data-field=quantity]').first().inputValue(),'5','Item table edits preserve direct fragments');
 await page.locator('[data-batch-category=items]').click();
 await page.locator('[data-modern-context] [name=scope]').selectOption('world');
 await Promise.all([page.waitForNavigation(),page.locator('[data-modern-context] button').click()]);
 assert.equal(await group('20100101.1').locator('[data-field=quantity]').first().inputValue(),'7');
 await group('20100101.1').locator('[data-field=quantity]').first().fill('9');await save();
 await page.locator('[data-modern-context] [name=scope]').selectOption('global');await Promise.all([page.waitForNavigation(),page.locator('[data-modern-context] button').click()]);
 assert.equal(await group('20100101.1').locator('[data-field=quantity]').first().inputValue(),'5','World save preserves global rules');
 // Malformed rejected input must never break the next editor render.
 const sourceData=await page.locator('[data-batch-data]').evaluate(e=>JSON.parse(e.textContent).sources.find(s=>s.key==='20100101.1'));
 for(const payload of ['{"invalid":null}','[null]',JSON.stringify([{source_key:sourceData.key,revision:sourceData.revision,config:{fragment_rows:[null,{target:{bad:true},quantity:[],chance:101}]}}])]){
  await page.locator('[data-batch-form]').evaluate(async(f,json)=>{const body=new FormData(f);body.set('reason','Malformed draft recovery check');body.set('updates_json',json);await fetch(f.action,{method:'POST',body});},payload);
  await page.reload();assert(await page.locator('[data-batch-editor]').isVisible());assert(await page.locator('[data-batch-status]').innerText());
  await page.goto(base+'/admin/rewards?type=farm&world_id=1&discard=1');await fit('Recovered malformed draft');
 }
 const formats=[['en',1440,1000],['de',390,844],['en',320,700],['fr',568,320],['de',844,390]];
 for(const [locale,width,height] of formats){
  await page.context().addCookies([{name:'conquer_locale',value:locale,url:base}]);await page.setViewportSize({width,height});
  for(const type of ['farm','monster']){
   await page.goto(base+`/admin/rewards?type=${type}&world_id=1`);await page.locator('[data-batch-count]').filter({hasText:/\d/}).waitFor();
   await page.locator('[data-batch-category=items]').click();
   await fit(`${type} ${width}`);await page.locator('[data-batch-search]').fill(type==='farm'?(locale==='fr'?'Mine':locale==='de'?'mine':'mine'):'Or');
   assert(await page.locator('.batch-source:visible').count()>0);await page.locator('[data-batch-search]').fill('');
   await page.locator('[data-batch-level]').selectOption('1');assert(await page.locator('.batch-source:visible').count()>0);
   const row=page.locator('.batch-source:visible').first(),fragment=row.locator('[data-batch-group=fragment_rows]');
   await fragment.locator('[data-batch-add]').click();const add=fragment.locator('.batch-entry').last();await add.locator('select').selectOption('fragment:epic');
   await add.locator('[data-field=quantity]').fill('2');await add.locator('[data-field=chance]').fill('10');
   assert.equal(await add.locator('output').innerText(),'20');await add.locator('[data-batch-remove]').click();
   await fit(`${type} editor ${width}`);await page.screenshot({path:path.join(out,`${type}-${locale}-${width}.png`),fullPage:false});
   if(width<900){await page.locator('.mobile-menu').click();assert.equal(await page.locator('#admin-nav [data-admin-area]:visible').count(),8);assert(await page.locator('.sidebar-bottom button').isVisible());await page.locator('.mobile-menu').click();}
  }
  await page.goto(base+'/admin/rewards?type=farm&source=20100101.1&world_id=1');await fit(`detail ${width}`);await page.screenshot({path:path.join(out,`detail-${locale}-${width}.png`),fullPage:false});
 }
 assert.deepEqual(errors,[]);console.log('MODERN ADMIN PASSED: navigation, item and fragment batch saves, world isolation, direct details, three languages and five viewport sizes.');
};
