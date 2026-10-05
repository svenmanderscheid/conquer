'use strict';
// Run only against tools/preview-feature-fixture.php --territory on an isolated port.
const assert=require('assert'),fs=require('fs'),path=require('path');
const {chromium}=require(process.env.PLAYWRIGHT_MODULE||'playwright');
const base=process.env.TERRITORY_FIXTURE_URL||'http://127.0.0.1:18946';assert(/^http:\/\/127\.0\.0\.1:\d+$/.test(base),'Disposable localhost fixture required');
const out=path.resolve(__dirname,'../artifacts/territory-main');fs.mkdirSync(out,{recursive:true});
(async()=>{const browser=await chromium.launch({headless:true,channel:'chrome'});try{
 const page=await browser.newPage({viewport:{width:1280,height:800}}),errors=[];page.on('pageerror',e=>errors.push(e.message));page.setDefaultTimeout(12000);page.setDefaultNavigationTimeout(45000);
 await page.goto(base+'/?zugang=login');await page.locator('[name="identifier"],[name="username"]').fill('PreviewPlayer');await page.locator('[name="password"]').fill('PreviewFixture!2026');await Promise.all([page.waitForURL('**/city'),page.locator('form[action$="/auth/local"] button[type="submit"]').click()]);await page.waitForFunction(()=>document.querySelector('#player-hud-name')?.textContent.includes('PreviewPlayer'));
 const response=await page.request.get(base+'/api/territory/state?world_id=1'),payload=await response.json();assert(payload.ok,payload.message);const territory=payload.data;assert.equal(territory.targets.length,113);
 const goal=territory.targets.find(t=>t.id===territory.goal.target_id);assert(goal&&goal.y>255);
 const artExamples=[...['food','lumber','stone','gold','abbey','rune'].map(benefit=>territory.targets.find(t=>t.kind==='commune'&&t.benefit_type===benefit)),territory.targets.find(t=>t.kind==='canton'),territory.targets.find(t=>t.kind==='crown')];
 for(const viewport of (process.argv.includes('--admin-only')?[]:[{width:1280,height:800},{width:390,height:844},{width:320,height:568},{width:844,height:390},{width:568,height:320}])){
  await page.setViewportSize(viewport);await page.locator('#navigation [data-id="world"]').click();await page.waitForFunction(()=>document.querySelector('.atlas-shell.is-luxembourg'));
  await page.evaluate(t=>ConquerWorld.focus(t.x,t.y),goal);await page.waitForSelector(`[data-atlas-target="territory:${goal.id}"]`);await page.locator(`[data-atlas-target="territory:${goal.id}"]`).click();await page.waitForSelector('.territory-target-summary');assert.match(await page.locator('.territory-target-summary').innerText(),new RegExp(goal.name));
  await page.waitForFunction(()=>{const img=document.querySelector('.territory-target-summary img');return img?.complete&&img.naturalWidth===512});
  if(viewport.width===1280){
   const artSources=new Set();
   for(const target of artExamples){
    assert(target,'All eight art categories have a real server target');
    await page.locator('[data-action="territory-tab"][data-id="territories"]').click();
    await page.locator(`[data-action="territory-target"][data-id="${target.id}"]`).click();
    await page.waitForFunction(name=>document.querySelector('.territory-target-summary h3')?.textContent===name,target.name);
    await page.waitForFunction(()=>{const img=document.querySelector('.territory-target-summary img');return img?.complete&&img.naturalWidth===512});
    const src=await page.locator('.territory-target-summary img').getAttribute('src');artSources.add(src);
    await page.locator('[data-action="territory-locate"]').click();
    const marker=page.locator(`[data-atlas-target="territory:${target.id}"]`);
    await marker.waitFor();await page.waitForFunction(id=>{const img=document.querySelector(`[data-atlas-target="territory:${id}"] img`);return img?.complete&&img.naturalWidth===512},target.id);
    assert.equal(await marker.locator('img').getAttribute('src'),src,'Map and target dialog use the same picture');
    assert.equal(await marker.locator('.atlas-marker-name').textContent(),target.name,'Map uses the canonical territory name');assert.equal(await page.locator('.atlas-village-banner strong').textContent(),target.name,'Map action banner retains the canonical name');assert((await marker.getAttribute('aria-label')).startsWith(target.name),'Accessible map label retains the same name');
    const artSize=await marker.locator('img').boundingBox(),tileSize=await marker.boundingBox();
    assert(artSize.width>=tileSize.width*.85&&artSize.width<=tileSize.width,'Landmark picture fills its authoritative footprint');
    await page.screenshot({path:path.join(out,`art-${path.basename(src,'.webp')}.png`)});
    await marker.click();await page.waitForSelector('.territory-target-summary');
   }
   assert.equal(artSources.size,8);console.log('PASS all eight territory illustrations in actual map and dialogs');
  }
  for(const tab of ['goal','history','crown','territories']){await page.locator(`[data-action="territory-tab"][data-id="${tab}"]`).click();await page.waitForTimeout(120);const fixed=await page.locator('.territory-heading button,.territory-tabs button,.territory-footer button').evaluateAll(es=>es.map(e=>{const b=e.getBoundingClientRect();return {x:b.x,y:b.y,w:b.width,h:b.height}}));assert(fixed.every(b=>b.x>=-1&&b.y>=-1&&b.x+b.w<=viewport.width+1&&b.y+b.h<=viewport.height+1),'Fixed controls remain reachable '+JSON.stringify({viewport,fixed}));const hit=await page.locator('.territory-heading [data-action="territory-close"]').evaluate(e=>{const b=e.getBoundingClientRect(),hit=document.elementFromPoint(b.x+b.width/2,b.y+b.height/2);return {okay:hit?.closest('button')===e,hit:hit?.outerHTML,rect:{x:b.x,y:b.y,width:b.width,height:b.height}}});if(!hit.okay)await page.screenshot({path:path.join(out,`close-failure-${viewport.width}x${viewport.height}.png`)});assert(hit.okay,'Close button is not covered '+JSON.stringify({viewport,tab,hit}));}
  await page.locator(`[data-action="territory-target"][data-id="${goal.id}"]`).click();await page.waitForSelector('[data-action="territory-army"][data-mode="start"]');await page.locator('[data-action="territory-army"][data-mode="start"]').click();const input=page.locator('[data-territory-troop]').first();await input.fill('1');await page.locator('[data-form="territory-army"] [type="submit"]').scrollIntoViewIfNeeded();const submit=await page.locator('[data-form="territory-army"] [type="submit"]').boundingBox();assert(submit.y>=0&&submit.y+submit.height<=viewport.height,'Rally confirmation can be reached');assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),true);
  await page.screenshot({path:path.join(out,`target-${viewport.width}x${viewport.height}.png`)});await page.locator('[data-action="territory-close"]').click();await page.waitForTimeout(180);assert(!await page.locator('#game-dialog').evaluate(d=>d.open));
  await page.locator('#navigation [data-id="city"]').click();await page.waitForFunction(()=>!document.querySelector('#scene-transition')?.classList.contains('is-active'));await page.screenshot({path:path.join(out,`city-${viewport.width}x${viewport.height}.png`)});
  const castle=page.locator('.painted-village-building[data-id="castle"]');
  await castle.evaluate(el=>el.scrollIntoView({block:'center',inline:'center'}));
  const touchPoint=await castle.evaluate(el=>{const r=el.getBoundingClientRect();for(const y of [.5,.8,.9,.3,.1])for(const x of [.5,.3,.7,.1,.9])if(el.contains(document.elementFromPoint(r.left+r.width*x,r.top+r.height*y)))return{x:r.width*x,y:r.height*y};return null;});
  assert(touchPoint,'Castle has an unobstructed touch target');await castle.click({position:touchPoint});await page.waitForSelector('.painted-building-actions');
  await page.screenshot({path:path.join(out,`city-building-${viewport.width}x${viewport.height}.png`)});
  await page.locator('.painted-selection-close').click();console.log(`PASS main ${viewport.width}×${viewport.height}`);
 }
 assert.deepEqual(errors,[]);
 // Backoffice reads the same persisted rule profile. No save is made here.
 const admin=await browser.newPage();await admin.goto(base+'/admin/login');await admin.locator('[name="username"]').fill('PreviewAdmin');await admin.locator('[name="password"]').fill('PreviewFixture!2026');await admin.locator('button[type="submit"]').click();await admin.goto(base+'/admin/world?world_id=1');
 for(const locale of ['de','fr','en']){await admin.evaluate(locale=>ConquerLocale.setLocale(locale),locale);assert.equal(await admin.locator('[name="rules[active_cantons][]"]').first().locator('..').locator('[translate="no"]').textContent(),'Shrine of Capellen','Admin retains Shrine names');}await admin.evaluate(()=>ConquerLocale.setLocale('de'));
 for(const rule of ['regional_supply_percent','regional_daily_cap','canton_mission_contributors','canton_mission_reward'])assert.equal(await admin.locator(`[name="rules[${rule}]"]`).count(),1,`Persisted territory rule ${rule} is editable`);
 for(const viewport of [{width:1280,height:800},{width:390,height:844},{width:320,height:568},{width:844,height:390},{width:568,height:320}]){await admin.setViewportSize(viewport);await admin.locator('[name="rules[canton_limit]"]').scrollIntoViewIfNeeded();assert.equal(await admin.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),true,'Admin profile has no page overflow');await admin.screenshot({path:path.join(out,`admin-${viewport.width}x${viewport.height}.png`)});}
 console.log('PASS admin territory rules in five sizes. Screenshots: '+out);
}finally{await browser.close();}})().catch(e=>{console.error(e);process.exitCode=1});
