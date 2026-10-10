'use strict';
const fs=require('node:fs'),path=require('node:path'),assert=require('node:assert/strict');
const {chromium}=require(process.env.PLAYWRIGHT_MODULE||'C:/Users/svenm/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright');
const base=process.env.OPS_TEST_BASE;
if(!base)throw new Error('Run through php tests/admin_operations_browser.php');
const output=path.join(__dirname,'../output/playwright/admin-operations');fs.mkdirSync(output,{recursive:true});
const checks=[],errors=[],failedResponses=[];
function ck(condition,label){assert.ok(condition,label);checks.push(label);console.log('PASS '+label);}
async function ready(page){await page.locator('main#main').waitFor();await page.waitForFunction(()=>!window.ConquerLocale?.ready||window.ConquerLocale.t);if(await page.evaluate(()=>Boolean(window.ConquerLocale?.ready)))await page.evaluate(()=>window.ConquerLocale.ready);}
async function inspect(page,label){
 await ready(page);
 const result=await page.evaluate(()=>({overflow:document.documentElement.scrollWidth>innerWidth+1,htmlLang:document.documentElement.lang,
  text:document.querySelector('#main').innerText,
  untranslated:[...document.querySelectorAll('[data-i18n]')].filter(e=>!e.closest('[data-user-content]')&&e.textContent.trim()===e.dataset.i18n).map(e=>e.dataset.i18n),
  offscreen:[...document.querySelectorAll('.ops-filters input:not([type=hidden]),.ops-filters select,.ops-filters button,.ops-tabs a')].filter(e=>{const r=e.getBoundingClientRect();return r.width&&r.height&&(r.left<-1||r.right>innerWidth+1);}).map(e=>e.outerHTML.slice(0,160))}));
 ck(!result.overflow,label+' has no page overflow');
 ck(!result.offscreen.length,label+' filter and tab controls stay within viewport');
 ck(!result.untranslated.length&&!/admin\.ops\.[a-z_]+|admin\.cases\.[a-z_]+/.test(result.text),label+' has no untranslated operational keys');
 ck(!/PHP Warning|Fatal error|Undefined variable|Die Ansicht konnte nicht geladen|view could not be loaded/i.test(result.text),label+' rendered without PHP fallback');
 return result;
}
(async()=>{const browser=await chromium.launch({headless:true});
try{
 for(const [width,height,language] of [[1440,1000,'en'],[1024,768,'de'],[390,844,'de'],[320,700,'fr'],[844,390,'fr']]){
  const context=await browser.newContext({viewport:{width,height},hasTouch:width<=844,locale:language==='en'?'en-US':language==='de'?'de-DE':'fr-FR'});
  await context.addCookies([{name:'conquer_locale',value:language,url:base}]);
  await context.addInitScript(lang=>localStorage.setItem('conquer.locale',lang),language);
  const page=await context.newPage();page.setDefaultTimeout(15000);page.on('pageerror',error=>errors.push(error.message));
  page.on('response',response=>{if(response.status()>=400)failedResponses.push({status:response.status(),url:response.url()});});
  await page.goto(base+'/fixture-login');await ready(page);
  ck(await page.locator('#admin-nav [data-admin-area]').count()===8,`Eight admin areas at ${width}px ${language}`);
  ck((await page.locator('html').getAttribute('lang'))===language,`Requested ${language} is the active locale`);
  ck((await page.locator('.brand').innerText()).includes('Union of Kingdoms'),'Public game name remains unchanged in every locale');
  if(await page.locator('.mobile-menu').isVisible()){
   await page.locator('.mobile-menu').click();ck(await page.locator('.mobile-menu').getAttribute('aria-expanded')==='true','Mobile workspace menu opens');
   await page.locator('.mobile-menu').click();
  }
  await page.goto(base+'/admin?world_id=0&days=1');
  await inspect(page,`Overview ${width}x${height} ${language}`);
  ck(await page.locator('.ops-metric').nth(0).locator('strong').innerText()==='2','All-world online count includes both fixture worlds');
  ck(await page.locator('.ops-world-table tbody tr').count()===2,'All-world overview shows both worlds');
  ck((await page.locator('.ops-event-table').innerText()).includes('building.upgrade'),'Successful actions show their action identifier instead of an opaque OK label');
  if(width<=390){
   for(const selector of ['.ops-world-table','.ops-event-table']){
    const region=page.locator(selector).locator('..');
    ck(await region.getAttribute('tabindex')==='0'&&await region.getAttribute('role')==='region','Wide mobile tables are labeled keyboard-focusable scroll regions');
    ck(await region.evaluate(e=>e.scrollWidth>e.clientWidth+20),'Mobile table retains readable column widths');
    await region.focus();await page.keyboard.press('ArrowRight');await page.waitForTimeout(180);
    ck(await region.evaluate(e=>e.scrollLeft>0),'Mobile table can be scrolled with the keyboard');
    await region.evaluate(e=>e.scrollLeft=0);
   }
   await page.evaluate(()=>scrollTo(0,0));
  }
  await page.screenshot({path:path.join(output,`overview-${language}-${width}.png`),fullPage:true});
  if(width===1440){
   for(const [index,pathname,mode] of [[0,'/admin/activity','online'],[1,'/admin/activity','players'],[2,'/admin/cases',null],[3,'/admin/technical',null]]){
    await page.locator('.ops-metric').nth(index).click();await ready(page);
    ck(new URL(page.url()).pathname.endsWith(pathname)&&(!mode||new URL(page.url()).searchParams.get('mode')===mode),'Overview metric '+index+' opens its investigation view');
    await page.goto(base+'/admin?world_id=0&days=1');await ready(page);
   }
  }
  for(const tab of ['economy','stability','activity']){
   await page.locator(`.ops-analysis .ops-tabs a[href*="tab=${tab}"]`).click();await ready(page);
   ck(new URL(page.url()).searchParams.get('tab')===tab,`Dashboard ${tab} is reachable in one click`);
   await inspect(page,`${tab} ${width}x${height} ${language}`);
  }
  const filter=page.locator('.ops-filters').first();await filter.locator('[name=world_id]').selectOption('3');await filter.locator('[name=days]').selectOption('7');
  await filter.locator('button[type=submit]').click();await ready(page);
  ck(new URL(page.url()).searchParams.get('world_id')==='3'&&new URL(page.url()).searchParams.get('days')==='7','World and period filters submit together');
  ck(await page.locator('.ops-metric').nth(0).locator('strong').innerText()==='1','Selected-world online count excludes a session active elsewhere');
  ck(await page.locator('.ops-world-table tbody tr').count()===1&&(await page.locator('.ops-world-table').innerText()).includes('Southern Fixture Realm'),'Selected-world table is scoped');
  await page.locator('.ops-metric').nth(0).click();await ready(page);
  ck(new URL(page.url()).searchParams.get('mode')==='online'&&new URL(page.url()).searchParams.get('world_id')==='3','Online metric drills into selected-world players');
  ck((await page.locator('#main table tbody').innerText()).includes('MireScout')&&!(await page.locator('#main table tbody').innerText()).includes('ArdentKeeper'),'Online drilldown matches metric player');
  await inspect(page,`Online players ${width} ${language}`);
  await page.goto(base+'/admin/activity?world_id=0&days=7');await inspect(page,`Activity ${width} ${language}`);
  const eventForm=page.locator('form').filter({has:page.locator('input[name=q]:not([type=hidden])')});
  await eventForm.locator('[name=player_id]').fill('2');await eventForm.locator('[name=category]').selectOption('action');await eventForm.locator('[name=outcome]').selectOption('success');await eventForm.locator('[name=q]').fill('SCOUT');await eventForm.locator('button[type=submit]').click();await ready(page);
  ck(await page.locator('.ops-event-table tbody tr').count()===1&&(await page.locator('.ops-event-table').innerText()).includes('march.scout'),'Player, category, outcome and safe action search filters combine');
  const rangeFilter=page.locator('.ops-filters').first();await rangeFilter.locator('[name=days]').selectOption('30');await rangeFilter.locator('button[type=submit]').click();await ready(page);
  ck(['player_id','category','outcome','q'].every(key=>new URL(page.url()).searchParams.has(key))&&await page.locator('.ops-event-table tbody tr').count()===1,'Changing the period preserves active player and event filters');
  await page.locator('.ops-event-table a').filter({hasText:'march.scout'}).click();await inspect(page,`Action detail ${width} ${language}`);
  ck((await page.locator('.ops-event-detail').innerText()).includes('MireScout')&&(await page.locator('.ops-context').innerText()).includes('march.scout'),'Action detail shows scoped player and safe action metadata');
  await page.locator('.card>.split a.button').click();await ready(page);
  ck(['player_id','category','outcome','q'].every(key=>new URL(page.url()).searchParams.has(key))&&await page.locator('.ops-event-table tbody tr').count()===1,'Back from action detail preserves the investigation filters');
  await page.goto(base+'/admin/technical?world_id=0&days=7');await inspect(page,`Technical ${width} ${language}`);
  const group=page.locator('.ops-group-list a').filter({hasText:'STATE_FAILURE'});ck(await group.count()===1,'Repeated server errors are grouped once');
  await group.click();await ready(page);ck(await page.locator('.ops-event-table tbody tr').count()===2,'Error group opens both recorded occurrences');
  await page.locator('.ops-event-table a').filter({hasText:'STATE_FAILURE'}).first().click();await inspect(page,`Incident detail ${width} ${language}`);
  ck((await page.locator('.ops-event-detail').innerText()).includes('fixture-release-2026-10-10'),'Incident detail includes recorded release');
  await page.screenshot({path:path.join(output,`incident-${language}-${width}.png`),fullPage:true});
  await page.goto(base+'/admin/analytics?world_id=0&days=7');await inspect(page,`Analytics ${width} ${language}`);
  ck(await page.locator('.alpha-playtest').count()===0,'Cross-world analytics does not invent a world-specific alpha cohort');
  await page.goto(base+'/admin/analytics?world_id=3&days=7');await ready(page);
  await page.locator('details.card>summary').click();await inspect(page,`World analytics ${width} ${language}`);
  ck(await page.locator('.alpha-playtest').isVisible(),'Selected-world alpha analysis expands');
  await context.close();
 }
 ck(errors.length===0,'No JavaScript errors across all operational views');
 ck(failedResponses.length===0,'No failed asset or navigation responses');
 fs.writeFileSync(path.join(output,'report.json'),JSON.stringify({checks,errors,failedResponses},null,2));
 console.log('PASS '+checks.length+' admin operations browser checks');
}finally{await browser.close();}})().catch(error=>{console.error(error);fs.writeFileSync(path.join(output,'failure.json'),JSON.stringify({error:error.message,checks,errors,failedResponses},null,2));process.exitCode=1;});
