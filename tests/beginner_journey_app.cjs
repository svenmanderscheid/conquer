'use strict';
// Run against tools/preview-feature-fixture.php --port=18986 --hud --chat.
// Newcomer states modify read responses only; this test never dispatches gameplay.
const assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path');
const {chromium}=require(process.env.PLAYWRIGHT_MODULE||'playwright');
const base=process.env.BEGINNER_JOURNEY_URL||'http://127.0.0.1:18986';
assert(/^http:\/\/127\.0\.0\.1:\d+$/.test(base),'Disposable local preview required');
const output=path.resolve(__dirname,'../artifacts/beginner-journey');fs.mkdirSync(output,{recursive:true});
(async()=>{
 const browser=await chromium.launch({headless:true,executablePath:process.env.BROWSER_EXECUTABLE_PATH||'C:/Program Files/Google/Chrome/Application/chrome.exe'});
 try{
  const page=await browser.newPage({viewport:{width:1280,height:800},hasTouch:true}),errors=[],writes=[];
  page.setDefaultTimeout(20000);page.on('pageerror',e=>errors.push(e.message));
  await page.addInitScript(()=>localStorage.setItem('conquer.locale','en'));
  await page.goto(base);await page.goto(new URL('?zugang=login',page.url()).href);
  await page.locator('[name=identifier], [name=username]').fill('PreviewPlayer');await page.locator('[name=password]').fill('PreviewFixture!2026');
  await Promise.all([page.waitForURL('**/city'),page.locator('form[action$="/auth/local"] button[type=submit]').click()]);await page.locator('.painted-village').waitFor();
  const original=await page.evaluate(async()=> (await (await fetch('/api/game/state')).json()).data);
  assert.equal(original.beginner_journey.player_id,Number(original.city.player_id),'real authenticated response scopes journey to player');
  assert.equal(original.beginner_journey.world_id,Number(original.city.world_id),'real authenticated response scopes journey to world');
  let complete=false,foreign=false;
  await page.route(base+'/api/game/state*',async route=>{
   const response=await route.fetch(),json=await response.json(),s=json.data;
   s.beginner_journey={player_id:Number(s.city.player_id),world_id:Number(s.city.world_id)+(foreign?1:0),alliance_member:true,help_available:true,progress:{gather:complete,monster:complete,charm:complete,alliance_help:complete}};
   for(const [code,b]of Object.entries(s.buildings)){b.level=code==='castle'?1:2;b.cost={food:0,lumber:0,stone:0,gold:0};b.requirements={};b.item_requirements=[];}
   s.build_queue=[{id:99001,building_code:'castle',level_to:2,started_at:'2099-01-01 00:00:00',finishes_at:'2099-01-02 00:00:00'}];
   s.trained_total=20;s.research={food_production:1};s.troops={50100101:100};s.marches=[];s.army_limits={...s.army_limits,march_slots:3};
   await route.fulfill({response,json});
  });
  page.on('request',r=>{if(r.method()==='POST'&&r.url().includes('/api/'))writes.push(r.url());});
  await page.reload();await page.locator('.painted-village').waitFor();
  await page.locator('#hud-menu').click();await page.locator('[data-action="dialog-tab"][data-id="help"]').click();await page.locator('.beginner-guide').waitFor();
  await page.locator('.guide-tabs [data-id="goals"]').click();
  assert.equal(await page.locator('[data-guide-goal]').count(),9,'nine connected journey goals');
  await page.waitForFunction(()=>document.querySelector('#hud-objective')?.textContent.includes('Bring home your first gathered resources'));
  assert.match(await page.locator('[data-guide-goal="castle"] [data-guide-availability]').innerText(),/already running/,'blocked upgrade explains the running order');
  console.log('Journey state, independent action hint and English text passed.');
  for(const id of ['gather','monster','charm','alliance_help'])assert(!await page.locator(`[data-guide-goal="${id}"]`).evaluate(e=>e.classList.contains('is-complete')),'unconfirmed milestone remains open');
  for(const [width,height]of [[1280,800],[390,844],[320,568],[568,320],[844,390]]){
   await page.setViewportSize({width,height});await page.evaluate(()=>document.fonts.ready);await page.waitForTimeout(100);
   for(const tab of await page.locator('.guide-tabs button').all()){
    await tab.scrollIntoViewIfNeeded();
    assert(await tab.evaluate(el=>{const r=el.getBoundingClientRect(),strip=el.closest('.guide-tabs').getBoundingClientRect();return r.width>=44&&r.height>=44&&r.left>=strip.left-1&&r.right<=strip.right+1&&el.scrollWidth<=el.clientWidth+1&&el.contains(document.elementFromPoint(r.x+r.width/2,r.y+r.height/2));}),`${width}x${height}: each guide tab is readable and reachable in its own strip`);
   }
   await page.locator('.guide-tabs [aria-pressed="true"]').scrollIntoViewIfNeeded();
   const issues=await page.evaluate(()=>{
    const issues=[],body=document.querySelector('.guide-body');
    if(body.scrollWidth>body.clientWidth+1)issues.push('horizontal overflow');
    for(const el of document.querySelectorAll('.guide-tabs [aria-pressed="true"],.panel-close,.mobile-page-back')){const r=el.getBoundingClientRect();if(r.width&&r.height&&(r.left<0||r.top<0||r.right>innerWidth+1||r.bottom>innerHeight+1))issues.push('unreachable navigation');}
    return issues;
   });assert.deepEqual(issues,[],`${width}x${height}`);
   const help=page.locator('[data-guide-goal="alliance_help"] button');await help.scrollIntoViewIfNeeded();const r=await help.boundingBox();assert(r&&r.width>=44&&r.height>=40,'help action remains touch accessible');
   await page.screenshot({path:path.join(output,`goals-${width}x${height}.png`)});
  }
  await page.setViewportSize({width:390,height:844});
  console.log('Five responsive goal views passed.');
  await page.locator('[data-guide-goal="alliance_help"] button').click();await page.locator('.community-shell').waitFor();
  assert.equal(await page.locator('.community-tabs [data-id=help]').getAttribute('aria-pressed'),'true','goal opens the actual help tab');
  await page.goBack();await page.locator('.beginner-guide').waitFor();await page.locator('.guide-tabs [data-id=goals]').click();
  const keepFocus=page.locator('[data-guide-goal="monster"] button');await keepFocus.focus();
  const scroll=await page.locator('.guide-body').evaluate(e=>e.scrollTop);
  complete=true;await page.evaluate(()=>document.dispatchEvent(new Event('visibilitychange')));
  await page.waitForFunction(()=>['gather','monster','charm','alliance_help'].every(id=>document.querySelector(`[data-guide-goal="${id}"]`)?.classList.contains('is-complete')));
  assert.equal(await keepFocus.evaluate(e=>document.activeElement===e),true,'goal updates preserve keyboard focus');
  assert.equal(await page.locator('.guide-body').evaluate(e=>e.scrollTop),scroll,'goal updates preserve reading position');
  foreign=true;await page.evaluate(()=>document.dispatchEvent(new Event('visibilitychange')));
  await page.waitForFunction(()=>document.querySelector('[data-guide-goal="monster"] .guide-goal-status')?.textContent===window.ConquerLocale.text('Spielstand derzeit nicht verfügbar'));
  assert(!await page.locator('[data-guide-goal="monster"]').evaluate(e=>e.classList.contains('is-complete')),'foreign-world read model cannot complete current goals');
  await page.keyboard.press('Escape');await page.waitForFunction(()=>!document.querySelector('#panel-dialog').open);
  assert.deepEqual(writes,[],'journey navigation never starts an action or claims a reward');assert.deepEqual(errors,[],'no browser errors');
  console.log('PASS beginner journey main app: authenticated contract, nine goals, independent action hint, live progress, scope, focus, mobile layouts and exact help destination. '+output);
 }finally{await browser.close();}
})().catch(error=>{console.error(error);process.exitCode=1;});
