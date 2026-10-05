'use strict';
// Relics remain in the Treasury, including when obsolete inventory events arrive.
const assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path'),net=require('node:net');
const {spawn}=require('node:child_process'),{chromium}=require(process.env.PLAYWRIGHT_MODULE||'playwright');
const root=path.resolve(__dirname,'..'),output=path.join(root,'output/playwright/inventory-relics');
fs.mkdirSync(output,{recursive:true});
(async()=>{
 let fixture,browser,page;
 try{
  const port=await new Promise(resolve=>{const server=net.createServer();server.listen(0,'127.0.0.1',()=>{const port=server.address().port;server.close(()=>resolve(port));});});
  fixture=spawn(process.env.PHP_BINARY||'C:/xampp/php/php.exe',[path.join(root,'tools/preview-feature-fixture.php'),'--port='+port,'--inventory-overview','--inventory-relics'],{cwd:root,stdio:['pipe','pipe','pipe'],windowsHide:true});
  await new Promise((resolve,reject)=>{let log='';fixture.stdout.on('data',data=>{log+=data;if(log.includes('Synthetic preview ready'))resolve();});fixture.stderr.on('data',data=>log+=data);fixture.on('error',reject);fixture.on('exit',()=>reject(Error(log)));});
  const base='http://127.0.0.1:'+port;
  browser=await chromium.launch({headless:true,channel:'chrome'});
  page=await browser.newPage({viewport:{width:1280,height:800},hasTouch:true});const errors=[],writes=[];
  page.on('pageerror',e=>{errors.push(e.message);console.error('Browser error:',e.message);});page.setDefaultTimeout(20000);
  await page.goto(base+'/?zugang=login');
  await page.locator('[name="identifier"], [name="username"]').first().fill('PreviewPlayer');
  await page.locator('[name="password"]').fill('PreviewFixture!2026');
  await Promise.all([page.waitForURL('**/city'),page.locator('form[action$="/auth/local"] button[type="submit"]').click()]);
  await page.locator('#app-start').waitFor({state:'detached'});
  page.on('request',r=>{if(r.method()==='POST')writes.push(r.url());});
  const state=await page.evaluate(async()=> (await (await fetch('/api/kingdom/state')).json()).data);
  const byCode=Object.fromEntries(state.treasures.items.map(i=>[i.treasure_code,i]));
  assert.equal(byCode[60100002].equipped_slot,2);
  const inventoryCategories=['resource_pack','speedup','boost','other'];
  async function checkInventory(){
   assert.equal(await page.locator('#panel-dialog').getAttribute('data-panel'),'inventory');
   assert.deepEqual(await page.locator('.inventory-category-tabs button').evaluateAll(nodes=>nodes.map(n=>n.dataset.id)),inventoryCategories);
   assert.equal(await page.locator('.inventory-category-tabs [aria-pressed="true"]').getAttribute('data-id'),'resource_pack');
   assert.equal(await page.locator('.inventory-views,.inventory-relic-board,.inventory-equipment-page,.inventory-bonus-page,[data-action="treasure-dialog"]').count(),0,'Inventory never renders the retired relic menu');
   assert(await page.locator('[data-action="inventory-item"]').count()>0,'Normal inventory items remain available');
  }
  for(const locale of ['en','de','fr']){
   if(await page.evaluate(()=>window.ConquerLocale.locale)!==locale){
    await Promise.all([page.waitForNavigation({waitUntil:'domcontentloaded'}),page.evaluate(locale=>window.ConquerLocale.setLocale(locale),locale)]);
    await page.locator('#app-start').waitFor({state:'detached'});
   }
   let localeReference;
   for(const [width,height] of [[1280,800],[390,844],[320,568],[844,390],[568,320]]){
    console.log('Checking',locale,`${width}x${height}`);
    await page.setViewportSize({width,height});
    for(let n=0;n<4&&await page.locator('dialog[open]').count();n++)await page.keyboard.press('Escape');
    await page.locator('#hud-menu').click();
    await page.locator('#game-dialog [data-action="dialog-tab"][data-id="treasures"]').click();
    await page.locator('.treasury-card').first().waitFor();
    const reference=await page.locator('.treasury-card').evaluateAll(nodes=>Object.fromEntries(nodes.map(n=>[n.dataset.id,{name:n.querySelector('.treasury-card-name')?.textContent||n.title,image:n.querySelector('.treasury-tile>img').getAttribute('src'),grade:[...n.classList].find(c=>c.startsWith('grade-')).slice(6)}])));
    assert.equal(Object.keys(reference).length,71);
    assert.deepEqual(Object.keys(reference).sort(),Object.keys(byCode).sort(),'All fixture relics remain in the Treasury');
    if(localeReference)assert.deepEqual(reference,localeReference,'All 71 identities stay consistent across viewports');
    else localeReference=reference;
    assert(reference[60100001].image.includes('/relics-v4-storybook/rel-001-kornhorn-der-ernte.png'));
    assert(reference[60100002].image.includes('/relics-v4-storybook/rel-006-axt-des-gruenhains.png'));
    assert.equal(reference[60300001].grade,'legendary');assert.equal(reference[60400001].grade,'epic');
    assert.equal(await page.locator('.treasury-card .is-framed,.treasury-card .is-reference').count(),0,'Painted portraits never receive a legacy crop/frame');
    await page.locator('.treasury-shell img').evaluateAll(images=>Promise.all(images.map(i=>{i.loading='eager';return i.decode();})));
    for(const i of state.treasures.items.filter(i=>i.equipped_slot)){
     const slot=page.locator(`.treasury-slot[data-id="${i.equipped_slot}"]`);
     assert.equal(await slot.locator('small').textContent(),reference[i.treasure_code].name);
     assert.equal(await slot.locator('.treasury-tile>img').getAttribute('src'),reference[i.treasure_code].image);
    }
    assert(!/lumber_gathering_speed|lumber_protection_capacity/.test(await page.locator('.treasury-active-bonuses').innerText()));
    for(const code of [60100001,60100002,60300001,60400001]){
     const card=page.locator(`.treasury-card[data-id="${code}"]`);await card.scrollIntoViewIfNeeded();await card.tap();
     assert.equal(await page.locator('#treasury-item-name').textContent(),reference[code].name);
     assert.equal(await page.locator('#treasury-inspector .treasury-tile>img').getAttribute('src'),reference[code].image);
     assert.equal(await page.locator('#treasury-inspector .treasury-tile').getAttribute('class').then(c=>c.includes('grade-'+reference[code].grade)),true);
     const detail=await page.locator('#treasury-inspector').innerText();
     assert(!detail.includes('lumber_gathering_speed')&&!detail.includes('lumber_protection_capacity'),'Bonus labels are translated');
     if(byCode[code].is_unlocked)assert((await page.locator('#treasury-inspector .treasury-tile-level').innerText()).includes(String(byCode[code].level)),'Server level remains visible');
     assert.equal(await page.locator('#game-dialog').evaluate(d=>d.open),false,'Relic details stay in the Treasury');
     await page.locator('[data-action="treasury-detail-close"]').tap();
    }
    if(locale==='de')await page.screenshot({path:path.join(output,`treasury-${width}x${height}.png`)});
    await page.keyboard.press('Escape');
    await page.locator('#navigation [data-id="inventory"]').click();
    await page.locator('[data-action="inventory-category"][data-id="resource_pack"]').tap();
    await checkInventory();
    // Replay both obsolete entry paths through the real application listener.
    for(const action of ['inventory-category','panel-tab']){
     await page.locator('.inventory-category-tabs').evaluate((el,action)=>{
      const b=document.createElement('button');b.dataset.action=action;b.dataset.group='inventory';b.dataset.id='treasures';
      el.append(b);b.click();b.remove();
     },action);
     await checkInventory();
    }
    const geometry=await page.locator('#panel-dialog').evaluate(e=>{const r=e.getBoundingClientRect();return {outside:r.left<0||r.top<0||r.right>innerWidth+2||r.bottom>innerHeight+2,overflow:e.scrollWidth-e.clientWidth};});
    assert(!geometry.outside&&geometry.overflow<=2,`${width}x${height} inventory fits`);
    if(locale==='de')await page.screenshot({path:path.join(output,`inventory-${width}x${height}.png`)});
   }
  }
  assert.deepEqual(errors,[]);assert.deepEqual(writes,[],'Display inspection never changes player state');
  fs.writeFileSync(path.join(output,'verification.json'),JSON.stringify({passed:true,relics:71,locales:3,viewports:5,browserErrors:errors,writes},null,2));
  console.log('PASS relic separation: 71 Treasury relics, details and equipment; no inventory relic menu through either legacy entry, 3 languages, 5 viewports. '+output);
 }catch(error){
  if(page){await page.screenshot({path:path.join(output,'failure.png')});console.error(await page.locator('#content').innerText());}
  throw error;
 }finally{
  if(browser)await browser.close();
  if(fixture&&fixture.exitCode===null){fixture.stdin.end('\n');await new Promise(resolve=>fixture.once('exit',resolve));}
 }
})().catch(e=>{console.error(e);process.exitCode=1;});
