'use strict';
const fs=require('fs'),path=require('path');
const {chromium}=require('C:/Users/svenm/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright');
const base=process.env.CASE_TEST_BASE,bug=Number(process.env.CASE_TEST_BUG),content=Number(process.env.CASE_TEST_CONTENT);
const output=path.join(__dirname,'../output/playwright/team-reports');fs.mkdirSync(output,{recursive:true});
const assert=(ok,message)=>{if(!ok)throw new Error(message);console.log('PASS '+message);};
const caseForm=(page,action)=>page.locator('form[data-case-form]').filter({has:page.locator(`input[name="case_action"][value="${action}"]`)});
(async()=>{const browser=await chromium.launch({headless:true});const errors=[];
try{
 const context=await browser.newContext({viewport:{width:1440,height:1000}});const page=await context.newPage();page.on('pageerror',e=>errors.push(e.message));
 await page.goto(base+'/fixture-login?id=2');await page.goto(base+`/admin/cases?source=bug&case_id=${bug}&status=all`);await page.locator('.case-detail').waitFor();
 assert(await page.locator('.case-context [name="assigned_to"]').count()===1,'Support sees the scoped assignment and case controls');
 assert((await context.request.get(base+'/admin/players')).status()===403&&(await context.request.get(base+'/admin/world')).status()===403,'Support direct URLs cannot enter player administration or world settings');
 assert((await context.request.get(base+'/admin/layout-data')).status()===403&&(await context.request.get(base+'/admin/links?export=csv')).status()===403,'Non-rendered layout and link export controllers also reject support directly');
 await page.goto(base+`/admin/bug-reports?world_id=1#meldung-${bug}`);await page.waitForURL(new RegExp(`case_id=${bug}.*#case-detail`));assert(await page.locator('#case-detail h2').innerText()==='Monster reward missing','Legacy report fragment opens the matching unified case');
 assert(!((await page.locator('.case-workspace').innerText()).includes('admin.cases.')),'Case workspace renders translated labels');
 const note=caseForm(page,'note');await note.locator('..').locator('summary').click();await note.locator('[name="body"]').fill('Browser private note');await note.locator('[name="reason"]').fill('Internal test note');await note.locator('button[type="submit"]').click();await page.waitForURL(/\/admin\/cases\?/);
 assert(await page.locator('.case-message.internal').filter({hasText:'Browser private note'}).count()===1,'Internal note persists with a private label');
 const stale=await context.newPage();await stale.goto(page.url());const staleNote=caseForm(stale,'note');await staleNote.locator('..').locator('summary').click();await staleNote.locator('[name="body"]').fill('Keep this stale draft');await staleNote.locator('[name="reason"]').fill('Stale form test');
 const update=caseForm(page,'update');await update.locator('[name="status"]').selectOption('waiting');await update.locator('[name="priority"]').selectOption('high');await update.locator('[name="reason"]').fill('Waiting for player');await update.locator('button[type="submit"]').click();await page.waitForURL(/\/admin\/cases\?/);
 await staleNote.locator('button[type="submit"]').click();await stale.waitForURL(/\/admin\/cases\?/);
 assert((await stale.locator('[role="alert"]').innerText()).includes('changed while you were editing'),'Concurrent editing returns a visible stale-case error');
 assert(await caseForm(stale,'note').locator('[name="body"]').inputValue()==='Keep this stale draft','Stale response preserves the authored note');await stale.close();
 await page.reload();const reply=caseForm(page,'reply');await reply.locator('[name="body"]').fill('Browser public support answer');await reply.locator('[name="reason"]').fill('Reply to reporting player');await reply.locator('button[type="submit"]').click();await page.waitForURL(/\/admin\/cases\?/);
 assert(await page.locator('.case-message.public').filter({hasText:'Browser public support answer'}).count()===1,'Public reply is stored separately from internal notes');
 for(const [width,height,lang] of [[1440,1000,'en'],[1024,768,'de'],[390,844,'de'],[320,700,'fr'],[844,390,'fr']]){
  await context.addCookies([{name:'conquer_locale',value:lang,url:base}]);await page.setViewportSize({width,height});await page.reload();await page.locator('.case-detail').waitFor();
  const overflow=await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth+1);assert(!overflow,`Team cases fit ${width}×${height} ${lang}`);
  await page.screenshot({path:path.join(output,`cases-${lang}-${width}.png`),fullPage:true});
 }
 const badCsrf=await context.request.post(base+'/admin/cases/action',{form:{source:'bug',case_id:String(bug),case_action:'note',body:'No CSRF',reason:'Reject forged form'}});assert(badCsrf.status()===403,'Admin case form rejects missing CSRF');
 await context.addCookies([{name:'conquer_locale',value:'en',url:base}]);await page.goto(base+'/fixture-login?id=3');await page.goto(base+`/admin/cases?source=content&case_id=${content}`);await page.locator('.case-context').waitFor();
 assert(await caseForm(page,'chat-ban').count()===1&&await page.locator('.case-list-item').count()===1,'Moderator sees reported-content cases and scoped moderation actions');
 assert((await context.request.get(base+`/admin/bug-reports/${bug}/screenshot`)).status()===403,'Moderator cannot retrieve private support screenshots directly');
 await page.goto(base+`/admin/cases?source=bug&case_id=${bug}`);assert(await page.locator('.case-detail').count()===0,'Moderator cannot open a support case using its direct URL');
 await context.close();
 const playerContext=await browser.newContext({viewport:{width:390,height:844}});await playerContext.addCookies([{name:'conquer_session',value:process.env.CASE_TEST_TOKEN,url:base}]);const player=await playerContext.newPage();player.on('pageerror',e=>errors.push(e.message));await player.goto(base+'/player-support');await player.waitForFunction(()=>typeof window.ConquerBugReports==='function'&&typeof window.ConquerLocale?.t==='function');
 await player.evaluate(({base,csrf})=>{
  const api=async(route,body)=>{const response=await fetch(base+'/api/'+route,{method:body?'POST':'GET',headers:body?{'Content-Type':'application/json','X-CSRF-Token':csrf}:{},body:body?JSON.stringify(body):undefined});const json=await response.json();if(!json.ok)throw new Error(json.error.message);return json.data;};
  window.testReports=window.ConquerBugReports({api,base,toast:()=>{},openDialog:()=>{},navigate:()=>window.testReports.render()});document.addEventListener('click',event=>{const b=event.target.closest('[data-action]');if(b)window.testReports.onClick(b.dataset.action,b);});document.addEventListener('submit',event=>{event.preventDefault();window.testReports.onSubmit(event.target);});window.testReports.render();
 },{base,csrf:process.env.CASE_TEST_CSRF});
 assert(await player.locator('[name="report_type"] option[value="support"]').count()===1,'Player can submit a dedicated support request');
 await player.locator('[data-action="support-case-list"]').click();await player.locator('[data-action="support-case-open"]').first().waitFor();await player.locator(`[data-action="support-case-open"][data-source="bug"][data-id="${bug}"]`).click();await player.locator('[data-form="support-reply"]').waitFor();
 const publicText=await player.locator('#content').innerText();assert(publicText.includes('Browser public support answer')&&!publicText.includes('Browser private note')&&!publicText.includes('Private note that'),'Player sees public answers while private team notes stay absent');
 await player.locator('[data-form="support-reply"] [name="body"]').fill('Browser player follow-up');await player.locator('[data-form="support-reply"] button').click();await player.locator('.support-player-message').filter({hasText:'Browser player follow-up'}).waitFor();
 assert((await player.locator('#content').innerText()).includes('New'),'Player follow-up reopens a waiting case');
 for(const [width,height] of [[1280,800],[390,844],[844,390]]){await player.setViewportSize({width,height});assert(!await player.evaluate(()=>document.documentElement.scrollWidth>innerWidth+1),`Player support conversation fits ${width}×${height}`);await player.screenshot({path:path.join(output,`player-conversation-${width}.png`),fullPage:true});}
 assert(errors.length===0,'No JavaScript page errors in team or player report workflows');await playerContext.close();
}finally{await browser.close();}})().catch(error=>{console.error(error);process.exitCode=1;});
