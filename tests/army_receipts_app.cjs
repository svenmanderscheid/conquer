'use strict';
const assert=require('node:assert/strict'),path=require('node:path'),net=require('node:net'),fs=require('node:fs'),{spawn}=require('node:child_process');
const {chromium}=require(process.env.PLAYWRIGHT_MODULE||'playwright');
const root=path.resolve(__dirname,'..');
(async()=>{
 const port=await new Promise(resolve=>{const s=net.createServer();s.listen(0,'127.0.0.1',()=>{const p=s.address().port;s.close(()=>resolve(p));});});
 const fixture=spawn('C:/xampp/php/php.exe',[root+'/tools/preview-feature-fixture.php','--port='+port,'--gathering','--army-receipts'],{cwd:root,stdio:['pipe','pipe','pipe'],windowsHide:true});
 let browser,log='';const errors=[];
 try{
  await new Promise((resolve,reject)=>{fixture.stdout.on('data',d=>{log+=d;if(log.includes('Synthetic preview ready'))resolve();});fixture.stderr.on('data',d=>log+=d);fixture.on('exit',()=>reject(Error(log)));fixture.on('error',reject);});
  browser=await chromium.launch({headless:true,executablePath:'C:/Program Files/Google/Chrome/Application/chrome.exe'});
  const page=await browser.newPage({viewport:{width:390,height:844},hasTouch:true});page.setDefaultTimeout(15000);page.on('pageerror',e=>errors.push(e.message));
  const base='http://127.0.0.1:'+port;
  await page.goto(base,{waitUntil:'domcontentloaded',timeout:30000});await page.locator('[data-mode=login]').click();await page.locator('[name=username]').fill('PreviewPlayer');await page.locator('[name=password]').fill('PreviewFixture!2026');
  await Promise.all([page.waitForURL('**/city',{waitUntil:'domcontentloaded',timeout:30000}),page.locator('#auth-submit').click()]);
  await page.goto(base+'/city#defense',{waitUntil:'domcontentloaded',timeout:30000});
  await page.locator('[data-action=defense-tab][data-id=support]').click();
  await page.locator('[name=target_player_id]').fill('2');
  let sent,original;
  await page.route('**/api/defense/action',async route=>{
   sent=route.request().postDataJSON();const reply=await route.fetch();assert.equal(reply.status(),200,await reply.text());original=await reply.json();await route.abort('failed');
  },{times:1});
  await page.locator('form[data-form=defense-dispatch] button[type=submit]').click();
  await page.locator('[data-action=command-retry]').waitFor();assert.match(sent.operation_key,/^[a-f0-9-]{36}$/);
  fs.mkdirSync(path.join(root,'artifacts/security-recovery'),{recursive:true});
  for(const [width,height]of[[1280,800],[390,844],[320,568],[844,390]]){
   await page.setViewportSize({width,height});await page.reload({waitUntil:'domcontentloaded',timeout:30000});
   const button=page.locator('[data-action=command-retry]');await button.waitFor();
   const rect=await button.boundingBox();assert.ok(rect&&rect.x>=0&&rect.y>=0&&rect.x+rect.width<=width&&rect.y+rect.height<=height,'recovery action visible');
   await page.screenshot({path:path.join(root,`artifacts/security-recovery/${width}x${height}.png`)});
  }
  const retried=page.waitForResponse(r=>r.url().endsWith('/api/defense/action')&&r.request().method()==='POST');
  await page.locator('[data-action=command-retry]').click();const replay=await retried;
  assert.equal(replay.status(),200,await replay.text());assert.deepEqual(replay.request().postDataJSON(),sent);
  assert.equal(replay.headers()['x-operation-replayed'],'1');assert.deepEqual(await replay.json(),original);
  await page.locator('[data-action=command-retry]').waitFor({state:'hidden'});
  const pending=await page.evaluate(()=>Object.keys(sessionStorage).filter(k=>k.startsWith('conquer:command:')));assert.equal(pending.length,0);
  assert.deepEqual(errors,[]);console.log('PASS real scout command: lost reply, persisted exact retry after reload, original receipt, desktop/390/320/landscape recovery.');
 }finally{if(browser)await browser.close();if(fixture.exitCode===null){fixture.stdin.end('\n');await new Promise(resolve=>fixture.once('exit',resolve));}}
})().catch(e=>{console.error(e);process.exitCode=1;});
