'use strict';
require('./fixtures/browser_locale.cjs')('de'); // This suite asserts the explicit German UI.
// Run against tools/preview-feature-fixture.php --training. Never saved accounts.
const assert=require('assert/strict'),fs=require('fs'),path=require('path'),{chromium}=require(process.env.PLAYWRIGHT_MODULE||'playwright');
const base=process.env.TRAINING_FIXTURE_URL||'http://127.0.0.1:19321';
assert(/^http:\/\/127\.0\.0\.1:\d+$/.test(base));
const out=path.resolve(__dirname,'../artifacts/training');fs.mkdirSync(out,{recursive:true});
(async()=>{const browser=await chromium.launch({headless:true,channel:'chrome'});
 try{const page=await browser.newPage({viewport:{width:1280,height:800},hasTouch:true}),errors=[];page.on('pageerror',e=>errors.push(e.message));page.setDefaultTimeout(20000);page.setDefaultNavigationTimeout(45000);
  await page.goto(base);await page.goto(new URL('?zugang=login', page.url()).href);await page.locator("[name=identifier], [name=username]").fill('PreviewPlayer');await page.locator('[name="password"]').fill('PreviewFixture!2026');await Promise.all([page.waitForURL('**/city'),page.locator("form[action$=\"/auth/local\"] button[type=\"submit\"]").click()]);
  await page.goto(base+'/city#army');await page.locator('.training-school.has-illustration').waitFor();
  assert.equal(await page.locator('#train-count').inputValue(),await page.locator('#train-count').getAttribute('max'),'Training initially selects the maximum affordable amount');
  const school=code=>page.locator(`[data-action="training-school"][data-id="${code}"]`),tier=n=>page.locator(`[data-action="training-tier"][data-id="${n}"]`);
  const pickTier=async n=>{await tier(n).click();};
  const state=async()=>{const r=await page.request.get(base+'/api/game/state');const j=await r.json();assert(j.ok,JSON.stringify(j));return j.data;};
  const post=async(url,body)=>{const s=await state();return page.request.post(base+'/api/'+url,{headers:{'X-CSRF-Token':s.player.csrf,'X-World-ID':'1'},data:{...body,expected_world_id:1}});};
  const illustration=async()=>{await page.locator('.training-portrait>img').evaluate(img=>img.decode());assert.equal(await page.locator('.training-model, .training-portrait canvas').count(),0);};
  for(const code of ['barrack','archery_range','stable']){
   await school(code).click();assert.equal(await page.locator('.training-tier').count(),5);assert.equal(await page.locator('#train-count').inputValue(),await page.locator('#train-count').getAttribute('max'),'Changing school selects its maximum amount');
   for(const t of [1,3,5]){await pickTier(t);await illustration();const prefix=code==='archery_range'?'fire-archer':code==='stable'?'shadow-rider':'guardian';assert((await page.locator('.training-portrait>img').getAttribute('src')).endsWith(`characters/fantasy-troops-v2/${prefix}-t${t}-ui.webp`));await page.locator('.training-view-tabs [data-action="training-mode"][data-id="stats"]').click();await illustration();assert.equal(await page.locator('.training-stat').count(),6);await page.screenshot({path:path.join(out,`${code}-t${t}.png`)});}
   await page.locator('[data-action="training-mode"][data-id="train"]').click();await pickTier(1);
  }
  for(const [width,height]of [[1280,800],[390,844],[320,568],[844,390],[568,320]]){
   await page.setViewportSize({width,height});await page.waitForTimeout(180);
   const layout=await page.locator('#panel-dialog').evaluate(p=>{const box=e=>e.getBoundingClientRect(),inside=e=>{const b=box(e);return b.width>0&&b.height>0&&b.left>=0&&b.top>=0&&b.right<=innerWidth+1&&b.bottom<=innerHeight+1;};const footer=p.querySelector('.training-schools'),submit=p.querySelector('#train-confirm');return {inside:inside(p)&&inside(footer)&&inside(submit),overflow:p.scrollWidth>p.clientWidth+2,footerOverlap:box(submit).bottom>box(footer).top+1,buttonHeight:box(submit).height};});
   assert(layout.inside&&!layout.overflow&&!layout.footerOverlap&&layout.buttonHeight>=40,JSON.stringify({width,height,layout}));await page.screenshot({path:path.join(out,`${width}x${height}.png`)});
  }
  // Read-only UI fixtures verify locked schools and insufficient resources.
  await page.route('**/api/game/state',async route=>{const response=await route.fetch(),json=await response.json();for(const key of ['food','lumber','stone','gold'])json.data.city[key]=0;json.data.buildings.academy.level=1;json.data.research={};for(const t of json.data.troop_defs)if(Number(t.type)===3&&Number(t.tier)>1)t.unlocked=false;await route.fulfill({response,json});});
  await page.reload();await page.locator('.training-school.has-illustration').waitFor();await school('stable').click();await pickTier(5);assert(await page.locator('#train-confirm').isDisabled());assert.equal(await page.locator('[data-training-lock] > button').getAttribute('data-id'),'academy');
  await pickTier(1);assert(await page.locator('#train-confirm').isDisabled());assert.match(await page.locator('#train-confirm').innerText(),/Rohstoffe fehlen/);await page.unroute('**/api/game/state');await page.reload();await page.locator('.training-school.has-illustration').waitFor();
  await page.setViewportSize({width:390,height:844});await school('barrack').click();await page.locator('#train-count').fill('200');await page.locator('#train-count').press('Tab');await page.waitForTimeout(4500);assert.equal(await page.locator('#train-count').inputValue(),'200','polling retains typed amount');assert.equal(await page.locator('#content>.subtabs').count(),1);
  let submitted;page.on('request',r=>{if(r.url().endsWith('/api/troops/train'))submitted=r.postDataJSON();});
  await page.locator('#train-confirm').click();await page.locator('[data-training-running]').waitFor({state:'visible'});const firstRequest=submitted;assert.equal((await state()).troop_queue.length,1);
  await school('archery_range').click();await page.locator('#train-count').fill('200');await page.locator('#train-count').press('Tab');
  let loseResponse=true;await page.route('**/api/troops/train',async route=>{if(!loseResponse)return route.continue();loseResponse=false;await route.fetch();await route.abort('failed');});
  await page.locator('#train-confirm').click();await page.locator('[data-training-request]').waitFor({state:'visible'});assert.equal((await state()).troop_queue.length,2);
  await page.reload();await page.locator('[data-training-request]').waitFor({state:'visible'});assert.equal(await school('archery_range').getAttribute('aria-pressed'),'true','reload restores the uncertain school');assert.equal(await page.locator('#train-count').inputValue(),'200');
  await page.locator('[data-action="training-retry"]').click();await page.locator('[data-training-request]').waitFor({state:'hidden'});assert.equal((await state()).troop_queue.length,2,'lost response retries the saved operation');await page.unroute('**/api/troops/train');
  await school('stable').click();await page.locator('#train-count').fill('200');await page.locator('#train-count').press('Tab');await page.locator('#train-confirm').click();await page.locator('[data-training-running]').waitFor({state:'visible'});assert.equal((await state()).troop_queue.length,3);
  assert.equal((await post('troops/train',{troop_code:50100101,count:1,barrack_slot:2})).status(),400);
  assert.equal((await post('troops/train',{troop_code:50101101,count:1})).status(),400);
  assert.equal((await post('troops/train',{troop_code:50100101,count:1})).status(),400);
  const useTrainingSpeedup=async()=>{await page.locator('[data-action="training-speedups"]').click();await page.locator('.queue-speedup-option[data-id="10103003"]').click();await page.locator('#queue-speedup-quantity').fill('1');const response=page.waitForResponse(r=>r.url().endsWith('/api/kingdom/action')&&r.request().method()==='POST');await page.locator('[data-action="queue-speedup-use"]').click();assert.equal((await response).status(),200);await page.locator('#game-dialog').waitFor({state:'hidden'});};
  await useTrainingSpeedup();await page.waitForFunction(()=>document.querySelector('[data-school-time="stable"]')?.textContent==='Bereit');assert.equal((await state()).troop_queue.length,2);await page.screenshot({path:path.join(out,'parallel-training.png')});
  await school('barrack').click();await useTrainingSpeedup();await page.waitForFunction(()=>document.querySelector('[data-school-time="barrack"]')?.textContent==='Bereit');
  await post('troops/train',firstRequest);assert.equal((await state()).troop_queue.length,1,'completed request does not start again');
  await page.locator("#panel-dialog .panel-close:visible, #panel-dialog .mobile-page-back:visible").first().click();await page.setViewportSize({width:1280,height:800});
  await page.locator('.painted-village').waitFor();assert.equal(await page.locator('#city-frame').count(),0);
  for(const code of ['archery_range','stable']){await page.locator(`.painted-village-building[data-id="${code}"]`).click();await page.locator(`[data-action="training-building"][data-id="${code}"]`).click();await page.locator('.training-school').waitFor();assert.equal(await school(code).getAttribute('aria-pressed'),'true');await page.locator("#panel-dialog .panel-close:visible, #panel-dialog .mobile-page-back:visible").first().click();}
  console.log('PASS training schools, tiers, queues, retry, speedups and painted building navigation');assert.deepEqual(errors,[]);
 }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exit(1)});
