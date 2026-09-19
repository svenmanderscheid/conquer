'use strict';
const assert=require('node:assert/strict'),path=require('node:path'),net=require('node:net'),{spawn}=require('node:child_process');
const {chromium}=require(process.env.PLAYWRIGHT_MODULE||'playwright');
const root=path.resolve(__dirname,'..');
(async()=>{
 const port=await new Promise(resolve=>{const server=net.createServer();server.listen(0,'127.0.0.1',()=>{const port=server.address().port;server.close(()=>resolve(port));});});
 const fixture=spawn(process.env.PHP_BINARY||'C:/xampp/php/php.exe',[root+'/tools/preview-feature-fixture.php','--port='+port,'--training','--hospital'],{cwd:root,stdio:['pipe','pipe','pipe'],windowsHide:true});
 let browser,log='';const errors=[];
 try{
  await new Promise((resolve,reject)=>{fixture.stdout.on('data',data=>{log+=data;if(log.includes('Synthetic preview ready'))resolve();});fixture.stderr.on('data',data=>log+=data);fixture.on('error',reject);fixture.on('exit',()=>reject(Error(log)));});
  browser=await chromium.launch({headless:true,executablePath:process.env.BROWSER_EXECUTABLE_PATH||'C:/Program Files/Google/Chrome/Application/chrome.exe',args:['--host-resolver-rules=MAP conquer-http.test 127.0.0.1','--no-proxy-server']});
  const page=await browser.newPage({viewport:{width:390,height:844},hasTouch:true});page.setDefaultTimeout(10000);
  await page.addInitScript(()=>window.__nativeRandomUuid=crypto.randomUUID);
  page.on('pageerror',e=>errors.push(e.message));const base='http://conquer-http.test:'+port;
  await page.goto(base);await page.locator('[data-mode=login]').click();await page.locator('[name=username]').fill('PreviewPlayer');await page.locator('[name=password]').fill('PreviewFixture!2026');
  await Promise.all([page.waitForURL('**/city'),page.locator('#auth-submit').click()]);
  await page.goto(base+'/city#army');await page.locator('.training-school').waitFor();
  assert.equal(await page.locator('#train-count').inputValue(),await page.locator('#train-count').getAttribute('max'),'Training starts with the maximum affordable amount');
  assert.equal(await page.evaluate(()=>isSecureContext),false,'Exercise a real HTTP origin, not trusted localhost');
  assert.equal(await page.evaluate(()=>typeof window.__nativeRandomUuid),'undefined','The browser really lacks native randomUUID');
  const uuid=/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/;
  const api=p=>page.evaluate(async p=>(await(await fetch('/api/'+p)).json()).data,p);
  const before=await api('game/state');await page.locator('#train-count').fill('200');await page.locator('#train-count').press('Tab');
  const response=page.waitForResponse(r=>r.url().endsWith('/api/troops/train')&&r.request().method()==='POST',{timeout:5000});
  await page.locator('#train-confirm').tap();const result=await response;assert.equal(result.status(),200,await result.text());
  const firstRequest=result.request().postDataJSON();assert.match(firstRequest.operation_key,uuid);
  await page.locator('[data-training-running]').waitFor({state:'visible'});assert.equal((await api('game/state')).troop_queue.length,1);
  await page.locator('[data-action=training-speedups]').tap();await page.locator('.queue-speedup-option[data-id="10103003"]').tap();await page.locator('#queue-speedup-quantity').fill('1');
  const sped=page.waitForResponse(r=>r.url().endsWith('/api/kingdom/action')&&r.request().method()==='POST');await page.locator('[data-action=queue-speedup-use]').tap();
  const speedup=await sped;assert.equal(speedup.status(),200,await speedup.text());assert.match(speedup.request().postDataJSON().operation_key,uuid);assert.notEqual(speedup.request().postDataJSON().operation_key,firstRequest.operation_key);
  await page.locator('#game-dialog').waitFor({state:'hidden'});
  assert.equal((await api('game/state')).troops['50100101']-before.troops['50100101'],200);
  // A lost reply must reuse the exact saved request after reloading over HTTP.
  await page.locator('[data-action=training-school][data-id=archery_range]').tap();await page.locator('#train-count').fill('200');await page.locator('#train-count').press('Tab');
  let lostRequest;await page.route('**/api/troops/train',async route=>{
   try{lostRequest=route.request().postDataJSON();const reply=await route.fetch({url:route.request().url().replace('conquer-http.test','127.0.0.1')});assert.equal(reply.status(),200);}
   catch(error){errors.push('Fixture proxy: '+error.message.split('\n')[0]);}
   await route.abort('failed');
  },{times:1});
  await page.locator('#train-confirm').tap();await page.locator('[data-training-request]').waitFor({state:'visible'});
  await page.reload();await page.locator('[data-training-request]').waitFor({state:'visible'});
  const retried=page.waitForResponse(r=>r.url().endsWith('/api/troops/train')&&r.request().method()==='POST');await page.locator('[data-action=training-retry]').tap();
  const replay=await retried;assert.equal(replay.status(),200,await replay.text());assert.deepEqual(replay.request().postDataJSON(),lostRequest);
  await page.locator('[data-training-request]').waitFor({state:'hidden'});assert.equal((await api('game/state')).troop_queue.length,1);
  // Hospital confirmation and the shared quantity picker use the same browser capability.
  await page.locator('[data-action=panel-tab][data-group=army][data-id=hospital]').tap();await page.locator('#hospital-number-50100101').fill('600');
  const healing=page.waitForResponse(r=>r.url().endsWith('/api/hospital/heal')&&r.request().method()==='POST');await page.locator('[data-action=hospital-heal]').tap();assert.equal(await page.locator('[data-action=confirm-operation]').count(),0);
  const healed=await healing;assert.equal(healed.status(),200,await healed.text());assert.match(healed.request().postDataJSON().operation_key,uuid);
  await page.locator('.hospital-running').waitFor();const hospitalStock=(await api('kingdom/state')).inventory.find(i=>i.subcategory==='healing'&&i.duration_seconds===60);
  await page.locator('[data-action=hospital-speedups]').tap();await page.locator(`.queue-speedup-option[data-id="${hospitalStock.item_code}"]`).tap();await page.locator('#queue-speedup-quantity').fill('2');
  const accelerated=page.waitForResponse(r=>r.url().endsWith('/api/hospital/speedup')&&r.request().method()==='POST');await page.locator('[data-action=queue-speedup-use]').tap();
  const faster=await accelerated;assert.equal(faster.status(),200,await faster.text());assert.equal((await faster.json()).data.quantity,2);
  await page.waitForFunction(()=>document.querySelector('.queue-speedup-feedback')?.textContent.includes('2 verwendet'));
  assert.equal((await api('kingdom/state')).inventory.find(i=>i.item_code===hospitalStock.item_code).quantity,hospitalStock.quantity-2);
  // Secure origins retain the browser's own implementation.
  const securePage=await browser.newPage();await securePage.goto('http://127.0.0.1:'+port);
  assert.equal(await securePage.evaluate(()=>{window.__originalRandomUuid=crypto.randomUUID;return isSecureContext&&typeof crypto.randomUUID==='function';}),true);
  await securePage.addScriptTag({url:'http://127.0.0.1:'+port+'/assets/js/browser-compat.js'});
  assert.equal(await securePage.evaluate(()=>crypto.randomUUID===window.__originalRandomUuid),true);await securePage.close();
  assert.deepEqual(errors,[]);console.log('PASS LAN HTTP at phone size: training start, training speedup, exact retry after lost reply/reload, hospital start/bulk speedup, distinct UUID v4 receipts; native secure-origin UUID unchanged.');
 }catch(error){console.error({browserErrors:errors});throw error;}
 finally{if(browser)await browser.close();if(fixture.exitCode===null){fixture.stdin.end('\n');await new Promise(resolve=>fixture.once('exit',resolve));}}
})().catch(e=>{console.error(e);process.exitCode=1;});
