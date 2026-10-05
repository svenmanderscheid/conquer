'use strict';
// Exercise both relic renderers in the real app with a disposable account.
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
  let reference;
  for(const locale of ['en','de','fr']){
   if(await page.evaluate(()=>window.ConquerLocale.locale)!==locale){
    await Promise.all([page.waitForNavigation({waitUntil:'domcontentloaded'}),page.evaluate(locale=>window.ConquerLocale.setLocale(locale),locale)]);
    await page.locator('#app-start').waitFor({state:'detached'});
   }
   for(const [width,height] of [[1280,800],[390,844],[320,568],[844,390],[568,320]]){
    console.log('Checking',locale,`${width}x${height}`);
    await page.setViewportSize({width,height});
    for(let n=0;n<4&&await page.locator('dialog[open]').count();n++)await page.keyboard.press('Escape');
    await page.locator('#hud-menu').click();
    await page.locator('#game-dialog [data-action="dialog-tab"][data-id="treasures"]').click();
    await page.locator('.treasury-card').first().waitFor();
    reference=await page.locator('.treasury-card').evaluateAll(nodes=>Object.fromEntries(nodes.map(n=>[n.dataset.id,{name:n.querySelector('.treasury-card-name')?.textContent||n.title,image:n.querySelector('.treasury-tile>img').getAttribute('src'),grade:[...n.classList].find(c=>c.startsWith('grade-')).slice(6)}])));
    assert.equal(Object.keys(reference).length,71);
    assert(reference[60100001].image.includes('/relics-v4-storybook/rel-001-kornhorn-der-ernte.png'));
    assert(reference[60100002].image.includes('/relics-v4-storybook/rel-006-axt-des-gruenhains.png'));
    assert.equal(reference[60300001].grade,'legendary');assert.equal(reference[60400001].grade,'epic');
    await page.keyboard.press('Escape');
    await page.locator('#navigation [data-id="inventory"]').click();
    // The existing compatibility renderer remains callable although current
    // navigation puts relics in their own window. Invoke its real app listener.
    await page.locator('.inventory-category-tabs').evaluate(el=>{const b=document.createElement('button');b.dataset.action='inventory-category';b.dataset.id='treasures';b.textContent='Relics';el.append(b);});
    await page.locator('[data-action="inventory-category"][data-id="treasures"]').click();
    await page.locator('[data-action="inventory-view"][data-id="collection"]').click();
    const cards=page.locator('.inventory-page-grid [data-action="treasure-dialog"]');
    await cards.first().waitFor();
    assert.equal(await cards.count(),71);
    const actual=await cards.evaluateAll(nodes=>Object.fromEntries(nodes.map(n=>[n.dataset.id,{name:n.title,image:n.querySelector('.loot-art>img').getAttribute('src'),grade:[...n.querySelector('.loot-tile').classList].find(c=>c.startsWith('grade-')).slice(6)}])));
    assert.deepEqual(actual,reference,'Names, portraits and rarity match the treasury for all 71 relics');
    assert.equal(await page.locator('.inventory-relic-board .is-framed,.inventory-relic-board .is-reference').count(),0,'Painted portraits never receive a legacy crop/frame');
    await page.locator('.inventory-relic-board img').evaluateAll(images=>Promise.all(images.map(i=>{i.loading='eager';return i.decode();})));
    for(const code of [60100001,60100002,60300001,60400001]){
     const card=page.locator(`.inventory-page-grid [data-action="treasure-dialog"][data-id="${code}"]`);await card.scrollIntoViewIfNeeded();await card.tap();
     assert.equal(await page.locator('#inventory-details h2').textContent(),reference[code].name);
     assert.equal(await page.locator('#inventory-details .loot-art>img').getAttribute('src'),reference[code].image);
     assert.equal(await page.locator('#inventory-details .loot-tile').getAttribute('class').then(c=>c.includes('grade-'+reference[code].grade)),true);
     const detail=await page.locator('#inventory-details').innerText();
     assert(!detail.includes('lumber_gathering_speed')&&!detail.includes('lumber_protection_capacity'),'Bonus labels are translated');
     assert(detail.includes(String(byCode[code].level)),'Server level remains visible');
     assert.equal(await page.locator('#game-dialog').evaluate(d=>d.open),false,'Details stay inside inventory');
    }
    const geometry=await page.locator('#panel-dialog').evaluate(e=>{const r=e.getBoundingClientRect();return {outside:r.left<0||r.top<0||r.right>innerWidth+2||r.bottom>innerHeight+2,overflow:e.scrollWidth-e.clientWidth};});
    assert(!geometry.outside&&geometry.overflow<=2,`${width}x${height} inventory fits`);
    if(locale==='de')await page.screenshot({path:path.join(output,`${width}x${height}.png`)});
    await page.locator('[data-action="inventory-view"][data-id="equipment"]').tap();
    for(const i of state.treasures.items.filter(i=>i.equipped_slot)){
     const slot=page.locator(`.relic-slot[data-id="${i.treasure_code}"]`);
     assert.equal(await slot.locator('strong').textContent(),reference[i.treasure_code].name);
     assert.equal(await slot.locator('.loot-art>img').getAttribute('src'),reference[i.treasure_code].image);
    }
    await page.locator('[data-action="inventory-view"][data-id="bonuses"]').tap();
    assert(!/lumber_gathering_speed|lumber_protection_capacity/.test(await page.locator('#inventory-body').innerText()));
   }
  }
  assert.deepEqual(errors,[]);assert.deepEqual(writes,[],'Display inspection never changes player state');
  fs.writeFileSync(path.join(output,'verification.json'),JSON.stringify({passed:true,relics:71,locales:3,viewports:5,browserErrors:errors,writes},null,2));
  console.log('PASS inventory relic identity: 71 relics, 3 languages, 5 viewports; collection, details and equipment. '+output);
 }catch(error){
  if(page){await page.screenshot({path:path.join(output,'failure.png')});console.error(await page.locator('#content').innerText());}
  throw error;
 }finally{
  if(browser)await browser.close();
  if(fixture&&fixture.exitCode===null){fixture.stdin.end('\n');await new Promise(resolve=>fixture.once('exit',resolve));}
 }
})().catch(e=>{console.error(e);process.exitCode=1;});
