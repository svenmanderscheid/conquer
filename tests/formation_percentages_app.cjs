'use strict';
// Real main app on the external disposable preview. Never writes a real player's army.
const fs=require('node:fs'),path=require('node:path'),assert=require('node:assert/strict');
const {chromium}=require(process.env.PLAYWRIGHT_MODULE||'playwright');
const base=process.env.CONQUER_PREVIEW_BASE;
assert.match(base||'',/^http:\/\/127\.0\.0\.1:\d+$/,'A disposable preview is required');
const out=path.resolve(__dirname,'../output/playwright/formation-percentages');fs.mkdirSync(out,{recursive:true});
const result={layouts:[],errors:[],writes:[],checks:[]};
const apiGet=(page,endpoint)=>page.evaluate(async endpoint=>{const response=await fetch('/api/'+endpoint,{cache:'no-store'}),json=await response.json();if(!response.ok)throw new Error(JSON.stringify(json));return json.data;},endpoint);
async function setLocale(page,locale){await Promise.all([page.waitForEvent('domcontentloaded'),page.evaluate(locale=>ConquerLocale.setLocale(locale),locale)]);await page.evaluate(()=>ConquerLocale.ready);}
const form=page=>page.locator('form[data-form="defense-formation"]');
const counts=page=>form(page).locator('[name^="troop_"]').evaluateAll(inputs=>Object.fromEntries(inputs.filter(input=>Number(input.value)>0).map(input=>[input.name.slice(6),Number(input.value)])));
async function reachable(page,locator,label){
 await locator.evaluate(element=>{const region=element.closest('#content'),r=element.getBoundingClientRect(),box=region.getBoundingClientRect(),scale=box.height/region.offsetHeight;region.scrollTop+=(r.top-box.top-(box.height-r.height)/2)/scale;});const geometry=await locator.evaluate(element=>{const r=element.getBoundingClientRect(),hit=document.elementFromPoint(r.x+r.width/2,r.y+r.height/2);return {height:r.height,width:r.width,fits:r.x>=0&&r.y>=0&&r.right<=innerWidth+1&&r.bottom<=innerHeight+1,hit:element.contains(hit),top:r.top,bottom:r.bottom,hitTag:hit?.outerHTML.slice(0,100)};});
 assert(geometry.fits&&geometry.hit&&geometry.height>=43,label+' '+JSON.stringify(geometry));
}
async function alignTop(page,locator){await locator.evaluate(element=>{const region=element.closest('#content'),r=element.getBoundingClientRect(),box=region.getBoundingClientRect(),scale=box.height/region.offsetHeight;region.scrollTop+=(r.top-box.top-8)/scale;});}
async function open(page){await page.goto(base+'/city#defense');await page.evaluate(()=>ConquerLocale.ready);await page.locator('.defense-panel:not([aria-busy])').waitFor();await page.locator('[data-action="defense-tab"][data-id="formations"]').click();try{await form(page).waitFor();}catch(error){await page.screenshot({path:path.join(out,'open-failure.png')});console.log(await page.locator('body').innerText());throw error;}await page.evaluate(()=>document.fonts.ready);}
async function save(page,name,total){await form(page).locator('[name="slot"]').selectOption('1');await form(page).locator('[name="name"]').fill(name);await form(page).locator('[name="formation_total"]').fill(String(total));const action=form(page).locator('[type="submit"]');await reachable(page,action,'Save');await Promise.all([page.waitForResponse(r=>r.url().endsWith('/api/defense/action')&&r.request().method()==='POST'),action.click()]);await page.waitForFunction(()=>document.querySelector('.formation-saved-card[data-slot="1"] [data-action="defense-preset"]'));await page.waitForFunction(()=>!document.querySelector('form[data-form="defense-formation"] [type="submit"]').disabled);}
(async()=>{
 const browser=await chromium.launch({headless:true,channel:process.env.PLAYWRIGHT_CHANNEL||'chrome'});
 try{
 const context=await browser.newContext({viewport:{width:1280,height:800}}),page=await context.newPage();
 page.on('pageerror',e=>result.errors.push(e.message));page.on('request',r=>{if(r.method()==='POST'&&r.url().endsWith('/api/defense/action'))result.writes.push(r.postDataJSON());});
 await page.goto(base+'/?zugang=login');await page.locator('[name="identifier"]').fill('PreviewPlayer');await page.locator('[name="password"]').fill('PreviewFixture!2026');
 await Promise.all([page.waitForURL('**/city'),page.locator('form[action$="/auth/local"] [type="submit"]').click()]);
 await page.goto(base+'/city#city');await page.locator('.painted-village').waitFor();await page.locator('.painted-village-building[data-id="castle"]').click();await page.locator('.painted-building-actions [data-action="building"]').click();await page.locator('#game-dialog[open]').waitFor();await page.screenshot({path:path.join(out,'city-building-desktop.png')});await page.keyboard.press('Escape');
 await open(page);const before=await apiGet(page,'defense/state');assert.equal(await page.locator('.formation-saved-card').count(),6);
 for(const [width,height] of [[1280,800],[390,844],[320,568],[844,390],[568,320]]){
  await page.setViewportSize({width,height});await form(page).locator('[name="formation_total"]').fill('10000');await form(page).locator('[name="percent_1"]').fill('70');await form(page).locator('[name="percent_2"]').fill('30');await form(page).locator('[name="percent_3"]').fill('0');
 const army=await counts(page);
  const expected=await page.evaluate(defs=>ConquerFormationComposition.allocate(defs,Object.fromEntries(defs.map(t=>[t.code,t.available])),10000,{percentages:{1:70,2:30,3:0},total:10000}),before.troops);assert.deepEqual(army,expected);
  assert.equal(Object.values(army).reduce((a,n)=>a+n,0),10000);assert.equal(before.troops.filter(t=>Number(t.type)===3).reduce((sum,t)=>sum+(army[t.code]||0),0),0);
  for(const slot of [1,2,3,4,5,6])await reachable(page,page.locator('.formation-saved-card[data-slot="'+slot+'"] button'),width+' slot '+slot);
  await alignTop(page,page.locator('.formation-library'));await page.screenshot({path:path.join(out,width+'x'+height+'-overview.png')});
  for(const field of ['percent_1','percent_2','percent_3','formation_total'])await reachable(page,form(page).locator('[name="'+field+'"]'),width+' '+field);
  for(const type of [1,2,3])await reachable(page,form(page).locator('[data-percent-range="'+type+'"]'),width+' slider '+type);
  await reachable(page,form(page).locator('[type="submit"]'),width+' save');
  const overflow=await page.locator('#content').evaluate(el=>el.scrollWidth-el.clientWidth);if(overflow>1){await page.screenshot({path:path.join(out,width+'-overflow.png')});console.log(await page.locator('#panel-dialog').evaluate(el=>({panel:el.getBoundingClientRect().toJSON(),scroll:el.scrollWidth,client:el.clientWidth,nodes:[...el.querySelectorAll('*')].filter(node=>node.getClientRects().length&&(node.scrollWidth>node.clientWidth+2||node.getBoundingClientRect().right>innerWidth-6)).map(node=>({class:node.className,tag:node.tagName,right:node.getBoundingClientRect().right,width:node.getBoundingClientRect().width,scroll:node.scrollWidth,client:node.clientWidth})).slice(0,25)})));}assert(overflow<=1,width+' no horizontal overflow: '+overflow);
  const missing=await page.locator('.formation-percent-card img').evaluateAll(images=>images.filter(i=>!i.complete||i.naturalWidth===0).map(i=>i.src));assert.deepEqual(missing,[],width+' artwork loaded');
  await form(page).locator('[name="percent_2"]').fill('40');assert(await form(page).locator('[type="submit"]').isDisabled());assert(await page.locator('.formation-percent-status.is-invalid').isVisible());await form(page).locator('[name="percent_2"]').fill('30');
  await form(page).locator('[name="percent_1"]').fill('70.5');assert(await form(page).locator('[type="submit"]').isDisabled());await form(page).locator('[name="percent_1"]').fill('70');
  await form(page).locator('[data-percent-range="1"]').focus();await page.keyboard.press('ArrowRight');assert.equal(await form(page).locator('[name="percent_1"]').inputValue(),'71');await page.keyboard.press('ArrowLeft');
  await alignTop(page,width>700?form(page):page.locator('.formation-distribution'));await page.screenshot({path:path.join(out,width+'x'+height+'-editor.png')});
  await form(page).locator('[data-action="defense-formation-mode"][data-id="amounts"]').click();assert(await page.locator('.formation-amount-editor').isVisible());assert(await page.locator('.formation-percent-editor').isHidden());
  const manual=await counts(page);assert.deepEqual(manual,army);await reachable(page,form(page).locator('[name="troop_50100101"]'),width+' manual amount');await form(page).locator('[data-action="defense-formation-mode"][data-id="percent"]').click();
  result.layouts.push({width,height,overflow,images:3,percentageInputs:3,sliders:3});
 }
 await page.setViewportSize({width:390,height:844});await save(page,'70 / 30 / 0 <guard>',10000);let state=await apiGet(page,'defense/state'),saved=state.formations.find(f=>f.slot===1);
 assert.deepEqual(saved.composition,{percentages:{1:70,2:30,3:0},total:10000});assert.deepEqual(state.troops.map(t=>[t.code,t.available]),before.troops.map(t=>[t.code,t.available]));
 assert.equal(await page.locator('.formation-saved-card[data-slot="1"] strong').textContent(),'70 / 30 / 0 <guard>');
 await page.locator('.formation-saved-card[data-slot="1"] [data-action="defense-preset"]').click();assert.equal(await form(page).getAttribute('data-mode'),'percent');assert.equal(await form(page).locator('[name="formation_total"]').inputValue(),'10000');
 const heading=await page.locator('#panel-dialog .page-heading').boundingBox();assert(heading.y>=-1&&heading.y+heading.height<=845,'slot selection keeps the window header visible: '+JSON.stringify({heading,frame:await page.locator('#panel-dialog').evaluate(el=>({scroll:el.scrollTop,rect:el.getBoundingClientRect().toJSON()}))}));
 await reachable(page,form(page).locator('[data-action="defense-delete"]'),'selected formation delete');
 assert.equal(await page.locator('.formation-saved-card[data-slot="1"] button[aria-pressed="true"]').count(),1);
 await alignTop(page,page.locator('.formation-library'));await page.screenshot({path:path.join(out,'390x844-selected.png')});
 await page.locator('[data-action="defense-tab"][data-id="support"]').click();await page.locator('details summary').first().click();await page.locator('[data-action="defense-preset"][data-id="1"]').click();
 const support=await page.locator('[data-form="defense-dispatch"] [name^="troop_"]').evaluateAll(inputs=>Object.fromEntries(inputs.filter(i=>Number(i.value)>0).map(i=>[i.name.slice(6),Number(i.value)])));assert.deepEqual(support,saved.troops);result.checks.push('percent template saves, reloads and fills reinforcement without reserving stock');
 await open(page);await page.locator('.formation-saved-card[data-slot="1"] [data-action="defense-preset"]').click();await form(page).locator('[data-action="defense-formation-mode"][data-id="amounts"]').click();await form(page).locator('[name="name"]').fill('Fixed guard');
 await Promise.all([page.waitForResponse(r=>r.url().endsWith('/api/defense/action')&&r.request().method()==='POST'),form(page).locator('[type="submit"]').click()]);
 await page.waitForFunction(()=>document.querySelector('.formation-saved-card[data-slot="1"] strong')?.textContent==='Fixed guard');state=await apiGet(page,'defense/state');assert.equal(state.formations.find(f=>f.slot===1).composition,undefined);result.checks.push('fixed amounts clear percentage mode');
 for(const locale of ['de','fr']){
  await setLocale(page,locale);await open(page);assert.equal(await page.evaluate(()=>ConquerLocale.locale),locale);
  const catalog=JSON.parse(fs.readFileSync(path.resolve(__dirname,'../data/i18n/'+locale+'.json'),'utf8'));assert.equal(await page.locator('.formation-percent-card strong').first().textContent(),catalog['formation.type.1']);
 assert.equal(await page.locator('.formation-saved-card[data-slot="1"] strong').textContent(),'Fixed guard');await reachable(page,form(page).locator('[type="submit"]'),locale+' save');await page.screenshot({path:path.join(out,'390x844-'+locale+'.png')});
 await form(page).locator('[name="name"]').fill('Monster1');await form(page).locator('[name="formation_total"]').fill('10000');
 for(const [width,height] of [[390,844],[320,568]]){await page.setViewportSize({width,height});assert.equal(await page.locator('#content').evaluate(el=>el.scrollWidth-el.clientWidth),0,locale+' '+width+' no overflow');for(const mode of ['percent','amounts'])await reachable(page,form(page).locator('[data-action="defense-formation-mode"][data-id="'+mode+'"]'),locale+' '+width+' '+mode);await alignTop(page,page.locator('.formation-distribution'));await page.screenshot({path:path.join(out,width+'x'+height+'-'+locale+'-distribution.png')});}
 if(locale==='de'){await page.setViewportSize({width:1280,height:800});await alignTop(page,form(page));await page.screenshot({path:path.join(out,'1280x800-de-editor.png')});await alignTop(page,page.locator('.formation-library'));await page.screenshot({path:path.join(out,'1280x800-de-overview.png')});}
 await page.setViewportSize({width:390,height:844});
 }
 await setLocale(page,'en');
 await open(page);await page.locator('.formation-saved-card[data-slot="1"] [data-action="defense-preset"]').click();
 const remove=form(page).locator('[data-action="defense-delete"]');await reachable(page,remove,'delete in editor footer');
 await Promise.all([page.waitForResponse(r=>r.url().endsWith('/api/defense/action')&&r.request().method()==='POST'),remove.click()]);
 await page.locator('.formation-saved-card[data-slot="1"] [data-action="defense-new"]').waitFor();state=await apiGet(page,'defense/state');assert(!state.formations.some(f=>f.slot===1));
 await page.locator('.formation-saved-card[data-slot="1"] [data-action="defense-new"]').click();assert.equal(await form(page).locator('[name="slot"]').inputValue(),'1');assert.equal(await form(page).locator('[name="name"]').inputValue(),'');assert(await form(page).locator('[data-action="defense-delete"]').isHidden());result.checks.push('compact slots select the editor; delete and create remain reachable');
 for(const [width,height] of [[390,844],[844,390]]){await page.setViewportSize({width,height});await page.goto(base+'/city#city');await page.locator('.painted-village').waitFor();await page.locator('.painted-village-building[data-id="castle"]').click();await page.locator('.painted-building-actions [data-action="building"]').click();await page.locator('#game-dialog[open]').waitFor();await page.screenshot({path:path.join(out,'city-building-'+width+'x'+height+'.png')});await page.keyboard.press('Escape');}
 assert.deepEqual(result.errors,[]);fs.writeFileSync(path.join(out,'result.json'),JSON.stringify(result,null,2));console.log('PASS actual app: '+result.layouts.length+' responsive formation layouts, percent save/reload, support, fixed amounts and en/de/fr. '+out);
 }finally{await browser.close();}
})().catch(error=>{console.error(error);process.exitCode=1;});
