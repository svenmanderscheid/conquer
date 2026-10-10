'use strict';
// The actual /city shell, login and HTTP endpoints; only disposable synthetic accounts are changed.
const fs=require('fs'),path=require('path'),net=require('net'),{spawn}=require('child_process');
const {chromium}=require('C:/Users/svenm/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright');
const root=path.resolve(__dirname,'..'),out=path.join(root,'output/playwright/team-reports-city');fs.mkdirSync(out,{recursive:true});
const assert=(value,label)=>{if(!value)throw new Error(label);console.log('PASS '+label);};
(async()=>{
 const port=await new Promise(resolve=>{const server=net.createServer();server.listen(0,'127.0.0.1',()=>{const port=server.address().port;server.close(()=>resolve(port));});});
 const fixture=spawn('C:/xampp/php/php.exe',[root+'/tools/preview-feature-fixture.php','--port='+port,'--hud','--appearance'],{cwd:root,stdio:['pipe','pipe','pipe'],windowsHide:true});
 let browser,page,log='';const errors=[],base='http://127.0.0.1:'+port;
 try{
  await new Promise((resolve,reject)=>{const timer=setTimeout(()=>reject(new Error(log||'Fixture timeout')),60000);fixture.stdout.on('data',d=>{log+=d;if(log.includes('Synthetic preview ready')){clearTimeout(timer);resolve();}});fixture.stderr.on('data',d=>log+=d);fixture.once('exit',()=>{clearTimeout(timer);reject(new Error(log));});});
  if(process.argv.includes('--legacy-bug')){await new Promise((resolve,reject)=>{const regression=spawn(process.execPath,[root+'/tests/bug_reports_app.cjs'],{cwd:root,env:{...process.env,BUG_REPORT_URL:base,PLAYWRIGHT_MODULE:'C:/Users/svenm/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright'},stdio:'inherit',windowsHide:true});regression.once('exit',code=>code===0?resolve():reject(new Error('Legacy bug-report regression failed')));});return;}
  browser=await chromium.launch({headless:true});const context=await browser.newContext({viewport:{width:390,height:844}});page=await context.newPage();page.setDefaultTimeout(25000);page.on('pageerror',e=>errors.push(e.message));
  await page.goto(base+'/?zugang=login');await page.locator('[name=identifier], [name=username]').fill('PreviewPlayer');await page.locator('[name=password]').fill('PreviewFixture!2026');await Promise.all([page.waitForURL('**/city'),page.locator('form[action$="/auth/local"] button[type=submit]').click()]);await page.locator('.painted-village').waitFor();
  await page.goto(base+'/city#bugreport');await page.locator('[data-form="bug-report"]').waitFor();
  await page.locator('[name="report_type"]').selectOption('support');await page.locator('[name="title"]').fill('Main app support request');await page.locator('[name="description"]').fill('This is a synthetic support request from the real city panel.');
  const submitted=page.waitForResponse(r=>r.url().endsWith('/api/bug-reports')&&r.request().method()==='POST');await page.locator('[data-form="bug-report"] button[type="submit"]').click();const reportJson=await (await submitted).json();assert(reportJson.ok,'Real city support form stores a support request');const id=reportJson.data.id;
  await page.locator('[data-action="support-case-list"]').click();await page.locator(`[data-action="support-case-open"][data-source="bug"][data-id="${id}"]`).click();await page.locator('[data-form="support-reply"]').waitFor();
  const adminContext=await browser.newContext({viewport:{width:1280,height:900}}),admin=await adminContext.newPage();admin.on('pageerror',e=>errors.push(e.message));await admin.goto(base+'/admin/login');await admin.locator('[name="username"]').fill('PreviewAdmin');await admin.locator('[name="password"]').fill('PreviewFixture!2026');await Promise.all([admin.waitForURL(/\/admin$/),admin.locator('button[type="submit"]').click()]);await admin.goto(base+`/admin/cases?source=bug&case_id=${id}`);
  const form=action=>admin.locator('[data-case-form]').filter({has:admin.locator(`[name="case_action"][value="${action}"]`)});
  await form('note').locator('..').locator('summary').click();await form('note').locator('[name="body"]').fill('Only the team can read this internal city test note.');await form('note').locator('[name="reason"]').fill('Internal city fixture note');await form('note').locator('[type="submit"]').click();await admin.waitForURL(/\/admin\/cases\?/);
  await form('reply').locator('[name="body"]').fill('Public answer from the support team in the real app.');await form('reply').locator('[name="reason"]').fill('Reply to city fixture');await form('reply').locator('[type="submit"]').click();await admin.waitForURL(/\/admin\/cases\?/);
  await page.locator('[data-action="support-case-list"]').click();await page.locator(`[data-action="support-case-open"][data-id="${id}"]`).click();await page.locator('.support-player-message.from-team').waitFor();
  assert((await page.locator('#content').innerText()).includes('Public answer from the support team')&&!(await page.locator('#content').innerText()).includes('Only the team can read'),'Real city conversation receives the public answer without the internal note');
  // Simulate losing the response after the server committed the reply; retry must retain its operation.
  let lose=true;await page.route(`**/api/support-cases/bug/${id}/reply`,async route=>{if(lose){lose=false;await route.fetch();await route.abort('failed');}else await route.continue();});
  await page.locator('[data-form="support-reply"] [name="body"]').fill('Reply whose first response is lost.');await page.locator('[data-form="support-reply"] button').click();await page.waitForFunction(()=>document.querySelector('[data-form="support-reply"] button')?.disabled===false);
  await page.locator('[data-form="support-reply"] button').click();await page.locator('.support-player-message').filter({hasText:'Reply whose first response is lost.'}).waitFor();
  assert(await page.locator('.support-player-message').filter({hasText:'Reply whose first response is lost.'}).count()===1,'Lost-response retry creates one public reply in the real app');
  for(const [width,height] of [[1280,800],[390,844],[320,700],[844,390]]){
   await page.setViewportSize({width,height});await page.waitForTimeout(120);await page.locator('[data-form="support-reply"] button').scrollIntoViewIfNeeded();
   const measured=await page.locator('[data-form="support-reply"] button').boundingBox();if(measured?.height<44)console.log(await page.locator('[data-form="support-reply"] button').evaluate(el=>{const c=getComputedStyle(el),rules=[];const scan=list=>{for(const r of list){if(r.selectorText&&r.style.minHeight&&el.matches(r.selectorText))rules.push([r.selectorText,r.style.minHeight]);if(r.cssRules)scan(r.cssRules);}};for(const s of document.styleSheets)scan(s.cssRules);return {minHeight:c.minHeight,height:c.height,transform:c.transform,zoom:c.zoom,rules};}));assert(measured&&measured.x>=0&&measured.x+measured.width<=width+1&&measured.y>=0&&measured.y+measured.height<=height+1&&measured.height>=44,`Real city support send action is reachable at ${width}×${height}: ${JSON.stringify(measured)}`);
   assert(!await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth+1),`Real city support has no horizontal page overflow at ${width}×${height}`);
   await page.screenshot({path:path.join(out,`city-support-${width}.png`)});
  }
  assert(errors.length===0,'Real city and team support flows have no JavaScript page errors');await adminContext.close();await context.close();
 }catch(error){if(page)await page.screenshot({path:path.join(out,'failure.png')}).catch(()=>{});throw error;}
 finally{if(browser)await browser.close();if(fixture.exitCode===null){fixture.stdin.end('\n');await new Promise(resolve=>fixture.once('exit',resolve));}}
})().catch(error=>{console.error(error);process.exitCode=1;});
