'use strict';
// Uses a temporary database and authenticates only the synthetic fixture account.
const fs=require('fs'),path=require('path'),net=require('net'),assert=require('assert/strict'),{spawn}=require('child_process');
require('./fixtures/browser_locale.cjs')('en');
const {chromium}=require(process.env.PLAYWRIGHT_MODULE||'playwright');
const output=path.resolve(__dirname,'../output/video-reference/qa/welcome');fs.mkdirSync(output,{recursive:true});
async function fixture(){
 const socket=net.createServer();await new Promise(r=>socket.listen(0,'127.0.0.1',r));const port=socket.address().port;await new Promise(r=>socket.close(r));
 const proc=spawn(process.env.PHP_BINARY||'C:/xampp/php/php.exe',['tools/preview-feature-fixture.php','--port='+port,'--hud','--chat'],{cwd:path.resolve(__dirname,'..'),stdio:['pipe','pipe','pipe'],windowsHide:true});
 let logs='';proc.stdout.on('data',s=>logs+=s);proc.stderr.on('data',s=>logs+=s);
 await new Promise((resolve,reject)=>{const timeout=setTimeout(()=>{clearInterval(poll);reject(Error(logs||'Fixture timed out'));},60000),poll=setInterval(()=>{if(logs.includes('Synthetic preview ready')){clearTimeout(timeout);clearInterval(poll);resolve();}else if(proc.exitCode!==null){clearTimeout(timeout);clearInterval(poll);reject(Error(logs));}},100);proc.once('error',reject);});
 return {proc,base:'http://127.0.0.1:'+port};
}
(async()=>{
 const {proc,base}=await fixture();let browser,page;
 try{
  browser=await chromium.launch({headless:true,executablePath:process.env.BROWSER_EXECUTABLE_PATH||'C:/Program Files/Google/Chrome/Application/chrome.exe'});
  page=await browser.newPage({viewport:{width:1280,height:800},hasTouch:true});page.setDefaultTimeout(20000);
  const errors=[];page.on('pageerror',e=>errors.push(e.message));let writes=0;const receipts=[];
  page.on('request',r=>{if(r.method()==='POST'&&r.url().endsWith('/api/kingdom/action')){writes++;receipts.push(r.postDataJSON());}});
  await page.goto(base+'/?zugang=login');await page.locator('[name=identifier],[name=username]').fill('PreviewPlayer');await page.locator('[name=password]').fill('PreviewFixture!2026');
  await Promise.all([page.waitForURL('**/city'),page.locator('form[action$="/auth/local"] button[type=submit]').click()]);
  await page.locator('#app-start').waitFor({state:'detached'});
  await page.locator('.hud-edge-button[data-id=events]').click();await page.locator('.welcome-event-teaser').waitFor();
  await page.locator('.welcome-event-teaser [data-id=welcome]').click();await page.locator('.welcome-event-list').waitFor();
  assert.equal(writes,0,'Opening the event must never claim a reward');assert.equal(await page.locator('.welcome-event-milestone').count(),7);
  assert.equal(await page.locator('[data-milestone=login_1] [data-action=welcome-claim]').count(),1);assert.equal(await page.locator('[data-milestone=login_2] button').count(),0);
  const original=await page.evaluate(async()=> (await(await fetch('/api/kingdom/state')).json()).data.welcome_event);
  assert.equal(original.visit_days,1,'Existing players receive no retroactive visits');
  for(const [width,height] of [[1280,800],[390,844],[320,568],[568,320],[844,390]]){
   await page.setViewportSize({width,height});await page.waitForTimeout(200);
   for(const category of ['login','growth']){
    await page.locator(`[data-action=welcome-tab][data-id=${category}]`).click();
    const boxes=await page.locator('.welcome-event').evaluate(root=>{const list=root.querySelector('.welcome-event-list'),buttons=[...root.querySelectorAll('.welcome-event-tabs button')],rect=list.getBoundingClientRect();return {width:root.clientWidth,scrollWidth:root.scrollWidth,listHeight:rect.height,visible:buttons.every(b=>{const r=b.getBoundingClientRect();return r.width>=43&&r.height>=43&&r.left>=0&&r.right<=innerWidth+1&&r.top>=0&&r.bottom<=innerHeight&&b.contains(document.elementFromPoint(r.x+r.width/2,r.y+r.height/2));})};});
    assert(boxes.scrollWidth<=boxes.width+1,JSON.stringify({width,height,category,...boxes}));assert(boxes.listHeight>=59,JSON.stringify({width,height,category,...boxes}));assert(boxes.visible,JSON.stringify({width,height,category,...boxes}));
    const first=page.locator('.welcome-event-list [data-action=welcome-claim]').first();await first.click({trial:true});
    assert(await first.evaluate(b=>{const r=b.getBoundingClientRect();return r.height>=43&&b.contains(document.elementFromPoint(r.x+r.width/2,r.y+r.height/2));}),JSON.stringify({width,height,category,reason:'Claim button visible and touchable'}));
    await page.screenshot({path:path.join(output,`${width}x${height}-${category}.png`)});
   }
  }
  await page.setViewportSize({width:390,height:844});await page.locator('[data-action=welcome-tab][data-id=login]').click();
  let loseResponse=true;
  await page.route('**/api/kingdom/action',async route=>{if(loseResponse&&route.request().postDataJSON()?.action==='welcome.claim'){loseResponse=false;await route.fetch();await route.abort('failed');}else await route.continue();});
  await page.locator('[data-action=welcome-claim][data-id=login_1]').click();await page.locator('.reward-recovery').waitFor();
  await page.reload();await page.locator('#app-start').waitFor({state:'detached'});await page.locator('.reward-recovery').waitFor();
  await page.locator('[data-action=reward-retry]').click();await page.locator('.reward-result').waitFor();
  assert.equal(receipts.length,2);assert.equal(receipts[0].operation_key,receipts[1].operation_key,'Lost reply retries same receipt');
  assert.equal(await page.locator('.reward-result .reward-item').count(),original.login_rewards[0].rewards.length);
  await page.screenshot({path:path.join(output,'confirmed-login-reward.png')});
  await page.locator('.reward-result [data-action=close-dialog]').click();await page.locator('#game-dialog').waitFor({state:'hidden'});
  await page.locator('.event-simple-tabs [data-id=welcome]').click();
  assert.equal(await page.locator('[data-milestone=login_1] [data-action=welcome-claim]').count(),0);
  const updated=await page.evaluate(async()=> (await(await fetch('/api/kingdom/state')).json()).data.welcome_event);
  assert(updated.login_rewards[0].claimed);assert.equal(updated.visit_days,1);assert.equal(updated.claimable_count,original.claimable_count-1);
  await page.waitForFunction(count=>Number(document.querySelector('.hud-edge-button[data-id=events] .welcome-event-count').textContent)===count,updated.claimable_count);
  await page.locator('[data-action=welcome-tab][data-id=growth]').click();await page.locator('[data-action=welcome-claim][data-id=castle_3]').click();await page.locator('.reward-result').waitFor();
  await page.locator('.reward-result [data-action=close-dialog]').click();await page.locator('#game-dialog').waitFor({state:'hidden'});
  assert.equal(await page.locator('[data-milestone=castle_3] [data-action=welcome-claim]').count(),0);
  await page.screenshot({path:path.join(output,'growth-claimed.png')});assert.deepEqual(errors,[]);
  console.log('PASS welcome events: real claims, lost-response replay, real rewards and HUD counts, five responsive layouts, no page errors. '+output);
 }catch(e){if(page){await page.screenshot({path:path.join(output,'failure.png')}).catch(()=>{});fs.writeFileSync(path.join(output,'failure.txt'),e.stack+'\n'+await page.locator('body').innerText().catch(()=>''));}throw e;}
 finally{await browser?.close();if(proc.exitCode===null){proc.stdin.write('\n');await new Promise(r=>proc.exitCode!==null?r():proc.once('exit',r));}}
})().catch(e=>{console.error(e);process.exitCode=1;});
