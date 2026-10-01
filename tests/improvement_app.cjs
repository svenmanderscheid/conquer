'use strict';
// Real main app, disposable data only: contribution briefing, admin metrics and boss explanation.
const fs=require('fs'),path=require('path'),assert=require('assert/strict'),net=require('net');
const {spawn}=require('child_process'),{chromium}=require(process.env.PLAYWRIGHT_MODULE||'playwright');
const root=path.resolve(__dirname,'..'),out=path.join(root,'artifacts/improvements-2026-09-30');fs.mkdirSync(out,{recursive:true});
const sizes=[[1280,800],[390,844],[320,568],[844,390],[568,320]];
async function fixture(flags,work){
 const port=await new Promise(resolve=>{const s=net.createServer();s.listen(0,'127.0.0.1',()=>{const p=s.address().port;s.close(()=>resolve(p));});});
 const child=spawn(process.env.PHP_BINARY||'C:/xampp/php/php.exe',['tools/preview-feature-fixture.php','--port='+port,'--appearance',...flags],{cwd:root,stdio:['pipe','pipe','pipe'],windowsHide:true});let log='';
 try{await new Promise((resolve,reject)=>{const timer=setTimeout(()=>reject(Error(log||'Fixture timeout')),60000);child.stdout.on('data',d=>{log+=d;if(log.includes('Synthetic preview ready')){clearTimeout(timer);resolve();}});child.stderr.on('data',d=>log+=d);child.on('exit',code=>{clearTimeout(timer);reject(Error('Fixture '+code+': '+log));});});await work('http://127.0.0.1:'+port);}
 finally{child.stdin.write('\n');await new Promise(resolve=>{if(child.exitCode!==null)return resolve();child.once('exit',resolve);});}
}
async function login(page,base){await page.goto(base+'/?zugang=login');await page.locator('[name=identifier]').fill('PreviewPlayer');await page.locator('[name=password]').fill('PreviewFixture!2026');await Promise.all([page.waitForURL('**/city'),page.locator('form[action$="/auth/local"] button[type=submit]').click()]);await page.locator('.painted-village').waitFor();}
async function fits(page,selector){return page.locator(selector).evaluate(el=>{const r=el.getBoundingClientRect();return r.x>=-1&&r.y>=-1&&r.right<=innerWidth+1&&r.bottom<=innerHeight+1&&el.scrollWidth<=el.clientWidth+2;});}
(async()=>{const browser=await chromium.launch({headless:true,channel:'chrome'}),errors=[];
 try{
  await fixture(['--territory'],async base=>{
   const page=await browser.newPage({viewport:{width:1280,height:800},hasTouch:true});page.on('pageerror',e=>errors.push(e.message));page.setDefaultTimeout(20000);await login(page,base);
   const territory=(await(await page.request.get(base+'/api/territory/state')).json()).data,goal=territory.targets.find(t=>t.id===territory.goal.target_id);
   await page.locator('#navigation [data-id=world]').click();await page.waitForSelector('.atlas-shell');await page.evaluate(t=>ConquerWorld.focus(t.x,t.y),goal);await page.locator(`[data-atlas-target="territory:${goal.id}"]`).click();await page.waitForSelector('.territory-target-summary');
   await page.locator('[data-action=territory-tab][data-id=goal]').click();await page.locator('.alliance-contribution').waitFor();
   assert.match(await page.locator('.alliance-contribution').innerText(),/Your part in the plan/);
   for(const [width,height]of sizes){await page.setViewportSize({width,height});await page.waitForTimeout(120);assert(await fits(page,'.territory-shell'),'briefing fits '+width+'x'+height);assert(await fits(page,'.territory-heading [data-action=territory-close]'),'briefing close reachable');await page.locator('.alliance-contribution').scrollIntoViewIfNeeded();await page.screenshot({path:path.join(out,`alliance-${width}x${height}.png`)});}
   await page.locator('[data-action=territory-close]').click();
   await page.goto(base+'/admin/login');await page.locator('[name=username]').fill('PreviewAdmin');await page.locator('[name=password]').fill('PreviewFixture!2026');await page.locator('button[type=submit]').click();await page.waitForURL(/\/admin/);await page.goto(base+'/admin/analytics');await page.locator('.alpha-playtest').waitFor();
   for(const [width,height]of sizes){await page.setViewportSize({width,height});await page.waitForTimeout(80);assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+2),true,'admin no horizontal overflow');assert.match(await page.locator('.alpha-playtest').innerText(),/No fully observed cohort yet/);await page.locator('.alpha-playtest h2').evaluate(el=>el.scrollIntoView({block:'start'}));await page.screenshot({path:path.join(out,`analytics-${width}x${height}.png`)});}
   await page.close();console.log('PASS alliance briefing and alpha metrics in five viewports');
  });
  await fixture(['--regional-bosses'],async base=>{
   const page=await browser.newPage({viewport:{width:1280,height:800},hasTouch:true});page.on('pageerror',e=>errors.push(e.message));page.setDefaultTimeout(20000);await login(page,base);await page.locator('#navigation [data-id=world]').click();await page.waitForSelector('.atlas-shell');
   await page.evaluate(()=>ConquerWorld.focus(64,64));await page.locator('.atlas-marker--monsters').filter({has:page.locator('img[src$="/grumwald.png"]')}).first().click();await page.locator('#march-preflight .boss-mechanic').waitFor();
   assert.match(await page.locator('#march-preflight .boss-mechanic').innerText(),/12%.*30%/s);
   await page.waitForFunction(()=>[...document.querySelectorAll('#game-dialog img')].every(img=>img.complete&&img.naturalWidth>0));
   for(const [width,height]of sizes){await page.setViewportSize({width,height});await page.waitForTimeout(100);const troopTab=page.locator('[data-action=march-view][data-id=troops]');if(await troopTab.isVisible())await troopTab.click();assert(await fits(page,'#game-dialog'),'boss march fits');await page.locator('#march-preflight .boss-mechanic').scrollIntoViewIfNeeded();assert(await page.locator('#march-preflight .boss-mechanic').evaluate(el=>el.scrollWidth<=el.clientWidth+2),'boss copy wraps');assert(await fits(page,'#march-confirm'),'confirm remains reachable');await page.screenshot({path:path.join(out,`grumwald-${width}x${height}.png`)});}
   await page.setViewportSize({width:1280,height:800});await page.locator('[data-action=march-preview]').click();await page.locator('.battle-preview-result .boss-mechanic').waitFor();assert.match(await page.locator('.battle-preview-result .boss-mechanic').innerText(),/Ranged share of base troop power/);assert.doesNotMatch(await page.locator('.battle-preview-dialog').innerText(),/Kampfrechner|Kampfglück|Monster überlebt|Truppen/);await page.screenshot({path:path.join(out,'grumwald-calculator.png')});await page.close();console.log('PASS Grumwald preflight and real calculator');
  });
  assert.deepEqual(errors,[]);fs.writeFileSync(path.join(out,'report.json'),JSON.stringify({errors,sizes,passed:true},null,2));console.log('ALL IMPROVEMENT APP CHECKS PASSED');
 }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1;});
