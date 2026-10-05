'use strict';
require('./fixtures/browser_locale.cjs')('de'); // This suite asserts the explicit German UI.
// Starts a disposable --hud --chat fixture; QUEST_FIXTURE_URL may target an existing one.
// Only the disposable PreviewPlayer account may be changed by this test.
const fs=require('fs'),path=require('path'),os=require('os'),net=require('net'),assert=require('assert/strict'),{spawn}=require('child_process');
const {chromium}=require(process.env.PLAYWRIGHT_MODULE||'playwright');
let base=process.env.QUEST_FIXTURE_URL;
const output=process.env.QUEST_OUTPUT?path.resolve(__dirname,'..',process.env.QUEST_OUTPUT):fs.mkdtempSync(path.join(os.tmpdir(),'conquer-quests-app-'));fs.mkdirSync(output,{recursive:true});
async function startFixture(){const server=net.createServer();await new Promise(resolve=>server.listen(0,'127.0.0.1',resolve));const port=server.address().port;await new Promise(resolve=>server.close(resolve));base='http://127.0.0.1:'+port;const fixture=spawn(process.env.PHP_BINARY||'C:/xampp/php/php.exe',['tools/preview-feature-fixture.php','--port='+port,'--hud','--chat','--appearance'],{cwd:path.resolve(__dirname,'..'),stdio:['pipe','pipe','pipe'],windowsHide:true});let log='';fixture.stdout.on('data',data=>log+=data);fixture.stderr.on('data',data=>log+=data);await new Promise((resolve,reject)=>{const timer=setTimeout(()=>{clearInterval(poll);reject(Error(log||'Preview fixture timeout'));},60000),poll=setInterval(()=>{if(log.includes('Synthetic preview ready')){clearTimeout(timer);clearInterval(poll);resolve();}else if(fixture.exitCode!==null){clearTimeout(timer);clearInterval(poll);reject(Error(log));}},100);fixture.on('error',reject);});return fixture;}
(async()=>{
 const fixture=base?null:await startFixture();
 assert(/^http:\/\/127\.0\.0\.1:\d+$/.test(base),'Disposable local preview required');
 const browser=await chromium.launch({headless:true,executablePath:process.env.BROWSER_EXECUTABLE_PATH||'C:/Program Files/Google/Chrome/Application/chrome.exe'});
 let activePage;
 try{
  const page=await browser.newPage({viewport:{width:1280,height:800},hasTouch:true}),errors=[],failures=[];activePage=page;
  page.on('pageerror',e=>errors.push(e.message));page.setDefaultTimeout(20000);page.setDefaultNavigationTimeout(45000);
  page.on('console',message=>{if(message.text().startsWith('Game refresh failed:'))errors.push(message.text());});
  await page.goto(base+'/?zugang=login',{waitUntil:'domcontentloaded'});
  await page.locator("[name=identifier], [name=username]").fill('PreviewPlayer');await page.locator('[name="password"]').fill('PreviewFixture!2026');
  await Promise.all([page.waitForURL('**/city'),page.locator("form[action$=\"/auth/local\"] button[type=\"submit\"]").click()]);
  const button=page.locator('#navigation [data-id="quests"]'),badge=button.locator('.dock-badge');
  await button.waitFor();
  const kingdom=await page.evaluate(async()=> (await (await fetch('/api/kingdom/state')).json()).data);
  assert.equal(kingdom.profile.display_name,'PreviewPlayer');
  const ready=kingdom.quests.filter(q=>q.completed&&!q.claimed);
  assert.equal(ready.filter(q=>!q.permanent).length,1,'Fresh fixture starts with one daily login reward');
  assert.equal(ready.filter(q=>q.permanent).length,3,'Completed starter building missions are ready too');
  assert.equal(await badge.innerText(),String(ready.length));assert.match(await button.getAttribute('aria-label'),/4 Aufgaben abholbereit/);
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
   assert.equal(await page.locator('.quest-row.ready').count(),ready.length);
   assert.equal(await page.locator('.quest-row').count(),kingdom.quests.filter(q=>!q.claimed).length);
   for(const row of await page.locator('.quest-row').all()){await row.scrollIntoViewIfNeeded();await row.locator('.quest-illustration').evaluate(img=>img.decode());const action=row.locator('button.quest-action');if(await action.count()){const reachable=await action.evaluate(button=>{const r=button.getBoundingClientRect();return button.contains(document.elementFromPoint(r.x+r.width/2,r.y+r.height/2));});assert(reachable,'Every quest action is reachable after scrolling');}}
   await page.locator('.quest-list').evaluate(list=>list.scrollTop=0);
   const questLayout=await page.locator('.quest-list').evaluate(list=>{const rows=[...list.querySelectorAll('.quest-row')];return {sideways:list.scrollWidth>list.clientWidth+2,missingText:/quests\.progress/.test(list.innerText),images:rows.every(row=>{const img=row.querySelector('.quest-illustration');return img&&img.complete&&img.naturalWidth>0&&img.getBoundingClientRect().width>=50;}),actions:rows.every(row=>{const button=row.querySelector('button.quest-action');return !button||button.getBoundingClientRect().height>=43;}),progress:rows.every(row=>row.querySelector('.quest-progress [role="progressbar"]')&&row.querySelector('.quest-progress strong'))};});
   assert.deepEqual(questLayout,{sideways:false,missingText:false,images:true,actions:true,progress:true},'Illustrated quests retain legible progress and touch actions without horizontal scrolling');
   const rewards=page.locator('.quest-row').first().locator('.quest-reward-item');
   assert.equal(await rewards.count(),2,'Every quest reward is shown');
   const expectedItem=kingdom.inventory_catalog.find(i=>Number(i.item_code)===Number(ready[0].rewards.find(r=>r.item_code).item_code));
   assert(expectedItem,'Quest item exists in the complete catalogue');
   assert((await rewards.first().locator('img').first().getAttribute('src')).includes('painted-v2/'+expectedItem.item_code+'.webp'),'Quest uses the approved individual item icon');
   assert.equal(await rewards.locator('img[src*="painted-v1/gems.webp"]').count(),1,'Crystals use their approved currency icon');
   const stacked=page.locator('[data-quest-code="attack_monster_1"] .quest-reward-item').first();
   assert.match(await stacked.locator('.quest-reward-value').innerText(),/×2/,'Multiple identical items show their quantity beside the unobstructed icon');
   const stackedItem=kingdom.inventory_catalog.find(i=>Number(i.item_code)===10103002);
   assert(stackedItem&&(await stacked.locator('img').first().getAttribute('src')).includes('painted-v2/'+stackedItem.item_code+'.webp'),'Generic speedups use their approved individual artwork');
   for(const code of ['upgrade_building_1','train_troops_100','research_complete_1']){
    const quest=kingdom.quests.find(q=>q.quest_code===code),row=page.locator(`[data-quest-code="${code}"]`);
    assert.equal(await row.locator('.quest-reward-item').count(),quest.rewards.length,'Every added starter resource pack is shown');
    const packs=quest.rewards.filter(reward=>reward.item_code&&kingdom.inventory_catalog.find(item=>Number(item.item_code)===Number(reward.item_code))?.category==='resource_pack');
    assert.equal(packs.length,code==='train_troops_100'?2:3);
    await row.scrollIntoViewIfNeeded();
    for(const reward of packs){
     const item=kingdom.inventory_catalog.find(item=>Number(item.item_code)===Number(reward.item_code));
     const icon=row.locator('.quest-reward-item img').filter({visible:true});
     assert((await icon.evaluateAll(imgs=>imgs.map(img=>img.getAttribute('src')))).some(src=>src.includes('painted-v2/'+item.item_code+'.webp')),'Resource pack uses its approved individual image');
    }
    const clipped=await row.evaluate(el=>{const bounds=el.getBoundingClientRect();return [...el.querySelectorAll('.quest-reward-item')].some(item=>{const r=item.getBoundingClientRect();return r.left<bounds.left-1||r.right>bounds.right+1;});});
    assert.equal(clipped,false,'Added resource rewards fit the quest row');
   }
   await page.screenshot({path:path.join(output,`${width}x${height}-resource-rewards.png`)});
   await page.locator('.quest-list').evaluate(list=>list.scrollTop=0);
   await page.screenshot({path:path.join(output,`${width}x${height}-quests.png`)});
   await page.locator("#panel-dialog .panel-close:visible, #panel-dialog .mobile-page-back:visible").first().click();
   console.log('PASS illustrated quest layout '+width+'x'+height);
  }
  await page.setViewportSize({width:1280,height:800});
  await button.click();
  for(const [index,quest] of ready.entries()){
   const [claimResponse]=await Promise.all([page.waitForResponse(response=>response.url().endsWith('/api/kingdom/action')&&response.request().method()==='POST'),page.locator(`[data-quest-code="${quest.quest_code}"] [data-action="quest-claim"]`).click()]);
   const claimResult=await claimResponse.json();assert(claimResult.ok,JSON.stringify(claimResult));
   assert.deepEqual(claimResult.data.result.rewards,quest.rewards,'Confirmed rewards match the preview');
   const next=ready[index+1]||kingdom.quests.find(q=>!q.completed&&!q.claimed);
   await page.waitForFunction(code=>document.querySelector('.quest-followup [data-action="quest-next"]')?.dataset.id===code,next.quest_code,{timeout:45000});
  }
  await page.waitForFunction(()=>document.querySelector('#navigation .dock-badge')?.hidden);
  assert.equal(await page.locator('.quest-row.ready').count(),0);
  await page.locator('[data-group="quests"][data-id="claimed"]').click();
  assert.equal(await page.locator('.quest-row.claimed').count(),ready.length);
  assert.equal(await page.locator('.quest-row.claimed button').count(),0);
  assert.equal(await page.locator('.quest-row.claimed').first().evaluate(el=>getComputedStyle(el).opacity),'1','Claimed quest text retains full readability');
  assert.equal(await page.locator('.quest-row.claimed .quest-copy').first().evaluate(el=>getComputedStyle(el).opacity),'1','Claimed quest description retains full readability');
  await page.screenshot({path:path.join(output,'claimed-quest.png')});
  await page.locator("#panel-dialog .panel-close:visible, #panel-dialog .mobile-page-back:visible").first().click();
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
  await page.locator("#game-dialog .dialog-close:visible, #game-dialog .mobile-page-back:visible").first().click();
  await page.unroute(base+'/api/kingdom/state');
  await page.waitForFunction(()=>document.querySelector('#navigation .dock-badge')?.hidden);
  await page.locator('#navigation [data-id="world"]').click();
  assert.equal(await button.count(),1,'The same quest entry is available on the world map');
  assert.equal(await badge.isVisible(),false);
  assert.deepEqual(errors,[],'No browser errors');
  assert.deepEqual(failures,[],JSON.stringify(failures));
  console.log('PASS quest navigation, ready-first ordering, real reward claim, 0/1/2 badges, dialog polling, city/world and six responsive layouts. '+output);
 }catch(error){if(activePage){await activePage.screenshot({path:path.join(output,'quests-failure.png')}).catch(()=>{});fs.writeFileSync(path.join(output,'quests-failure.json'),JSON.stringify({message:error.message,text:await activePage.locator('body').innerText().catch(()=>''),badge:await activePage.locator('#navigation .dock-badge').evaluateAll(nodes=>nodes.map(node=>({text:node.textContent,hidden:node.hidden})))},null,2));}throw error;}finally{await browser.close();if(fixture&&fixture.exitCode===null){fixture.stdin.write('\n');await new Promise(resolve=>fixture.exitCode!==null?resolve():fixture.once('exit',resolve));}}
})().catch(e=>{console.error(e);console.error('Screenshots: '+output);process.exitCode=1});
