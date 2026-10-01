'use strict';
require('./fixtures/browser_locale.cjs')('de'); // This suite asserts the explicit German UI.
// Disposable PHP app: tools/preview-feature-fixture.php --port=19317 --hospital
const assert=require('assert/strict'),fs=require('fs'),path=require('path');
const {chromium}=require(process.env.PLAYWRIGHT_MODULE||'playwright');
const base=process.env.HOSPITAL_FIXTURE_URL||'http://127.0.0.1:19317';assert(/^http:\/\/127\.0\.0\.1:\d+$/.test(base));
const output=path.resolve(__dirname,'../artifacts/hospital');fs.mkdirSync(output,{recursive:true});
(async()=>{
 const browser=await chromium.launch({headless:true,channel:'chrome'});let page;
 try{
  page=await browser.newPage({viewport:{width:1280,height:800},hasTouch:true});const errors=[];let speedupPosts=0;
  page.on('pageerror',e=>errors.push(e.stack));page.on('request',r=>{if(r.method()==='POST'&&r.url().endsWith('/api/hospital/speedup'))speedupPosts++;});page.setDefaultTimeout(20000);
  let mode='normal';
  await page.route('**/api/kingdom/state*',async route=>{
   const response=await route.fetch(),json=await response.json();
   if(mode==='many'&&json.data?.hospital){const h=json.data.hospital;h.wounded=Array.from({length:15},(_,i)=>({...h.wounded[i%3],troop_code:i<3?h.wounded[i].troop_code:80000000+i,name:'Verwundete Veteranen der königlichen Stadtwache'}));h.used=h.waiting=h.wounded.reduce((n,w)=>n+w.count,0);h.capacity=10000;}
   await route.fulfill({response,json});
  });
  await page.route('**/api/game/state*',async route=>{const response=await route.fetch(),json=await response.json();if(mode==='poor'&&json.data?.city)json.data.city.food=0;await route.fulfill({response,json});});
  const api=async(path)=>page.evaluate(async p=>(await(await fetch('/api/'+p)).json()).data,path);
  const clickHospital=async(button,path)=>{const response=page.waitForResponse(r=>r.url().endsWith('/api/hospital/'+path)&&r.request().method()==='POST');await button.click();const r=await response;assert.equal(r.status(),200,await r.text());return (await r.json()).data;};
  await page.goto(base);await page.goto(new URL('?zugang=login', page.url()).href);await page.locator("[name=identifier], [name=username]").fill('PreviewPlayer');await page.locator('[name="password"]').fill('PreviewFixture!2026');
  await Promise.all([page.waitForURL('**/city'),page.locator("form[action$=\"/auth/local\"] button[type=\"submit\"]").click()]);await page.locator('.painted-village').waitFor();
  await page.locator('#navigation [data-id="world"]').click();await page.waitForFunction(()=>document.body.classList.contains('world-mode'));assert.equal(await page.locator('#hud-healing').isVisible(),true);
  await page.locator('#hud-menu').click();await page.locator('#game-dialog [data-id="army"]').click();
  await page.locator('[data-action="panel-tab"][data-group="army"][data-id="hospital"]').click();
  await page.locator('.hospital-unit').first().waitFor();
  const panel=page.locator('#panel-dialog'),heal=page.locator('[data-action="hospital-heal"]'),select=page.locator('[data-action="hospital-select"]');
  const before=await api('game/state'),kingdom=await api('kingdom/state');assert.equal(kingdom.hospital.waiting,950);assert.equal(kingdom.hospital.active,null);
  // Current game rules: current T1 catalog is 0.5 seconds each; validate the server quote before using it.
  const auditHospital=kingdom.hospital.wounded;
  const auditCatalog=JSON.parse(fs.readFileSync(path.resolve(__dirname,'../data/troops.json'),'utf8')).troops;
  for(const code of [50100101,50200101,50300101]){
   assert.equal(auditCatalog.find(t=>Number(t.code)===code).heal_time,0.5,'Current T1 base healing time');
   assert.equal(auditHospital.find(w=>Number(w.troop_code)===code).seconds_per_troop,0.5,'Unbuffed fixture server rate matches current catalog');
  }
  const auditQuote=counts=>{const cost={food:0,lumber:0,stone:0,gold:0};let seconds=0;for(const [code,count]of Object.entries(counts)){const row=auditHospital.find(w=>Number(w.troop_code)===Number(code));assert(row);seconds+=count*row.seconds_per_troop;for(const key of Object.keys(cost))cost[key]+=count*Number(row.resources[key]||0);}return {seconds:Math.ceil(seconds),cost};};
  const auditInitial=auditQuote({50100101:620,50200101:245,50300101:85}),audit600=auditQuote({50100101:600});
  assert.equal(auditInitial.seconds,475);assert.equal(audit600.seconds,300);
  assert.deepEqual(audit600.cost,{food:1800,lumber:0,stone:3600,gold:1200},'Current T1 resource cost, rounded per troop');
  const auditClock=seconds=>[Math.floor(seconds/3600),Math.floor(seconds%3600/60),seconds%60].map(x=>String(x).padStart(2,'0')).join(':');
  fs.writeFileSync(path.join(output,'current-healing-quotes.json'),JSON.stringify({initial:auditInitial,selection600:audit600},null,2));
  assert.equal(await page.locator('#page-title').innerText(),'Hospital');assert.equal(await page.locator('.army-quick-actions').count(),0);assert.equal(await page.locator('[data-hospital-duration]').innerText(),auditClock(auditInitial.seconds));
  const checkLayout=async(name)=>{
   const layout=await panel.evaluate(p=>{const inside=e=>{const r=e.getBoundingClientRect();return r.width>0&&r.height>0&&r.left>=0&&r.top>=0&&r.right<=innerWidth+1&&r.bottom<=innerHeight+1;};const list=p.querySelector('.hospital-list'),foot=p.querySelector('.hospital-footer'),a=list.getBoundingClientRect(),b=foot.getBoundingClientRect();return {inside:inside(p)&&inside(foot)&&inside([...p.querySelectorAll('.panel-close,.mobile-page-back')].find(e=>e.checkVisibility())),overflow:p.scrollWidth>p.clientWidth+2,overlap:a.left<b.right-1&&a.right>b.left+1&&a.top<b.bottom-1&&a.bottom>b.top+1,controls:[...p.querySelectorAll('.hospital-actions button')].every(e=>inside(e)&&e.getBoundingClientRect().height>=44)};});
   const separated=await panel.evaluate(p=>{const a=p.querySelector('.hospital-summary').getBoundingClientRect(),b=p.querySelector('.hospital-footer').getBoundingClientRect();return a.bottom<=b.top+1;});
   await page.screenshot({path:path.join(output,name+'.png')});assert(separated,'Summary overlaps costs: '+name);assert.deepEqual(layout,{inside:true,overflow:false,overlap:false,controls:true},name+' '+JSON.stringify(layout));
  };
  for(const [width,height] of [[1280,800],[390,844],[320,568],[568,320],[844,390]]){await page.setViewportSize({width,height});await page.waitForFunction(()=>[...document.querySelectorAll('.hospital-shell img')].every(i=>i.complete&&i.naturalWidth>0));await page.waitForFunction(()=>{const r=document.querySelector('.hospital-heal').getBoundingClientRect();return r.right<=innerWidth+1&&r.bottom<=innerHeight+1;},{},{timeout:3000}).catch(async error=>{console.error(await panel.evaluate(p=>({viewport:[innerWidth,innerHeight],boxes:[...p.querySelectorAll('.hospital-shell,.hospital-workspace,.hospital-footer,.hospital-heal')].map(e=>({class:e.className,rect:e.getBoundingClientRect().toJSON()}))})));await page.screenshot({path:path.join(output,'failure.png')});throw error;});await checkLayout(`${width}x${height}`);}
  await page.setViewportSize({width:390,height:844});
  mode='poor';await page.waitForFunction(()=>document.querySelector('[data-hospital-feedback]')?.textContent.includes('Nicht genügend'));assert(await heal.isDisabled());assert.equal(await page.locator('[data-action="hospital-instant"]').count(),0);await page.screenshot({path:path.join(output,'insufficient-resources.png')});
  mode='many';await page.waitForFunction(()=>document.querySelectorAll('.hospital-unit').length===15);await page.locator('#hospital-number-50100101').fill('20');await page.locator('.hospital-list').evaluate(e=>e.scrollLeft=180);await page.waitForTimeout(5500);assert.equal(await page.locator('#hospital-number-50100101').inputValue(),'20');assert(await page.locator('.hospital-list').evaluate(e=>e.scrollLeft>=175));await page.screenshot({path:path.join(output,'many-troops.png')});
  mode='normal';await page.waitForFunction(()=>document.querySelectorAll('.hospital-unit').length===3);await select.click();await select.tap();assert(await heal.isDisabled());
  const number=page.locator('#hospital-number-50100101'),range=page.locator('#hospital-range-50100101');
  await number.fill('300');assert.equal(await range.inputValue(),'300');await range.focus();await page.keyboard.press('ArrowRight');assert.equal(await number.inputValue(),'301');
  await number.fill('99999');assert.equal(await number.inputValue(),'620');await number.fill('-1');assert.equal(await number.inputValue(),'0');await number.fill('600');assert.equal(await page.locator('[data-hospital-duration]').innerText(),auditClock(audit600.seconds));
  const started=await clickHospital(heal,'heal');assert.equal(await page.locator('[data-action="confirm-operation"]').count(),0);assert.equal(started.troops_healed,0);assert.equal(started.duration_seconds,audit600.seconds);assert.deepEqual(started.resources_spent,audit600.cost);
  await page.locator('.hospital-running').waitFor();assert.match(await page.locator('.hospital-active-note').innerText(),/600 Truppen in Behandlung/);assert.equal(await page.locator('[data-hospital-count]').count(),0);const during=await api('game/state');assert.equal(during.troops['50100101'],before.troops['50100101']);
  for(const [width,height]of [[1280,800],[390,844],[320,568],[568,320],[844,390]]){await page.setViewportSize({width,height});await checkLayout(`${width}x${height}-healing`);}
  await page.setViewportSize({width:390,height:844});const first=await page.locator('[data-hospital-time]').innerText();await page.waitForTimeout(1500);assert.notEqual(await page.locator('[data-hospital-time]').innerText(),first);
  for(const category of ['healing','generic']){
   const item=kingdom.inventory.find(i=>i.category==='speedup'&&i.subcategory===category&&i.duration_seconds===60);assert(item);
   await page.locator('[data-action="hospital-speedups"]').click();const option=page.locator(`.queue-speedup-option[data-id="${item.item_code}"]`);await option.click();
   const input=page.locator('#queue-speedup-quantity'),beforePosts=speedupPosts;await input.fill('1');assert.equal(speedupPosts,beforePosts,'changing speedup quantity must not call the API');
   const options=await page.locator('[data-action="queue-speedup-select"] small').allTextContents();assert(options.every(t=>/^(Heilung|Allgemein|\d.*vorhanden)/.test(t)));
   await page.screenshot({path:path.join(output,category+'-speedup.png')});const response=page.waitForResponse(r=>r.url().endsWith('/api/hospital/speedup')&&r.request().method()==='POST');await page.locator('[data-action="queue-speedup-use"]').click();
   const rr=await response;assert.equal(rr.status(),200,await rr.text());const sped=(await rr.json()).data;assert.equal(sped.seconds,60);assert.equal(sped.item_code,item.item_code);assert.equal(speedupPosts,beforePosts+1);await page.locator('.queue-speedup-picker').waitFor();await page.locator("#game-dialog>.dialog-close:visible, #game-dialog .mobile-page-back:visible").first().click();await page.locator('.hospital-running').waitFor();
  }
  const food=(await api('game/state')).city.food,gems=(await api('kingdom/state')).profile.gems;
  assert.equal(await page.locator('[data-action="hospital-finish"]').count(),0);
  const finishWithSpeedup=async()=>{const item=kingdom.inventory.find(i=>i.category==='speedup'&&i.subcategory==='healing'&&i.duration_seconds===3600);assert(item);await page.locator('[data-action="hospital-speedups"]').click();await page.locator(`.queue-speedup-option[data-id="${item.item_code}"]`).click();await page.locator('#queue-speedup-quantity').fill('1');const response=page.waitForResponse(r=>r.url().endsWith('/api/hospital/speedup')&&r.request().method()==='POST');await page.locator('[data-action="queue-speedup-use"]').click();const rr=await response;assert.equal(rr.status(),200,await rr.text());await page.locator('#game-dialog').waitFor({state:'hidden'});return (await rr.json()).data;};
  await finishWithSpeedup();await page.locator('[data-hospital-count]').first().waitFor();
  let current=await api('game/state');assert.equal(Number(current.troops['50100101'])-Number(before.troops['50100101']),600);assert(Number(current.city.food)>=Number(food)-1);
  const second=await clickHospital(heal,'heal');assert.equal(second.troops_started,350);assert.equal(second.gems_spent,0);await page.locator('.hospital-running').waitFor();await finishWithSpeedup();await page.locator('.hospital-showcase-empty').waitFor();const healthy=await api('kingdom/state');assert.equal(healthy.hospital.waiting,0);assert.equal(healthy.hospital.used,0);assert.equal(healthy.hospital.active,null);assert.equal(healthy.profile.gems,gems);await page.screenshot({path:path.join(output,'healthy.png')});
  for(const [width,height]of [[1280,800],[390,844],[320,568],[568,320],[844,390]]){await page.setViewportSize({width,height});const training=page.locator('.hospital-showcase-empty [data-action=army-troops]');await training.scrollIntoViewIfNeeded();assert(await training.evaluate(el=>{const r=el.getBoundingClientRect();return r.height>=44&&r.top>=0&&r.bottom<=innerHeight&&el.contains(document.elementFromPoint(r.x+r.width/2,r.y+r.height/2));}),'Healthy hospital action reachable '+width+'x'+height);await page.screenshot({path:path.join(output,`healthy-${width}x${height}.png`)});}
  await page.setViewportSize({width:390,height:844});
  // Mutations require both an authenticated session and CSRF.
  const csrf=await page.evaluate(async()=>{const r=await fetch('/api/hospital/heal',{method:'POST',headers:{'Content-Type':'application/json'},body:'{}'});return r.status;});assert.equal(csrf,403);
  const anonymous=await browser.newContext();assert.equal((await anonymous.request.post(base+'/api/hospital/heal',{data:{}})).status(),401);await anonymous.close();
  await page.locator('[data-action="army-troops"]').click();await page.locator('[data-action="panel-tab"][data-group="army"][data-id="hospital"]').click();await page.keyboard.press('Escape');await page.waitForFunction(()=>!document.querySelector('#panel-dialog').open);await page.locator('#navigation [data-id="city"]').click();await page.waitForFunction(()=>document.body.classList.contains('city-mode'));await page.locator('.painted-village').waitFor({state:'visible'});
  await page.locator('.painted-village-building[data-id=hospital]').click();await page.locator('.painted-building-menu [data-action=training-building]').click();await page.locator('.hospital-showcase-empty').waitFor();await page.locator("#panel-dialog .panel-close:visible, #panel-dialog .mobile-page-back:visible").first().click();
  await page.unrouteAll({behavior:'ignoreErrors'});
  assert.deepEqual(errors,[]);console.log('PASS resource-paid start, live healing, both speedups without crystal spending, authentication/CSRF, preserved selection/scroll, city action and five portrait/landscape layouts. Screenshots: '+output);
 }catch(error){if(page){console.error(JSON.stringify(await page.evaluate(()=>({url:location.href,history:history.state,panel:document.querySelector('#panel-dialog')?.open,dialog:document.querySelector('#game-dialog')?.open,empty:[...document.querySelectorAll('.hospital-showcase-empty')].map(e=>({text:e.textContent,rect:e.getBoundingClientRect().toJSON(),display:getComputedStyle(e).display,visibility:getComputedStyle(e).visibility})),shell:document.querySelector('.hospital-shell')?.className})),null,2));await page.screenshot({path:path.join(output,'failure.png')});}throw error;
 }finally{await page?.unrouteAll({behavior:'ignoreErrors'});await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1;});
