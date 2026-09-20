'use strict';
const assert=require('assert/strict');
const {chromium}=require(process.env.PLAYWRIGHT_MODULE||'playwright');
const base=process.env.HOSPITAL_FIXTURE_URL||'http://127.0.0.1:19318';
assert(/^http:\/\/127\.0\.0\.1:\d+$/.test(base));

(async()=>{
 const browser=await chromium.launch({headless:true,channel:'chrome'});let page;
 try{
  page=await browser.newPage({viewport:{width:390,height:844},hasTouch:true});page.setDefaultTimeout(20000);
  const errors=[];page.on('pageerror',e=>errors.push(e.stack));
  await page.goto(base);await page.locator('[data-auth-target="login"]').first().click();
  await page.locator('[name="username"]').fill('PreviewPlayer');await page.locator('[name="password"]').fill('PreviewFixture!2026');
  await Promise.all([page.waitForURL('**/city'),page.locator('#auth-submit').click()]);
  await page.locator('#navigation [data-id="world"]').click();await page.waitForFunction(()=>document.body.classList.contains('world-mode'));
  const trigger=page.locator('#hud-healing');await trigger.waitFor();
  assert.equal(await trigger.getAttribute('data-action'),'hospital-quick-heal');
  assert.match(await trigger.getAttribute('aria-label'),/Antippen, um sofort alle zu heilen/);
  assert.equal(await trigger.locator('.hud-heal-now').evaluate(e=>getComputedStyle(e).display),'grid');
  for(const [width,height] of [[1280,800],[390,844],[568,320]]){
   await page.setViewportSize({width,height});
   assert(await trigger.evaluate(e=>{const r=e.getBoundingClientRect();return r.width>=44&&r.height>=44&&r.left>=0&&r.top>=0&&r.right<=innerWidth+1&&r.bottom<=innerHeight+1;}),`healing shortcut remains touchable at ${width}x${height}`);
  }
  await page.setViewportSize({width:390,height:844});
  const response=page.waitForResponse(r=>r.url().endsWith('/api/hospital/heal')&&r.request().method()==='POST');
  await trigger.tap();const result=await response;assert.equal(result.status(),200,await result.text());
  await page.waitForFunction(()=>document.querySelector('#hud-healing')?.dataset.action==='army-hospital');
  assert.equal(await page.locator('#panel-dialog').evaluate(e=>e.open),false,'successful shortcut keeps the playfield visible');
  assert.match(await trigger.getAttribute('aria-label'),/Truppen in Behandlung/);
  await trigger.tap();await page.locator('.hospital-running').waitFor();
  assert.deepEqual(errors,[]);console.log('PASS one-tap healing starts all wounded troops and the active icon opens treatment details.');
 }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1;});
