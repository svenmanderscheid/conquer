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
  await page.goto(base+'/admin/login');await page.locator('[name=username]').fill('RewardAdmin');await page.locator('[name=password]').fill('Fixture-Reward-123!');await page.getByRole('button',{name:'Anmelden',exact:true}).click();await page.waitForURL(base+'/admin');
  assert.equal(await page.locator('.quick-action').count(),4);
  await page.goto(base+'/admin/rewards?type=monster&source=20209901');
  await page.locator('[data-add-drop]').click();await page.locator('#item-picker-search').fill('10103003');await page.locator('[data-pick-item="10103003"]').click();
  await page.locator('[name="config[rows][0][quantity]"]').fill('8');await page.locator('[name="config[rows][0][chance]"]').fill('100');await page.locator('.reward-editor [name=reason]').fill('Browser save fixture');
  await page.getByRole('button',{name:'Beute speichern'}).click();await page.waitForURL('**/admin/rewards?world_id=1&type=monster&source=20209901&scope=global');
  assert((await page.locator('.notice.success').innerText()).includes('gespeichert'));
  assert.equal(await page.locator('[name="config[rows][0][quantity]"]').inputValue(),'8');
  await page.reload();assert.equal(await page.locator('[name="config[rows][0][target]"]').inputValue(),'10103003');
  const invalid=await page.locator('.reward-editor form').evaluate(async f=>{const data=new FormData(f);data.set('reason','Invalid fixture percentage');data.set('config[rows][0][chance]','101');return (await fetch(f.action,{method:'POST',body:data})).text();});
  assert(invalid.includes('0 bis 100 Prozent'),'Server rejects forged percentage');
  await page.reload();assert.equal(await page.locator('[name="config[rows][0][chance]"]').inputValue(),'101','Invalid draft is retained');
  await page.goto(base+'/admin/rewards?type=monster&source=20209901&discard=1');
  await page.locator('.reset-settings summary').click();await page.locator('.reset-settings [name=reason]').fill('Restore fixture defaults');await page.locator('.reset-settings button[type=submit]').click();await page.waitForURL('**/admin/rewards?world_id=1&type=monster&source=20209901&scope=global');assert.equal(await page.locator('.drop-row').count(),0);
  for(const [width,height] of [[1440,1000],[390,844],[320,700],[568,320]]){
   await page.setViewportSize({width,height});
   for(const route of ['','/rewards?type=monster','/rewards?type=dungeon','/rewards?type=chest&source=platinum','/rewards?type=expedition','/items','/world','/lands','/players','/bug-reports']){
    await page.goto(base+'/admin'+route);
    assert(!(await page.locator('main').innerText()).includes('Ansicht konnte nicht geladen'),'View renders '+route);
    const overflow=await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth+2);
    if(overflow){console.log('Overflow elements',await page.locator('main *').evaluateAll(es=>es.filter(e=>e.getBoundingClientRect().right>innerWidth+2).slice(0,10).map(e=>({tag:e.tagName,class:e.className,text:e.textContent.slice(0,65),right:e.getBoundingClientRect().right}))));await page.screenshot({path:path.join(out,'overflow.png'),fullPage:true});}
    assert.equal(overflow,false,`${route} overflow at ${width}x${height}`);
    if(route===''||route.includes('dungeon')||route==='/items'||route==='/lands')await page.screenshot({path:path.join(out,`${route===''?'overview':route==='/items'?'items':route==='/lands'?'lands':'dungeon'}-${width}x${height}.png`),fullPage:true});
   }
   await page.goto(base+'/admin/rewards?type=dungeon');await page.locator('.drop-row [data-item-picker]').first().click();await page.locator('#item-picker-search').fill('Nahrung');
   assert((await page.locator('.picker-result').count())>0);await page.locator('.picker-result img').evaluateAll(async images=>{await Promise.all(images.map(i=>{i.loading='eager';return i.decode();}));});
   assert.equal(await page.locator('#item-picker-dialog').evaluate(e=>e.scrollWidth>e.clientWidth+2),false,'Picker does not overflow');
   await page.screenshot({path:path.join(out,`picker-${width}x${height}.png`)});await page.keyboard.press('Escape');assert.equal(await page.locator('#item-picker-dialog').evaluate(e=>e.open),false);
   if(width<850){await page.locator('.mobile-menu').click();assert.equal(await page.locator('.mobile-menu').getAttribute('aria-expanded'),'true');await page.locator('#admin-nav a').filter({hasText:'Gegenstände'}).click();await page.waitForURL('**/admin/items?world_id=1');}
  }
  await page.goto(base+'/admin/rewards?type=chest&source=gold');const rows=await page.locator('.drop-row').count();assert(rows>100,'Large chest table is complete');await page.locator('[data-drop-search]').fill('Nahrung');assert(await page.locator('.drop-row:visible').count()<rows);assert(!(await page.locator('[data-save-status]').innerText()).includes('Ungespeicherte'),'Filtering does not mark config dirty');
  await page.goto(base+'/admin/items');await page.locator('[data-catalog-search]').fill('xyz-not-an-item');assert(await page.locator('[data-catalog-empty]').isVisible());await page.locator('[data-catalog-search]').fill('10103001');assert.equal(await page.locator('.catalog-item:visible').count(),1);
  await page.goto(base+'/admin/logout');await page.locator('[name=username]').fill('RewardModerator');await page.locator('[name=password]').fill('Fixture-Reward-123!');await page.getByRole('button',{name:'Anmelden',exact:true}).click();await page.waitForURL(base+'/admin');await page.goto(base+'/admin/rewards?type=dungeon');assert(await page.locator('.reward-editor button[type=submit]').isDisabled());assert(await page.locator('[data-add-drop]').isDisabled());
  assert.deepEqual(errors,[],'No browser errors');console.log('ALL ADMIN BROWSER CHECKS PASSED · '+out);
 }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1;});
