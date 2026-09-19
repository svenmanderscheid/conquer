'use strict';
// Run against tools/preview-feature-fixture.php --port=18958 --hud --chat.
// Only the disposable PreviewPlayer account may be changed by this test.
const fs=require('fs'),path=require('path'),os=require('os'),assert=require('assert/strict');
const {chromium}=require(process.env.PLAYWRIGHT_MODULE||'playwright');
const base=process.env.QUEST_FIXTURE_URL||'http://127.0.0.1:18958';
assert(/^http:\/\/127\.0\.0\.1:\d+$/.test(base),'Disposable local preview required');
const output=fs.mkdtempSync(path.join(os.tmpdir(),'conquer-quests-app-'));
(async()=>{
 const browser=await chromium.launch({headless:true,executablePath:process.env.BROWSER_EXECUTABLE_PATH||'C:/Program Files/Google/Chrome/Application/chrome.exe'});
 try{
  const page=await browser.newPage({viewport:{width:1280,height:800},hasTouch:true}),errors=[],failures=[];
  page.on('pageerror',e=>errors.push(e.message));page.setDefaultTimeout(15000);
  await page.goto(base);await page.locator('[data-mode="login"]').click();
  await page.locator('[name="username"]').fill('PreviewPlayer');await page.locator('[name="password"]').fill('PreviewFixture!2026');
  await Promise.all([page.waitForURL('**/city'),page.locator('#auth-submit').click()]);
  const button=page.locator('#navigation [data-id="quests"]'),badge=button.locator('.dock-badge');
  await button.waitFor();
  const kingdom=await page.evaluate(async()=> (await (await fetch('/api/kingdom/state')).json()).data);
  assert.equal(kingdom.profile.display_name,'PreviewPlayer');
  const ready=kingdom.quests.filter(q=>q.completed&&!q.claimed);
  assert.equal(ready.length,1,'Fresh fixture starts with one daily login reward');
  assert.equal(await badge.innerText(),'1');assert.match(await button.getAttribute('aria-label'),/1 Aufgabe abholbereit/);
  for(const [width,height] of [[1280,800],[820,720],[390,844],[320,568],[568,320],[844,390]]){
   await page.setViewportSize({width,height});
   await page.waitForTimeout(250);
   const layout=await page.evaluate(()=>{
    const visible=e=>{const r=e.getBoundingClientRect();return r.width&&r.height&&getComputedStyle(e).visibility!=='hidden'&&getComputedStyle(e).display!=='none'},bad=[];
    const overlap=(a,b)=>a.left<b.right-1&&a.right>b.left+1&&a.top<b.bottom-1&&a.bottom>b.top+1;
    const nav=[...document.querySelectorAll('#navigation button')],other=[...document.querySelectorAll('.hud-right-tools button,#world-chat,#hud-menu,.topbar button')].filter(visible);
    for(const el of nav){const r=el.getBoundingClientRect();
     if(r.left<0||r.top<0||r.right>innerWidth||r.bottom>innerHeight)bad.push(el.dataset.id+' outside viewport');
     for(const n of other)if(overlap(r,n.getBoundingClientRect()))bad.push(el.dataset.id+' overlaps '+(n.id||n.getAttribute('aria-label')));
     const label=el.querySelector('.dock-label');if(label.scrollWidth>label.clientWidth+1)bad.push(el.dataset.id+' clips label');
    }
    const badge=document.querySelector('#navigation .dock-badge'),r=badge.getBoundingClientRect();
    if(r.left<0||r.top<0||r.right>innerWidth||r.bottom>innerHeight)bad.push('Badge outside viewport');
    return bad;
   });
   if(layout.length)failures.push({width,height,layout});
   await page.screenshot({path:path.join(output,`${width}x${height}-city.png`)});
   await button.tap();await page.locator('.quest-list').waitFor();
   assert.equal(await page.locator('.quest-row').first().getAttribute('data-quest-code'),ready[0].quest_code);
   assert.equal(await page.locator('.quest-row.ready').count(),1);
   assert.equal(await page.locator('.quest-row').count(),kingdom.quests.filter(q=>!q.claimed).length);
   const rewards=page.locator('.quest-row').first().locator('.quest-reward-item');
   assert.equal(await rewards.count(),2,'Every quest reward is shown');
   const expectedItem=kingdom.inventory_catalog.find(i=>Number(i.item_code)===Number(ready[0].rewards.find(r=>r.item_code).item_code));
   assert(expectedItem,'Quest item exists in the complete catalogue');
   assert((await rewards.first().locator('img').first().getAttribute('src')).includes(expectedItem.icon),'Quest uses the catalogue icon');
   assert.equal(await rewards.locator('img[src*="gems.svg"]').count(),1,'Gems use their real icon');
   const stacked=page.locator('[data-quest-code="attack_monster_1"] .quest-reward-item').first();
   assert.match(await stacked.locator('.quest-reward-value').innerText(),/×2/,'Multiple identical items show their quantity beside the unobstructed icon');
   const stackedItem=kingdom.inventory_catalog.find(i=>Number(i.item_code)===10103002);
   assert(stackedItem&&(await stacked.locator('img').first().getAttribute('src')).includes(stackedItem.icon),'Generic speedups use their catalogue artwork');
   await page.screenshot({path:path.join(output,`${width}x${height}-quests.png`)});
   await page.locator('.panel-close').click();
  }
  await page.setViewportSize({width:1280,height:800});
  await button.click();await page.locator('.quest-row.ready [data-action="quest-claim"]').click();
  await page.waitForFunction(()=>document.querySelector('#navigation .dock-badge')?.hidden);
  assert.equal(await page.locator('.quest-row.ready').count(),0);
  await page.locator('[data-group="quests"][data-id="claimed"]').click();
  assert.equal(await page.locator('.quest-row.claimed').count(),1);
  assert.equal(await page.locator('.quest-row.claimed button').count(),0);
  await page.locator('.panel-close').click();
  // Polling must refresh the badge even while a dialog suppresses page rendering.
  await page.route(base+'/api/kingdom/state',async route=>{
   const response=await route.fetch(),json=await response.json();
   let count=0;for(const q of json.data.quests)if(!q.claimed&&count++<2){q.completed=true;q.progress=q.target;}
   await route.fulfill({response,json});
  });
  await page.locator('#hud-menu').click();
  await page.waitForFunction(()=>document.querySelector('#navigation .dock-badge')?.textContent==='2');
  assert.equal(await badge.isVisible(),true);
  assert.match(await button.getAttribute('aria-label'),/2 Aufgaben abholbereit/);
  await page.locator('.dialog-close').click();
  await page.unroute(base+'/api/kingdom/state');
  await page.waitForFunction(()=>document.querySelector('#navigation .dock-badge')?.hidden);
  await page.locator('#navigation [data-id="world"]').click();
  assert.equal(await button.count(),1,'The same quest entry is available on the world map');
  assert.equal(await badge.isVisible(),false);
  assert.deepEqual(errors,[],'No browser errors');
  assert.deepEqual(failures,[],JSON.stringify(failures));
  console.log('PASS quest navigation, ready-first ordering, real reward claim, 0/1/2 badges, dialog polling, city/world and six responsive layouts. '+output);
 }finally{await browser.close()}
})().catch(e=>{console.error(e);console.error('Screenshots: '+output);process.exitCode=1});
