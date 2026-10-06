'use strict';
// Read-only lookup in the disposable --monster-reports --relic-rewards preview.
const assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path');
const {chromium}=require(process.env.PLAYWRIGHT_MODULE||'C:/Users/svenm/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright');
const base=process.env.REWARD_FIXTURE_URL||'http://127.0.0.1:19017';
assert(/^http:\/\/127\.0\.0\.1:\d+$/.test(base),'Disposable preview required');
const output=path.resolve('output/playwright/relic-sources');fs.mkdirSync(output,{recursive:true});
(async()=>{
 const browser=await chromium.launch({headless:true,channel:'chrome'});
 try{
  const page=await browser.newPage({viewport:{width:1280,height:800},hasTouch:true}),errors=[];
  page.on('pageerror',error=>errors.push(error.message));
  await page.goto(base+'/?zugang=login');
  await page.locator('[name=identifier], [name=username]').fill('PreviewPlayer');await page.locator('[name=password]').fill('PreviewFixture!2026');
  await Promise.all([page.waitForURL(url=>url.pathname==='/city'),page.locator('form[action$="/auth/local"] button[type=submit]').click()]);
  await page.goto(base+'/city#city');await page.locator('#player-hud-name').filter({hasText:'PreviewPlayer'}).waitFor();
  await page.goto(base+'/city#treasures');await page.locator('.treasury-card[data-id="60100001"]').click();
  await page.locator('.treasury-inspector [data-action=item-sources]').click();await page.locator('.item-source-card').first().waitFor();
  assert.match(await page.locator('.item-sources-selected').innerText(),/Whole relics and fragments/);
  const selectedName=await page.evaluate(()=>ConquerRelicPresentation.name({treasure_code:60100001}));
  assert((await page.locator('.item-sources-selected').innerText()).startsWith(selectedName),'Source heading uses the selected relic\'s shared current name');
  const source=page.locator('.item-source-card').filter({has:page.locator('.item-source-reward',{hasText:'Whole relics: 2'})}).first();
  assert.equal(await source.count(),1,'Configured chest is a source for its exact relic: '+await page.locator('.item-source-card').allInnerTexts());
  assert.match(await source.locator('.item-source-reward').innerText(),/Whole relics: 2/i,'Whole relic quantity is not labelled as fragments');
  for(const [width,height]of[[1280,800],[390,844],[568,320]]){
   await page.setViewportSize({width,height});await source.scrollIntoViewIfNeeded();
   const layout=await page.locator('#game-dialog').evaluate(dialog=>{const r=dialog.getBoundingClientRect(),scroll=dialog.querySelector('.item-sources-scroll');return {fits:r.left>=0&&r.top>=0&&r.right<=innerWidth+1&&r.bottom<=innerHeight+1,overflow:dialog.scrollWidth>dialog.clientWidth+1||scroll.scrollWidth>scroll.clientWidth+1,scrollable:scroll.clientHeight>60};});
   assert.deepEqual(layout,{fits:true,overflow:false,scrollable:true},width+'x'+height);
   await page.screenshot({path:path.join(output,width+'x'+height+'.png')});
  }
  assert.deepEqual(errors,[]);console.log('PASS selected whole relic source, exact quantity, source labels and desktop/portrait/landscape layout. '+output);
 }finally{await browser.close();}
})().catch(error=>{console.error(error);process.exitCode=1;});
