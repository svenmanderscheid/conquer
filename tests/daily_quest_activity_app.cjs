'use strict';
// Real authenticated claims, only in the disposable --quest-activity fixture.
const fs=require('fs'),path=require('path'),os=require('os'),net=require('net'),assert=require('assert/strict'),{spawn}=require('child_process');
const locale=process.env.QUEST_LOCALE||'en';require('./fixtures/browser_locale.cjs')(locale);
const {chromium}=require(process.env.PLAYWRIGHT_MODULE||'playwright');
const output=process.env.QUEST_OUTPUT?path.resolve(__dirname,'..',process.env.QUEST_OUTPUT):fs.mkdtempSync(path.join(os.tmpdir(),'conquer-quest-activity-'));fs.mkdirSync(output,{recursive:true});
let base=process.env.QUEST_FIXTURE_URL;
async function startFixture(){
 const socket=net.createServer();await new Promise(resolve=>socket.listen(0,'127.0.0.1',resolve));const port=socket.address().port;await new Promise(resolve=>socket.close(resolve));base='http://127.0.0.1:'+port;
 const fixture=spawn(process.env.PHP_BINARY||'C:/xampp/php/php.exe',['tools/preview-feature-fixture.php','--port='+port,'--hud','--chat','--quest-activity'],{cwd:path.resolve(__dirname,'..'),stdio:['pipe','pipe','pipe'],windowsHide:true});
 let log='';fixture.stdout.on('data',chunk=>log+=chunk);fixture.stderr.on('data',chunk=>log+=chunk);
 await new Promise((resolve,reject)=>{const timeout=setTimeout(()=>{clearInterval(poll);reject(Error(log||'Fixture timeout'));},60000),poll=setInterval(()=>{if(log.includes('Synthetic preview ready')){clearTimeout(timeout);clearInterval(poll);resolve();}else if(fixture.exitCode!==null){clearTimeout(timeout);clearInterval(poll);reject(Error(log));}},100);fixture.on('error',reject);});return fixture;
}
(async()=>{
 const fixture=base?null:await startFixture();assert(/^http:\/\/127\.0\.0\.1:\d+$/.test(base),'Disposable preview required');
 const browser=await chromium.launch({headless:true,executablePath:process.env.BROWSER_EXECUTABLE_PATH||'C:/Program Files/Google/Chrome/Application/chrome.exe'});
 let page;
 try{
  page=await browser.newPage({viewport:{width:1280,height:800},hasTouch:true});page.setDefaultTimeout(20000);page.setDefaultNavigationTimeout(45000);
  const errors=[];let writes=0;page.on('pageerror',error=>errors.push(error.message));page.on('request',request=>{if(request.method()==='POST'&&request.url().endsWith('/api/kingdom/action'))writes++;});
  await page.goto(base+'/?zugang=login',{waitUntil:'domcontentloaded'});await page.locator('[name=identifier], [name=username]').fill('PreviewPlayer');await page.locator('[name=password]').fill('PreviewFixture!2026');
  await Promise.all([page.waitForURL('**/city'),page.locator('form[action$="/auth/local"] button[type=submit]').click()]);
  const state=()=>page.evaluate(async()=> (await (await fetch('/api/kingdom/state')).json()).data);
  const initial=await state();assert.equal(initial.profile.display_name,'PreviewPlayer');
  const daily=initial.quests.filter(q=>!q.permanent);assert.equal(daily.length,13);assert(daily.every(q=>q.completed&&!q.claimed),'Use --quest-activity fixture');assert.equal(initial.quest_activity.points,0);
  const nav=page.locator('#navigation [data-id=quests]'),badge=nav.locator('.dock-badge');await nav.click();await page.locator('.quest-list').waitFor();
  assert.equal(await page.locator('.quest-list').getAttribute('data-scope'),'main','Unclaimed permanent goals are the initial category');
  await page.locator('[data-action=quest-category][data-id=daily]').click();
  async function claim(selector,code){const [response]=await Promise.all([page.waitForResponse(response=>response.url().endsWith('/api/kingdom/action')&&response.request().method()==='POST'),page.locator(selector).click()]);const json=await response.json();assert(json.ok,JSON.stringify(json));await page.waitForFunction(code=>!document.querySelector(`[data-action="quest-claim"][data-id="${code}"]`),code);return json.data;}
  async function closePreview(){await page.locator('.quest-activity-actions [data-action=close-dialog]').click();await page.waitForFunction(()=>!document.querySelector('#game-dialog').open);}
  async function preview(target){await page.locator(`[data-action=quest-activity-preview][data-id=daily_activity_${target}]`).click();await page.locator('.quest-activity-preview').waitFor();}
  async function checkBadge(){const kingdom=await state(),expected=kingdom.quests.filter(q=>q.completed&&!q.claimed).length+kingdom.quest_activity.milestones.filter(m=>m.completed&&!m.claimed).length;await page.waitForFunction(expected=>{const badge=document.querySelector('#navigation [data-id=quests] .dock-badge');return expected?Number(badge.textContent)===expected&&!badge.hidden:badge.hidden;},expected);}
  for(const [width,height] of [[1280,800],[390,844],[320,568],[568,320],[844,390]]){
   await page.setViewportSize({width,height});
   // The panel animates after a resize and after the reward dialog closes.
   // Wait for its real touch targets to settle rather than sampling mid-transition.
   await page.waitForFunction(()=>[...document.querySelectorAll('.quest-activity button')].length===5&&[...document.querySelectorAll('.quest-activity button')].every(button=>{const r=button.getBoundingClientRect();return r.width>=43&&r.height>=43&&r.left>=0&&r.right<=innerWidth+1&&button.contains(document.elementFromPoint(r.x+r.width/2,r.y+r.height/2));}),null,{timeout:5000});
   assert.equal(await page.locator('.quest-activity-chest').count(),5);assert.equal(await page.locator('.quest-activity-award').count(),13);
   const layout=await page.locator('.quest-activity').evaluate(section=>{const list=document.querySelector('.quest-list'),rect=list.getBoundingClientRect(),buttons=[...section.querySelectorAll('button')];return {horizontal:section.scrollWidth>section.clientWidth+1,listHeight:rect.height,buttons:buttons.every(button=>{const r=button.getBoundingClientRect();return r.width>=43&&r.height>=43&&r.left>=0&&r.right<=innerWidth+1&&button.contains(document.elementFromPoint(r.x+r.width/2,r.y+r.height/2));})};});
   assert.equal(layout.horizontal,false);assert(layout.listHeight>=60,JSON.stringify({width,height,...layout}));assert.equal(layout.buttons,true,JSON.stringify({width,height,...layout}));
   await page.screenshot({path:path.join(output,`${locale}-${width}x${height}-activity.png`)});
   const before=writes;await preview(100);assert.equal(await page.locator('[data-action=quest-activity-claim]').count(),0);assert.equal(await page.locator('.quest-activity-rewards>li').count(),8);assert.equal(writes,before,'Preview must never claim a chest');
   await page.locator('.quest-activity-actions button').scrollIntoViewIfNeeded();await page.screenshot({path:path.join(output,`${locale}-${width}x${height}-preview.png`)});await closePreview();
  }
  await page.setViewportSize({width:390,height:844});
  await page.locator('[data-action=quest-category][data-id=main]').click();
  for(const q of initial.quests.filter(q=>q.permanent&&q.completed&&!q.claimed))await claim(`[data-action=quest-claim][data-id="${q.quest_code}"]`,q.quest_code);
  assert.equal((await state()).quest_activity.points,0,'Starter claims grant no activity');
  await page.locator('[data-action=quest-category][data-id=daily]').click();
  assert.match(await page.locator('.quest-page-summary').innerText(),/0\s*\/\s*13/);
  for(const [index,q] of daily.entries()){
   const result=await claim(`[data-action=quest-claim][data-id="${q.quest_code}"]`,q.quest_code);assert.deepEqual(result.result.rewards,q.rewards,'Ordinary quest rewards remain unchanged');
   const points=(index+1)*10;await page.waitForFunction(points=>document.querySelector('.quest-activity-heading>span')?.textContent.includes(String(points)),points);assert.equal((await state()).quest_activity.points,points);await checkBadge();
   const current=await state(),dailyReady=current.quests.filter(q=>!q.permanent&&q.completed&&!q.claimed).length+current.quest_activity.milestones.filter(m=>m.completed&&!m.claimed).length;
   const categoryBadge=page.locator('[data-action=quest-category][data-id=daily] .quest-category-count');
   if(dailyReady)assert.equal(Number(await categoryBadge.innerText()),dailyReady,'Category badge includes newly unlocked chests');else assert.equal(await categoryBadge.count(),0);
   if(points%20===0&&points<=100){
    const chest=(await state()).quest_activity.milestones.find(m=>m.target===points);assert(chest.completed&&!chest.claimed);
    const before=writes;await preview(points);assert.equal(writes,before,'A ready preview also requires a separate claim');
    const amounts=await page.locator('.quest-activity-rewards>li>strong').allTextContents();assert.deepEqual(amounts,chest.rewards.map(r=>'×'+(r.quantity||r.gems).toLocaleString(locale)),'Preview exposes exact reward quantities');
    await page.screenshot({path:path.join(output,`${locale}-ready-${points}.png`)});
    const receipt=await claim(`[data-action=quest-activity-claim][data-id="${chest.quest_code}"]`,chest.quest_code);assert.deepEqual(receipt.result.rewards,chest.rewards);
    await page.waitForFunction(code=>document.querySelector(`[data-action=quest-activity-preview][data-id="${code}"]`)?.classList.contains('is-claimed'),chest.quest_code);
    assert.equal((await state()).quest_activity.points,points,'Chests do not grant additional activity');await checkBadge();
    await preview(points);assert.equal(await page.locator('[data-action=quest-activity-claim]').count(),0,'Claimed rewards cannot be claimed again');await closePreview();
   }
  }
  assert.equal(await page.locator('.quest-activity-chest.is-claimed').count(),5);assert.equal(await badge.isVisible(),false);
  assert.equal(await page.locator('.quest-activity [role=progressbar]').getAttribute('aria-valuenow'),'100','Progress bar caps at the last milestone');assert.match(await page.locator('.quest-activity-heading>span').innerText(),/130/,'Actual activity is retained above 100');assert.match(await page.locator('.quest-page-summary').innerText(),/13\s*\/\s*13/);
  await page.screenshot({path:path.join(output,`${locale}-all-claimed.png`)});assert.deepEqual(errors,[],'No browser errors');
  console.log('PASS daily activity: five responsive layouts, exact previews without writes, 13 real daily claims, starter exclusion, all five chest claims, badges and capped bar. '+output);
 }catch(error){if(page){await page.screenshot({path:path.join(output,'activity-failure.png')}).catch(()=>{});fs.writeFileSync(path.join(output,'activity-failure.txt'),error.stack+'\n'+await page.locator('body').innerText().catch(()=>''));}throw error;}
 finally{await browser.close();if(fixture&&fixture.exitCode===null){fixture.stdin.write('\n');await new Promise(resolve=>fixture.exitCode!==null?resolve():fixture.once('exit',resolve));}}
})().catch(error=>{console.error(error);console.error('Screenshots: '+output);process.exitCode=1;});
