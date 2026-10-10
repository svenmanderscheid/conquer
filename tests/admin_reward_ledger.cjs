'use strict';
const assert=require('assert'),fs=require('fs'),path=require('path');
const {chromium}=require(process.env.PLAYWRIGHT_MODULE||'playwright');
const base=process.argv[2];assert(/^http:\/\/127\.0\.0\.1:\d+$/.test(base),'Disposable fixture required');
const out=path.join(__dirname,'../output/playwright/admin-reward-ledger');fs.mkdirSync(out,{recursive:true});
(async()=>{
 const browser=await chromium.launch({headless:true,channel:process.env.PLAYWRIGHT_CHANNEL||'chrome'});
 const page=await browser.newPage();const errors=[];page.on('pageerror',e=>errors.push(e.message));
 try{
  await page.goto(base+'/admin/login');await page.locator('[name=username]').fill('LedgerAdmin');await page.locator('[name=password]').fill('Fixture-Ledger-123!');await page.locator('button[type=submit]').click();await page.waitForURL(base+'/admin');
  for(const [locale,width,height] of [['en',1440,1000],['de',390,844],['fr',844,390],['en',320,700]]){
   await page.context().addCookies([{name:'conquer_locale',value:locale,url:base}]);await page.setViewportSize({width,height});
   for(const tab of ['actual','invalid']){
    await page.goto(base+'/admin/rewards?tab='+tab+'&world_id=0');
    assert(await page.locator('[data-reward-receipt]').count()>0,'Receipts visible: '+tab);
    assert.equal(await page.locator('.reward-ledger-tabs [aria-current=page]').count(),1);
    assert.equal(await page.locator('[name=world_id]').last().inputValue(),'0','All-world filter retained');
    await page.locator('[data-reward-receipt] summary').first().click();
    assert(await page.locator('[data-reward-receipt][open] .reward-ledger-detail').isVisible());
    assert(!/admin\.reward_ledger\./.test(await page.locator('main').innerText()),'No untranslated reward keys');
    assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth+2),false,'No overflow '+width+' '+tab);
    assert.equal(await page.evaluate(()=>getComputedStyle(document.documentElement).scrollbarWidth),'none','Hidden scrollbars retained');
    await page.screenshot({path:path.join(out,`${tab}-${locale}-${width}x${height}.png`)});
   }
  }
  await page.goto(base+'/admin/rewards?tab=invalid&world_id=0');await page.locator('[name=item_code]').fill('99999999');await Promise.all([page.waitForURL('**item_code=99999999*'),page.locator('.reward-ledger-filters button').click()]);
  assert.equal(await page.locator('[data-reward-receipt]').count(),1,'Unknown item filter exact');
  await page.locator('[data-reward-receipt] summary').click();assert((await page.locator('.reward-ledger-detail').innerText()).includes('0'),'Rejected record shows zero credited');
  await page.locator('[name=player_id]').fill('999');await Promise.all([page.waitForURL('**player_id=999*'),page.locator('.reward-ledger-filters button').click()]);assert.equal(await page.locator('[data-reward-receipt]').count(),0,'Player filter excludes unrelated rewards');
  await Promise.all([page.waitForURL('**tab=rules*'),page.locator('.reward-ledger-tabs a').first().click()]);assert(await page.locator('[data-batch-editor]').count()>0,'Existing inline rules remain reachable');
  assert.deepEqual(errors,[],'No browser errors');console.log('REWARD HISTORY BROWSER CHECKS PASSED · '+out);
 }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exit(1);});
