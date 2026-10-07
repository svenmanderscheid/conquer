'use strict';
const assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path'),net=require('node:net');
const {spawn}=require('node:child_process'),{chromium}=require(process.env.PLAYWRIGHT_MODULE||'playwright');
const root=path.resolve(__dirname,'..'),out=path.join(root,'output/playwright/completion-reports');
const keys=['report_build_complete','report_research_complete','report_train_complete','report_heal_complete'];
(async()=>{
 fs.mkdirSync(out,{recursive:true});
 const port=await new Promise(resolve=>{const server=net.createServer();server.listen(0,'127.0.0.1',()=>{const port=server.address().port;server.close(()=>resolve(port));});});
 const fixture=spawn(process.env.PHP_BINARY||'C:/xampp/php/php.exe',[root+'/tools/preview-feature-fixture.php','--appearance','--port='+port],{cwd:root,stdio:['pipe','pipe','pipe'],windowsHide:true});
 let browser,log='';const errors=[];
 try {
  await new Promise((resolve,reject)=>{const timer=setTimeout(()=>reject(Error(log||'Preview timeout')),60000);fixture.stdout.on('data',data=>{log+=data;if(log.includes('Synthetic preview ready')){clearTimeout(timer);resolve();}});fixture.stderr.on('data',data=>log+=data);fixture.on('error',reject);fixture.on('exit',()=>{clearTimeout(timer);reject(Error(log));});});
  browser=await chromium.launch({headless:true,executablePath:process.env.BROWSER_EXECUTABLE_PATH||'C:/Program Files/Google/Chrome/Application/chrome.exe'});
  for (const [locale,title] of [['en','Completion reports'],['de','Fertigstellungsberichte'],['fr','Rapports de fin']]) {
   const context=await browser.newContext({viewport:{width:1280,height:800},hasTouch:true,locale:'de-DE'});
   // English uses no explicit preference, including with a German browser locale.
   if(locale!=='en')await context.addInitScript(value=>{localStorage.setItem('conquer.locale',value);document.cookie='conquer_locale='+value+'; Path=/; SameSite=Lax';},locale);
   const page=await context.newPage();page.setDefaultTimeout(20000);page.on('pageerror',e=>errors.push(e.message));
   const base='http://127.0.0.1:'+port;
   await page.goto(base+'/?zugang=login');await page.locator('[name=identifier], [name=username]').fill('PreviewPlayer');await page.locator('[name=password]').fill('PreviewFixture!2026');
   await Promise.all([page.waitForURL('**/city'),page.locator('form[action$="/auth/local"] button[type="submit"]').click()]);
   await page.goto(base+'/city#city');await page.locator('#navigation').waitFor();
   await page.goto(base+'/city#settings');await page.locator('.completion-report-settings').waitFor();
   assert.equal(await page.locator('.completion-report-settings legend').innerText(),title);
   assert.equal(await page.evaluate(()=>ConquerLocale.locale),locale);
   assert.equal(await page.locator('[name=report_heal_complete]').locator('..').locator('strong').innerText(),{en:'Healing reports',de:'Heilungsberichte',fr:'Rapports de soins'}[locale]);
   for(const [width,height] of [[1280,800],[390,844],[320,568],[844,390],[568,320]]) {
    await page.setViewportSize({width,height});
    for(const key of keys) {
     const input=page.locator(`[name=${key}]`),label=input.locator('..');await label.scrollIntoViewIfNeeded();
     const layout=await label.evaluate(el=>{const rect=el.getBoundingClientRect(),input=el.querySelector('input'),r=input.getBoundingClientRect(),top=document.elementFromPoint(r.x+r.width/2,r.y+r.height/2),fieldset=el.closest('fieldset');return {touch:rect.height>=44,fits:rect.left>=0&&rect.right<=innerWidth+1,visible:r.top>=0&&r.bottom<=innerHeight+1,uncovered:top===input||input.contains(top),overflow:fieldset.scrollWidth-fieldset.clientWidth};});
     assert(layout.touch&&layout.fits&&layout.visible&&layout.uncovered&&layout.overflow<=1,JSON.stringify({locale,width,height,key,...layout}));
     const before=await input.isChecked();await label.tap();assert.equal(await input.isChecked(),!before,'whole label toggles by touch');await label.tap();
    }
    const save=page.locator('form[data-form=settings] button');await save.scrollIntoViewIfNeeded();
    const box=await save.boundingBox();assert(box.y>=0&&box.y+box.height<=height+1&&box.x>=0&&box.x+box.width<=width+1,'save reachable after scrolling');
    await page.screenshot({path:path.join(out,`${locale}-${width}x${height}.png`)});
   }
   await page.setViewportSize({width:390,height:844});
   for (const key of keys)await page.locator(`[name=${key}]`).check();
   await page.locator('[name=report_build_complete]').uncheck();await page.locator('[name=report_train_complete]').uncheck();
   await page.locator('[name=report_heal_complete]').uncheck();
   const responsePromise=page.waitForResponse(r=>r.url().endsWith('/api/kingdom/action')&&r.request().postDataJSON()?.action==='settings.save');
   await page.locator('form[data-form=settings] button').tap();
   const response=await responsePromise;assert.equal(response.status(),200);
   const data=await response.json();for(const key of keys)assert.equal(data.data.state.settings[key],key==='report_research_complete');
   await page.reload();await page.locator('.completion-report-settings').waitFor();
   for(const key of keys)assert.equal(await page.locator(`[name=${key}]`).isChecked(),key==='report_research_complete','preferences survive page reload');
   for(const key of keys)await page.locator(`[name=${key}]`).check();
   const saved=page.waitForResponse(r=>r.url().endsWith('/api/kingdom/action')&&r.request().postDataJSON()?.action==='settings.save');await page.locator('form[data-form=settings] button').tap();assert.equal((await saved).status(),200);
   await context.close();
  }
  assert.deepEqual(errors,[]);console.log('PASS completion report settings: 3 languages, 15 desktop/portrait/landscape layouts, touch labels, save and persistence. '+out);
 } finally {
  if(browser)await browser.close();
  const stopped=new Promise(resolve=>fixture.once('exit',resolve));fixture.stdin.write('\n');
  await Promise.race([stopped,new Promise((_,reject)=>{const timer=setTimeout(()=>reject(Error('Fixture cleanup timeout')),15000);timer.unref();})]);
 }
})().catch(e=>{console.error(e);process.exitCode=1;});
