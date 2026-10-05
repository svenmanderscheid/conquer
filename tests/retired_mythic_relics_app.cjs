'use strict';
// Uses the real main app served by tools/preview-feature-fixture.php.
require('./fixtures/browser_locale.cjs')('en');
const assert=require('assert/strict'),fs=require('fs'),path=require('path'),os=require('os');
const {chromium}=require(process.env.PLAYWRIGHT_MODULE||'playwright');
const base=process.env.RELIC_FIXTURE_URL||'http://127.0.0.1:18984';
assert(/^http:\/\/127\.0\.0\.1:\d+$/.test(base));
const output=fs.mkdtempSync(path.join(os.tmpdir(),'conquer-retired-relics-app-'));
(async()=>{
 const browser=await chromium.launch({headless:true,channel:process.env.PLAYWRIGHT_CHANNEL||'chrome'});
 try{
  const page=await browser.newPage({viewport:{width:1280,height:800},hasTouch:true}),errors=[];
  page.on('pageerror',e=>errors.push(e.message));page.setDefaultTimeout(20000);
  await page.goto(base);
  if(await page.locator('[data-auth-target="login"]').count())await page.locator('[data-auth-target="login"]').first().click();
  await page.locator('[name="identifier"], [name="username"]').first().fill('PreviewPlayer');
  await page.locator('[name="password"]').fill('PreviewFixture!2026');
  await Promise.all([page.waitForURL('**/city'),page.locator('form[action$="/auth/local"] button[type="submit"], #auth-submit').first().click()]);
  await page.locator('#app-start').waitFor({state:'detached'});
  for(const [width,height] of [[1280,800],[390,844],[320,568],[844,390],[568,320]]){
   await page.setViewportSize({width,height});
   for(let i=0;i<4&&await page.locator('dialog[open]').count();i++)await page.keyboard.press('Escape');
   await page.locator('#hud-menu').click();await page.locator('#game-dialog [data-action="dialog-tab"][data-id="treasures"]').click();
   await page.locator('.treasury-card').first().waitFor();
   assert.equal(await page.locator('.treasury-card').count(),71);
   assert.equal(await page.locator('.grade-mythic').count(),0);
   const codes=await page.locator('.treasury-card').evaluateAll(nodes=>nodes.map(n=>Number(n.dataset.id)));
   assert(!codes.some(c=>[60400003,60500001,60500101,60500102,60500103,60500104,60500105].includes(c)));
   const geometry=await page.locator('#panel-dialog').evaluate(e=>{const r=e.getBoundingClientRect();return {outside:r.left<0||r.top<0||r.right>innerWidth+2||r.bottom>innerHeight+2,overflow:e.scrollWidth-e.clientWidth};});
   assert(!geometry.outside&&geometry.overflow<=2,`${width}x${height} relic window fits`);
   await page.locator('.treasury-card').first().click();
   await page.locator('.treasury-detail').waitFor();assert.equal(await page.locator('.treasury-effect-row').count(),2);
   await page.locator('[data-action="treasury-detail-close"]').click();
   await page.locator('.treasury-card').last().evaluate(e=>e.scrollIntoView({block:'center'}));
   assert(await page.locator('.treasury-scroll').evaluate(e=>e.scrollTop>0));
   await page.locator('.treasury-shell img').evaluateAll(images=>Promise.all(images.map(i=>{i.loading='eager';return i.decode().catch(()=>{});})));
   assert.deepEqual(await page.locator('.treasury-shell img').evaluateAll(images=>images.filter(i=>!i.naturalWidth).map(i=>i.src)),[]);
   await page.screenshot({path:path.join(output,`${width}x${height}.png`)});
  }
  assert.deepEqual(errors,[]);console.log(JSON.stringify({passed:true,activeRelics:71,mythicRelics:0,viewports:5,browserErrors:errors,output}));
 }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1;});
