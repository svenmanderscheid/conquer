'use strict';
// Uses only admin_world_delete.php's disposable loopback fixture.
const assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path');
const {chromium}=require(process.env.PLAYWRIGHT_MODULE||'playwright');
const base=process.argv[2];assert(/^http:\/\/127\.0\.0\.1:\d+$/.test(base));
const out=path.resolve(__dirname,'../output/playwright/admin-world-delete');fs.mkdirSync(out,{recursive:true});
(async()=>{
 const browser=await chromium.launch({headless:true,channel:process.env.PLAYWRIGHT_CHANNEL||'chrome'});
 const context=await browser.newContext({viewport:{width:1280,height:800}});
 const page=await context.newPage(),errors=[];page.on('pageerror',e=>errors.push(e.message));
 async function login(target,name){await target.goto(base+'/admin/login');await target.locator('[name=username]').fill(name);await target.locator('[name=password]').fill('Fixture-World-123!');await Promise.all([target.waitForURL(base+'/admin'),target.locator('button[type=submit]').click()]);}
 try{
  await login(page,'WorldAdmin');
  await page.locator('a[href$="#world-delete"]').click();await page.locator('#world-delete').waitFor();
  assert.equal(await page.locator('#world-delete button[type=submit]').isDisabled(),true,'Running world is protected');
  const titles={en:'Delete world',de:'Welt löschen',fr:'Supprimer le monde'};
  for(const locale of ['en','de','fr']){
   await page.setViewportSize({width:1280,height:800});await page.locator('[data-locale-select]').first().selectOption(locale);
   await page.waitForFunction(expected=>document.documentElement.lang===expected,locale);
   for(const [width,height] of [[1280,800],[390,844],[320,568],[844,390],[568,320]]){
    await page.setViewportSize({width,height});await page.goto(base+'/admin/world?world_id=3#world-delete');
    await page.locator('#world-delete').waitFor();await page.locator('#world-delete').scrollIntoViewIfNeeded();
    assert.equal(await page.locator('#world-delete-title').innerText(),titles[locale]);
    assert.match(await page.locator('#world-delete-name-help').innerText(),/Browser Realm/,'World name remains unchanged');
    assert.equal(await page.locator('.notice.error').count(),0,'World settings render without errors');
    const bounds=await page.locator('#world-delete').evaluate(el=>{
     const elements=[el,...el.querySelectorAll('input,button,label')];
     return {overflow:document.documentElement.scrollWidth>innerWidth+1,clipped:elements.some(e=>{const r=e.getBoundingClientRect();return r.width>0&&(r.left<0||r.right>innerWidth+1);}),buttonHeight:el.querySelector('button').getBoundingClientRect().height,scrollbar:getComputedStyle(document.documentElement).scrollbarWidth,warning:getComputedStyle(el.querySelector('.world-delete-warning')).backgroundColor};
    });
    assert.equal(bounds.overflow,false,`${locale} ${width}: no horizontal page overflow`);assert.equal(bounds.clipped,false,`${locale} ${width}: all controls fit`);assert(bounds.buttonHeight>=44);assert.equal(bounds.scrollbar,'none');
    assert.equal(bounds.warning,'rgb(255, 240, 240)','Danger warning retains semantic colors');
    await page.screenshot({path:path.join(out,`${locale}-${width}x${height}.png`)});
   }
  }
  await page.locator('[data-locale-select]').first().selectOption('en');await page.waitForFunction(()=>document.documentElement.lang==='en');await page.goto(base+'/admin/world?world_id=3#world-delete');
  const form=page.locator('form[action$="/world-delete"]');
  await form.locator('[name=reason]').fill('Isolated browser deletion');await form.locator('[name=confirm_name]').fill('Browser Realm');
  assert.equal(await form.evaluate(el=>el.checkValidity()),false,'Unchecked acknowledgement prevents submission');
  await form.locator('[name=confirm_delete]').check();assert.equal(await form.evaluate(el=>el.checkValidity()),true);
  const invalid=await form.evaluate(el=>Object.fromEntries(new FormData(el)));invalid.csrf_token='invalid';
  assert.equal((await page.request.post(base+'/admin/action/world-delete',{form:invalid})).status(),403,'CSRF enforced');
  assert.equal((await page.request.get(base+'/admin/action/world-delete')).status(),405,'GET cannot delete');
  await form.locator('[name=confirm_name]').fill('Wrong name');await Promise.all([page.waitForNavigation(),form.locator('button[type=submit]').click()]);
  await page.locator('.notice.error').waitFor();assert.match(await page.locator('.notice.error').innerText(),/exact world name/);assert.equal(new URL(page.url()).searchParams.get('world_id'),'3');
  const moderator=await browser.newContext();const modPage=await moderator.newPage();await login(modPage,'WorldModerator');await modPage.goto(base+'/admin/world?world_id=3');
  assert.equal(await modPage.locator('form[action$="/world-delete"]').count(),0,'Moderator has no deletion form');
  assert.equal((await modPage.request.post(base+'/admin/action/world-delete',{form:invalid})).status(),403,'Moderator cannot call endpoint');await moderator.close();
  const ready=page.locator('form[action$="/world-delete"]');await ready.locator('[name=reason]').fill('Isolated browser deletion');await ready.locator('[name=confirm_name]').fill('Browser Realm');await ready.locator('[name=confirm_delete]').check();
  const payload=await ready.evaluate(el=>Object.fromEntries(new FormData(el)));
  await Promise.all([page.waitForNavigation(),ready.locator('button[type=submit]').click()]);
  assert.equal(new URL(page.url()).searchParams.get('world_id'),'1','Redirect selects a surviving world');assert.equal(await page.locator('.notice.error').count(),0);assert.match(await page.locator('.notice.success').innerText(),/Browser Realm.*permanently deleted/);
  assert.equal(await page.locator('[name=world_id] option[value="3"]').count(),0,'Deleted world removed from selector');assert.equal(await page.locator('#world-delete button[type=submit]').isDisabled(),true,'Final world cannot be deleted');
  const replay=await page.request.post(base+'/admin/action/world-delete',{form:payload});assert.equal(replay.status(),200);assert.match(await replay.text(),/Browser Realm.*permanently deleted/);
  assert.deepEqual(errors,[]);console.log('PASS admin world deletion: 15 responsive/localized views, confirmation, CSRF, roles, deletion, redirect and replay');
 }finally{await browser.close();}
})().catch(error=>{console.error(error);process.exitCode=1;});
