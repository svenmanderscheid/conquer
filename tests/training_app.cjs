'use strict';
// Run against tools/preview-feature-fixture.php --training. Never saved accounts.
const assert=require('assert/strict'),fs=require('fs'),path=require('path'),{chromium}=require('playwright');
const base=process.env.TRAINING_FIXTURE_URL||'http://127.0.0.1:19321';
assert(/^http:\/\/127\.0\.0\.1:\d+$/.test(base));
const out=path.resolve(__dirname,'../artifacts/training');fs.mkdirSync(out,{recursive:true});
(async()=>{const browser=await chromium.launch({headless:true,channel:'chrome'});
 try{const page=await browser.newPage({viewport:{width:1280,height:800},hasTouch:true}),errors=[];page.on('pageerror',e=>errors.push(e.message));page.setDefaultTimeout(20000);
  await page.goto(base);await page.locator('[data-mode="login"]').click();await page.locator('[name="username"]').fill('PreviewPlayer');await page.locator('[name="password"]').fill('PreviewFixture!2026');await Promise.all([page.waitForURL('**/city'),page.locator('#auth-submit').click()]);
  await page.goto(base+'/city#army');await page.locator('.training-school.has-model').waitFor();
  assert.equal(await page.locator('#train-count').inputValue(),await page.locator('#train-count').getAttribute('max'),'Training initially selects the maximum affordable amount');
  const school=code=>page.locator(`[data-action="training-school"][data-id="${code}"]`),tier=n=>page.locator(`[data-action="training-tier"][data-id="${n}"]`);
  const pickTier=async n=>{if(!await tier(n).isVisible())await page.locator(`[data-action="training-tier-page"][data-id="${n>5?1:0}"]`).click();await tier(n).click();};
  const state=async()=>{const r=await page.request.get(base+'/api/game/state');const j=await r.json();assert(j.ok,JSON.stringify(j));return j.data;};
  const post=async(url,body)=>{const s=await state();return page.request.post(base+'/api/'+url,{headers:{'X-CSRF-Token':s.player.csrf,'X-World-ID':'1'},data:{...body,expected_world_id:1}});};
  const model=async()=>{await page.locator('.training-model canvas').waitFor();await page.waitForTimeout(120);};
  for(const code of ['barrack','archery_range','stable']){
   await school(code).click();assert.equal(await page.locator('.training-tier').count(),10);assert.equal(await page.locator('#train-count').inputValue(),await page.locator('#train-count').getAttribute('max'),'Changing school selects its maximum amount');
   for(const t of [1,5,10]){await pickTier(t);const usesModel=t===10||(code==='barrack'&&t===1);if(usesModel)await model();else{await page.locator('.training-school.has-illustration').waitFor();const prefix=code==='archery_range'?'archer':code==='stable'?'cavalry':'infantry';assert.match(await page.locator('.training-portrait>img').getAttribute('src'),new RegExp(`characters/${prefix}-t${t}-ui-v1\\.png$`));}await page.locator('[data-action="training-mode"][data-id="stats"]').click();if(usesModel)await model();else await page.locator('.training-school.has-illustration').waitFor();assert.equal(await page.locator('.training-stat').count(),7);await page.screenshot({path:path.join(out,`${code}-t${t}.png`)});}
   await page.locator('[data-action="training-mode"][data-id="train"]').click();await pickTier(1);
  }
  for(const [width,height]of [[1280,800],[390,844],[320,568],[844,390],[568,320]]){
   await page.setViewportSize({width,height});await page.waitForTimeout(180);
   const layout=await page.locator('#panel-dialog').evaluate(p=>{const box=e=>e.getBoundingClientRect(),inside=e=>{const b=box(e);return b.width>0&&b.height>0&&b.left>=0&&b.top>=0&&b.right<=innerWidth+1&&b.bottom<=innerHeight+1;};const footer=p.querySelector('.training-schools'),submit=p.querySelector('#train-confirm');return {inside:inside(p)&&inside(footer)&&inside(submit),overflow:p.scrollWidth>p.clientWidth+2,footerOverlap:box(submit).bottom>box(footer).top+1,buttonHeight:box(submit).height};});
   assert(layout.inside&&!layout.overflow&&!layout.footerOverlap&&layout.buttonHeight>=40,JSON.stringify({width,height,layout}));await page.screenshot({path:path.join(out,`${width}x${height}.png`)});
  }
  // Read-only UI fixtures verify locked schools and insufficient resources.
  await page.route('**/api/game/state',async route=>{const response=await route.fetch(),json=await response.json();for(const key of ['food','lumber','stone','gold'])json.data.city[key]=0;json.data.buildings.stable.level=1;for(const t of json.data.troop_defs)if(Number(t.type)===3&&Number(t.tier)>1)t.unlocked=false;await route.fulfill({response,json});});
  await page.reload();await page.locator('.training-school.has-model').waitFor();await school('stable').click();await pickTier(10);assert(await page.locator('#train-confirm').isDisabled());assert.equal(await page.locator('[data-training-lock] button').getAttribute('data-id'),'stable');
  await pickTier(1);assert(await page.locator('#train-confirm').isDisabled());assert.match(await page.locator('#train-confirm').innerText(),/Rohstoffe fehlen/);await page.unroute('**/api/game/state');await page.reload();await page.locator('.training-school.has-model').waitFor();
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
  await page.locator('.panel-close').click();await page.setViewportSize({width:1280,height:800});const frame=page.frames().find(f=>f.url().includes('/city/3d'));await frame.waitForFunction(()=>window.conquer3D?.getState().ready);
  for(const sel of ['.realm-hud','#resource-bar','.realm-nav','.city-footer'])assert.equal(await frame.locator(sel).isVisible(),false,sel+' remains hidden in embedded city');
  await frame.locator('#reset').click();await page.waitForTimeout(600);await page.screenshot({path:path.join(out,'city-overview.png')});
  for(const code of ['archery_range','stable']){await frame.evaluate(code=>window.dispatchEvent(new CustomEvent('conquer-focus-building',{detail:{code}})),code);for(let i=0;i<5;i++)await frame.locator('#zoomIn').click();await page.waitForTimeout(500);await page.screenshot({path:path.join(out,`city-${code}.png`)});assert(await frame.locator(`#label-${code} .building-name`).isVisible());await frame.locator('#building-function').click();await page.locator('.training-school').waitFor();assert.equal(await school(code).getAttribute('aria-pressed'),'true','building opens its own parent training screen');await page.locator('.panel-close').click();}
  await frame.evaluate(()=>window.dispatchEvent(new CustomEvent('conquer-open-order',{detail:{code:'archery_range',kind:'training',troopCode:50200501}})));await page.locator('.training-school').waitFor();assert.equal(await school('archery_range').getAttribute('aria-pressed'),'true');assert.equal(await tier(5).getAttribute('aria-pressed'),'true');assert(frame.url().includes('/city/3d'),'training receipts keep the city iframe intact');
  console.log(JSON.stringify({checks:'three schools, 30 tiers, seven stats, five viewports, unlock and resource gates, retained input, parallel queues, lost response and reload, completed replay, speedups, embedded HUD, close-up 3D buildings, parent navigation',errors,scene:await frame.evaluate(()=>window.conquer3D.getState())}));assert.deepEqual(errors,[]);
 }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exit(1)});
