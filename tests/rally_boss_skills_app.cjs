'use strict';
// Real app; external disposable tools/preview-feature-fixture.php --appearance --boss-skills.
// Root owns fixture/server lifetime. This suite never starts a database or dispatches an army.
const fs=require('node:fs'),path=require('node:path'),assert=require('node:assert/strict'),crypto=require('node:crypto');
const {chromium}=require(process.env.PLAYWRIGHT_MODULE||'playwright');
const root=path.resolve(__dirname,'..');
const base=process.env.RALLY_BOSS_SKILLS_FIXTURE_URL||'http://127.0.0.1:18979';
assert.match(base,/^http:\/\/127\.0\.0\.1:\d+$/,'An external disposable loopback fixture is required');
const output=path.resolve(process.env.RALLY_BOSS_SKILLS_OUTPUT||path.join(root,'artifacts/rally-boss-skills'));
const selectedLocales=(process.env.RALLY_BOSS_SKILLS_LOCALES||'en,de,fr').split(',');
assert.ok(selectedLocales.length&&selectedLocales.every(locale=>['en','de','fr'].includes(locale)),'Only supported locales can be selected for a focused rerun');
fs.mkdirSync(output,{recursive:true});
const bosses=[
 {name:'grumwald',code:20202401,id:'grumwald_regeneration',region:[64,64],counter:2,parameter:'heal_percent',percent:12},
 {name:'frostgrimm',code:20202101,id:'frostgrimm_ice_armor',region:[192,64],counter:1,parameter:'required_power_percent',percent:12},
 {name:'sandmaul',code:20202201,id:'sandmaul_sandstorm',region:[64,192],counter:3,parameter:'army_power_reduction_percent',percent:10},
 {name:'glutramm',code:20202301,id:'glutramm_ember_backlash',region:[192,192],counter:2,parameter:'injury_increase_percent',percent:20},
 {name:'daemmerhorn',code:20200501,id:'daemmerhorn_runic_barrier',region:[75,75],counter:0,parameter:'required_power_percent',percent:15},
];
const catalogs=Object.fromEntries(['en','de','fr'].map(locale=>[locale,JSON.parse(fs.readFileSync(path.join(root,'data/i18n',locale+'.json'),'utf8'))]));
const result={started_at:new Date().toISOString(),base,selected_locales:selectedLocales,cases:[],reports:[],previews:[],writes:[],unexpected_writes:[],api_errors:[],page_errors:[],source_sha256:{}};
for(const file of ['tests/rally_boss_skills_app.cjs','assets/js/boss-mechanic.js','assets/js/march-panel.js','assets/js/battle-preview.js','assets/js/monster-report.js','assets/css/village-theme.css','views/monster_report.php','tests/fixtures/rally_boss_skills.php',...['en','de','fr'].map(l=>'data/i18n/'+l+'.json')])result.source_sha256[file]=crypto.createHash('sha256').update(fs.readFileSync(path.join(root,file))).digest('hex');
const normalizedTroops=value=>JSON.stringify(Object.entries(value||{}).filter(([,n])=>Number(n)>0).map(([code,n])=>[Number(code),Number(n)]).sort((a,b)=>a[0]-b[0]));
const ruleState=rule=>rule.id==='daemmerhorn_runic_barrier'&&rule.phase_active===false?'phase-inactive':rule.countered===true?'countered':rule.active===true?'active':rule.active===false?'inactive':'rule';
const prefix=boss=>'boss.'+boss.name+'.';
function assertDefinition(rule,boss){
 assert.ok(rule,'Server supplies a boss rule for '+boss.name);assert.equal(rule.id,boss.id);assert.equal(rule.version,1);
 assert.equal(rule[boss.parameter],boss.percent);assert.equal(rule.counter_power_percent,boss.counter?50:30);
 assert.equal(rule.counter_type,boss.counter?['','infantry','ranged','cavalry'][boss.counter]:'balanced');
 if(!boss.counter)assert.equal(rule.active_above_hp_percent,50);
}
function assertResolved(payload,boss,countered,{historical=false,phaseInactive=false}={}){
 const rule=payload.boss_mechanic;assertDefinition(rule,boss);assert.equal(rule.countered,countered,'Server counter: '+boss.name);
 assert.equal(typeof rule.counter_power_share_percent,'number');assert.ok(Number.isFinite(rule.counter_power_share_percent));
 assert.equal(rule.counter_power_share_percent>=rule.counter_power_percent,countered,'Unrounded base-power share agrees with counter result');
 if(!historical){assert.equal(payload.kind,'monster-rally');assert.equal(payload.luck_percent,0);assert.deepEqual(payload.luck_range,[-10,10]);assert.ok(Number.isInteger(payload.calculated_at));}
 if(boss.name==='grumwald'){
  assert.equal(typeof rule.hp_restored,'number');assert.ok(rule.hp_restored>=0);
  if(countered)assert.equal(rule.hp_restored,0);else if(payload.monster_hp_after>0)assert.ok(rule.hp_restored>0,'Nonlethal uncountered preview regenerates');
  return;
 }
 assert.equal(typeof rule.active,'boolean');
 if(!boss.counter){assert.equal(rule.phase_active,!phaseInactive);assert.deepEqual(Object.keys(rule.type_power_share_percent).sort(),['cavalry','infantry','ranged']);assert.ok(Math.abs(Object.values(rule.type_power_share_percent).reduce((sum,n)=>sum+n,0)-100)<.00001);}
 if(boss.parameter==='required_power_percent'){
  assert.equal(rule.required_power_after,countered||phaseInactive?rule.required_power_before:Math.ceil(rule.required_power_before*(100+boss.percent)/100));
  assert.equal(payload.required_power,rule.required_power_after);assert.equal(rule.active,!countered&&!phaseInactive);
 }else if(boss.name==='sandmaul'){
  assert.ok(Math.abs(rule.army_power_after-rule.army_power_before*(countered?1:.9))<.00001);assert.equal(payload.army_power,Math.round(rule.army_power_after));assert.equal(rule.active,!countered);
 }else{
  const expected=countered||payload.monster_hp_after===0?rule.injury_ratio_before:Math.min(.35,rule.injury_ratio_before*1.2);
  assert.ok(Math.abs(rule.injury_ratio_after-expected)<.0000001);assert.ok(rule.injury_ratio_after<=.35);assert.equal(rule.active,rule.injury_ratio_after>rule.injury_ratio_before);
 }
}
async function apiGet(page,endpoint){
 const response=await page.request.get(base+'/api/'+endpoint);assert.equal(response.status(),200,endpoint+' HTTP status');const body=await response.json();assert.equal(body.ok,true,endpoint+' response');assert.ok(body.data);return body.data;
}
async function login(page){
 await page.goto(base+'/?zugang=login');await page.locator('[name=identifier]').fill('PreviewPlayer');await page.locator('[name=password]').fill('PreviewFixture!2026');
 await Promise.all([page.waitForURL('**/city'),page.locator('form[action$="/auth/local"] button[type=submit]').click()]);await page.locator('.painted-village').waitFor();await page.evaluate(()=>ConquerLocale.ready);
}
async function cardCheck(page,selector,boss,rule,locale,{rally=true}={}){
 const expectedKey=prefix(boss)+(boss.name==='grumwald'?(rule.countered?'countered':'active'):ruleState(rule)==='phase-inactive'?'phase_inactive':ruleState(rule));
 await page.waitForFunction(({selector,key})=>Boolean(document.querySelector(selector)?.querySelector('[data-i18n="'+key+'"]')),{selector,key:expectedKey});
 // Polling can replace an equivalent preflight card between animation frames.
 // Resolve and scroll the current node atomically, then assert its content and bounds below.
 const card=page.locator(selector);await card.evaluate(el=>el.scrollIntoView({block:'nearest',inline:'nearest',behavior:'instant'}));
 const data=await card.evaluate(el=>({id:el.dataset.bossMechanic,state:el.dataset.bossState,overflow:el.scrollWidth>el.clientWidth+2,labels:[...el.querySelectorAll('[data-i18n]')].map(label=>{const key=label.dataset.i18n,parameters=JSON.parse(label.dataset.i18nParams||'{}');return{key,parameters,text:label.textContent,expected:ConquerLocale.t(key,parameters)};})}));
 assert.equal(data.overflow,false,locale+' '+boss.name+' skill has no horizontal overflow');
 if(boss.name!=='grumwald'){assert.equal(data.id,boss.id);assert.equal(data.state,ruleState(rule));}
 const get=key=>data.labels.find(label=>label.key===key);
 for(const label of data.labels){assert.equal(typeof catalogs[locale][label.key],'string',locale+' owns '+label.key);assert.equal(label.text,label.expected,locale+' localized '+label.key);}
 assert.equal(get(prefix(boss)+'title')?.text,catalogs[locale][prefix(boss)+'title']);
 const format=async value=>page.evaluate(n=>ConquerLocale.formatNumber(n,{maximumFractionDigits:2}),value);
 const ruleParameters=get(prefix(boss)+'rule').parameters;
 assert.equal(ruleParameters[boss.name==='grumwald'?'heal':'effect'],await format(rule[boss.parameter]));assert.equal(ruleParameters.threshold,await format(rule.counter_power_percent));
 assert.equal(Boolean(get(prefix(boss)+'rally')),rally,'Only contribution previews show the rally-scope note');
 const share=await page.evaluate(({n,legacy})=>ConquerLocale.formatNumber(n,{maximumFractionDigits:legacy?1:2}),{n:rule.counter_power_share_percent,legacy:boss.name==='grumwald'});
 assert.equal(get(prefix(boss)+'share')?.parameters.share,share,'Visible share is the actual server result');
 if(!boss.counter){assert.equal(ruleParameters.hp,await format(50));for(const type of ['infantry','ranged','cavalry'])assert.equal(get(prefix(boss)+'formation').parameters[type],await format(rule.type_power_share_percent[type]));}
 if(boss.name!=='grumwald'){
  const effect=boss.parameter==='required_power_percent'?'required_power':boss.name==='sandmaul'?'army_power':'injury_ratio',multiply=effect==='injury_ratio'?100:1;
  assert.deepEqual(get('boss.effects.'+effect)?.parameters,{before:await format(rule[effect+'_before']*multiply),after:await format(rule[effect+'_after']*multiply)},'Visible before/after matches server exactly');
 }
 return data;
}
async function reachable(page,selector,{minimum=44}={}){
 const geometry=await page.locator(selector).evaluate(el=>{const r=el.getBoundingClientRect();return{x:r.x,y:r.y,width:r.width,height:r.height,right:r.right,bottom:r.bottom,viewport:[innerWidth,innerHeight],hit:el.contains(document.elementFromPoint(r.x+r.width/2,r.y+r.height/2)),disabled:el.disabled};});
 assert.ok(geometry.width>=minimum-1&&geometry.height>=minimum-1,selector+' has a '+minimum+'px touch area: '+JSON.stringify(geometry));
 assert.ok(geometry.x>=-1&&geometry.y>=-1&&geometry.right<=geometry.viewport[0]+1&&geometry.bottom<=geometry.viewport[1]+1,selector+' stays in viewport');assert.ok(geometry.hit,selector+' receives a centre tap');assert.equal(geometry.disabled,false);return geometry;
}
async function viewportCheck(page,selector){assert.equal(await page.locator(selector).evaluate(el=>{const r=el.getBoundingClientRect();return r.left>=-1&&r.right<=innerWidth+1&&r.top>=-1&&r.bottom<=innerHeight+1&&el.scrollWidth<=el.clientWidth+2;}),true,selector+' fits viewport');}
function nextPreview(page,target,troops){return page.waitForResponse(response=>{if(!response.url().endsWith('/api/march/preview')||response.request().method()!=='POST')return false;const body=response.request().postDataJSON();return Number(body.target_id)===Number(target.id)&&normalizedTroops(body.troops)===normalizedTroops(troops);});}
async function previewPayload(response){assert.equal(response.status(),200,'Real preview response');const body=await response.json();assert.equal(body.ok,true);assert.ok(body.data?.boss_mechanic);return body.data;}
async function forecastCheck(page,payload){
 const expected=await page.evaluate(p=>ConquerLocale.t('boss.common.forecast_resolved',{power:ConquerLocale.formatNumber(Math.floor(p.army_power)),required:ConquerLocale.formatNumber(Math.floor(p.required_power)),minutes:ConquerLocale.formatNumber(5)}),payload);
 await page.waitForFunction(text=>document.querySelector('#march-forecast')?.textContent===text,expected);assert.equal(await page.locator('#march-forecast').textContent(),expected,'Forecast uses the latest resolved skill values');
 for(const key of ['battle.preview.losses_reference','battle.preview.luck_notice'])assert.equal(await page.locator('#march-preflight [data-i18n="'+key+'"]').textContent(),await page.evaluate(key=>ConquerLocale.t(key),key),'Preflight has a complete translated '+key);
}
async function formation(page,target,troops){
 // Subscribe before the user edits. An older response must not satisfy the new selection.
 const next=nextPreview(page,target,troops);await page.locator('[data-action=march-clear]').click();
 for(const [code,count]of Object.entries(troops))await page.locator('#march-unit-'+code).fill(String(count));
 return previewPayload(await next);
}
async function closeMarch(page){
 await page.locator('#game-dialog .dialog-close:visible,#game-dialog .mobile-page-back:visible').first().click();
 await page.locator('#game-dialog').waitFor({state:'hidden'});await page.waitForFunction(()=>history.state?.conquerMobilePage?.overlay!=='dialog');
}
async function runCase(page,boss,target,codes,locale,width,height){
 const tag=locale+'-'+boss.name+'-'+width+'x'+height;await page.setViewportSize({width,height});
 await page.evaluate(({x,y})=>ConquerWorld.focus(x,y),{x:Number(target.coord_x)+.5,y:Number(target.coord_y)+.5});
 const marker=page.locator('[data-atlas-target="monsters:'+target.id+'"]');await marker.waitFor({state:'visible'});await marker.click();
 await page.locator('#game-dialog .is-monster-rally').waitFor();
 const troopTab=page.locator('[data-action=march-view][data-id=troops]');if(await troopTab.isVisible())await troopTab.click();
 const inactiveTroops={[codes[boss.counter===1?2:1]]:500},counterTroops=boss.counter?{[codes[boss.counter]]:500}:Object.fromEntries(Object.values(codes).map(code=>[code,400]));
 const active=await formation(page,target,inactiveTroops);result.previews.push({tag,stage:'uncountered-preflight',troops:inactiveTroops,payload:active});assertResolved(active,boss,false);
 await cardCheck(page,'#march-preflight .boss-mechanic',boss,active.boss_mechanic,locale);await forecastCheck(page,active);await viewportCheck(page,'#game-dialog');await reachable(page,'#march-confirm');
 await page.screenshot({path:path.join(output,tag+'-active.png')});
 const countered=await formation(page,target,counterTroops);result.previews.push({tag,stage:'countered-preflight',troops:counterTroops,payload:countered});assertResolved(countered,boss,true);
 await cardCheck(page,'#march-preflight .boss-mechanic',boss,countered.boss_mechanic,locale);await forecastCheck(page,countered);await reachable(page,'#march-confirm');
 await page.screenshot({path:path.join(output,tag+'-countered.png')});
 const next=nextPreview(page,target,counterTroops);await page.locator('[data-action=march-preview]').click();const calculator=await previewPayload(await next);result.previews.push({tag,stage:'countered-calculator',troops:counterTroops,payload:calculator});assertResolved(calculator,boss,true);
 assert.deepEqual(calculator.boss_mechanic,countered.boss_mechanic,'Calculator and preflight resolve the same contribution');
 await cardCheck(page,'.battle-preview-result .boss-mechanic',boss,calculator.boss_mechanic,locale);await viewportCheck(page,'.battle-preview-dialog');await page.screenshot({path:path.join(output,tag+'-calculator.png')});
 await page.locator('.battle-preview-dialog [data-preview-close]').first().click();await page.locator('.battle-preview-dialog').waitFor({state:'detached'});await page.waitForFunction(()=>!history.state?.conquerBattlePreview);
 assert.equal(await page.locator('#game-dialog').evaluate(el=>el.open),true,'Closing calculator preserves the contribution dialog');await reachable(page,'#march-confirm');await closeMarch(page);
 result.cases.push({tag,boss:boss.id,locale,viewport:[width,height],active:active.boss_mechanic,countered:countered.boss_mechanic,passed:true});console.log('PASS '+tag+': live preflight, changed formation, calculator, Back and reachable confirm');
}
async function main(){
 const browser=await chromium.launch({headless:true,channel:process.env.PLAYWRIGHT_CHANNEL||'chrome'}),diagnostics=[];let lastPage;
 try{
  let reportRows;
  for(const locale of selectedLocales){
   const context=await browser.newContext({viewport:{width:1280,height:800},hasTouch:true,locale:'de-DE'});
   // English deliberately has no cookie/storage override, even with a German browser locale.
   if(locale!=='en'){await context.addCookies([{name:'conquer_locale',value:locale,url:base}]);await context.addInitScript(language=>localStorage.setItem('conquer.locale',language),locale);}
   await context.route('**/api/**',async route=>{const request=route.request();if(!['GET','HEAD','OPTIONS'].includes(request.method())){const endpoint=new URL(request.url()).pathname;result.writes.push({locale,method:request.method(),endpoint});if(!(request.method()==='POST'&&endpoint==='/api/march/preview')){result.unexpected_writes.push({locale,method:request.method(),endpoint});await route.abort('blockedbyclient');return;}}await route.continue();});
   const page=lastPage=await context.newPage();page.setDefaultTimeout(25000);page.setDefaultNavigationTimeout(45000);
   page.on('pageerror',error=>result.page_errors.push({locale,message:error.stack||error.message}));
   page.on('response',response=>{if(response.url().startsWith(base+'/api/')&&response.status()>=400)diagnostics.push((async()=>{result.api_errors.push({locale,status:response.status(),url:response.url(),body:await response.text().catch(()=>'<body unavailable>')});})());});
   await login(page);assert.equal(await page.locator('html').getAttribute('lang'),locale);assert.equal(await page.evaluate(()=>ConquerLocale.locale),locale);
   const initial=await apiGet(page,'game/state'),codes={};for(const type of [1,2,3]){const unit=initial.troop_defs.find(row=>Number(row.type)===type&&Number(row.tier)===1);assert.ok(unit,'Fixture T1 type '+type);codes[type]=Number(unit.code);assert.ok(Number(initial.troops[unit.code])>=500,'Fixture provides each counter formation');}
   const targets={};for(const boss of bosses){const state=await apiGet(page,'game/state?map_x='+boss.region[0]+'&map_y='+boss.region[1]+'&map_radius=30');const target=state.monsters.find(row=>Number(row.monster_code)===boss.code);assert.ok(target,'Active reachable fixture boss '+boss.name);assertDefinition(target.definition?.boss_mechanic,boss);targets[boss.name]=target;}
   await page.locator('#navigation [data-id=world]').click();await page.locator('.atlas-shell').waitFor();
   if(locale==='en'){
    for(const [width,height]of [[320,568],[568,320]])for(const boss of bosses)await runCase(page,boss,targets[boss.name],codes,locale,width,height);
    await runCase(page,bosses[1],targets.frostgrimm,codes,locale,1280,800);await runCase(page,bosses[2],targets.sandmaul,codes,locale,390,844);
   }else{const boss=locale==='de'?bosses[1]:bosses[4];await runCase(page,boss,targets[boss.name],codes,locale,320,568);}
   const after=await apiGet(page,'game/state');for(const code of Object.values(codes))assert.equal(Number(after.troops[code]),Number(initial.troops[code]),'Previews never reserve or remove T1 troops');
   assert.deepEqual(after.marches.map(row=>Number(row.id)).sort((a,b)=>a-b),initial.marches.map(row=>Number(row.id)).sort((a,b)=>a-b),'Preview creates no march');assert.equal(after.active_rally_count,initial.active_rally_count,'Preview creates no rally');
   for(const boss of bosses){const state=await apiGet(page,'game/state?map_x='+boss.region[0]+'&map_y='+boss.region[1]+'&map_radius=30');assert.equal(Number(state.monsters.find(row=>Number(row.id)===Number(targets[boss.name].id))?.hp_current),Number(targets[boss.name].hp_current),'Preview leaves '+boss.name+' HP unchanged');}
   if(!reportRows){reportRows=[];for(let number=1;number<=5;number++){const data=await apiGet(page,'battle/reports?page='+number);reportRows.push(...data.reports);if(data.reports.length<20)break;}for(const boss of bosses)for(const countered of [false,true])assert.ok(reportRows.some(row=>row.details?.type==='monster_rally'&&row.details.boss_mechanic?.id===boss.id&&row.details.boss_mechanic.countered===countered&&row.details.boss_mechanic.phase_active!==false),'Fixture has historical '+boss.name+' '+countered);assert.ok(reportRows.some(row=>row.details?.boss_mechanic?.id===bosses[4].id&&row.details.boss_mechanic.phase_active===false),'Fixture has 50%-HP Dämmerhorn report');}
   const chosen=reportRows.filter(row=>row.details?.type==='monster_rally'&&bosses.some(boss=>boss.id===row.details.boss_mechanic?.id)&&(locale==='en'||row.details.boss_mechanic.id===(locale==='de'?bosses[1].id:bosses[4].id)));
   await page.setViewportSize({width:320,height:568});
   for(const row of chosen){const boss=bosses.find(b=>b.id===row.details.boss_mechanic.id),rule=row.details.boss_mechanic;assertResolved(row.details,boss,rule.countered,{historical:true,phaseInactive:rule.phase_active===false});await page.goto(base+'/reports/'+row.id);await page.locator('.monster-report').waitFor();await cardCheck(page,'.monster-report .boss-mechanic',boss,rule,locale,{rally:false});await viewportCheck(page,'.cr-scroll');const tag=locale+'-'+boss.name+'-'+(rule.phase_active===false?'phase-inactive':rule.countered?'countered':'active')+'-report';await page.screenshot({path:path.join(output,tag+'.png')});result.reports.push({tag,id:row.id,rule,passed:true});console.log('PASS '+tag+': actual historical report and explicit localized effect');}
   await context.close();
  }
  await Promise.all(diagnostics);assert.deepEqual(result.unexpected_writes,[],'No automatic gameplay-writing request');assert.deepEqual(result.api_errors,[],'No failed real API requests');assert.deepEqual(result.page_errors,[],'No browser errors');result.passed=true;
  console.log('PASS boss skills ('+selectedLocales.join('/')+'): '+result.cases.length+' app cases, '+result.reports.length+' historical report views and no gameplay action dispatched.');
 }catch(error){result.failure=error.stack||String(error);if(lastPage&&!lastPage.isClosed())await lastPage.screenshot({path:path.join(output,'failure.png')}).catch(()=>{});throw error;}
 finally{await Promise.all(diagnostics);result.completed_at=new Date().toISOString();fs.writeFileSync(path.join(output,'report.json'),JSON.stringify(result,null,2));await browser.close();}
}
main().catch(error=>{console.error(error);process.exitCode=1;});
