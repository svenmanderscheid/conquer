'use strict';
const fs=require('fs'),path=require('path'),net=require('net'),assert=require('assert/strict'),{spawn}=require('child_process'),{chromium}=require('playwright');
const root=path.resolve(__dirname,'..'),out=path.join(root,'artifacts/charm-runes');fs.mkdirSync(out,{recursive:true});
(async()=>{
 const port=await new Promise(resolve=>{const s=net.createServer();s.listen(0,'127.0.0.1',()=>{const p=s.address().port;s.close(()=>resolve(p));});});
 const fixture=spawn('C:/xampp/php/php.exe',[root+'/tools/preview-feature-fixture.php','--port='+port,'--charm-runes','--regional-bosses','--appearance'],{cwd:root,windowsHide:true,stdio:['pipe','pipe','pipe']});let log='',browser,page;const errors=[];
 try{
  await new Promise((resolve,reject)=>{const t=setTimeout(()=>reject(Error(log)),60000);fixture.stdout.on('data',d=>{log+=d;if(log.includes('Synthetic preview ready')){clearTimeout(t);resolve();}});fixture.stderr.on('data',d=>log+=d);fixture.on('error',reject);fixture.on('exit',()=>{clearTimeout(t);reject(Error(log));});});
  browser=await chromium.launch({headless:true,channel:'chrome'});const context=await browser.newContext({viewport:{width:1280,height:800},hasTouch:true});
  await context.addInitScript(()=>{if(location.pathname.endsWith('/city')&&!location.hash)history.replaceState(null,'','#world');});
  page=await context.newPage();page.setDefaultTimeout(20000);page.on('pageerror',e=>errors.push(e.message));
  await page.goto('http://127.0.0.1:'+port+'/?zugang=login');await page.locator('[name=username]').fill('PreviewPlayer');await page.locator('[name=password]').fill('PreviewFixture!2026');
  await Promise.all([page.waitForURL('**/city'),page.locator('#auth-submit').click()]);
  await page.locator('.atlas-marker--charms').first().waitFor({state:'attached'});await page.evaluate(()=>ConquerWorld.focus(72,69));
  const markers=page.locator('.atlas-marker--charms');assert.equal(await markers.count(),3);
  await markers.locator('img').evaluateAll(imgs=>Promise.all(imgs.map(img=>img.decode())));
  const sizes=await page.evaluate(()=>{
   const size=selector=>{const el=document.querySelector(selector),s=getComputedStyle(el);return parseFloat(s.width)/parseFloat(s.getPropertyValue('--tile-size'));};
   return{charm:size('.atlas-marker--charms>img'),solo:size('.atlas-marker--monsters[data-monster-type=solo]>img'),rally:size('.atlas-marker--monsters.is-regional-boss>img'),mine:size('.atlas-marker--nodes>img[data-life-kind=gold]')};
  });
  assert(Math.abs(sizes.charm-1.0125)<.02&&Math.abs(sizes.solo-1.75)<.02&&Math.abs(sizes.rally-2.7)<.02&&Math.abs(sizes.mine-2.3125)<.02,JSON.stringify(sizes));
  assert(sizes.charm<sizes.solo&&sizes.solo<sizes.rally);
  for(const grade of ['normal','epic','legendary']){const marker=page.locator('.atlas-marker--charms[data-grade="'+grade+'"]');assert.match(await marker.locator('img').getAttribute('src'),new RegExp('/runes-v1/'+grade+'\\.webp$'));assert.equal(await marker.locator('.charm-rune-spark').count(),3);}
  const crystal=markers.first().locator('img');const first=await crystal.evaluate(el=>getComputedStyle(el).transform);await page.waitForFunction(previous=>getComputedStyle(document.querySelector('.atlas-marker--charms>img')).transform!==previous,first);
  for(const [width,height]of [[1280,800],[390,844],[320,568],[844,390]]){
   await page.setViewportSize({width,height});await page.evaluate(()=>ConquerWorld.focus(72,69));await page.waitForTimeout(200);
   await page.screenshot({path:path.join(out,width+'x'+height+'-map.png')});
  }
  await page.setViewportSize({width:390,height:844});await page.evaluate(()=>ConquerWorld.focus(72,69));await page.waitForTimeout(200);
  const epic=page.locator('.atlas-marker--charms[data-grade=epic]');await epic.click();
  const collect=page.locator('.atlas-target-actions [data-kind=charms][data-action=expedition]');await collect.waitFor();await page.screenshot({path:path.join(out,'390-menu.png')});
  await collect.click();await page.locator('.march-command').waitFor();
  await page.locator('[data-action=march-view][data-id=target]').click();await page.locator('.march-target-art img').evaluate(img=>img.decode());assert.match(await page.locator('.march-target-art img').getAttribute('src'),/runes-v1\/epic-detail\.webp$/);
  await page.screenshot({path:path.join(out,'390-collect.png')});await page.keyboard.press('Escape');
  await page.emulateMedia({reducedMotion:'reduce'});assert.equal(await crystal.evaluate(el=>getComputedStyle(el).animationName),'none');
  await page.emulateMedia({reducedMotion:'no-preference'});await page.evaluate(()=>document.body.classList.add('reduced-motion'));assert.equal(await crystal.evaluate(el=>getComputedStyle(el).animationName),'none');
  assert.deepEqual(errors,[]);console.log('PASS three rune grades, asset loading, animation, reduced motion, four viewports, target selection and collection preview.');
 }catch(e){if(page)await page.screenshot({path:path.join(out,'failure.png')}).catch(()=>{});throw e;}
 finally{if(browser)await browser.close();fixture.stdin.end('\n');await new Promise(resolve=>fixture.exitCode!==null?resolve():fixture.once('exit',resolve));}
})().catch(e=>{console.error(e);process.exitCode=1;});
