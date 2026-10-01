'use strict';
require('./fixtures/browser_locale.cjs')('de'); // This suite asserts the explicit German UI.
// Real app and API against tools/preview-feature-fixture.php --march-skins only.
const fs=require('fs'),path=require('path'),assert=require('assert/strict');
const {chromium}=require(process.env.PLAYWRIGHT_MODULE||'playwright');
const base=process.env.MARCH_SKIN_FIXTURE_URL||'http://127.0.0.1:18968';
assert(/^http:\/\/127\.0\.0\.1:\d+$/.test(base),'loopback fixture required');
const textContrast=require('./fixtures/menu_text_contrast.cjs');
const output=path.resolve(__dirname,'../artifacts/march-skin-ui');fs.mkdirSync(output,{recursive:true});
(async()=>{
 const browser=await chromium.launch({headless:true,channel:process.env.PLAYWRIGHT_CHANNEL||'chrome'});
 const page=await browser.newPage({viewport:{width:1280,height:720},serviceWorkers:'block'}),errors=[],warnings=[];let checks=0;
 page.on('pageerror',error=>errors.push(error.message));
 page.on('console',event=>{if(['warning','error'].includes(event.type())&&event.text()!=='Service Worker registration blocked by Playwright')warnings.push(event.text());});
 const kingdom=()=>page.evaluate(async()=>{const response=await fetch('/api/kingdom/state',{cache:'no-store'});return(await response.json()).data;});
 const openVillage=async()=>{await page.evaluate(()=>window.dispatchEvent(new CustomEvent('conquer-village-menu',{detail:{kind:'home'}})));await page.locator('[data-action="city-skins"]').click();};
 const openMarch=async()=>{await openVillage();await page.locator('[data-action="skin-collection-tab"][data-id="march"]').click();};
 try{
  await page.goto(base);await page.goto(new URL('?zugang=login', page.url()).href);await page.locator("[name=identifier], [name=username]").fill('PreviewPlayer');await page.locator('[name="password"]').fill('PreviewFixture!2026');
await Promise.all([page.waitForURL('**/city'),page.locator("form[action$=\"/auth/local\"] button[type=\"submit\"]").click()]);await page.waitForFunction(()=>window.ConquerMarchSkins?.ids?.length===5);
let initial=await kingdom();assert.equal(initial.profile.gems,10000);assert.equal(initial.march_skins.entries.length,5);assert.equal(initial.march_skins.entries.find(entry=>entry.id==='fire').price_gems,1200);assert.equal(initial.march_skins.entries.find(entry=>entry.id==='default').can_claim,true);checks+=4;
  await openMarch();assert.equal(await page.locator('.march-skin-option').count(),5);assert.match(await page.locator('.march-skin-benefit').innerText(),/nächsten Marsch/);checks+=2;
  await page.locator('.march-skin-option[data-id="default"]').click();
  let responsePromise=page.waitForResponse(response=>response.url().endsWith('/api/kingdom/action')&&response.request().postDataJSON()?.action==='march_skin.claim');
  await page.locator('[data-action="march-skin-claim"]').click();let response=await responsePromise;assert.equal(response.status(),200);await page.locator('[data-action="march-skin-equip"]').waitFor();
  let state=await kingdom();assert.equal(state.march_skins.entries.find(entry=>entry.id==='default').owned,true);assert.equal(state.profile.march_skin,null);checks+=3;
  responsePromise=page.waitForResponse(response=>response.url().endsWith('/api/kingdom/action')&&response.request().postDataJSON()?.action==='march_skin.equip');await page.locator('[data-action="march-skin-equip"]').click();await responsePromise;
  state=await kingdom();assert.equal(state.profile.march_skin,'default');assert.equal(state.march_skins.bonus_pct,5);checks+=2;
  await openMarch();await page.locator('.march-skin-option[data-id="water"]').click();
  assert.equal(await page.locator('[data-action="march-skin-buy-review"]').count(),0);checks++;
  responsePromise=page.waitForResponse(response=>response.url().endsWith('/api/kingdom/action')&&response.request().postDataJSON()?.action==='march_skin.buy');await page.locator('[data-action="march-skin-buy"]').click();response=await responsePromise;const firstBuy=await response.json();assert.equal(firstBuy.data.result.charged_gems,1200);checks++;
  await page.locator('[data-action="march-skin-equip"]').waitFor();state=await kingdom();assert.equal(state.profile.gems,8800);assert.equal(state.march_skins.entries.find(entry=>entry.id==='water').owned,true);checks+=2;
  const repeated=await page.evaluate(async()=>{const game=(await(await fetch('/api/game/state')).json()).data;const response=await fetch('/api/kingdom/action',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-Token':game.player.csrf},body:JSON.stringify({action:'march_skin.buy',march_skin:'water',expected_world_id:1})});return{status:response.status,body:await response.json()};});
  assert.equal(repeated.status,200);assert.equal(repeated.body.data.result.charged_gems,0);assert.equal((await kingdom()).profile.gems,8800);checks+=3;
  responsePromise=page.waitForResponse(response=>response.url().endsWith('/api/kingdom/action')&&response.request().postDataJSON()?.action==='march_skin.equip');await page.locator('[data-action="march-skin-equip"]').click();await responsePromise;await page.locator('.march-skin-picker').waitFor();
  await openVillage();await page.locator('.castle-skin-picker').waitFor();await page.locator('[data-action="city-skin-filter"][data-id="legendary"]').click();responsePromise=page.waitForResponse(response=>response.url().endsWith('/api/kingdom/action')&&response.request().postDataJSON()?.action==='skin.save');await page.locator('.skin-option[data-id="forest"]').click();await responsePromise;
  state=await kingdom();assert.equal(state.profile.city_skin,'forest');assert.equal(state.profile.march_skin,'water');checks+=2;
await page.reload();await page.waitForFunction(()=>window.ConquerMarchSkins?.ids?.length===5);state=await kingdom();assert.equal(state.profile.city_skin,'forest');assert.equal(state.profile.march_skin,'water');assert.equal(state.profile.gems,8800);checks+=3;
  await page.evaluate(()=>{location.hash='army'});await page.locator('#panel-dialog[open][data-panel="army"]').waitFor();await page.locator('.army-quick-actions [data-action="march-skins"]').click();assert.equal(await page.locator('.march-skin-picker').isVisible(),true);checks+=2;
  await page.locator("#game-dialog>.dialog-close:visible, #game-dialog .mobile-page-back:visible").first().click();await page.locator('#game-dialog').waitFor({state:'hidden'});await page.locator('.army-quick-actions [data-action="march-skins"]').click();assert.equal(await page.locator('.march-skin-option[data-id="water"]').getAttribute('aria-pressed'),'true');checks++;
  for(const [width,height] of [[1280,720],[390,844],[320,568],[844,390]]){
   await page.setViewportSize({width,height});if(!await page.locator('#game-dialog').isVisible())await page.locator('.army-quick-actions [data-action="march-skins"]').click();
   for(const card of await page.locator('.march-skin-option').all()){await card.scrollIntoViewIfNeeded();const r=await card.boundingBox();assert(r&&r.x>=0&&r.x+r.width<=width+1&&r.y>=0&&r.y+r.height<=height+1,'every skin is reachable by scrolling');}
   await page.locator('.march-skin-options').evaluate(el=>el.scrollTop=0);
   const measure=await page.evaluate(()=>{const dialog=document.querySelector('#game-dialog').getBoundingClientRect(),grid=document.querySelector('.march-skin-options'),outside=[...document.querySelectorAll('#game-dialog button')].filter(button=>{const box=button.getBoundingClientRect();if(button.closest('.skin-scroll-grid'))return false;return box.width&&box.height&&(box.left<dialog.left-1||box.right>dialog.right+1||box.top<dialog.top-1||box.bottom>dialog.bottom+1)}).map(button=>button.textContent.trim());return{outside,gridOverflow:grid.scrollWidth>grid.clientWidth+1};});
   assert.deepEqual(measure.outside,[],JSON.stringify({width,height,measure}));assert.equal(measure.gridOverflow,false,JSON.stringify({width,height,measure}));checks+=2;await page.screenshot({path:path.join(output,`${width}x${height}.png`)});
  }
  for(const [width,height] of [[320,568],[844,390]]){
   await page.setViewportSize({width,height});await page.locator('.march-skin-option[data-id="fire"]').click();const buy=page.locator('[data-action="march-skin-buy"]');assert.equal(await buy.isVisible(),true);assert.equal(await buy.evaluate(button=>getComputedStyle(button).backgroundColor),'rgb(237, 138, 49)');checks+=2;
   assert.deepEqual((await textContrast(page,'.march-skin-detail')).failures,[],'detail and crystal purchase remain readable');const buttonBox=await buy.boundingBox(),dialogBox=await page.locator('#game-dialog').boundingBox();assert(buttonBox&&dialogBox&&buttonBox.y>=dialogBox.y&&buttonBox.y+buttonBox.height<=dialogBox.y+dialogBox.height);assert.equal(await page.locator('[data-action="march-skin-buy-review"]').count(),0);checks+=2;await page.screenshot({path:path.join(output,`${width}x${height}-detail.png`)});
   await page.getByRole('button',{name:'Zur Sammlung',exact:true}).click();await page.locator('.march-skin-picker').waitFor();
  }
  assert.deepEqual(errors,[]);assert.deepEqual(warnings,[]);checks+=2;console.log(JSON.stringify({checks,errors,warnings,output}));
 }catch(error){console.error(JSON.stringify({errors,warnings,url:page.url()}));await page.screenshot({path:path.join(output,'failure.png')});throw error;}finally{await browser.close();}
})().catch(error=>{console.error(error);process.exitCode=1;});
